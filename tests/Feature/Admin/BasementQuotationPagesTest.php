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
            ->assertSee('Final Details &amp; Cleanup', false);

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('Labor productivity')
            ->assertSee('Default materials')
            ->assertSee('Line item')
            ->assertSee('Materials DB')
            ->assertSee('Unit price (CAD$)')
            ->assertSee('Linked unit prices are locked', false);
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
