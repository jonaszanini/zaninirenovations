<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementCeilingQuantities;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementCeilingQuantitiesTest extends TestCase
{
    public function test_unified_ceiling_sf_drives_drywall_hang_when_type_is_drywall(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
        ]), $this->settings());

        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'ceiling_sf' => 1500,
        ]));
        $this->assertEqualsWithDelta(1500.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('1,600', $hang['detail']);
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'ceil_drop'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'drop_ceiling'));
    }

    public function test_legacy_split_1500_vs_118_keeps_hang_on_saved_drywall_ceiling(): void
    {
        $inputs = $this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 100,
        ]);
        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs));

        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(118.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('218', $hang['detail']);
        $this->assertStringNotContainsString('1,600', $hang['detail']);
        $this->assertStringNotContainsString('1,500', $hang['detail']);
    }

    public function test_drop_ceiling_uses_ceiling_sf_and_does_not_hang_drywall_ceiling(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
        ]), $this->settings());

        $this->assertEqualsWithDelta(0.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('100', $hang['detail']);
        $this->assertStringNotContainsString('1,500', $hang['detail']);
        $drop = collect($calc['labor'])->firstWhere('key', 'ceil_drop');
        $this->assertNotNull($drop);
        $this->assertStringContainsString('1,500', $drop['detail']);
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'drop_ceiling'));
    }

    public function test_new_drop_without_hang_field_does_not_hang_ceiling(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 3767.65,
        ]), $this->settings());

        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
        ]));
        $this->assertEqualsWithDelta(0.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('3,768', $hang['detail']);
        $this->assertStringNotContainsString('1,500', $hang['detail']);
        $drop = collect($calc['labor'])->firstWhere('key', 'ceil_drop');
        $this->assertNotNull($drop);
        $this->assertStringContainsString('1,500', $drop['detail']);
    }

    public function test_legacy_split_drop_quote_keeps_hang_and_total(): void
    {
        $inputs = $this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 3767.65,
        ]);
        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs));
        $first = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(118.0, $first['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($first['labor'])->firstWhere('key', 'dw_hang');
        $this->assertStringContainsString('3,886', $hang['detail']);
        $this->assertNotNull(collect($first['labor'])->firstWhere('key', 'ceil_drop'));
        $second = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta($first['summary']['grand_total'], $second['summary']['grand_total'], 0.01);
    }

    public function test_legacy_skip_and_ceiling_off_split_keep_hang_and_total(): void
    {
        $skipInputs = $this->quote([
            'ceiling_type' => 'none',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 100,
        ]);
        $skip = (new BasementQuotationCalculator)->calculate($skipInputs, $this->settings());
        $this->assertEqualsWithDelta(118.0, $skip['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('218', collect($skip['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertEqualsWithDelta(
            $skip['summary']['grand_total'],
            (new BasementQuotationCalculator)->calculate($skipInputs, $this->settings())['summary']['grand_total'],
            0.01
        );

        $enabled = array_values(array_filter(
            BasementQuotationCalculator::presetEnabledSections('full'),
            fn ($key) => $key !== 'ceiling'
        ));
        $offInputs = $this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 100,
            'enabled_sections' => $enabled,
        ]);
        $off = (new BasementQuotationCalculator)->calculate($offInputs, $this->settings());
        $this->assertEqualsWithDelta(118.0, $off['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('218', collect($off['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertEqualsWithDelta(
            $off['summary']['grand_total'],
            (new BasementQuotationCalculator)->calculate($offInputs, $this->settings())['summary']['grand_total'],
            0.01
        );
    }

    public function test_clearing_legacy_hang_adopts_unified_drop_gating(): void
    {
        $adopted = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => '',
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => '',
        ]));
        $this->assertEqualsWithDelta(0.0, $adopted['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($adopted['labor'])->firstWhere('key', 'dw_hang')['detail']);
    }

    public function test_skip_and_disabled_ceiling_trade_do_not_hang_ceiling_on_new_quotes(): void
    {
        $skip = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'none',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertEqualsWithDelta(0.0, $skip['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($skip['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertNull(collect($skip['labor'])->firstWhere('key', 'ceil_drop'));

        $enabled = BasementQuotationCalculator::presetEnabledSections('full');
        $enabled = array_values(array_filter($enabled, fn ($key) => $key !== 'ceiling'));
        $off = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
            'enabled_sections' => $enabled,
        ]), $this->settings());
        $this->assertEqualsWithDelta(0.0, $off['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($off['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertNull(collect($off['labor'])->firstWhere('key', 'paint_ceiling'));
    }

    public function test_blank_ceiling_sf_falls_back_to_work_area_for_drywall_type(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertEqualsWithDelta(1500.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('1,600', collect($calc['labor'])->firstWhere('key', 'dw_hang')['detail']);
    }

    public function test_explicit_zero_ceiling_skips_hang_and_paint_without_using_work_area(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 0,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertEqualsWithDelta(0.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($calc['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertStringNotContainsString('1,500', collect($calc['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'paint_ceiling'));
    }

    public function test_blank_paint_ceiling_follows_hang_area_not_work_area_when_hang_is_smaller(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 3767.65,
        ]), $this->settings());
        $paint = collect($calc['labor'])->firstWhere('key', 'paint_ceiling');
        $this->assertNotNull($paint);
        $this->assertStringContainsString('118', $paint['detail']);
        $this->assertStringNotContainsString('1,500', $paint['detail']);
    }

    public function test_insulation_ceiling_stays_on_its_own_toggle(): void
    {
        $off = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'insulate_ceiling' => false,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertNull(collect($off['labor'])->firstWhere('key', 'ins_ceiling'));

        $on = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 400,
            'insulate_ceiling' => true,
            'insulation_ceiling_sf' => 400,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $ins = collect($on['labor'])->firstWhere('key', 'ins_ceiling');
        $this->assertNotNull($ins);
        $this->assertStringContainsString('400', $ins['detail']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function quote(array $overrides = []): array
    {
        return array_merge([
            'area_sqft' => 1500,
            'ceiling_height_ft' => 8,
            'include_demolition' => false,
            'insulate_walls' => false,
            'insulate_ceiling' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'paint_coats' => 2,
            'owner_hourly_rate' => 85,
        ], $overrides);
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
