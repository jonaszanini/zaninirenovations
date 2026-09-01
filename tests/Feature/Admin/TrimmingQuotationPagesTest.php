<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\TrimmingQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class TrimmingQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_hub_list_create_and_config_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Trimming');

        $this->get(route('admin.quotations.trimming.index'))
            ->assertOk()
            ->assertSee('New trimming quotation');

        $this->get(route('admin.quotations.trimming.create'))
            ->assertOk()
            ->assertSee('Doors & windows', false)
            ->assertSee('Floor to wall')
            ->assertSee('Ceiling to wall')
            ->assertSee('Wall treatments')
            ->assertSee('Include this family')
            ->assertSee('Door openings')
            ->assertSee('Pick from directory')
            ->assertSee('fa-info-circle', false)
            ->assertSee('Live breakdown')
            ->assertSee('Client supplies materials');

        $this->get(route('admin.quotations.trimming.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Default materials')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Materials</button>', false)
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
            ->assertSee('>Each</option>', false)
            ->assertSee('>Sq ft</option>', false)
            ->assertSee('Material waste')
            ->assertSee('Save configuration');
    }

    public function test_project_context_and_back_to_project_on_create(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Trim around the living-room windows.',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $html = $this->get(route('admin.quotations.trimming.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Trim around the living-room windows.')
            ->assertSee('Back to project')
            ->getContent();

        $price = strpos($html, 'Price configuration');
        $context = strpos($html, 'Project Context');
        $live = strpos($html, 'Live breakdown');

        $this->assertNotFalse($price);
        $this->assertNotFalse($context);
        $this->assertNotFalse($live);
    }

    public function test_preview_returns_live_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.trimming.preview'), [
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 80,
            'include_baseboard' => 1,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.total_lf', 80)
            ->assertJsonPath('eta.scenarios.0.crew', 1);
    }

    public function test_store_publishes_a_standalone_quotation(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.quotations.trimming.store'), $this->publishPayload([
            'client_name' => 'Trim Test Client',
            'floor_lf' => 80,
        ]));

        $quote = TrimmingQuotation::query()->where('client_name', 'Trim Test Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertStringContainsString('80', $quote->summaryDescription());
        $this->assertStringStartsWith('TR-', $quote->quote_number);
        $response->assertRedirect(route('admin.quotations.trimming.edit', $quote));
    }

    public function test_save_stays_on_quotation_and_shows_back_to_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->get(route('admin.quotations.trimming.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Back to project');

        $store = $this->post(route('admin.quotations.trimming.store', ['project' => $project->id]), $this->publishPayload([
            'client_name' => $project->client_name,
            'floor_lf' => 40,
            'link_client_project_id' => $project->id,
        ]));

        $quote = TrimmingQuotation::query()->orderByDesc('id')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.trimming.edit', $quote));
        $this->assertSame($project->id, (int) $quote->client_project_id);

        $this->get(route('admin.quotations.trimming.edit', [
            'trimming_quotation' => $quote,
            'project' => $project->id,
        ]))->assertOk()->assertSee('Back to project');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 80,
            'include_baseboard' => 1,
            'include_base_cap' => 0,
            'include_shoe' => 0,
            'include_quarter_round' => 0,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $overrides);
    }
}
