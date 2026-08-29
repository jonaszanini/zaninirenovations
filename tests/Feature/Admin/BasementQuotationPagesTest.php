<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
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
            ->assertSee('Ceiling lighting')
            ->assertSee('Spot lights', false)
            ->assertSee('Wall / ceiling colors');

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Default materials')
            ->assertSee('>Rates</button>', false)
            ->assertSee('>Materials</button>', false)
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
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
        $this->assertDoesNotMatchRegularExpression(
            '/data-br-goto="plumbing"[^>]*\bhidden\b/s',
            $html
        );
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
}
