<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkPartBuilder;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

/**
 * Tester PR A acceptance checks 1–4, 5 (labour), 6, and 7 (v1 snapshots).
 */
class MillworkCutPlanPlacementTest extends TestCase
{
    public function test_heavy_shelf_places_all_nineteen_parts_and_splits_back_at_stated_seam(): void
    {
        $piece = $this->heavyShelfPiece();
        $exploded = (new MillworkPartBuilder)->explode($piece, 0.75, 0.018);
        $this->assertSame(19, $this->partQty($exploded['parts']));

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'sheet_id' => 'ws_ply_34_cab_48x96',
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $plan = $calc['cut_plan'];
        $this->assertSame(MillworkQuotationCalculator::CUT_PLAN_VERSION, $plan['version']);
        $this->assertSame(19, $plan['original_part_count']);
        $this->assertSame([], $plan['unplaced']);
        $this->assertGreaterThanOrEqual(19, $plan['placed_part_count']);
        $this->assertNotEmpty($plan['seams']);

        $seam = $plan['seams'][0];
        $this->assertStringContainsString('48', (string) $seam['note']);
        $this->assertStringContainsString('96', (string) $seam['note']);
        $panelInches = 0.0;
        foreach ($seam['panel_sizes'] ?? [] as $size) {
            if (preg_match('/([\d.]+)"?×([\d.]+)"?/', str_replace('×', '×', (string) $size), $m)
                || preg_match('/([\d.]+).+?([\d.]+)/', (string) $size, $m)) {
                $a = (float) $m[1];
                $b = (float) $m[2];
                $panelInches += max($a, $b) === 84.0 ? min($a, $b) : (($a + $b) - 84.0);
            }
        }
        $this->assertEqualsWithDelta(96.0, (float) $seam['along'], 0.01);
        $this->assertEqualsWithDelta(48.0, (float) $seam['seam_at'], 0.01);

        $labels = $this->placedLabels($plan);
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Kallax side')));
        $this->assertNotEmpty(array_filter($labels, fn ($l) => str_contains($l, 'Kallax back')));

        foreach ($this->placedSizes($plan) as $size) {
            $this->assertDoesNotMatchRegularExpression('/47\.\d+/', $size, 'Back must not be silently shrunk');
        }

        foreach ($plan['stock_used'] as $stock) {
            $this->assertStringNotContainsStringIgnoringCase('5×5', (string) $stock['name']);
            $this->assertStringNotContainsStringIgnoringCase('5x5', (string) $stock['name']);
        }
    }

    public function test_default_closet_places_all_eleven_parts(): void
    {
        $piece = MillworkQuotationCalculator::defaultPiece('closet');
        $exploded = (new MillworkPartBuilder)->explode($piece, 0.75, 0.018);
        $this->assertSame(11, $this->partQty($exploded['parts']));

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'include_install' => false,
        ], $this->settings());

        $this->assertSame(11, $calc['cut_plan']['original_part_count']);
        $this->assertSame(11, $calc['cut_plan']['placed_part_count']);
        $this->assertSame([], $calc['cut_plan']['unplaced']);
    }

    public function test_tall_cabinet_places_all_eight_parts(): void
    {
        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 24,
            'height' => 84,
            'depth' => 24,
            'unit' => 'in',
            'include_doors' => true,
            'door_count' => 2,
            'include_toe_kick' => true,
            'include_back' => false,
            'shelf_count' => 1,
            'edge_banding' => true,
        ];
        $exploded = (new MillworkPartBuilder)->explode($piece, 0.75, 0.018);
        $this->assertSame(8, $this->partQty($exploded['parts']));

        $sides = array_values(array_filter($exploded['parts'], fn ($p) => str_contains($p->label, 'left') || str_contains($p->label, 'right')));
        $this->assertNotEmpty($sides);
        foreach ($sides as $side) {
            $this->assertEqualsWithDelta(84.0, $side->length, 0.01);
            $this->assertEqualsWithDelta(24.0, $side->width, 0.01);
        }

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'include_install' => false,
        ], $this->settings());

        $this->assertSame(8, $calc['cut_plan']['original_part_count']);
        $this->assertSame(8, $calc['cut_plan']['placed_part_count']);
        $this->assertSame([], $calc['cut_plan']['unplaced']);
    }

    public function test_eighteen_by_eighty_door_places_both_doors(): void
    {
        $piece = [
            'type' => 'cabinet_door',
            'edge_banding' => true,
            'sets' => [[
                'qty' => 2,
                'width' => 18,
                'height' => 80,
                'unit' => 'in',
            ]],
        ];
        $exploded = (new MillworkPartBuilder)->explode($piece, 0.75, 0.018);
        $this->assertSame(2, $this->partQty($exploded['parts']));
        $this->assertEqualsWithDelta(80.0, $exploded['parts'][0]->length, 0.01);
        $this->assertEqualsWithDelta(18.0, $exploded['parts'][0]->width, 0.01);

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'include_install' => false,
        ], $this->settings());

        $this->assertSame(2, $calc['cut_plan']['original_part_count']);
        $this->assertSame(2, $calc['cut_plan']['placed_part_count']);
        $this->assertSame([], $calc['cut_plan']['unplaced']);
        $this->assertGreaterThan(0, $calc['cut_plan']['sheet_count']);
    }

    public function test_part_too_big_for_any_sheet_charges_no_labour(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'finish' => 'none',
                'fastener' => 'screws',
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertNotEmpty($calc['cut_plan']['unplaced']);
        $this->assertSame(0, $calc['cut_plan']['placed_part_count']);
        $this->assertSame(0.0, (float) $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, (float) $calc['summary']['total_hours']);

        $hints = (new MillworkQuotationCalculator)->validationHints([
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
        ], false, $this->settings());
        $this->assertNotEmpty($hints);
        $this->assertStringContainsString('do not fit', strtolower(implode(' ', $hints)));
    }

    public function test_baltic_birch_at_129_prices_every_sheet_at_129(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'cabinet_door',
                'edge_banding' => false,
                'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
            ]],
            'sheet_id' => 'ws_bb_bb_18mm_48x96',
            'include_install' => false,
        ], $this->settings());

        $this->assertSame([], $calc['cut_plan']['unplaced']);
        $this->assertGreaterThan(0, $calc['cut_plan']['sheet_count']);
        foreach ($calc['cut_plan']['stock_used'] as $stock) {
            $this->assertEqualsWithDelta(129.0, (float) $stock['unit_price'], 0.01);
            $this->assertStringContainsString('Baltic Birch', (string) $stock['name']);
            $this->assertStringContainsString('18mm', (string) $stock['name']);
        }
        $sheetRows = array_values(array_filter(
            $calc['materials'],
            fn ($row) => str_starts_with((string) ($row['key'] ?? ''), 'sheet_')
        ));
        $this->assertNotEmpty($sheetRows);
        foreach ($sheetRows as $row) {
            $this->assertEqualsWithDelta(129.0, (float) $row['unit_price'], 0.01);
        }
    }

    public function test_saved_v1_quotes_keep_main_snapshot_output(): void
    {
        foreach (['closet_default.json', 'heavy_shelf.json'] as $file) {
            $fixture = $this->loadV1Fixture($file);
            $calculation = $fixture['calculation'];
            $quote = new MillworkQuotation([
                'calculation' => $calculation,
            ]);

            $this->assertSame(1, $quote->cutPlanVersion());
            $this->assertFalse($quote->hasUnplacedCutParts());
            $this->assertSame((int) $fixture['sheet_count'], $quote->sheetCount());
            $this->assertEqualsWithDelta(
                (float) $fixture['labor_pre_hst'],
                $quote->labourPreTaxAmountForProjectQuotation(),
                0.001
            );
            $this->assertEqualsWithDelta(
                (float) $fixture['materials_pre_hst'],
                (float) ($calculation['summary']['materials_pre_hst'] ?? 0),
                0.001
            );
            $this->assertEqualsWithDelta(
                (float) $fixture['grand_total'],
                (float) ($calculation['summary']['grand_total'] ?? 0),
                0.001
            );
            $this->assertSame(
                (int) $fixture['sheet_count'],
                (int) ($calculation['cut_plan']['sheet_count'] ?? 0)
            );
            $this->assertArrayNotHasKey('unplaced', $calculation['cut_plan']);
            $this->assertArrayNotHasKey('version', $calculation['cut_plan']);
        }

        $live = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $this->assertSame(2, $live['cut_plan']['version']);
        $this->assertSame(11, $live['cut_plan']['placed_part_count']);
    }

    public function test_separate_shelves_do_not_share_a_part_id_or_drop_silently(): void
    {
        $small = [
            'type' => 'shelf',
            'edge_banding' => false,
            'sets' => [['qty' => 1, 'length' => 30, 'depth' => 12, 'unit' => 'in']],
        ];
        $huge = [
            'type' => 'shelf',
            'edge_banding' => false,
            'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
        ];
        $builder = new MillworkPartBuilder();
        $a = $builder->explode($small, 0.75, 0.0, 0);
        $b = $builder->explode($huge, 0.75, 0.0, 1);
        $this->assertNotSame($a['parts'][0]->id, $b['parts'][0]->id);

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$small, $huge],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertSame(1, $calc['cut_plan']['placed_part_count']);
        $this->assertNotEmpty($calc['cut_plan']['unplaced']);
        $this->assertSame(1, (int) ($calc['cut_plan']['unplaced'][0]['qty'] ?? 1));
        $this->assertStringContainsString('100"', (string) ($calc['cut_plan']['unplaced'][0]['size'] ?? ''));
        $this->assertGreaterThan(0.0, (float) $calc['summary']['labor_pre_hst']);
    }

    public function test_closet_plus_unplaced_shelves_does_not_charge_shelf_labour(): void
    {
        $closet = MillworkQuotationCalculator::defaultPiece('closet');
        $closet['edge_banding'] = false;
        $closet['include_install'] = false;
        $shelf = [
            'type' => 'shelf',
            'edge_banding' => false,
            'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
        ];

        $closetOnly = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$closet],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $mixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$closet, $shelf, $shelf, $shelf],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertNotEmpty($mixed['cut_plan']['unplaced']);
        $this->assertEqualsWithDelta(
            (float) $closetOnly['summary']['labor_pre_hst'],
            (float) $mixed['summary']['labor_pre_hst'],
            0.02
        );
        $this->assertNotEquals(0.0, (float) $mixed['summary']['labor_pre_hst']);
    }

    public function test_baltic_18mm_does_not_swap_to_five_by_five_or_cabinet_offcut(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'cabinet_door',
                'edge_banding' => false,
                'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
            ]],
            'sheet_id' => 'ws_bb_bb_18mm_48x96',
            'include_install' => false,
        ], $this->settings());

        $this->assertSame([], $calc['cut_plan']['unplaced']);
        foreach ($calc['cut_plan']['stock_used'] as $stock) {
            $this->assertEqualsWithDelta(129.0, (float) $stock['unit_price'], 0.01);
            $this->assertStringContainsString('Baltic Birch', (string) $stock['name']);
            $this->assertStringNotContainsStringIgnoringCase('5×5', (string) $stock['name']);
            $this->assertNotEquals(89.0, (float) $stock['unit_price']);
            $this->assertNotEquals(24.0, (float) $stock['unit_price']);
        }
    }

    public function test_upright_front_banding_swaps_with_dims_on_v2(): void
    {
        $piece = [
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 24,
            'height' => 84,
            'depth' => 24,
            'unit' => 'in',
            'include_doors' => false,
            'include_toe_kick' => false,
            'include_back' => false,
            'shelf_count' => 0,
            'edge_banding' => true,
        ];
        $v2 = (new MillworkPartBuilder(2))->explode($piece, 0.75, 0.018, 0);
        $v1 = (new MillworkPartBuilder(1))->explode($piece, 0.75, 0.018, 0);
        $sideV2 = collect($v2['parts'])->first(fn ($p) => str_contains($p->label, 'left'));
        $sideV1 = collect($v1['parts'])->first(fn ($p) => str_contains($p->label, 'left'));
        $this->assertNotNull($sideV2);
        $this->assertNotNull($sideV1);
        $this->assertEqualsWithDelta(84.0, $sideV2->length, 0.01);
        $this->assertEqualsWithDelta(24.0, $sideV2->width, 0.01);
        $this->assertGreaterThan(0, $sideV2->edgeBandLeft);
        $this->assertEquals(0.0, $sideV2->edgeBandTop);
        $this->assertEqualsWithDelta(84.0, $sideV1->width, 0.01);
        $this->assertEqualsWithDelta(24.0, $sideV1->length, 0.01);
        $this->assertGreaterThan(0, $sideV1->edgeBandTop);
        $this->assertEquals(0.0, $sideV1->edgeBandLeft);
    }

    public function test_back_splitter_splits_evenly_and_banner_groups_qty(): void
    {
        $piece = [
            'type' => 'kallax',
            'qty' => 1,
            'width' => 100,
            'height' => 36,
            'depth' => 12,
            'unit' => 'in',
            'columns' => 2,
            'rows' => 2,
            'include_back' => true,
            'edge_banding' => false,
        ];
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$piece],
            'include_install' => false,
        ], $this->settings());
        $this->assertNotEmpty($calc['cut_plan']['seams']);
        $seam = $calc['cut_plan']['seams'][0];
        $this->assertEqualsWithDelta(50.0, (float) $seam['seam_at'], 0.05);
        $this->assertEqualsWithDelta(100.0, (float) $seam['along'], 0.05);
        $this->assertMatchesRegularExpression('/width|height/', (string) $seam['note']);
        foreach ($seam['panel_sizes'] ?? [] as $size) {
            $this->assertDoesNotMatchRegularExpression('/\b4(\.0+)?["\']?$/', (string) $size);
        }

        $mixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
            ],
            'include_install' => false,
        ], $this->settings());
        $this->assertCount(1, $mixed['cut_plan']['unplaced']);
        $this->assertSame(3, (int) $mixed['cut_plan']['unplaced'][0]['qty']);
        $this->assertStringStartsWith('3×', (string) $mixed['cut_plan']['unplaced'][0]['label']);
        $this->assertSame('100"×12"', (string) $mixed['cut_plan']['unplaced'][0]['size']);
    }

    public function test_builder_version_gate_keeps_v1_side_orientation(): void
    {
        $piece = [
            'type' => 'closet',
            'qty' => 1,
            'width' => 72,
            'height' => 84,
            'depth' => 14,
            'unit' => 'in',
            'include_doors' => false,
            'include_back' => false,
            'shelf_count' => 0,
            'columns' => 2,
            'edge_banding' => false,
        ];
        $v1 = (new MillworkPartBuilder(1))->explode($piece, 0.75, 0.0, 0);
        $v2 = (new MillworkPartBuilder(2))->explode($piece, 0.75, 0.0, 0);
        $leftV1 = collect($v1['parts'])->first(fn ($p) => str_contains($p->label, 'left'));
        $leftV2 = collect($v2['parts'])->first(fn ($p) => str_contains($p->label, 'left'));
        $this->assertEqualsWithDelta(84.0, $leftV1->width, 0.01);
        $this->assertEqualsWithDelta(14.0, $leftV1->length, 0.01);
        $this->assertEqualsWithDelta(84.0, $leftV2->length, 0.01);
        $this->assertEqualsWithDelta(14.0, $leftV2->width, 0.01);
    }

    /**
     * @param  list<\App\Services\CutOptimizer\Part>  $parts
     */
    private function partQty(array $parts): int
    {
        $n = 0;
        foreach ($parts as $part) {
            $n += max(1, (int) $part->quantity);
        }

        return $n;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function placedLabels(array $plan): array
    {
        $labels = [];
        foreach ($plan['layouts'] ?? [] as $layout) {
            foreach ($layout['legend'] ?? [] as $row) {
                $labels[] = (string) ($row['label'] ?? '');
            }
        }

        return $labels;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<string>
     */
    private function placedSizes(array $plan): array
    {
        $sizes = [];
        foreach ($plan['layouts'] ?? [] as $layout) {
            foreach ($layout['legend'] ?? [] as $row) {
                $sizes[] = (string) ($row['size'] ?? '');
            }
        }

        return $sizes;
    }

    /**
     * @return array<string, mixed>
     */
    private function heavyShelfPiece(): array
    {
        return [
            'type' => 'kallax',
            'qty' => 1,
            'width' => 96,
            'height' => 84,
            'depth' => 24,
            'unit' => 'in',
            'columns' => 3,
            'rows' => 5,
            'include_back' => true,
            'edge_banding' => true,
            'finish' => 'none',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadV1Fixture(string $file): array
    {
        $path = base_path('tests/fixtures/millwork-cut-plan-v1/'.$file);
        $this->assertFileExists($path);
        $json = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($json);

        return $json;
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
