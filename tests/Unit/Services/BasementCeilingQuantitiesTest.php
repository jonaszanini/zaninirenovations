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
            'area_sqft' => 1200,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
            'drywall_wall_sf' => 100,
        ]), $this->settings());

        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
        ]));
        $this->assertEqualsWithDelta(1500.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $hang = collect($calc['labor'])->firstWhere('key', 'dw_hang');
        $this->assertNotNull($hang);
        $this->assertStringContainsString('1,600', $hang['detail']);
        $this->assertStringNotContainsString('1,300', $hang['detail']);
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
            'ceiling_hang_unified' => true,
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
            'ceiling_hang_unified' => true,
            'drywall_wall_sf' => 3767.65,
        ]), $this->settings());

        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
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

    public function test_clearing_legacy_hang_without_adoption_flag_stays_legacy(): void
    {
        $inputs = $this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => '',
            'drywall_wall_sf' => 100,
        ]);
        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs));
        $cleared = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(0.0, $cleared['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($cleared['labor'])->firstWhere('key', 'dw_hang')['detail']);
    }

    public function test_matching_hang_and_ceiling_area_on_drop_stays_legacy_until_confirm(): void
    {
        $inputs = $this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
        ]);
        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs));
        $first = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(1500.0, $first['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('1,600', collect($first['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertEqualsWithDelta(
            $first['summary']['grand_total'],
            (new BasementQuotationCalculator)->calculate($inputs, $this->settings())['summary']['grand_total'],
            0.01
        );

        $adopted = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertFalse(BasementCeilingQuantities::usesSplit([
            'drywall_ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
        ]));
        $this->assertEqualsWithDelta(0.0, $adopted['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($adopted['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertStringNotContainsString('1,600', collect($adopted['labor'])->firstWhere('key', 'dw_hang')['detail']);
    }

    public function test_skip_and_disabled_ceiling_trade_do_not_hang_ceiling_on_new_quotes(): void
    {
        $skip = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'none',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
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
            'ceiling_hang_unified' => true,
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
            'ceiling_hang_unified' => true,
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
            'ceiling_hang_unified' => true,
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

    public function test_explicit_paint_zero_is_not_replaced_by_hang_area(): void
    {
        $calc = (new BasementQuotationCalculator)->calculate($this->quote([
            'area_sqft' => 1500,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
            'paint_ceiling_sf' => 0,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertEqualsWithDelta(1500.0, $calc['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertNull(collect($calc['labor'])->firstWhere('key', 'paint_ceiling'));
    }

    public function test_insulation_ceiling_stays_on_its_own_toggle(): void
    {
        $off = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
            'insulate_ceiling' => false,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $this->assertNull(collect($off['labor'])->firstWhere('key', 'ins_ceiling'));

        $on = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 400,
            'ceiling_hang_unified' => true,
            'insulate_ceiling' => true,
            'insulation_ceiling_sf' => 400,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $ins = collect($on['labor'])->firstWhere('key', 'ins_ceiling');
        $this->assertNotNull($ins);
        $this->assertStringContainsString('400', $ins['detail']);

        $drop = (new BasementQuotationCalculator)->calculate($this->quote([
            'ceiling_type' => 'drop',
            'ceiling_sf' => 1500,
            'ceiling_hang_unified' => true,
            'insulate_ceiling' => true,
            'insulation_ceiling_sf' => 400,
            'drywall_wall_sf' => 100,
        ]), $this->settings());
        $dropIns = collect($drop['labor'])->firstWhere('key', 'ins_ceiling');
        $this->assertNotNull($dropIns);
        $this->assertStringContainsString('400', $dropIns['detail']);
        $this->assertNotNull(collect($drop['labor'])->firstWhere('key', 'ceil_drop'));
        $this->assertEqualsWithDelta(0.0, $drop['scope']['drywall_ceiling_sf'], 0.01);
    }

    public function test_legacy_blank_hang_keeps_work_area_not_ceiling_sf(): void
    {
        $inputs = $this->quote([
            'area_sqft' => 1200,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => '',
            'drywall_wall_sf' => 100,
        ]);
        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs));
        $legacy = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(1200.0, $legacy['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('1,300', collect($legacy['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $this->assertStringContainsString('1,200', collect($legacy['labor'])->firstWhere('key', 'paint_ceiling')['detail']);
        $this->assertStringNotContainsString('1,500', collect($legacy['labor'])->firstWhere('key', 'dw_hang')['detail']);

        $unified = (new BasementQuotationCalculator)->calculate(
            array_merge($inputs, ['ceiling_hang_unified' => true]),
            $this->settings()
        );
        $this->assertEqualsWithDelta(1500.0, $unified['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertNotEquals(
            $legacy['summary']['grand_total'],
            $unified['summary']['grand_total']
        );
    }

    public function test_legacy_hang_zero_and_blank_paint_still_paints_work_area(): void
    {
        $inputs = $this->quote([
            'area_sqft' => 1200,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 0,
            'drywall_wall_sf' => 100,
        ]);
        $legacy = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(0.0, $legacy['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('100', collect($legacy['labor'])->firstWhere('key', 'dw_hang')['detail']);
        $paint = collect($legacy['labor'])->firstWhere('key', 'paint_ceiling');
        $this->assertNotNull($paint);
        $this->assertStringContainsString('1,200', $paint['detail']);

        $unified = (new BasementQuotationCalculator)->calculate(
            array_merge($inputs, ['ceiling_hang_unified' => true]),
            $this->settings()
        );
        $this->assertEqualsWithDelta(1500.0, $unified['scope']['drywall_ceiling_sf'], 0.01);
        $this->assertStringContainsString('1,500', collect($unified['labor'])->firstWhere('key', 'paint_ceiling')['detail']);
        $this->assertNotEquals(
            $legacy['summary']['grand_total'],
            $unified['summary']['grand_total']
        );
    }

    public function test_legacy_drywall_off_numeric_hang_paints_work_area(): void
    {
        $enabled = array_values(array_filter(
            BasementQuotationCalculator::presetEnabledSections('full'),
            fn ($key) => $key !== 'drywall'
        ));
        $inputs = $this->quote([
            'area_sqft' => 1200,
            'ceiling_type' => 'drywall',
            'ceiling_sf' => 1500,
            'drywall_ceiling_sf' => 118,
            'drywall_wall_sf' => 100,
            'enabled_sections' => $enabled,
        ]);
        $legacy = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertNull(collect($legacy['labor'])->firstWhere('key', 'dw_hang'));
        $paint = collect($legacy['labor'])->firstWhere('key', 'paint_ceiling');
        $this->assertNotNull($paint);
        $this->assertStringContainsString('1,200', $paint['detail']);
        $this->assertStringNotContainsString('118', $paint['detail']);
    }

    /**
     * @dataProvider legacyHangPaintMatrix
     * @param  array<string, mixed>  $hang
     * @param  array<string, mixed>  $mode
     */
    public function test_legacy_hang_paint_matrix_matches_original_calculator(
        string $label,
        array $hang,
        $paint,
        array $mode
    ): void {
        $inputs = $this->quote(array_merge([
            'area_sqft' => 1200,
            'ceiling_sf' => 1500,
            'drywall_wall_sf' => 100,
            'ceiling_type' => $mode['ceiling_type'],
            'enabled_sections' => $mode['enabled_sections'],
        ], $hang));
        if ($paint === 'blank') {
            unset($inputs['paint_ceiling_sf']);
        } else {
            $inputs['paint_ceiling_sf'] = $paint;
        }

        $this->assertTrue(BasementCeilingQuantities::usesSplit($inputs), $label);
        $this->assertArrayNotHasKey('ceiling_hang_unified', $inputs, $label);

        [$expectedHang, $expectedPaint] = $this->legacyHangPaintSf($inputs);
        $calc = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $drywallOn = BasementQuotationCalculator::sectionEnabled($inputs, 'drywall');
        if ($drywallOn) {
            $this->assertEqualsWithDelta($expectedHang, (float) $calc['scope']['drywall_ceiling_sf'], 0.01, $label.' hang');
        }
        $paintLine = collect($calc['labor'])->firstWhere('key', 'paint_ceiling');
        if ($expectedPaint > 0 && BasementQuotationCalculator::sectionEnabled($inputs, 'painting')) {
            $this->assertNotNull($paintLine, $label.' paint line');
            $this->assertStringContainsString(number_format($expectedPaint, 0), $paintLine['detail'], $label);
        } else {
            $this->assertNull($paintLine, $label.' no paint');
        }

        $again = (new BasementQuotationCalculator)->calculate($inputs, $this->settings());
        $this->assertEqualsWithDelta(
            $calc['summary']['grand_total'],
            $again['summary']['grand_total'],
            0.01,
            $label.' total stable'
        );
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>, 2: mixed, 3: array<string, mixed>}>
     */
    public function legacyHangPaintMatrix(): array
    {
        $full = BasementQuotationCalculator::presetEnabledSections('full');
        $ceilingOff = array_values(array_filter($full, fn ($key) => $key !== 'ceiling'));
        $drywallOff = array_values(array_filter($full, fn ($key) => $key !== 'drywall'));
        $modes = [
            'drywall' => ['ceiling_type' => 'drywall', 'enabled_sections' => $full],
            'drop' => ['ceiling_type' => 'drop', 'enabled_sections' => $full],
            'skip' => ['ceiling_type' => 'none', 'enabled_sections' => $full],
            'ceiling-off' => ['ceiling_type' => 'drywall', 'enabled_sections' => $ceilingOff],
            'drywall-off' => ['ceiling_type' => 'drywall', 'enabled_sections' => $drywallOff],
        ];
        $hangs = [
            'missing' => [],
            'null' => ['drywall_ceiling_sf' => null],
            'blank' => ['drywall_ceiling_sf' => ''],
            'zero' => ['drywall_ceiling_sf' => 0],
            'numeric' => ['drywall_ceiling_sf' => 118],
        ];
        $paints = ['blank', 0];
        $rows = [];
        foreach ($modes as $modeName => $mode) {
            foreach ($hangs as $hangName => $hang) {
                foreach ($paints as $paint) {
                    $paintName = $paint === 'blank' ? 'blank-paint' : 'paint-0';
                    $rows[] = [$modeName.' '.$hangName.' '.$paintName, $hang, $paint, $mode];
                }
            }
        }

        return $rows;
    }

    /**
     * Original hang/paint fallbacks from main before unified ceiling_sf.
     *
     * @param  array<string, mixed>  $inputs
     * @return array{0: float, 1: float}
     */
    private function legacyHangPaintSf(array $inputs): array
    {
        $area = round(max(0, (float) ($inputs['area_sqft'] ?? 0)), 2);
        $ceilingType = (string) ($inputs['ceiling_type'] ?? 'drywall');
        $on = function (string $section) use ($inputs): bool {
            return BasementQuotationCalculator::sectionEnabled($inputs, $section);
        };
        $skip = (($inputs['scope_preset'] ?? 'full') === 'wall') && ! $on('ceiling');
        $dwCeil = 0.0;
        if ($on('drywall')) {
            $hangRaw = array_key_exists('drywall_ceiling_sf', $inputs) ? $inputs['drywall_ceiling_sf'] : null;
            $dwCeil = $skip ? 0.0 : $this->qtyOrLegacy(
                $hangRaw,
                $on('ceiling') && in_array($ceilingType, ['drywall', 'paint'], true) ? $area : 0.0
            );
        }
        $pCeil = 0.0;
        if ($on('painting')) {
            $paintRaw = array_key_exists('paint_ceiling_sf', $inputs) ? $inputs['paint_ceiling_sf'] : null;
            $pCeil = $skip ? 0.0 : $this->qtyOrLegacy(
                $paintRaw,
                (! $on('ceiling') || in_array($ceilingType, ['drop', 'none'], true)) ? 0.0 : ($dwCeil ?: $area)
            );
        }

        return [$dwCeil, $pCeil];
    }

    private function qtyOrLegacy(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return round(max(0, $fallback), 2);
        }

        return round(max(0, (float) $value), 2);
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
