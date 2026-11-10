<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementCornerBeadTest extends TestCase
{
    public function test_cb1_outside_corners_use_ceiling_height_and_eight_foot_sticks(): void
    {
        $settings = new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'ceiling_height_ft' => 9,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 400,
            'drywall_corners' => 2,
            'finish_level' => 4,
            'corner_bead_by_height' => true,
            'drywall_boxing_v2' => true,
            'bulkhead_lf' => 0,
            'header_lf' => 0,
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ], $settings);

        $wallBead = collect($calc['materials'])->firstWhere('key', 'corner_bead');
        $this->assertNotNull($wallBead);
        $this->assertSame(4.0, (float) $wallBead['qty']);
        $this->assertEqualsWithDelta(26.0, (float) $wallBead['amount'], 0.02);
    }

    public function test_cb2_bulkhead_and_header_boxing_add_drywall_labor_bead_and_sf(): void
    {
        $settings = new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);

        $calc = (new BasementQuotationCalculator)->calculate([
            'area_sqft' => 400,
            'ceiling_height_ft' => 8,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 400,
            'drywall_corners' => 0,
            'finish_level' => 4,
            'corner_bead_by_height' => true,
            'drywall_boxing_v2' => true,
            'bulkhead_lf' => 10,
            'header_lf' => 8,
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ], $settings);

        $boxingLabor = collect($calc['labor'])->firstWhere('key', 'dw_boxing');
        $this->assertNotNull($boxingLabor);
        $this->assertStringContainsString('30 sf duct boxing', (string) $boxingLabor['detail']);
        $this->assertStringContainsString('24 sf header/beam boxing', (string) $boxingLabor['detail']);

        $boxingBead = collect($calc['materials'])->firstWhere('key', 'corner_bead_boxing');
        $this->assertNotNull($boxingBead);
        $this->assertSame(5.0, (float) $boxingBead['qty']);

        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $finish = collect($calc['labor'])->firstWhere('key', 'dw_finish');
        $this->assertNotNull($hang);
        $this->assertNotNull($finish);
        $this->assertStringContainsString('54 sf duct/header boxing', (string) $finish['detail']);

        $recon = $calc['summary']['reconciliation'] ?? [];
        $lines = is_array($recon['lines'] ?? null) ? $recon['lines'] : [];
        $this->assertNotEmpty($lines);
    }

    public function test_legacy_corner_bead_without_height_flag_keeps_one_stick_per_corner(): void
    {
        $legacy = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 400,
            'ceiling_height_ft' => 9,
            'drywall_wall_sf' => 400,
            'drywall_corners' => 2,
            'finish_level' => 4,
            'bulkhead_lf' => 10,
            'header_lf' => 8,
            'flooring_type' => 'none',
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($legacy['corner_bead_by_height'], $legacy['drywall_boxing_v2']);

        $settings = new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);
        $calc = (new BasementQuotationCalculator)->calculate($legacy, $settings);
        $wallBead = collect($calc['materials'])->firstWhere('key', 'corner_bead');
        $this->assertNotNull($wallBead);
        $this->assertSame(2.0, (float) $wallBead['qty']);
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'dw_boxing'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'corner_bead_boxing'));
    }
}
