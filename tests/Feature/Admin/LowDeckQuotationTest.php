<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\LowDeckMaterial;
use App\Models\LowDeckQuotation;
use App\Models\Material;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class LowDeckQuotationTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_config_links_a_deck_material_to_the_catalog(): void
    {
        $this->actingAsAdmin();

        $catalog = Material::create([
            'name' => 'Test 4x4 Deck Block',
            'price_cad' => 12.50,
            'is_active' => true,
        ]);

        $block = LowDeckMaterial::where('key', LowDeckMaterial::KEY_BLOCK_4X4)->firstOrFail();
        $this->assertNull($block->material_id);

        $response = $this->put(route('admin.quotations.low-deck.config.materials.update'), [
            'materials' => [
                ['id' => $block->id, 'material_id' => $catalog->id],
            ],
        ]);

        $response->assertRedirect(route('admin.quotations.low-deck.config'));
        $this->assertSame($catalog->id, $block->fresh()->material_id);
    }

    public function test_store_with_project_links_a_line_to_the_project_quotation(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $response = $this->post(route('admin.quotations.low-deck.store', ['project' => $project->id]), [
            'client_name' => 'Jane Homeowner',
            'width_ft' => 12,
            'length_ft' => 16,
            'joist_spacing_in' => 16,
            'labour_rate_per_sqft' => 10,
        ]);

        $response->assertRedirect();

        $quote = LowDeckQuotation::query()->latest('id')->first();
        $this->assertNotNull($quote);
        $this->assertSame($project->id, $quote->client_project_id);
        $this->assertSame(192.0, (float) $quote->calculation['geometry']['area_sq_ft']);

        $line = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_LOW_DECK)
            ->where('source_id', $quote->id)
            ->first();

        $this->assertNotNull($line);
        $this->assertSame($project->id, $line->client_project_id);
    }

    public function test_store_rejects_a_deck_over_the_area_guardrail(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.low-deck.create'))
            ->post(route('admin.quotations.low-deck.store'), [
                'client_name' => 'Too Big',
                'width_ft' => 30,
                'length_ft' => 30,
                'joist_spacing_in' => 16,
            ]);

        $response->assertSessionHasErrors('width_ft');
        $this->assertSame(0, LowDeckQuotation::query()->count());
    }

    public function test_layout_diagrams_print_page_renders_saved_geometry(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $this->post(route('admin.quotations.low-deck.store', ['project' => $project->id]), [
            'client_name' => 'Jane Homeowner',
            'width_ft' => 12,
            'length_ft' => 16,
            'joist_spacing_in' => 16,
            'labour_rate_per_sqft' => 10,
        ]);

        $quote = LowDeckQuotation::query()->latest('id')->firstOrFail();

        $response = $this->get(route('admin.quotations.low-deck.layout-diagrams', $quote));

        $response->assertOk();
        $response->assertSee('Layout diagrams', false);
        $response->assertSee($quote->quote_number, false);
        $response->assertSee('1. Foundations', false);
        $response->assertSee('12 in. pier holes', false);
        $response->assertSee('members shown 1.5 in. wide', false);
    }
}
