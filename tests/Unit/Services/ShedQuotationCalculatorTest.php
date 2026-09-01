<?php

namespace Tests\Unit\Services;

use App\Models\ShedSettings;
use App\Services\ShedQuotationCalculator;
use Tests\TestCase;

class ShedQuotationCalculatorTest extends TestCase
{
    public function test_empty_size_has_zero_totals(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate(
            ['length_ft' => 0, 'width_ft' => 0],
            $this->settings()
        );

        $this->assertSame(0.0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['grand_total']);
    }

    public function test_standard_gable_shed_produces_labor_materials_and_eta(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'shed_type' => 'gable',
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'roof_pitch' => 6,
            'foundation' => 'skids',
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ], $this->settings());

        $this->assertSame(120.0, $calc['scope']['area_sqft']);
        $this->assertGreaterThan(0, $calc['summary']['total_hours']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['materials_pre_hst']);
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] + $calc['summary']['materials_pre_hst'], 2),
            $calc['summary']['subtotal_pre_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(
            round($calc['summary']['labor_pre_hst'] * 0.13, 2),
            $calc['summary']['labor_hst'],
            0.01
        );
        $this->assertCount(3, $calc['eta']['scenarios']);
        $this->assertFalse($calc['scope']['permit_required']);
    }

    public function test_area_over_15_sqm_sets_permit_flag(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 12,
            'width_ft' => 14,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertGreaterThan(15, $calc['scope']['area_sqm']);
        $this->assertTrue($calc['scope']['permit_required']);
        $this->assertNotEmpty($calc['scope']['permit_warnings']);
        $this->assertStringContainsString('Max without a permit: 161.5 sq ft', $calc['scope']['permit_warnings'][0]);
        $this->assertStringContainsString($calc['scope']['area_sqft'].' sq ft', $calc['scope']['permit_warnings'][0]);
        $this->assertStringNotContainsString('m²', $calc['scope']['permit_warnings'][0]);
    }

    public function test_attached_triggers_permit_warning_under_threshold(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 8,
            'width_ft' => 8,
            'attached' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertLessThanOrEqual(15, $calc['scope']['area_sqm']);
        $this->assertTrue($calc['scope']['permit_required']);
        $this->assertTrue(collect($calc['scope']['permit_warnings'])->contains(
            fn ($w) => str_contains((string) $w, 'Attached')
        ));
    }

    public function test_gambrel_roof_area_is_larger_than_gable(): void
    {
        $base = [
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'roof_pitch' => 6,
            'owner_hourly_rate' => 80,
        ];
        $gable = (new ShedQuotationCalculator)->calculate(array_merge($base, ['shed_type' => 'gable']), $this->settings());
        $gambrel = (new ShedQuotationCalculator)->calculate(array_merge($base, ['shed_type' => 'gambrel']), $this->settings());

        $this->assertGreaterThan($gable['scope']['roof_sf'], $gambrel['scope']['roof_sf']);
    }

    public function test_client_provided_materials_zero_material_total(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 10,
            'client_provides_all' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
    }

    public function test_finished_interior_adds_insulation_and_drywall(): void
    {
        $rough = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 10,
            'interior_finish' => 'rough',
            'owner_hourly_rate' => 80,
        ], $this->settings());
        $finished = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 10,
            'interior_finish' => 'finished',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertGreaterThan($rough['summary']['total_hours'], $finished['summary']['total_hours']);
        $this->assertNotNull(collect($finished['labor'])->firstWhere('key', 'insulation'));
        $this->assertTrue($finished['scope']['permit_required']);
    }

    public function test_electrical_adds_outlets_and_permit_note(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 8,
            'width_ft' => 8,
            'include_electrical' => true,
            'outlet_qty' => 4,
            'light_qty' => 2,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'elec_outlets'));
        $this->assertTrue(collect($calc['scope']['permit_warnings'])->contains(
            fn ($w) => str_contains((string) $w, 'ESA')
        ));
    }

    public function test_validation_hints_require_size(): void
    {
        $hints = (new ShedQuotationCalculator)->validationHints(['length_ft' => 0, 'width_ft' => 0], false);
        $this->assertNotEmpty($hints);
        $this->assertSame([], (new ShedQuotationCalculator)->validationHints(['length_ft' => 10, 'width_ft' => 8], false));
        $this->assertSame([], (new ShedQuotationCalculator)->validationHints(['length_ft' => 0], true));
    }

    public function test_asphalt_shingles_are_counted_in_bundles(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'roof_pitch' => 6,
            'roof_type' => 'asphalt',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $shingles = collect($calc['materials'])->firstWhere('key', 'asphalt_shingle');
        $this->assertNotNull($shingles);
        $this->assertSame('bundle', $shingles['unit']);

        $expected = max(1, (int) ceil(
            ((float) $calc['scope']['roof_sf'] * ShedSettings::MATERIAL_WASTE) / ShedSettings::SHINGLE_SF_PER_BUNDLE
        ));
        $this->assertSame((float) $expected, (float) $shingles['qty']);
        $this->assertGreaterThan(1, $expected);
    }

    public function test_metal_black_and_silver_are_counted_in_panels(): void
    {
        $base = [
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'roof_pitch' => 6,
            'owner_hourly_rate' => 80,
        ];
        $black = (new ShedQuotationCalculator)->calculate(array_merge($base, ['roof_type' => 'metal_black']), $this->settings());
        $silver = (new ShedQuotationCalculator)->calculate(array_merge($base, ['roof_type' => 'metal_galvanized']), $this->settings());

        $blackRow = collect($black['materials'])->firstWhere('key', ShedSettings::MATERIAL_METAL_BLACK);
        $silverRow = collect($silver['materials'])->firstWhere('key', ShedSettings::MATERIAL_METAL_GALVANIZED);
        $this->assertNotNull($blackRow);
        $this->assertNotNull($silverRow);
        $this->assertNull(collect($black['materials'])->firstWhere('key', 'asphalt_shingle'));
        $this->assertSame('panel', $blackRow['unit']);
        $this->assertSame('panel', $silverRow['unit']);

        $expected = max(1, (int) ceil(
            ((float) $black['scope']['roof_sf'] * ShedSettings::MATERIAL_WASTE) / ShedSettings::METAL_PANEL_SF
        ));
        $this->assertSame((float) $expected, (float) $blackRow['qty']);
        $this->assertSame((float) $expected, (float) $silverRow['qty']);
        $this->assertEqualsWithDelta(72.28, (float) $blackRow['unit_price'], 0.001);
        $this->assertEqualsWithDelta(63.38, (float) $silverRow['unit_price'], 0.001);
    }

    public function test_legacy_metal_roof_type_uses_black_panels(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 12,
            'roof_type' => 'metal',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', ShedSettings::MATERIAL_METAL_BLACK));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'asphalt_shingle'));
        $this->assertSame('Metal — Black', ShedQuotationCalculator::roofLabel('metal'));
        $this->assertSame('Metal — Silver', ShedQuotationCalculator::roofLabel('metal_silver'));
        $this->assertSame('metal_black', ShedQuotationCalculator::normalizeRoofType('metal'));
        $this->assertSame('metal_galvanized', ShedQuotationCalculator::normalizeRoofType('metal_silver'));
    }

    public function test_not_now_skips_roof_covering_and_siding(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 12,
            'roof_type' => 'none',
            'siding' => 'none',
            'include_paint' => true,
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $keys = collect($calc['materials'])->pluck('key');
        $this->assertFalse($keys->contains('asphalt_shingle'));
        $this->assertFalse($keys->contains('siding_vinyl'));
        $this->assertFalse($keys->contains('house_wrap'));
        $this->assertFalse($keys->contains('underlayment'));
        $this->assertFalse($keys->contains('paint_exterior'));
        $this->assertTrue($keys->contains('osb_58_roof'));
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'roof_cover'));
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'siding'));
        $this->assertSame('Not now', ShedQuotationCalculator::roofLabel('none'));
        $this->assertSame('Not now', ShedQuotationCalculator::sidingLabel('none'));
    }

    public function test_weather_coat_quotes_sealer_instead_of_finish(): void
    {
        $calc = (new ShedQuotationCalculator)->calculate([
            'length_ft' => 10,
            'width_ft' => 12,
            'roof_type' => 'weather_coat',
            'siding' => 'weather_coat',
            'owner_hourly_rate' => 80,
        ], $this->settings());

        $roofCoat = collect($calc['materials'])->firstWhere('key', 'weather_coat_roof');
        $wallCoat = collect($calc['materials'])->firstWhere('key', 'weather_coat_siding');
        $this->assertNotNull($roofCoat);
        $this->assertNotNull($wallCoat);
        $this->assertNotNull(collect($calc['materials'])->firstWhere('key', 'house_wrap'));
        $this->assertSame('gallon', $roofCoat['unit']);
        $this->assertSame(ShedSettings::weatherCoatGallons((float) $calc['scope']['roof_sf']), (int) $roofCoat['qty']);
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'asphalt_shingle'));
        $this->assertNull(collect($calc['materials'])->firstWhere('key', 'siding_vinyl'));
        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'roof_weather'));
        $this->assertNotNull(collect($calc['labor'])->firstWhere('key', 'siding_weather'));
    }

    private function settings(): ShedSettings
    {
        return new ShedSettings([
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => null,
            'materials' => null,
        ]);
    }
}
