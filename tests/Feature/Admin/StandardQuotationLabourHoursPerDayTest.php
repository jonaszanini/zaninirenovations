<?php

namespace Tests\Feature\Admin;

use App\Models\SharedCrewRate;
use App\Models\StandardQuotation;
use App\Services\StandardQuotationCalculator;
use App\Support\QuotationSharedLabourRates;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class StandardQuotationLabourHoursPerDayTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_new_quote_uses_shared_crew_hours_per_day_for_days_mode(): void
    {
        $crew = SharedCrewRate::current();
        $crew->hours_per_day = 6;
        $crew->save();

        $calc = new StandardQuotationCalculator();
        $result = $calc->compute([], 100, 'days', 2, false, 0, false, 0, false, true, 6);

        $this->assertEqualsWithDelta(12.0, $result['labour_hours'], 0.01);
        $this->assertEqualsWithDelta(1200.0, $result['labour_total'], 0.01);
    }

    public function test_saved_quote_keeps_frozen_hours_per_day_on_recompute(): void
    {
        $quote = StandardQuotation::query()->create([
            'quote_number' => 'SQ-HPD-'.random_int(10000, 99999),
            'client_name' => 'Frozen HPD Client',
            'display_title' => 'Cabinet work',
            'labour_hours_per_day' => 8,
            'labour_mode' => 'days',
            'labour_quantity' => 1,
            'labour_hourly_rate' => 100,
            'materials' => [],
            'works' => [[
                'title' => 'Job',
                'materials' => [],
                'labour_mode' => 'days',
                'labour_quantity' => 1,
                'labour_hourly_rate' => 100,
                'labour_use_fixed' => false,
            ]],
        ]);

        $crew = SharedCrewRate::current();
        $crew->hours_per_day = 6;
        $crew->save();

        $this->assertEqualsWithDelta(8.0, QuotationSharedLabourRates::resolveHoursPerDay($quote), 0.01);
        $this->assertEqualsWithDelta(800.0, $quote->fresh()->computedTotals()['labour_total'], 0.01);
    }

    public function test_create_form_shows_shared_hours_per_day_in_days_label(): void
    {
        $this->actingAsAdmin();

        $crew = SharedCrewRate::current();
        $crew->hours_per_day = 6;
        $crew->save();

        $html = $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-labour-hours-per-day="6.00"', preg_replace('/\s+/', ' ', $html) ?: $html);
        $this->assertStringContainsString('× 6 h', preg_replace('/\s+/', ' ', $html) ?: $html);
        $this->assertStringNotContainsString('× 8 h', preg_replace('/\s+/', ' ', $html) ?: $html);
    }
}
