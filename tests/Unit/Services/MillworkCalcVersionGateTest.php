<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\Frozen\V2MillworkPartBuilder;
use App\Services\Millwork\LegacyMillworkPartBuilder;
use App\Services\Millwork\MillworkDimensionParser;
use App\Services\Millwork\MillworkPartBuilder;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

class MillworkCalcVersionGateTest extends TestCase
{
    public function test_legacy_calculator_file_hash_matches_main(): void
    {
        $relative = 'app/Services/Millwork/LegacyMillworkQuotationCalculator.php';
        $ours = hash_file('sha256', base_path($relative));
        $this->assertSame(
            'fc1e540f1050344445db0068d23eabf7d1902d85de17b9990bc5c51c39a79332',
            $ours,
            'LegacyMillworkQuotationCalculator.php must stay byte-for-byte identical to main'
        );

        $mainSrc = (string) shell_exec('git show origin/main:'.escapeshellarg($relative).' 2>/dev/null');
        if ($mainSrc !== '') {
            $this->assertSame(hash('sha256', $mainSrc), $ours);
        }
    }

    public function test_v1_text_size_762mm_does_not_reprice_on_open(): void
    {
        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34.5,
            'depth' => '762mm',
            'unit' => 'in',
            'include_doors' => false,
            'include_back' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
        ];
        $stored = $this->total($piece, 1);
        $converted = $this->total(MillworkDimensionParser::toLegacyInchPiece($piece), 1);
        $this->assertGreaterThan(1000, $stored);
        $this->assertNotEquals(round($converted, 2), round($stored, 2));
        $this->assertEqualsWithDelta($stored, $this->total($piece, 1), 0.01);
    }

    public function test_edited_piece_never_stays_on_v2(): void
    {
        $edited = [
            'type' => 'shelf',
            'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            '_calc_version' => 3,
        ];
        $this->assertTrue(MillworkPartBuilder::usesNewParsing($edited));
        $this->assertSame(3, MillworkPartBuilder::pieceCalcVersion($edited, 2));
        $this->assertFalse(MillworkPartBuilder::usesNewParsing([
            'type' => 'shelf',
            '_calc_version' => 2,
        ], 2));

        $blankEdited = (new MillworkPartBuilder(2))->explode([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
            '_calc_version' => 3,
        ], 0.75, 0.0);
        $frozen = (new V2MillworkPartBuilder)->explode([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
        ], 0.75, 0.0);
        $this->assertStringContainsString('18"', implode(' ', $blankEdited['labels']));
        $this->assertStringNotContainsString('18"', implode(' ', $frozen['labels']));
    }

    public function test_frozen_v2_trim_default_matches_legacy_main(): void
    {
        $piece = [
            'type' => 'trim',
            'profile' => 'baseboard',
            'linear_ft' => 24,
            'qty' => 1,
            'edge_banding' => false,
        ];
        $frozen = (new V2MillworkPartBuilder)->explode($piece, 0.75, 0.0);
        $legacy = (new LegacyMillworkPartBuilder)->explode($piece, 0.75, 0.0);
        $this->assertSame($legacy['labels'], $frozen['labels']);
        $this->assertNotSame([], $frozen['labels']);

        $total = $this->total([
            'type' => 'trim',
            'profile' => 'baseboard',
            'linear_ft' => 24,
            'qty' => 1,
            'edge_banding' => false,
        ], 2);
        $this->assertGreaterThan(0, $total);
        $this->assertEqualsWithDelta($total, $this->total([
            'type' => 'trim',
            'profile' => 'baseboard',
            'linear_ft' => 24,
            'qty' => 1,
            'edge_banding' => false,
            'stock_length_ft' => 12,
        ], 2), 0.02);
    }

    public function test_new_quotes_default_to_calc_version_three(): void
    {
        $this->assertSame(3, MillworkQuotationCalculator::CALC_VERSION);
        $this->assertSame(3, MillworkQuotationCalculator::resolveCalcVersion([]));
        $this->assertSame(2, MillworkQuotationCalculator::resolveCalcVersion(['_cut_plan_version' => 2]));
        $this->assertSame(2, MillworkQuotationCalculator::resolveCutPlanVersion(['_cut_plan_version' => 3]));
    }

    public function test_v2_bench_blank_height_stays_zero_like_main(): void
    {
        $v2 = $this->total([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
        ], 2);
        $v3 = $this->total([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
        ], 3);
        $this->assertEqualsWithDelta(0.0, $v2, 0.01);
        $this->assertGreaterThan(100, $v3);
    }

    public function test_v2_closet_and_mantel_ft_defaults_stay_on_main_unit(): void
    {
        $closetV2 = (new MillworkPartBuilder(2))->explode([
            'type' => 'closet',
            'unit' => 'ft',
            'width' => 6,
            'height' => '',
            'depth' => '',
            'qty' => 1,
            'edge_banding' => false,
        ], 0.75, 0.0);
        $closetV3 = (new MillworkPartBuilder(3))->explode([
            'type' => 'closet',
            'unit' => 'ft',
            'width' => 6,
            'height' => '',
            'depth' => '',
            'qty' => 1,
            'edge_banding' => false,
        ], 0.75, 0.0);
        $this->assertStringContainsString('1008"', implode(' ', $closetV2['labels']));
        $this->assertStringContainsString('84"', implode(' ', $closetV3['labels']));
        $this->assertStringNotContainsString('1008"', implode(' ', $closetV3['labels']));

        $mantelV2 = (new MillworkPartBuilder(2))->explode([
            'type' => 'mantel',
            'unit' => 'ft',
            'qty' => 1,
            'edge_banding' => false,
        ], 0.75, 0.0);
        $mantelV3 = (new MillworkPartBuilder(3))->explode([
            'type' => 'mantel',
            'unit' => 'ft',
            'qty' => 1,
            'edge_banding' => false,
        ], 0.75, 0.0);
        $this->assertStringContainsString('720"', implode(' ', $mantelV2['labels']));
        $this->assertStringContainsString('60"', implode(' ', $mantelV3['labels']));
    }

    public function test_v2_over_240_wainscot_does_not_skip_cut_plan(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'wainscoting',
                'unit' => 'in',
                'length' => 300,
                'height' => 36,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $this->assertFalse((bool) ($calc['cut_plan']['preview_skipped'] ?? false));
        $this->assertGreaterThan(0, (float) ($calc['summary']['grand_total'] ?? 0));
        $this->assertGreaterThan(0, (int) ($calc['cut_plan']['sheet_count'] ?? 0));
    }

    public function test_v2_hidden_trim_depth_does_not_skip_or_reprice(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'linear_ft' => 40,
                'openings' => 2,
                'width' => 72,
                'height' => 84,
                'depth' => 24,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ], $this->settings(), 2);
        $this->assertFalse((bool) ($calc['cut_plan']['preview_skipped'] ?? false));
        $this->assertEqualsWithDelta(670.88, (float) ($calc['summary']['grand_total'] ?? 0), 0.02);
        $this->assertSame([], (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'linear_ft' => 40,
                'openings' => 2,
                'width' => 72,
                'height' => 84,
                'depth' => 24,
                'qty' => 1,
            ]],
            '_cut_plan_version' => 2,
        ], true));
    }

    public function test_edited_v2_piece_uses_v3_defaults(): void
    {
        $blank = (new MillworkPartBuilder(2))->explode([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
            '_calc_version' => 3,
        ], 0.75, 0.0);
        $this->assertStringContainsString('18"', implode(' ', $blank['labels']));
    }

    public function test_v2_publish_does_not_require_every_piece_to_be_sized(): void
    {
        $messages = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [
                [
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                ],
                [
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => '', 'depth' => '', 'unit' => 'in']],
                ],
            ],
            '_cut_plan_version' => 2,
        ], false, $this->settings());
        $this->assertArrayNotHasKey('pieces.1', $messages);
        $this->assertArrayNotHasKey('calculation', $messages);
    }

    public function test_v2_floating_shelf_uses_set_unit_thickness_fallback(): void
    {
        $inPieceFtSet = $this->total([
            'type' => 'floating_shelf',
            'unit' => 'in',
            'qty' => 1,
            'edge_banding' => false,
            'sets' => [[
                'qty' => 1,
                'length' => 3.9,
                'depth' => 1.1,
                'thickness' => '',
                'unit' => 'ft',
            ]],
        ], 2);
        $ftPieceInSet = $this->total([
            'type' => 'floating_shelf',
            'unit' => 'ft',
            'qty' => 1,
            'edge_banding' => false,
            'sets' => [[
                'qty' => 1,
                'length' => 46.8,
                'depth' => 13.2,
                'thickness' => '',
                'unit' => 'in',
            ]],
        ], 2);
        $this->assertEqualsWithDelta(334.65, $inPieceFtSet, 0.05);
        $this->assertEqualsWithDelta(230.41, $ftPieceInSet, 0.05);
        $this->assertGreaterThan($ftPieceInSet, $inPieceFtSet);
    }

    public function test_v2_pdf_keeps_delivery_wording_when_client_provides_all(): void
    {
        $inputs = [
            'pieces' => [[
                'type' => 'closet',
                'unit' => 'in',
                'width' => 72,
                'height' => 84,
                'depth' => 14,
                'qty' => 1,
                'include_doors' => false,
                'edge_banding' => false,
            ]],
            'client_provides_all' => true,
            'delivery_amount' => 80,
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate($inputs, $this->settings(), 2);
        $quote = new MillworkQuotation([
            'cut_plan_version' => 2,
            'inputs' => $inputs,
            'calculation' => $calc,
        ]);
        $this->assertStringContainsString('delivery', strtolower($quote->scopeDescriptionForPdf()));
    }

    public function test_v2_qty_and_spare_sheets_are_not_clamped_on_calculate(): void
    {
        $qty = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'qty' => 1,
                'edge_banding' => false,
                'sets' => [['qty' => 250, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $one = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'qty' => 1,
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $spare = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'qty' => 1,
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
            ]],
            'spare_sheets' => 60,
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $this->assertGreaterThan((float) ($one['summary']['grand_total'] ?? 0) * 50, (float) ($qty['summary']['grand_total'] ?? 0));
        $this->assertGreaterThan((float) ($one['summary']['grand_total'] ?? 0), (float) ($spare['summary']['grand_total'] ?? 0));
    }

    public function test_edited_v2_closet_over_240_shows_limit_message(): void
    {
        $messages = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'closet',
                'unit' => 'ft',
                'width' => 22,
                'height' => 7,
                'depth' => 1.2,
                'qty' => 1,
                'include_doors' => false,
                '_calc_version' => 3,
            ]],
            '_cut_plan_version' => 2,
        ], false, $this->settings());
        $this->assertArrayHasKey('calculation', $messages);
        $this->assertStringContainsString('240 inches', $messages['calculation']);
    }

    public function test_v1_pdf_uses_legacy_float_cast_not_converted_inches(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'cabinet',
                'name' => 'Pantry',
                'room' => 'Kitchen',
                'qty' => 1,
                'width' => '23 1/2',
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
                'include_doors' => false,
                'include_back' => false,
                'edge_banding' => false,
                'shelf_count' => 0,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 1);
        $quote = new MillworkQuotation([
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'name' => 'Pantry',
                    'room' => 'Kitchen',
                    'qty' => 1,
                    'width' => '23 1/2',
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => false,
                    'edge_banding' => false,
                    'shelf_count' => 0,
                ]],
            ],
            'calculation' => $calc,
        ]);
        $pdf = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('23" W', $pdf);
        $this->assertStringNotContainsString('23.5" W', $pdf);
        $this->assertStringNotContainsString('23 1/2" W', $pdf);
    }

    public function test_additional_labour_30_percent_is_inside_the_grand_total(): void
    {
        $piece = [
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => 18,
            'qty' => 1,
            'edge_banding' => false,
            '_calc_version' => 3,
        ];
        $baseInputs = [
            'pieces' => [$piece],
            'include_install' => true,
            'include_building' => false,
            'building_labor_percent' => 30,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            '_calc_version' => 3,
        ];
        $off = (new MillworkQuotationCalculator)->calculate($baseInputs, $this->settings(), 3);
        $on = (new MillworkQuotationCalculator)->calculate(array_merge($baseInputs, [
            'include_building' => true,
        ]), $this->settings(), 3);

        $baseLabour = (float) $off['summary']['labor_pre_hst'];
        $this->assertGreaterThan(0, $baseLabour);
        $surcharge = round($baseLabour * 0.30, 2);
        $this->assertEqualsWithDelta($surcharge, (float) $on['summary']['building_labor_surcharge'], 0.001);
        $this->assertEqualsWithDelta($baseLabour + $surcharge, (float) $on['summary']['labor_pre_hst'], 0.001);
        $this->assertEqualsWithDelta(
            (float) $off['summary']['materials_goods_pre_hst'],
            (float) $on['summary']['materials_goods_pre_hst'],
            0.001,
            'Additional labour must not change the material price'
        );

        $labourDelta = (float) $on['summary']['labor_pre_hst'] - $baseLabour;
        $hstDelta = (float) $on['summary']['hst'] - (float) $off['summary']['hst'];
        $grandDelta = (float) $on['summary']['grand_total'] - (float) $off['summary']['grand_total'];
        $this->assertEqualsWithDelta($labourDelta + $hstDelta, $grandDelta, 0.001);
        $this->assertGreaterThan($surcharge, $grandDelta, 'Grand total must include the surcharge and the HST on it');

        $lines = (float) $on['summary']['labor_pre_hst']
            + (float) $on['summary']['materials_pre_hst']
            + (float) $on['summary']['markup_amount']
            + (float) $on['summary']['hst'];
        $this->assertEqualsWithDelta($lines, (float) $on['summary']['grand_total'], 0.02);
        $this->assertEqualsWithDelta((float) $on['summary']['grand_total'], (float) $on['summary']['final_quote_total'], 0.001);
    }

    public function test_same_inputs_keep_the_same_material_price(): void
    {
        $inputs = [
            'pieces' => [[
                'type' => 'bench',
                'unit' => 'in',
                'length' => 48,
                'depth' => 16,
                'height' => 18,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => true,
            'owner_hourly_rate' => 85,
            '_calc_version' => 3,
        ];
        $calc = new MillworkQuotationCalculator;
        $first = $calc->calculate($inputs, $this->settings(), 3);
        $second = $calc->calculate($inputs, $this->settings(), 3);
        $this->assertSame(
            (float) $first['summary']['materials_goods_pre_hst'],
            (float) $second['summary']['materials_goods_pre_hst']
        );
        $this->assertGreaterThan(0, (float) $first['summary']['materials_goods_pre_hst']);
    }

    public function test_edited_v2_bench_calculator_price_is_336_34(): void
    {
        $edited = $this->total([
            'type' => 'bench',
            'unit' => 'in',
            'length' => 48,
            'depth' => 16,
            'height' => '',
            'qty' => 1,
            'edge_banding' => false,
            '_calc_version' => 3,
        ], 2);
        $this->assertEquals(336.34, round($edited, 2));
        $this->assertNotEquals(0.0, $edited);
    }

    /**
     * @param  array<string, mixed>  $piece
     */
    private function total(array $piece, int $version): float
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ], $this->settings(), $version);

        return (float) ($calc['summary']['grand_total'] ?? 0);
    }

    private function settings(): MillworkSettings
    {
        $s = new MillworkSettings();
        $s->owner_hourly_rate = 85;
        $s->helper_hourly_rate = 45;
        $s->hours_per_day = 8;
        $s->productivity = MillworkSettings::defaultProductivity();
        $s->catalog = MillworkSettings::defaultCatalog();
        $s->cut_settings = MillworkSettings::defaultCutSettings();

        return $s;
    }
}
