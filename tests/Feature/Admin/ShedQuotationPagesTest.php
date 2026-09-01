<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ShedQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ShedQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_hub_and_wizard_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Shed');

        $this->get(route('admin.quotations.shed.index'))
            ->assertOk()
            ->assertSee('New shed quotation');

        $this->get(route('admin.quotations.shed.create'))
            ->assertOk()
            ->assertSee('1 of 8')
            ->assertSee('Shed type')
            ->assertSee('Gable')
            ->assertSee('Lean-to')
            ->assertSee('Gambrel')
            ->assertSee('Saltbox')
            ->assertSee('Length (ft)')
            ->assertSee('Concrete pad')
            ->assertSee('Helical piles')
            ->assertSee('Asphalt shingles')
            ->assertSee('Metal — Black')
            ->assertSee('Metal — Silver')
            ->assertSee('Weather coat')
            ->assertSee('Not now')
            ->assertSee('Hardie board')
            ->assertSee('Fully finished')
            ->assertSee('fa-info-circle', false)
            ->assertSee('Live breakdown');

        $this->get(route('admin.quotations.shed.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Default materials')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Materials</button>', false)
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
            ->assertSee('From stud', false)
            ->assertSee('Save configuration')
            ->assertSee('Asphalt shingles (bundle / 33.3 sq ft)')
            ->assertSee('Sold in bundles (~33.3 sq ft), not squares.')
            ->assertSee('Metal roof Black (36×93 panel)')
            ->assertSee('Metal roof Silver (36×93 panel)')
            ->assertSee('Weather coat, 1 gal (each)')
            ->assertSee('Fiber-cement wall cladding (Hardie-style), not roof shingles.')
            ->assertSee('>Each</option>', false)
            ->assertSee('>Sq ft</option>', false)
            ->assertSee('>Bundle</option>', false);
    }

    public function test_project_context_sits_between_price_configuration_and_next(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Backyard is sloped toward the fence.',
        ]);

        $html = $this->get(route('admin.quotations.shed.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Backyard is sloped toward the fence.')
            ->getContent();

        $price = strpos($html, 'Price configuration');
        $context = strpos($html, 'Project Context');
        $next = strpos($html, '>Next</button>');
        $live = strpos($html, 'Live breakdown');

        $this->assertNotFalse($price);
        $this->assertNotFalse($context);
        $this->assertNotFalse($next);
        $this->assertNotFalse($live);
        $this->assertLessThan($context, $price);
        $this->assertLessThan($next, $context);
        $this->assertLessThan($live, $next);
    }

    public function test_preview_returns_live_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.shed.preview'), [
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'shed_type' => 'gable',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.area_sqft', 120)
            ->assertJsonPath('eta.scenarios.0.crew', 1);
    }

    public function test_store_publishes_a_standalone_quotation(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.quotations.shed.store'), [
            'save_intent' => 'publish',
            'client_name' => 'Shed Test Client',
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'shed_type' => 'gable',
            'foundation' => 'skids',
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ]);

        $quote = ShedQuotation::query()->where('client_name', 'Shed Test Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertStringContainsString('10', $quote->summaryDescription());
        $this->assertStringStartsWith('SH-', $quote->quote_number);
        $response->assertRedirect(route('admin.quotations.shed.edit', $quote));
    }

    public function test_save_stays_on_quotation_and_shows_back_to_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->get(route('admin.quotations.shed.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Back to project');

        $store = $this->post(route('admin.quotations.shed.store', ['project' => $project->id]), [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'length_ft' => 10,
            'width_ft' => 10,
            'wall_height_ft' => 8,
            'shed_type' => 'gable',
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'link_client_project_id' => $project->id,
        ]);

        $quote = ShedQuotation::query()->orderByDesc('id')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.shed.edit', $quote));

        $this->get(route('admin.quotations.shed.edit', [
            'shed_quotation' => $quote,
            'project' => $project->id,
        ]))->assertOk()->assertSee('Back to project');
    }
}
