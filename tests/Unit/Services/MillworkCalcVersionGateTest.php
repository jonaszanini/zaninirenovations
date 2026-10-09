<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkPartBuilder;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

class MillworkCalcVersionGateTest extends TestCase
{
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

    public function test_v1_retyped_pdf_uses_converted_inches(): void
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
        $this->assertStringContainsString('23.5" W', $pdf);
        $this->assertStringNotContainsString('23 1/2" W', $pdf);
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
