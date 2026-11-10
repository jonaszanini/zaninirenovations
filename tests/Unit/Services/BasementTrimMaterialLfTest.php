<?php

namespace Tests\Unit\Services;

use App\Models\BasementSettings;
use App\Models\TrimmingSettings;
use App\Services\BasementQuotationCalculator;
use App\Services\SharedWorkCatalog;
use Tests\TestCase;

class BasementTrimMaterialLfTest extends TestCase
{
    public function test_baseboard_and_shoe_use_lf_qty_and_default_prices(): void
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
            'material_waste_percent' => 0,
            'include_demolition' => false,
            'insulate_walls' => false,
            'underlayment' => false,
            'flooring_type' => 'none',
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'door_qty' => 0,
            'baseboard_lf' => 100,
            'include_shoe' => true,
            'shoe_lf' => 50,
            'trim_material_lf_v2' => true,
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ], $settings);

        $base = collect($calc['materials'])->firstWhere('key', 'baseboard');
        $this->assertNotNull($base);
        $this->assertSame('lf', $base['unit']);
        $this->assertSame(100.0, (float) $base['qty']);
        $this->assertEqualsWithDelta(3.0, (float) $base['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(300.0, (float) $base['amount'], 0.02);
        $this->assertStringContainsString('Baseboard (per lin. ft)', (string) $base['label']);

        $shoe = collect($calc['materials'])->firstWhere('key', 'shoe');
        $this->assertNotNull($shoe);
        $this->assertSame('lf', $shoe['unit']);
        $this->assertSame(50.0, (float) $shoe['qty']);
        $this->assertEqualsWithDelta(1.5, (float) $shoe['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(75.0, (float) $shoe['amount'], 0.02);

        $recon = $calc['summary']['reconciliation'] ?? [];
        $lines = is_array($recon['lines'] ?? null) ? $recon['lines'] : [];
        $this->assertNotEmpty($lines);
    }

    public function test_trimming_overlay_keeps_eight_foot_stick_prices_from_lf_source(): void
    {
        $trimMats = TrimmingSettings::defaultMaterials();
        $overlay = SharedWorkCatalog::overlayMaterials('trimming', $trimMats);
        $this->assertSame('each', $overlay['baseboard']['unit']);
        $this->assertSame('each', $overlay['shoe']['unit']);
        $this->assertGreaterThan(0, (float) $overlay['baseboard']['unit_price']);
        $this->assertGreaterThan(0, (float) $overlay['shoe']['unit_price']);
    }

    public function test_linked_catalog_each_price_converts_to_per_lf(): void
    {
        $rows = BasementSettings::defaultMaterials();
        $rows['baseboard']['name'] = 'Custom 8 ft baseboard (each)';
        $rows['baseboard']['unit'] = 'each';
        $rows['baseboard']['unit_price'] = 24.0;

        $derived = BasementSettings::applyDerivedMaterialPrices($rows);
        $this->assertSame('lf', $derived['baseboard']['unit']);
        $this->assertEqualsWithDelta(3.0, (float) $derived['baseboard']['unit_price'], 0.0001);
        $this->assertSame('Baseboard (per lin. ft)', $derived['baseboard']['name']);
    }

    public function test_legacy_trim_keeps_eight_foot_stick_pricing(): void
    {
        $legacy = array_merge(BasementQuotationCalculator::defaultInputs(), [
            'area_sqft' => 400,
            'material_waste_percent' => 10,
            'baseboard_lf' => 40,
            'include_shoe' => false,
            'door_qty' => 0,
            'drywall_wall_sf' => 0,
            'flooring_type' => 'none',
            'owner_hourly_rate' => 80,
            'enabled_sections' => array_merge(['overview'], array_keys(BasementQuotationCalculator::SECTIONS)),
        ]);
        unset($legacy['trim_material_lf_v2']);

        $settings = new BasementSettings([
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'hours_per_day' => 8,
            'productivity' => BasementSettings::defaultProductivity(),
            'materials' => BasementSettings::defaultMaterials(),
        ]);

        $calc = (new BasementQuotationCalculator)->calculate($legacy, $settings);
        $base = collect($calc['materials'])->firstWhere('key', 'baseboard');
        $this->assertNotNull($base);
        $this->assertSame('each', $base['unit']);
        $this->assertSame(6.0, (float) $base['qty']);
        $this->assertEqualsWithDelta(16.8, (float) $base['unit_price'], 0.0001);
        $this->assertEqualsWithDelta(100.8, (float) $base['amount'], 0.02);
    }
}
