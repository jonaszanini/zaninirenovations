<?php

namespace Tests\Feature\Admin;

use App\Models\FlooringType;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FlooringTypeDeleteCheckboxTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_delete_checkbox_posts_flooring_type_id_not_literal_one(): void
    {
        $this->actingAsAdmin();

        $first = FlooringType::query()->orderBy('id')->firstOrFail();
        $second = FlooringType::query()->where('id', '>', $first->id)->orderBy('id')->firstOrFail();

        $html = $this->get(route('admin.quotations.flooring.price-configuration'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="delete_flooring_type_ids\[\]"[^>]*value="'.preg_quote((string) $second->id, '/').'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="delete_flooring_type_ids\[\]"[^>]*value="1"(?![0-9])/',
            $html
        );

        $settings = \App\Models\FlooringSettings::current();

        $response = $this->post(route('admin.quotations.flooring.database.update'), [
            'remove_carpet_dispose_rate' => $settings->remove_carpet_dispose_rate,
            'rescrew_subfloor_rate' => $settings->rescrew_subfloor_rate,
            'install_shoe_moulding_rate' => $settings->install_shoe_moulding_rate,
            'caulk_paint_shoe_moulding_rate' => $settings->caulk_paint_shoe_moulding_rate,
            'closet_door_reinstall_rate' => $settings->closet_door_reinstall_rate,
            'flooring_types' => [
                0 => [
                    'id' => $first->id,
                    'name' => $first->name,
                    'installation_price_per_sqft' => $first->installation_price_per_sqft,
                    'sort_order' => $first->sort_order,
                    'is_active' => $first->is_active ? '1' : '0',
                ],
                1 => [
                    'id' => $second->id,
                    'name' => $second->name,
                    'installation_price_per_sqft' => $second->installation_price_per_sqft,
                    'sort_order' => $second->sort_order,
                    'is_active' => $second->is_active ? '1' : '0',
                ],
            ],
            'delete_flooring_type_ids' => [(string) $second->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('flooring_types', ['id' => $second->id]);
        $this->assertDatabaseHas('flooring_types', ['id' => $first->id]);
    }
}
