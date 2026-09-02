<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementQuotationPagesTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_hub_and_wizard_pages_load(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee('Basement Renovation');

        $this->get(route('admin.quotations.basement.index'))
            ->assertOk()
            ->assertSee('New basement quotation');

        $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('1 of 13')
            ->assertSee('Project Overview')
            ->assertSee('What are you quoting?')
            ->assertSee('Full basement')
            ->assertSee('Bathroom only')
            ->assertSee('New wall')
            ->assertSee('Trades to include')
            ->assertSee('Total basement area')
            ->assertSee('Total sq ft')
            ->assertSee('Length × width')
            ->assertSee('Flooring')
            ->assertSee('Basement ceiling lighting')
            ->assertSee('Spot lights', false)
            ->assertSee('Wall / ceiling colors')
            ->assertSee('fa-info-circle', false)
            ->assertSee('New drain, vent, and hot/cold supply');

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('class="admin-form-grid-4"', false)
            ->assertSee('Default materials')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Materials</button>', false)
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
            ->assertSee('>Each</option>', false)
            ->assertSee('>Sq ft</option>', false)
            ->assertSee('per sq ft', false)
            ->assertSee('From 2x4 stud', false)
            ->assertSee('cannot be linked separately', false)
            ->assertSee('17 L', false)
            ->assertSee('2x4x8', false)
            ->assertSee('75 m', false)
            ->assertSee('5 gal', false)
            ->assertSee('Trim paint', false)
            ->assertSee('Tape / cut-in two colors', false)
            ->assertSee('br-config-source--manual', false)
            ->assertSee('br-config-source--derived', false)
            ->assertSee('From stud', false)
            ->assertSee('br-config-legend', false)
            ->assertSee('form="br-config-form"', false)
            ->assertSee('Save configuration');
    }

    public function test_project_context_sits_between_price_configuration_and_next(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'notes' => 'Site has a low beam in the rec room.',
        ]);

        $html = $this->get(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Project Context')
            ->assertSee('Site has a low beam in the rec room.')
            ->getContent();

        $price = strpos($html, 'Price configuration');
        $context = strpos($html, 'Project Context');
        $next = strpos($html, '>Next</button>');
        $live = strpos($html, 'Live breakdown');

        $this->assertNotFalse($price);
        $this->assertNotFalse($context);
        $this->assertNotFalse($next);
        $this->assertNotFalse($live);
        $this->assertLessThan($price, $context);
        $this->assertLessThan($next, $context);
        $this->assertLessThan($live, $next);
        $this->assertMatchesRegularExpression(
            '/<details class="admin-accordion admin-card cp-project-context-card">/',
            $html
        );
    }

    public function test_bathroom_preset_hides_flooring_in_progress_nav(): void
    {
        $this->actingAsAdmin();

        $html = $this->withInput([
            'scope_preset' => 'bathroom',
            'enabled_sections' => \App\Services\BasementQuotationCalculator::presetEnabledSections('bathroom'),
        ])->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('Bathroom only')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-br-goto="flooring"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-br-goto="electrical"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/data-br-goto="plumbing"[^>]*\bhidden\b/s',
            $html
        );
        $this->assertMatchesRegularExpression('/id="br-outlets"[^>]*value="3"/', $html);
        $this->assertMatchesRegularExpression('/id="br-switches"[^>]*value="2"/', $html);
        $this->assertMatchesRegularExpression('/id="br-bath-fixture-qty"[^>]*value="1"/', $html);
        $this->assertStringContainsString('name="include_bath_fixture"', $html);
        $this->assertStringContainsString('Light fixture', $html);
        $this->assertStringContainsString('Spot light', $html);
        $this->assertStringContainsString('1 at the sink', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertStringContainsString('New drain, vent, and hot/cold supply', $html);
        $this->assertStringContainsString('Add tile area', $html);
        $this->assertStringContainsString('e.g. Floor, Shower', $html);
        $this->assertStringContainsString('Vinyl (LVP)', $html);
        $this->assertStringContainsString('name="bath_tile_areas[0][finish]"', $html);
        $this->assertMatchesRegularExpression(
            '/id="br-lighting-fixture-qty"[^>]*\bhidden\b/',
            $html
        );
        $this->assertStringContainsString('Pedestal sink + faucet', $html);
        $this->assertStringContainsString('name="include_pedestal_sink"', $html);
        $this->assertStringContainsString('Pedestal sink', $html);
        $this->assertStringContainsString('value="pedestal_sink"', $html);
        $this->assertStringContainsString('id="br-plumbing-slot-bath"', $html);
    }

    public function test_price_configuration_keeps_active_tab_after_save(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.price-configuration', ['tab' => 'materials']))
            ->assertOk()
            ->assertSee('id="br-config-tab" value="materials"', false);

        $this->post(route('admin.quotations.basement.database.update'), [
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'tab' => 'materials',
        ])->assertRedirect(route('admin.quotations.basement.price-configuration', ['tab' => 'materials']));
    }

    public function test_preview_returns_live_totals(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.basement.preview'), [
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'lvp',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ])->assertOk()
            ->assertJsonPath('scope.area_sqft', 850)
            ->assertJsonPath('eta.scenarios.0.crew', 1);
    }

    public function test_store_publishes_a_standalone_quotation(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.quotations.basement.store'), [
            'save_intent' => 'publish',
            'client_name' => 'Basement Test Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ]);

        $quote = BasementQuotation::query()->where('client_name', 'Basement Test Client')->first();
        $this->assertNotNull($quote);
        $this->assertFalse((bool) $quote->is_draft);
        $this->assertGreaterThan(0, (float) $quote->final_quote_total);
        $this->assertStringContainsString('850', $quote->summaryDescription());
        $response->assertRedirect(route('admin.quotations.basement.edit', $quote));
    }

    public function test_save_stays_on_quotation_and_shows_back_to_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->get(route('admin.quotations.basement.create', ['project' => $project->id]))
            ->assertOk()
            ->assertSee('Back to project', false);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Stay On Quote Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ];

        $store = $this->post(route('admin.quotations.basement.store', ['project' => $project->id]), $payload);

        $quote = BasementQuotation::query()->where('client_name', 'Stay On Quote Client')->first();
        $this->assertNotNull($quote);
        $this->assertSame($project->id, $quote->client_project_id);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $this->get(route('admin.quotations.basement.edit', [
            'basement_quotation' => $quote,
            'return' => 'project',
        ]))
            ->assertOk()
            ->assertSee('Back to project', false)
            ->assertSee('#quotation', false);

        $this->put(route('admin.quotations.basement.update', [
            'basement_quotation' => $quote,
            'return' => 'project',
        ]), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote));
    }
}
