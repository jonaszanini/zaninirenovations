<?php

namespace Tests\Feature;

use App\Models\Material;
use App\Models\SharedWork;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SharedPricesCatalogMaterialTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_linked_row_ignores_posted_material_unit_price(): void
    {
        $this->actingAsAdmin();

        $material = Material::query()->create([
            'name' => 'Linked only moulding',
            'store_sku' => 'SP-LINK-ONLY',
            'price_cad' => 11.00,
            'is_active' => true,
            'unit' => 'each',
        ]);

        $work = SharedWork::query()->whereNotIn('unit', ['lf', 'sf'])->first()
            ?? SharedWork::query()->firstOrFail();
        $work->catalog_material_id = $material->id;
        $work->material_unit_price = 5.55;
        $work->save();

        $this->put(route('admin.quotations.shared-prices.update'), [
            'owner_hourly_rate' => '100.00',
            'helper_hourly_rate' => '50.00',
            'hours_per_day' => '8',
            'works' => [
                $work->id => [
                    'id' => $work->id,
                    'catalog_material_id' => $material->id,
                    'material_unit_price' => '88.8888',
                ],
            ],
        ])->assertRedirect(route('admin.quotations.shared-prices.edit'));

        $work->refresh();
        $this->assertEqualsWithDelta(5.55, (float) $work->material_unit_price, 0.0001);
    }

    public function test_editor_drops_mat_price_column_and_shows_not_linked_hint(): void
    {
        $this->actingAsAdmin();

        $work = SharedWork::query()->whereNull('catalog_material_id')->first()
            ?? SharedWork::query()->firstOrFail();
        $work->catalog_material_id = null;
        $work->save();

        $html = $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertDontSee('Mat. price', false)
            ->assertSee('Not linked', false)
            ->assertSee('sp-mat-fallback-price', false)
            ->getContent();

        $this->assertStringNotContainsString('class="sp-col-price"', $html);
    }
}
