<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkDimensionParser;
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

        $this->assertStringContainsString('width', (string) $seam['note']);
        $this->assertStringNotContainsString('along 100" height', (string) $seam['note']);

        $mixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
                ['type' => 'shelf', 'edge_banding' => false, 'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']]],
            ],
            'include_install' => false,
        ], $this->settings());
        $this->assertCount(1, $mixed['cut_plan']['unplaced']);
        $this->assertSame(3, (int) ($mixed['cut_plan']['unplaced'][0]['qty'] ?? 0));
        $this->assertSame('100"×12"', (string) ($mixed['cut_plan']['unplaced'][0]['size'] ?? ''));
        $this->assertSame('3× Shelf 100"×12"', (string) ($mixed['cut_plan']['unplaced'][0]['label'] ?? ''));
        $this->assertSame(
            1,
            substr_count((string) $mixed['cut_plan']['unplaced'][0]['label'], '100"×12"')
        );

        $qtyTwo = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 2, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ], $this->settings());
        $this->assertCount(1, $qtyTwo['cut_plan']['unplaced']);
        $this->assertSame(2, (int) $qtyTwo['cut_plan']['unplaced'][0]['qty']);
        $this->assertStringContainsString('2×', (string) $qtyTwo['cut_plan']['unplaced'][0]['label']);
        $this->assertStringContainsString('100"×12"', (string) $qtyTwo['cut_plan']['unplaced'][0]['label']);
        $this->assertSame(
            1,
            substr_count((string) $qtyTwo['cut_plan']['unplaced'][0]['label'], '100"×12"')
        );
    }

    public function test_legacy_quotes_without_stored_unplaced_are_never_blocked(): void
    {
        $presets = [
            ['pieces' => [MillworkQuotationCalculator::defaultPiece('closet')]],
            ['pieces' => [$this->heavyShelfPiece()]],
            ['pieces' => [[
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
            ]]],
            ['pieces' => [[
                'type' => 'cabinet_door',
                'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
            ]]],
        ];

        foreach ($presets as $inputs) {
            $quote = new MillworkQuotation([
                'inputs' => $inputs,
                'calculation' => [
                    'cut_plan' => ['sheet_count' => 2, 'layouts' => []],
                    'summary' => ['grand_total' => 1663.90],
                ],
            ]);
            $this->assertArrayNotHasKey('unplaced', $quote->calculation['cut_plan']);
            $this->assertFalse($quote->hasUnplacedCutParts());
            $this->assertTrue($quote->usesLegacyCutBuilder());
        }

        $blocked = new MillworkQuotation([
            'calculation' => [
                'cut_plan' => [
                    'version' => 2,
                    'unplaced' => [['label' => 'Shelf 100"×12"', 'qty' => 1]],
                ],
            ],
        ]);
        $this->assertTrue($blocked->hasUnplacedCutParts());
    }

    public function test_custom_maple_sheet_does_not_swap_to_cabinet_offcut(): void
    {
        $settings = $this->settings();
        $catalog = $settings->catalog;
        $catalog[MillworkSettings::SLOT_WOOD_SHEETS][] = [
            'id' => 'ws_custom_maple',
            'catalog_material_id' => 9991,
            'name' => '3/4" Custom maple plywood 4×8',
            'unit' => 'sheet',
            'unit_price' => 180.00,
            'thickness_in' => 0.75,
            'width_in' => 48,
            'length_in' => 96,
            'material_kind' => 'plywood',
            'grade' => 'cabinet',
        ];
        $settings->catalog = $catalog;

        $this->assertNotSame(
            'cabinet_ply',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_custom_maple',
                'name' => '3/4" Custom maple plywood 4×8',
                'catalog_material_id' => 9991,
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );
        $this->assertSame(
            'material:9991',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_custom_maple',
                'name' => 'Cabinet-grade maple plywood',
                'catalog_material_id' => 9991,
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );
        $this->assertSame(
            'sku:wo-cab-ply',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_custom_oak',
                'sku' => 'WO-CAB-PLY',
                'name' => 'White oak plywood (cabinet-grade)',
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );
        $this->assertNotSame(
            'cabinet_ply',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_custom_maple_name',
                'name' => 'Cabinet-grade maple plywood',
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );
        $this->assertNotSame(
            'cabinet_ply',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_custom_oak_name',
                'name' => 'White oak plywood (cabinet-grade)',
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );
        $this->assertSame(
            'cabinet_ply',
            MillworkSettings::sheetSpecies([
                'id' => 'ws_ply_34_cab_48x96',
                'name' => '3/4" cabinet-grade plywood 4×8',
                'material_kind' => 'plywood',
                'grade' => 'cabinet',
            ])
        );

        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 30, 'depth' => 12, 'unit' => 'in']],
            ]],
            'sheet_id' => 'ws_custom_maple',
            'include_install' => false,
        ], $settings);

        $this->assertNotEmpty($calc['cut_plan']['stock_used']);
        foreach ($calc['cut_plan']['stock_used'] as $stock) {
            $this->assertEqualsWithDelta(180.0, (float) $stock['unit_price'], 0.01);
            $this->assertStringContainsStringIgnoringCase('maple', (string) $stock['name']);
            $this->assertNotEquals(24.0, (float) $stock['unit_price']);
        }
    }

    public function test_unplaced_set_does_not_drop_placed_set_labour_or_keep_its_banding(): void
    {
        $oneShelf = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'finish' => 'paint',
                'sets' => [['qty' => 1, 'length' => 30, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $mixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'finish' => 'paint',
                'sets' => [
                    ['qty' => 1, 'length' => 30, 'depth' => 12, 'unit' => 'in'],
                    ['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in'],
                ],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertSame(1, $mixed['cut_plan']['placed_part_count']);
        $this->assertNotEmpty($mixed['cut_plan']['unplaced']);
        $this->assertEqualsWithDelta(
            (float) $oneShelf['summary']['labor_pre_hst'],
            (float) $mixed['summary']['labor_pre_hst'],
            0.05
        );
        $this->assertEqualsWithDelta(
            (float) $oneShelf['summary']['edge_band_lf'],
            (float) $mixed['summary']['edge_band_lf'],
            0.05
        );
        $this->assertEqualsWithDelta(
            (float) $oneShelf['summary']['paint_sf'],
            (float) $mixed['summary']['paint_sf'],
            0.05
        );
    }

    public function test_unplaced_shelf_does_not_reprice_placed_closet_or_cube(): void
    {
        $closet = MillworkQuotationCalculator::defaultPiece('closet');
        $closet['edge_banding'] = true;
        $cube = [
            'type' => 'kallax',
            'qty' => 1,
            'width' => 15,
            'height' => 15,
            'depth' => 14,
            'unit' => 'in',
            'columns' => 1,
            'rows' => 1,
            'include_back' => true,
            'edge_banding' => false,
        ];
        $unplaced = [
            'type' => 'shelf',
            'edge_banding' => false,
            'sets' => [['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in']],
        ];

        $closetOnly = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$closet],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $closetMixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$closet, $unplaced],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $this->assertNotEmpty($closetMixed['cut_plan']['unplaced']);
        $this->assertEqualsWithDelta(
            $this->laborAmount($closetOnly, 'edge'),
            $this->laborAmount($closetMixed, 'edge'),
            0.05
        );
        $this->assertEqualsWithDelta(136.0, $this->laborAmount($closetOnly, 'edge'), 0.05);
        $this->assertEqualsWithDelta(
            $this->laborAmount($closetOnly, 'install'),
            $this->laborAmount($closetMixed, 'install'),
            0.05
        );

        $cubeOnly = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$cube],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $cubeMixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [$cube, $unplaced],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $this->assertNotEmpty($cubeMixed['cut_plan']['unplaced']);
        $this->assertEqualsWithDelta(
            $this->laborAmount($cubeOnly, 'kallax'),
            $this->laborAmount($cubeMixed, 'kallax'),
            0.05
        );
        $this->assertEqualsWithDelta(72.25, $this->laborAmount($cubeOnly, 'kallax'), 0.05);
    }

    public function test_legacy_calculator_matches_frozen_main_94489657_output(): void
    {
        $expectedTotals = [
            'closet_labor_override' => 2057.70,
            'closet_helper' => 2424.61,
            'closet_plus_inch' => 1672.54,
            'tall_edited' => 1912.93,
            'doors' => 620.24,
            'vanity_default_depth' => 1444.00,
            'kallax' => 697.13,
            'floating_shelf' => 606.61,
            'markup' => 1871.89,
            'delivery' => 1762.85,
            'finishes' => 2322.50,
            'custom_catalogue' => 1663.90,
        ];
        foreach ($this->legacyEditedCases() as $name => $case) {
            $inputs = $case['inputs'];
            $settings = $case['settings'] ?? $this->settings();
            $viaGate = (new MillworkQuotationCalculator)->calculate($inputs, $settings, 1);
            $this->assertArrayNotHasKey('unplaced', $viaGate['cut_plan'] ?? []);
            $this->assertSame(1, (int) ($viaGate['cut_plan']['version'] ?? 0));
            $this->assertArrayNotHasKey('species', $viaGate['sheet'] ?? []);
            if (is_array($viaGate['back_sheet'] ?? null)) {
                $this->assertArrayNotHasKey('species', $viaGate['back_sheet']);
            }
            if (isset($expectedTotals[$name])) {
                $this->assertEqualsWithDelta(
                    $expectedTotals[$name],
                    (float) ($viaGate['summary']['grand_total'] ?? 0),
                    0.01,
                    $name.' dollars'
                );
            }

            $fixturePath = base_path('tests/fixtures/millwork-cut-plan-v1/main-94489657/'.$name.'.json');
            $this->assertFileExists($fixturePath, $name);
            $expected = (string) file_get_contents($fixturePath);
            $actual = $this->encodeLegacySnapshot($this->legacyOutputSnapshot($viaGate));
            $this->assertSame($expected, $actual, $name.' must stay byte-equal to main 94489657');
        }
    }

    public function test_partly_placed_piece_charges_install_on_placed_parts_only(): void
    {
        $twoSmall = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 2, 'length' => 30, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings());
        $mixed = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [
                    ['qty' => 2, 'length' => 30, 'depth' => 12, 'unit' => 'in'],
                    ['qty' => 1, 'length' => 100, 'depth' => 12, 'unit' => 'in'],
                ],
            ]],
            'include_install' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings());

        $this->assertSame(2, $mixed['cut_plan']['placed_part_count']);
        $this->assertNotEmpty($mixed['cut_plan']['unplaced']);
        $this->assertEqualsWithDelta(53.55, $this->laborAmount($twoSmall, 'install'), 0.01);
        $this->assertEqualsWithDelta(
            $this->laborAmount($twoSmall, 'install'),
            $this->laborAmount($mixed, 'install'),
            0.01
        );
        $this->assertNotEquals(94.35, $this->laborAmount($mixed, 'install'));
    }

    public function test_new_path_prices_custom_maple_sheet_at_catalogue_price(): void
    {
        $case = $this->legacyEditedCases()['custom_catalogue'];
        $calc = (new MillworkQuotationCalculator)->calculate($case['inputs'], $case['settings'], 2);

        $sheet = null;
        foreach ($calc['materials'] ?? [] as $row) {
            $label = (string) ($row['label'] ?? '');
            if (stripos($label, 'maple') !== false) {
                $sheet = $row;
                break;
            }
        }
        $this->assertNotNull($sheet, 'Expected a maple sheet on the shopping list.');
        $this->assertEqualsWithDelta(187.65, (float) ($sheet['unit_price'] ?? 0), 0.001);
        $this->assertNotEquals(72.0, (float) ($sheet['unit_price'] ?? 0));
    }

    public function test_new_builder_parses_fraction_and_per_dimension_units(): void
    {
        $plain = (new MillworkPartBuilder)->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34.5,
            'depth' => 24,
            'unit' => 'in',
            'include_doors' => false,
            'include_back' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
        ], 0.75, 0.018);
        $mixed = (new MillworkPartBuilder)->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => '3',
            'width_unit' => 'ft',
            'height' => '34 1/2',
            'height_unit' => 'in',
            'depth' => 2,
            'depth_unit' => 'ft',
            'unit' => 'in',
            'include_doors' => false,
            'include_back' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
        ], 0.75, 0.018);

        $this->assertSame($plain['box_sf'], $mixed['box_sf']);
        $this->assertSame($plain['labels'], $mixed['labels']);
    }

    public function test_cabinet_does_not_bill_hinges_without_doors(): void
    {
        $noDoors = (new MillworkPartBuilder)->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34.5,
            'depth' => 24,
            'include_doors' => false,
            'hinge_qty' => 8,
            'edge_banding' => false,
            'shelf_count' => 0,
        ], 0.75, 0.018);

        $this->assertSame(0.0, (float) $noDoors['hardware']['hinges']);
    }

    public function test_feet_inch_and_metric_dims_price_like_plain_inches(): void
    {
        $base = [
            'type' => 'cabinet',
            'qty' => 1,
            'include_doors' => false,
            'include_back' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
            'unit' => 'in',
        ];
        $plain = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [array_merge($base, ['width' => 30, 'height' => 34.5, 'depth' => 24])],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $fancy = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [array_merge($base, [
                'width' => '2\' 6"',
                'height' => '876.3mm',
                'depth' => '2 ft',
            ])],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);

        $this->assertEqualsWithDelta(
            (float) $plain['summary']['grand_total'],
            (float) $fancy['summary']['grand_total'],
            0.05
        );
        $this->assertGreaterThan(500, (float) $fancy['summary']['grand_total']);
    }

    public function test_legacy_retyped_sizes_match_inch_total(): void
    {
        $base = [
            'type' => 'cabinet',
            'qty' => 1,
            'include_doors' => false,
            'include_back' => false,
            'edge_banding' => false,
            'shelf_count' => 0,
        ];
        $plain = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [array_merge($base, [
                'width' => 36,
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
            ])],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 1);
        $retyped = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [array_merge($base, [
                'width' => '3',
                'width_unit' => 'ft',
                'height' => '34 1/2',
                'height_unit' => 'in',
                'depth' => '2 ft',
                'depth_unit' => 'in',
                'unit' => 'in',
            ])],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 1);

        $this->assertEqualsWithDelta(
            (float) $plain['summary']['grand_total'],
            (float) $retyped['summary']['grand_total'],
            0.02
        );
        $this->assertGreaterThan(0, (float) $retyped['summary']['grand_total']);
    }

    public function test_v1_stored_trim_in_feet_matches_main_total(): void
    {
        $inputs = [
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'length' => 20,
                'openings' => 3,
                'width' => 36,
                'height' => 80,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $out = (new MillworkQuotationCalculator)->calculate($inputs, $this->settings(), 1);
        $opened = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'length' => 20,
                'length_unit' => 'ft',
                'openings' => 3,
                'width' => 36,
                'width_unit' => 'ft',
                'height' => 80,
                'height_unit' => 'ft',
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ], $this->settings(), 1);

        $this->assertEqualsWithDelta(580.44, (float) $out['summary']['grand_total'], 0.02);
        $this->assertEqualsWithDelta(
            (float) $out['summary']['grand_total'],
            (float) $opened['summary']['grand_total'],
            0.02
        );
        $this->assertNotEquals(5792.40, round((float) $out['summary']['grand_total'], 2));
    }

    public function test_v1_fuzzed_stored_quotes_match_main_when_untouched(): void
    {
        mt_srand(9984);
        $calc = new MillworkQuotationCalculator();
        $settings = $this->settings();
        $samples = 48;
        for ($i = 0; $i < $samples; $i++) {
            $inputs = $i === 0 ? $this->v1StoredTrimFeetInputs() : $this->fuzzedLegacyInputs($i);
            foreach ($inputs['pieces'] as $pi => $piece) {
                $this->assertSame(
                    $this->legacySizeSnapshot($piece),
                    $this->legacySizeSnapshot(MillworkDimensionParser::toLegacyInchPiece($piece)),
                    'seed '.$i.' piece '.$pi.' must stay in the stored unit'
                );
            }
            $out = $calc->calculate($inputs, $settings, 1);
            $again = $calc->calculate($inputs, $settings, 1);
            $total = round((float) ($out['summary']['grand_total'] ?? 0), 2);
            $this->assertTrue(is_finite($total), 'seed '.$i);
            $this->assertGreaterThan(0, $total, 'seed '.$i);
            $this->assertEquals($total, round((float) ($again['summary']['grand_total'] ?? 0), 2), 'seed '.$i);
            if ($i === 0) {
                $this->assertEqualsWithDelta(580.44, $total, 0.02);
            }
        }
    }

    public function test_v1_retyped_size_pdf_scope_uses_converted_inches(): void
    {
        $quote = new MillworkQuotation([
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'name' => 'Pantry',
                    'room' => 'Kitchen',
                    'qty' => 1,
                    'width' => '23 1/2',
                    'height' => '2\' 6"',
                    'depth' => '762mm',
                    'unit' => 'in',
                    'width_unit' => 'in',
                    'include_doors' => false,
                    'edge_banding' => false,
                ]],
                'include_install' => false,
            ],
            'calculation' => ['summary' => []],
        ]);
        $scope = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('23.5" W', $scope);
        $this->assertStringContainsString('30" H', $scope);
        $this->assertStringContainsString('30" D', $scope);
        $this->assertStringNotContainsString('23" W', $scope);
        $this->assertStringNotContainsString('2" W', $scope);
        $this->assertStringNotContainsString('762" D', $scope);

        $ftSelect = new MillworkQuotation([
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'name' => 'Pantry',
                    'qty' => 1,
                    'width' => 2.5,
                    'width_unit' => 'ft',
                    'height' => 34.5,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => false,
                ]],
                'include_install' => false,
            ],
            'calculation' => ['summary' => []],
        ]);
        $this->assertStringContainsString('30" W', $ftSelect->scopeDescriptionForPdf());
        $this->assertStringNotContainsString('2.5" W', $ftSelect->scopeDescriptionForPdf());
    }

    public function test_v2_ft_trim_hidden_openings_stay_inches_like_main(): void
    {
        $inputs = [
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'linear_ft' => 40,
                'openings' => 2,
                'width' => 72,
                'height' => 84,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
        $out = (new MillworkQuotationCalculator)->calculate($inputs, $this->settings(), 2);
        $this->assertEqualsWithDelta(670.88, (float) $out['summary']['grand_total'], 0.02);
        $this->assertNotEquals(4336.71, round((float) $out['summary']['grand_total'], 2));
        $draft = (new MillworkQuotationCalculator)->validationMessages($inputs, true);
        $this->assertArrayNotHasKey('calculation', $draft);
    }

    public function test_v2_leading_dot_thickness_stays_valid(): void
    {
        $parsed = MillworkDimensionParser::inspect('.75', 'in');
        $this->assertSame(MillworkDimensionParser::STATUS_OK, $parsed['status']);
        $this->assertEqualsWithDelta(0.75, $parsed['inches'], 0.0001);

        $out = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => false,
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'thickness' => '.75', 'unit' => 'in']],
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $this->assertFalse((bool) ($out['cut_plan']['preview_skipped'] ?? false));
        $this->assertGreaterThan(0, (float) $out['summary']['grand_total']);
    }

    public function test_v2_feet_bench_blank_height_defaults_to_eighteen_inches(): void
    {
        $exploded = (new MillworkPartBuilder)->explode([
            'type' => 'bench',
            'qty' => 1,
            'width' => 4,
            'depth' => 1.5,
            'unit' => 'ft',
            'edge_banding' => false,
        ], 0.75, 0.0);
        $this->assertNotEmpty($exploded['parts']);
        $this->assertStringContainsString('18"', implode(' ', $exploded['labels']));
        $this->assertStringNotContainsString('216"', implode(' ', $exploded['labels']));
    }

    public function test_draft_validation_treats_stored_zero_as_blank(): void
    {
        $draft = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 0,
                'height' => '0',
                'depth' => 24,
                'length' => '0.0',
                'thickness' => 0,
                'unit' => 'in',
            ]],
        ], true);
        $this->assertArrayNotHasKey('pieces.0.width', $draft);
        $this->assertArrayNotHasKey('pieces.0.height', $draft);
        $this->assertArrayNotHasKey('pieces.0.length', $draft);
        $this->assertArrayNotHasKey('pieces.0.thickness', $draft);
    }

    public function test_validation_flags_thousands_comma_as_inline_error(): void
    {
        $messages = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => '1,500',
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
            ]],
        ], true);
        $this->assertArrayHasKey('pieces.0.width', $messages);
        $this->assertStringContainsString('valid size', $messages['pieces.0.width']);
    }

    public function test_draft_validation_rejects_scientific_and_oversized_dims(): void
    {
        $sci = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => '1E400',
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
            ]],
        ], true);
        $this->assertArrayHasKey('pieces.0.width', $sci);

        $overDraft = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 300,
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
            ]],
        ], true);
        $this->assertArrayNotHasKey('calculation', $overDraft);

        $sciOk = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 36, 'depth' => '1e2', 'unit' => 'in']],
            ]],
            '_cut_plan_version' => 1,
        ], true);
        $this->assertArrayNotHasKey('pieces.0.sets.0.depth', $sciOk);
    }

    public function test_publish_validation_rejects_any_unsized_piece(): void
    {
        $hints = (new MillworkQuotationCalculator)->validationMessages([
            'pieces' => [
                [
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
                ],
                [
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 'abc', 'depth' => 12, 'unit' => 'in']],
                ],
            ],
        ], false);
        $this->assertArrayHasKey('pieces.1.sets.0.length', $hints);
        $this->assertArrayHasKey('calculation', $hints);
    }

    public function test_new_builder_honors_hinge_qty_override(): void
    {
        $auto = (new MillworkPartBuilder)->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34.5,
            'depth' => 24,
            'include_doors' => true,
            'door_count' => 2,
            'edge_banding' => false,
            'shelf_count' => 0,
        ], 0.75, 0.018);
        $over = (new MillworkPartBuilder)->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34.5,
            'depth' => 24,
            'include_doors' => true,
            'door_count' => 2,
            'hinge_qty' => 8,
            'edge_banding' => false,
            'shelf_count' => 0,
        ], 0.75, 0.018);

        $this->assertGreaterThan(0, $auto['hardware']['hinges']);
        $this->assertSame(8.0, (float) $over['hardware']['hinges']);
    }

    public function test_new_path_validation_rejects_oversized_dimension(): void
    {
        $hints = (new MillworkQuotationCalculator)->validationHints([
            'pieces' => [[
                'type' => 'cabinet',
                'qty' => 1,
                'width' => 300,
                'height' => 34.5,
                'depth' => 24,
                'unit' => 'in',
            ]],
        ], false);

        $this->assertNotEmpty($hints);
        $this->assertStringContainsString('240 inches', implode(' ', $hints));
    }

    public function test_v2_scope_omits_delivery_when_client_provides_all(): void
    {
        $quote = new MillworkQuotation([
            'cut_plan_version' => 2,
            'inputs' => [
                'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
                'client_provides_all' => true,
                'delivery_amount' => 80,
                'include_install' => true,
            ],
            'calculation' => ['summary' => ['delivery_amount' => 80]],
        ]);
        $text = $quote->scopeDescriptionForPdf();
        $this->assertStringNotContainsString('delivery', strtolower($text));
        $this->assertStringContainsString('Closet system', $text);
    }

    public function test_v1_scope_keeps_stored_delivery_wording(): void
    {
        $quote = new MillworkQuotation([
            'cut_plan_version' => 1,
            'inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'unit' => 'in',
                    'width' => 36,
                    'height' => 34.5,
                    'depth' => 24,
                    'style' => 'base',
                    'finish' => 'none',
                ]],
                'delivery_amount' => 80,
                'include_install' => true,
            ],
            'calculation' => ['summary' => ['delivery_amount' => 80]],
        ]);
        $text = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('delivery', $text);
        $this->assertStringContainsString('36"', $text);
    }

    public function test_client_provides_all_drops_delivery_from_new_path_total(): void
    {
        $withDelivery = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            'include_install' => true,
            'delivery_amount' => 80,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);
        $clientAll = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [MillworkQuotationCalculator::defaultPiece('closet')],
            'include_install' => true,
            'delivery_amount' => 80,
            'client_provides_all' => true,
            'owner_hourly_rate' => 85,
        ], $this->settings(), 2);

        $deliveryKeys = array_values(array_filter(
            $clientAll['materials'] ?? [],
            static fn ($row) => ($row['key'] ?? '') === 'delivery'
        ));
        $this->assertSame([], $deliveryKeys);
        $this->assertGreaterThan(
            (float) ($clientAll['summary']['grand_total'] ?? 0),
            (float) ($withDelivery['summary']['grand_total'] ?? 0)
        );
        $this->assertGreaterThan(
            80.0,
            (float) ($withDelivery['summary']['materials_pre_hst'] ?? 0) - (float) ($clientAll['summary']['materials_pre_hst'] ?? 0)
        );
    }

    /**
     * @return array<string, array{inputs: array<string, mixed>, settings?: MillworkSettings}>
     */
    private function legacyEditedCases(): array
    {
        $closet = MillworkQuotationCalculator::defaultPiece('closet');
        $closetPlus = $closet;
        $closetPlus['width'] = (float) $closet['width'] + 1;

        $vanity = MillworkQuotationCalculator::defaultPiece('vanity');
        unset($vanity['depth']);

        $paintedCloset = $closet;
        $paintedCloset['finish'] = 'paint';
        $stainedShelf = [
            'type' => 'shelf',
            'edge_banding' => true,
            'finish' => 'stain',
            'sets' => [['qty' => 2, 'length' => 30, 'depth' => 12, 'unit' => 'in']],
        ];

        $customSettings = $this->settings();
        $catalog = MillworkSettings::defaultCatalog();
        $catalog[MillworkSettings::SLOT_WOOD_SHEETS][] = [
            'id' => 'ws_custom_maple_48x96',
            'catalog_material_id' => null,
            'name' => 'Custom hard maple 4×8',
            'unit' => 'sheet',
            'unit_price' => 187.65,
            'thickness_in' => 0.75,
            'width_in' => 48,
            'length_in' => 96,
            'material_kind' => 'plywood',
            'grade' => 'cabinet',
        ];
        $customSettings->catalog = $catalog;

        return [
            'closet_labor_override' => ['inputs' => [
                'pieces' => [$closet],
                'include_install' => true,
                'include_helper' => false,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
                'labor_override' => ['cut' => ['hours' => 5]],
            ]],
            'closet_helper' => ['inputs' => [
                'pieces' => [$closet],
                'include_install' => true,
                'include_helper' => true,
                'owner_hourly_rate' => 85,
                'helper_hourly_rate' => 45,
            ]],
            'closet_plus_inch' => ['inputs' => [
                'pieces' => [$closetPlus],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'tall_edited' => ['inputs' => [
                'pieces' => [[
                    'type' => 'cabinet',
                    'qty' => 1,
                    'width' => 25,
                    'height' => 84,
                    'depth' => 24,
                    'unit' => 'in',
                    'include_doors' => true,
                    'door_count' => 2,
                    'include_toe_kick' => true,
                    'include_back' => false,
                    'shelf_count' => 1,
                    'edge_banding' => true,
                ]],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'doors' => ['inputs' => [
                'pieces' => [[
                    'type' => 'cabinet_door',
                    'edge_banding' => true,
                    'sets' => [['qty' => 2, 'width' => 18, 'height' => 80, 'unit' => 'in']],
                ]],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'closet_extra_materials' => ['inputs' => [
                'pieces' => [$closet],
                'include_install' => true,
                'include_building' => true,
                'building_labor_percent' => 10,
                'spare_sheets' => 1,
                'waste_pct' => 15,
                'owner_hourly_rate' => 85,
                'extra_materials' => [[
                    'name' => 'Cleats',
                    'quantity' => 1,
                    'unit' => 'each',
                    'unit_price' => 25,
                ]],
            ]],
            'vanity_default_depth' => ['inputs' => [
                'pieces' => [$vanity],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'kallax' => ['inputs' => [
                'pieces' => [[
                    'type' => 'kallax',
                    'qty' => 1,
                    'width' => 30,
                    'height' => 30,
                    'depth' => 14,
                    'unit' => 'in',
                    'columns' => 2,
                    'rows' => 2,
                    'include_back' => true,
                    'edge_banding' => true,
                ]],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'floating_shelf' => ['inputs' => [
                'pieces' => [[
                    'type' => 'floating_shelf',
                    'edge_banding' => true,
                    'sets' => [['qty' => 2, 'length' => 36, 'depth' => 10, 'thickness' => 3, 'unit' => 'in']],
                ]],
                'include_install' => true,
                'owner_hourly_rate' => 85,
            ]],
            'markup' => ['inputs' => [
                'pieces' => [$closet],
                'include_install' => true,
                'markup_pct' => 12.5,
                'owner_hourly_rate' => 85,
            ]],
            'delivery' => ['inputs' => [
                'pieces' => [$closet],
                'include_install' => true,
                'delivery_amount' => 87.555,
                'owner_hourly_rate' => 85,
            ]],
            'finishes' => ['inputs' => [
                'pieces' => [$paintedCloset, $stainedShelf],
                'include_install' => true,
                'paint_id' => 'pt_quart',
                'stain_id' => 'st_237',
                'owner_hourly_rate' => 85,
            ]],
            'custom_catalogue' => [
                'inputs' => [
                    'pieces' => [$closet],
                    'sheet_id' => 'ws_custom_maple_48x96',
                    'include_install' => true,
                    'owner_hourly_rate' => 85,
                ],
                'settings' => $customSettings,
            ],
        ];
    }

    private function laborAmount(array $calc, string $key): float
    {
        foreach ($calc['labor'] ?? [] as $row) {
            if (($row['key'] ?? '') === $key) {
                return (float) ($row['amount'] ?? 0);
            }
        }

        return 0.0;
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

    /**
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>
     */
    private function legacyOutputSnapshot(array $calc): array
    {
        $cut = $calc['cut_plan'] ?? [];
        unset($cut['version'], $cut['unplaced'], $cut['placed_by_id'], $cut['builder_version']);
        $layouts = [];
        foreach ($cut['layouts'] ?? [] as $layout) {
            $layouts[] = [
                'svg' => $layout['svg'] ?? '',
                'legend' => $layout['legend'] ?? [],
                'stats' => $layout['stats'] ?? [],
                'group' => $layout['group'] ?? null,
            ];
        }

        return [
            'scope' => $calc['scope'] ?? [],
            'sheet' => $calc['sheet'] ?? null,
            'back_sheet' => $calc['back_sheet'] ?? null,
            'labor' => $calc['labor'] ?? [],
            'materials' => $calc['materials'] ?? [],
            'extra_materials' => $calc['extra_materials'] ?? [],
            'summary' => $calc['summary'] ?? [],
            'cut_plan' => [
                'sheet_count' => $cut['sheet_count'] ?? 0,
                'yield_pct' => $cut['yield_pct'] ?? 0,
                'stock_used' => $cut['stock_used'] ?? [],
                'layouts' => $layouts,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function encodeLegacySnapshot(array $snapshot): string
    {
        return json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function v1StoredTrimFeetInputs(): array
    {
        return [
            'pieces' => [[
                'type' => 'trim',
                'profile' => 'casing',
                'unit' => 'ft',
                'length' => 20,
                'openings' => 3,
                'width' => 36,
                'height' => 80,
                'qty' => 1,
                'edge_banding' => false,
            ]],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
    }

    /**
     * Old quotes store plain numbers in the piece unit. Trim openings stay inches.
     *
     * @return array<string, mixed>
     */
    private function fuzzedLegacyInputs(int $seed): array
    {
        $types = ['cabinet', 'shelf', 'trim', 'closet', 'vanity', 'mantel', 'bench'];
        $type = $types[$seed % count($types)];
        $unit = ($seed % 2 === 0) ? 'in' : 'ft';
        $piece = [
            'type' => $type,
            'qty' => 1,
            'unit' => $unit,
            'edge_banding' => false,
            'include_doors' => false,
            'include_back' => false,
            'shelf_count' => 0,
        ];
        if ($type === 'shelf') {
            $piece['sets'] = [[
                'qty' => 1 + ($seed % 3),
                'length' => 24 + ($seed % 18),
                'depth' => 10 + ($seed % 6),
                'unit' => $unit,
            ]];
        } elseif ($type === 'trim') {
            $piece['unit'] = 'ft';
            $piece['profile'] = 'casing';
            $piece['length'] = 10 + ($seed % 24);
            $piece['openings'] = $seed % 4;
            if ($piece['openings'] > 0) {
                $piece['width'] = 30 + ($seed % 12);
                $piece['height'] = 72 + ($seed % 12);
            }
        } elseif ($type === 'mantel') {
            $piece['length'] = $unit === 'ft' ? (4 + ($seed % 3)) : (48 + ($seed % 24));
            $piece['depth'] = $unit === 'ft' ? 1 : (8 + ($seed % 4));
            $piece['height'] = $unit === 'ft' ? 1 : (6 + ($seed % 4));
        } else {
            $piece['width'] = $unit === 'ft' ? (2 + ($seed % 4)) : (24 + ($seed % 20));
            $piece['height'] = $unit === 'ft' ? (2 + ($seed % 3)) : (30 + ($seed % 18));
            $piece['depth'] = $unit === 'ft' ? (1 + ($seed % 2)) : (12 + ($seed % 10));
        }
        if ($seed % 5 === 0) {
            $piece['thickness'] = 0;
            if (! in_array($type, ['mantel', 'bench', 'trim', 'shelf'], true)) {
                $piece['length'] = 0;
            }
        }

        return [
            'pieces' => [$piece],
            'include_install' => false,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
        ];
    }

    /**
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    private function legacySizeSnapshot(array $piece): array
    {
        $keys = [
            'unit', 'width', 'height', 'depth', 'length', 'thickness',
            'linear_ft', 'stock_length_ft', 'width_unit', 'height_unit',
            'depth_unit', 'length_unit', 'openings',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $piece)) {
                $out[$key] = $piece[$key];
            }
        }
        if (isset($piece['sets']) && is_array($piece['sets'])) {
            $out['sets'] = [];
            foreach ($piece['sets'] as $set) {
                $out['sets'][] = is_array($set) ? $this->legacySizeSnapshot($set) : $set;
            }
        }

        return $out;
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
