<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Models\BasementQuotation;
use App\Services\BasementClientScope;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementOntarioScopeCalculationTest extends TestCase
{
    protected function settings(): BasementSettings
    {
        return new BasementSettings();
    }

    public function test_ontario_framing_2x6_acoustic_type_x_rc_dricore_stairs_millwork_and_egress_calculate_cleanly(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 800,
            'framing_2x6_lf' => 16,
            'include_acoustic_insulation' => true,
            'acoustic_insulation_sf' => 800,
            'drywall_type_x_ceiling' => true,
            'drywall_resilient_channel' => true,
            'dricore_subfloor' => true,
            'dricore_sf' => 800,
            'include_stairs' => true,
            'stairs_treads' => 14,
            'stairs_handrail_lf' => 16,
            'under_stair_storage' => true,
            'include_millwork' => true,
            'cabinet_base_lf' => 8,
            'cabinet_upper_lf' => 6,
            'include_egress_window' => true,
            'egress_window_qty' => 1,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $laborKeys = collect($calc['labor'])->pluck('key')->all();
        $materialKeys = collect($calc['materials'])->pluck('key')->all();

        foreach ([
            'framing_2x6',
            'ins_acoustic',
            'dw_resilient_channel',
            'fl_dricore',
            'stairs_treads_install',
            'stairs_handrail_install',
            'stairs_under_storage',
            'millwork_base',
            'millwork_upper',
        ] as $key) {
            $this->assertContains($key, $laborKeys);
        }

        foreach ([
            'stud_2x6',
            'acoustic_batt',
            'drywall_sheet_type_x',
            'resilient_channel',
            'dricore_panel',
            'stair_tread_kit',
            'stair_handrail',
            'millwork_cabinet_base',
            'millwork_cabinet_upper',
            'egress_window',
        ] as $key) {
            $this->assertContains($key, $materialKeys);
        }

        $subtotal = $calc['summary']['subtotal_pre_hst'];
        $this->assertGreaterThan(0, $subtotal);
        $this->assertEqualsWithDelta(
            $subtotal,
            $calc['summary']['reconciliation']['checks']['sections_plus_extra_plus_fee_minus_trade_pre'] ?? -1,
            0.01
        );

        $plate2x6 = collect($calc['materials'])->firstWhere('key', 'plate_2x6');
        $this->assertNotNull($plate2x6);
        $this->assertEqualsWithDelta(round(8.50 / 8, 4), (float) $plate2x6['unit_price'], 0.0001);
    }

    public function test_empty_area_with_zero_qty_ontario_toggles_keeps_zero_totals(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 0,
            'partition_lf' => 0,
            'framing_2x6_lf' => 0,
            'include_stairs' => true,
            'stairs_treads' => 0,
            'stairs_handrail_lf' => 0,
            'under_stair_storage' => false,
            'include_millwork' => true,
            'cabinet_base_lf' => 0,
            'cabinet_upper_lf' => 0,
            'include_egress_window' => true,
            'egress_window_qty' => 0,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(0.0, $calc['summary']['subtotal_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
    }

    public function test_disabled_dricore_on_skip_flooring_still_charges_dricore_when_dricore_checked(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'flooring_type' => 'none',
            'dricore_subfloor' => true,
            'dricore_sf' => 400,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $laborKeys = collect($calc['labor'])->pluck('key')->all();
        $materialKeys = collect($calc['materials'])->pluck('key')->all();

        $this->assertContains('fl_dricore', $laborKeys);
        $this->assertContains('dricore_panel', $materialKeys);
    }

    public function test_client_scope_generated_copy_includes_enabled_ontario_items(): void
    {
        $copyEnabled = BasementClientScope::generatedCopy([
            'drywall_type_x_ceiling' => true,
            'drywall_resilient_channel' => true,
            'include_acoustic_insulation' => true,
            'dricore_subfloor' => true,
            'include_stairs' => true,
            'include_millwork' => true,
            'include_egress_window' => true,
            'egress_window_qty' => 1,
        ]);
        $textEnabled = is_array($copyEnabled) ? implode(' ', $copyEnabled) : (string) $copyEnabled;

        $this->assertStringContainsString('Type X', $textEnabled);
        $this->assertStringContainsString('Resilient', $textEnabled);
        $this->assertStringContainsString('Safe', $textEnabled);
        $this->assertStringContainsString('Insulated dimpled', $textEnabled);
        $this->assertStringContainsString('Basement stairs', $textEnabled);
        $this->assertStringContainsString('cabinetry', strtolower($textEnabled));
        $this->assertStringContainsString('egress', strtolower($textEnabled));

        $copyDisabled = BasementClientScope::generatedCopy([
            'drywall_type_x_ceiling' => false,
            'drywall_resilient_channel' => false,
            'include_acoustic_insulation' => false,
            'dricore_subfloor' => false,
            'include_stairs' => false,
            'include_millwork' => false,
            'include_egress_window' => false,
        ]);
        $textDisabled = is_array($copyDisabled) ? implode(' ', $copyDisabled) : (string) $copyDisabled;

        $this->assertStringNotContainsString('Type X', $textDisabled);
        $this->assertStringNotContainsString('Resilient', $textDisabled);
        $this->assertStringNotContainsString('Dricore', $textDisabled);
    }

    public function test_pdf_scope_itemizes_electrical_device_counts_and_plumbing_rough_ins(): void
    {
        $quotation = new BasementQuotation([
            'inputs' => [
                'area_sqft' => 800,
                'scope_preset' => 'full',
                'enabled_sections' => ['overview', 'demolition', 'framing', 'electrical', 'plumbing', 'drywall', 'flooring', 'doors_trim', 'painting', 'finishing'],
                'outlet_qty' => 10,
                'switch_qty' => 4,
                'light_qty' => 12,
                'lighting_type' => 'spot',
                'detector_qty' => 2,
                'panel_upgrade' => true,
                'include_plumbing' => true,
                'bath_rough_in' => true,
                'wet_bar' => true,
            ],
            'calculation' => [
                'scope' => ['area_sqft' => 800],
                'labor' => [],
            ]
        ]);

        $html = (string) $quotation->scopeDescriptionForPdf();

        $this->assertStringContainsString('10 receptacle outlet(s)', $html);
        $this->assertStringContainsString('4 wall switch(es)', $html);
        $this->assertStringContainsString('12 recessed pot light(s)', $html);
        $this->assertStringContainsString('2 interconnected smoke / CO detector(s)', $html);
        $this->assertStringContainsString('Electrical panel upgrade / sub-panel capacity', $html);
        $this->assertStringContainsString('Bathroom drain, vent &amp; supply rough-in', $html);
        $this->assertStringContainsString('Wet bar sink rough-in', $html);
        $this->assertStringContainsString('Electrical', $html);
        $this->assertStringContainsString('Plumbing', $html);
    }
}
