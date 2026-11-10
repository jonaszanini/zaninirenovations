<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementHeaderLfTest extends TestCase
{
    public function test_header_linear_feet_labor_uses_lf_per_hr_and_literal_hours(): void
    {
        $settings = new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => array_merge(BasementSettings::defaultProductivity(), [
                'header_lf_per_hr' => 4,
            ]),
            'materials' => BasementSettings::defaultMaterials(),
        ]);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 200,
            'ceiling_height_ft' => 8,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'partition_lf' => 0,
            'bulkhead_lf' => 0,
            'header_lf' => 16,
            'framing_door_openings' => 0,
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ], $settings);

        $row = collect($calc['labor'])->firstWhere('key', 'framing_headers');
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(4.0, (float) $row['hours'], 0.001);
        $this->assertSame('16 lf', $row['detail']);
        $this->assertEqualsWithDelta(320.0, (float) $row['amount'], 0.02);
        $this->assertEqualsWithDelta(
            320.0,
            (float) ($calc['summary']['section_subtotals_pre_hst']['framing'] ?? 0),
            0.02
        );
    }

    public function test_legacy_header_qty_keeps_opening_hours_when_header_lf_key_missing(): void
    {
        $prod = array_merge(BasementSettings::defaultProductivity(), [
            'header_hours_each' => 2,
            'header_lf_per_hr' => 99,
        ]);
        $legacyInputs = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 200,
            'header_qty' => 2,
        ]);
        unset($legacyInputs['header_lf']);

        [$hours, $detail] = BasementQuotationCalculator::headerFramingLabor($legacyInputs, $prod);
        $this->assertEqualsWithDelta(4.0, $hours, 0.001);
        $this->assertSame('2 opening(s)', $detail);
    }
}
