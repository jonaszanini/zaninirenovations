<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementQuotationCalculatorTest extends TestCase
{
    public function test_empty_area_has_zero_totals(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate(
            ['area_sqft' => 0],
            $this->settings()
        );

        $this->assertSame(0.0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
    }

    public function test_full_finish_produces_labor_materials_and_eta(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 800,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'include_demolition' => true,
            'demo_flooring_sf' => 800,
            'demo_bin_qty' => 1,
            'partition_lf' => 40,
            'outlet_qty' => 12,
            'switch_qty' => 6,
            'light_qty' => 10,
            'insulate_walls' => true,
            'drywall_wall_sf' => 640,
            'ceiling_type' => 'drywall',
            'flooring_type' => 'lvp',
            'flooring_sf' => 800,
            'door_qty' => 2,
            'paint_coats' => 2,
            'include_bathroom' => false,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $this->settings());

        $this->assertGreaterThan(0, $calc['summary']['total_hours']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['materials_pre_hst']);
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] + $calc['summary']['materials_pre_hst'], 2),
            $calc['summary']['subtotal_pre_hst'],
            0.01
        );
        $this->assertCount(3, $calc['eta']['scenarios']);
        $this->assertSame(1, $calc['eta']['scenarios'][0]['crew']);
        $this->assertGreaterThan(
            $calc['eta']['scenarios'][2]['calendar_days'],
            $calc['eta']['scenarios'][0]['calendar_days']
        );
        $this->assertSame(800.0, $calc['scope']['area_sqft']);
    }

    public function test_client_provided_materials_zero_material_total(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_demolition' => false,
            'client_provides_all' => true,
            'flooring_sf' => 400,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
    }

    public function test_overrides_replace_calculated_hours_and_qty(): void
    {
        $base = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'partition_lf' => 20,
            'owner_hourly_rate' => 100,
        ], $this->settings());

        $overridden = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'partition_lf' => 20,
            'owner_hourly_rate' => 100,
            'labor_override' => [
                'framing_walls' => ['hours' => 10],
            ],
        ], $this->settings());

        $framing = collect($overridden['labor'])->firstWhere('key', 'framing_walls');
        $this->assertNotNull($framing);
        $this->assertSame(10.0, $framing['hours']);
        $this->assertSame(1000.0, $framing['amount']);
        $this->assertNotEquals($base['summary']['total_hours'], $overridden['summary']['total_hours']);
    }

    public function test_validation_requires_area_when_publishing(): void
    {
        $hints = (new BasementQuotationCalculator)->validationHints(['area_sqft' => 0], false);
        $this->assertNotEmpty($hints);

        $draft = (new BasementQuotationCalculator)->validationHints(['area_sqft' => 0], true);
        $this->assertSame([], $draft);
    }

    public function test_bathroom_skip_omits_fixtures(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'include_bathroom' => false,
            'include_vanity' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['labor'])->pluck('key')->all();
        $this->assertNotContains('bath_vanity', $keys);
        $this->assertNotContains('bath_toilet', $keys);
    }

    public function test_extra_materials_add_to_total(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'extra_materials' => [
                ['name' => 'Custom fixture', 'quantity' => 2, 'unit_price' => 25],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(50.0, $calc['summary']['materials_pre_hst']);
        $this->assertCount(1, $calc['extra_materials']);
    }

    public function test_extra_material_delivery_is_added_once(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'extra_materials' => [
                [
                    'name' => 'Debris bin',
                    'quantity' => 2,
                    'unit_price' => 400,
                    'delivery_cad' => 80,
                ],
            ],
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertEqualsWithDelta(880.0, $calc['summary']['materials_pre_hst'], 0.01);
        $this->assertCount(2, $calc['extra_materials']);
        $delivery = collect($calc['extra_materials'])->firstWhere('is_delivery', true);
        $this->assertNotNull($delivery);
        $this->assertSame(1.0, $delivery['qty']);
        $this->assertEqualsWithDelta(80.0, $delivery['amount'], 0.01);
    }

    private function settings(): BasementSettings
    {
        return new BasementSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);
    }
}
