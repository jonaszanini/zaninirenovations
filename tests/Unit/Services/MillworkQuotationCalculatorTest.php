<?php

namespace Tests\Unit\Services;

use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkPartBuilder;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

class MillworkQuotationCalculatorTest extends TestCase
{
    public function test_empty_pieces_have_zero_totals(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [MillworkQuotationCalculator::defaultPiece('shelf')],
        ], $this->settings());

        $this->assertSame(0.0, $calc['summary']['labor_pre_hst']);
        $this->assertSame(0.0, $calc['summary']['materials_pre_hst']);
        $this->assertSame(0, $calc['cut_plan']['sheet_count']);
    }

    public function test_simple_shelf_sets_produce_parts_and_sheets(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'finish' => 'none',
                'fastener' => 'screws',
                'sets' => [
                    ['qty' => 2, 'length' => 8, 'depth' => 1, 'unit' => 'ft'],
                    ['qty' => 3, 'length' => 4, 'depth' => 1, 'unit' => 'ft'],
                ],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => false,
        ], $this->settings());

        $this->assertGreaterThan(0, $calc['cut_plan']['sheet_count']);
        $this->assertGreaterThan(0, $calc['summary']['labor_pre_hst']);
        $this->assertGreaterThan(0, $calc['summary']['materials_pre_hst']);
        $this->assertNotEmpty($calc['cut_plan']['layouts'][0]['svg'] ?? '');
        $this->assertStringContainsString('svg', $calc['cut_plan']['layouts'][0]['svg']);
        $this->assertNotEmpty($calc['cut_plan']['layouts'][0]['legend'] ?? []);
    }

    public function test_floating_shelf_counts_box_parts(): void
    {
        $builder = new MillworkPartBuilder();
        $out = $builder->explode([
            'type' => 'floating_shelf',
            'edge_banding' => true,
            'sets' => [['qty' => 1, 'length' => 36, 'depth' => 10, 'thickness' => 3, 'unit' => 'in']],
        ], 0.75, 0.018);

        $this->assertSame(1, $out['unit_count']);
        $qty = 0;
        $sizes = [];
        foreach ($out['parts'] as $part) {
            $qty += $part->quantity;
            $sizes[$this->sizeKey($part->width, $part->length)] = ($sizes[$this->sizeKey($part->width, $part->length)] ?? 0) + $part->quantity;
        }
        $this->assertSame(5, $qty);
        $this->assertSame(2, $sizes[$this->sizeKey(10, 36)] ?? 0);
        $this->assertSame(1, $sizes[$this->sizeKey(3, 36)] ?? 0);
        $this->assertSame(2, $sizes[$this->sizeKey(3, 10)] ?? 0);
        $this->assertArrayNotHasKey($this->sizeKey(1.5, 10), $sizes);
    }

    public function test_floating_shelf_cut_plan_uses_four_by_eight_for_full_length_box(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'floating_shelf',
                'edge_banding' => true,
                'include_back' => false,
                'finish' => 'none',
                'fastener' => 'screws',
                'sets' => [[
                    'qty' => 3,
                    'length' => 96,
                    'width' => '',
                    'depth' => 12,
                    'thickness' => 3,
                    'unit' => 'in',
                ]],
            ]],
            'include_install' => false,
        ], $this->settings());

        $out = (new MillworkPartBuilder)->explode([
            'type' => 'floating_shelf',
            'edge_banding' => true,
            'sets' => [['qty' => 3, 'length' => 96, 'depth' => 12, 'thickness' => 3, 'unit' => 'in']],
        ], 0.75, 0.018);

        $sizes = [];
        $qty = 0;
        foreach ($out['parts'] as $part) {
            $qty += $part->quantity;
            $sizes[$this->sizeKey($part->width, $part->length)] = ($sizes[$this->sizeKey($part->width, $part->length)] ?? 0) + $part->quantity;
        }
        $this->assertSame(15, $qty);
        $this->assertSame(6, $sizes[$this->sizeKey(12, 96)] ?? 0);
        $this->assertSame(6, $sizes[$this->sizeKey(3, 12)] ?? 0);
        $this->assertSame(3, $sizes[$this->sizeKey(3, 96)] ?? 0);

        $placed = 0;
        $usesFourByEight = false;
        foreach ($calc['cut_plan']['layouts'] as $layout) {
            $placed += (int) ($layout['stats']['part_count'] ?? 0);
            $w = (float) ($layout['stats']['width'] ?? 0);
            $l = (float) ($layout['stats']['length'] ?? 0);
            if (abs($w - 48) < 0.2 && abs($l - 96) < 0.2) {
                $usesFourByEight = true;
            }
        }
        $this->assertSame(15, $placed);
        $this->assertTrue($usesFourByEight);
        $this->assertGreaterThan(1, $calc['cut_plan']['sheet_count']);
        $this->assertGreaterThan(20, $calc['cut_plan']['yield_pct']);
    }

    public function test_kallax_cube_count(): void
    {
        $builder = new MillworkPartBuilder();
        $out = $builder->explode([
            'type' => 'kallax',
            'qty' => 1,
            'width' => 30,
            'height' => 30,
            'depth' => 13,
            'unit' => 'in',
            'columns' => 2,
            'rows' => 2,
            'include_back' => true,
        ], 0.75, 0.018);

        $this->assertSame(4, $out['cube_count']);
        $this->assertNotEmpty($out['parts']);
    }

    public function test_cabinet_doors_add_hinges(): void
    {
        $builder = new MillworkPartBuilder();
        $out = $builder->explode([
            'type' => 'cabinet',
            'qty' => 1,
            'width' => 36,
            'height' => 34,
            'depth' => 24,
            'unit' => 'in',
            'include_doors' => true,
            'door_count' => 2,
            'include_back' => true,
            'include_toe_kick' => true,
            'shelf_count' => 1,
        ], 0.75, 0.018);

        $this->assertSame(2, $out['door_count']);
        $this->assertEquals(4, $out['hardware']['hinges']);
    }

    public function test_bench_without_drawers_ignores_pull_qty(): void
    {
        $builder = new MillworkPartBuilder();
        $out = $builder->explode([
            'type' => 'bench',
            'qty' => 1,
            'length' => 48,
            'depth' => 16,
            'height' => 18,
            'unit' => 'in',
            'below' => 'divisions',
            'division_count' => 2,
            'pull_qty' => 6,
        ], 0.75, 0.018);

        $this->assertSame(0.0, (float) $out['hardware']['pulls']);
    }

    public function test_bench_with_drawers_uses_pull_qty(): void
    {
        $builder = new MillworkPartBuilder();
        $out = $builder->explode([
            'type' => 'bench',
            'qty' => 1,
            'length' => 48,
            'depth' => 16,
            'height' => 18,
            'unit' => 'in',
            'below' => 'drawers',
            'drawer_count' => 2,
            'pull_qty' => 2,
        ], 0.75, 0.018);

        $this->assertSame(2.0, (float) $out['hardware']['pulls']);
    }

    public function test_paint_packaging_uses_quarts_or_half_gallons(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'finish' => 'paint',
                'edge_banding' => false,
                'sets' => [['qty' => 4, 'length' => 48, 'depth' => 12, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ], $this->settings());

        $paintRows = array_filter($calc['materials'], function ($row) {
            return str_contains((string) ($row['label'] ?? ''), 'paint')
                || str_contains(strtolower((string) ($row['key'] ?? '')), 'paint');
        });
        $this->assertNotEmpty($paintRows);
        $this->assertGreaterThan(0, $calc['summary']['paint_sf']);
    }

    public function test_screws_shopping_list_uses_pack_size(): void
    {
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'fastener' => 'screws',
                'sets' => [['qty' => 2, 'length' => 36, 'depth' => 10, 'unit' => 'in']],
            ]],
            'include_install' => false,
        ], $this->settings());

        $fast = array_values(array_filter($calc['materials'], function ($row) {
            return ($row['key'] ?? '') === 'fastener';
        }));
        $this->assertNotEmpty($fast);
        $this->assertGreaterThan(0, $fast[0]['qty']);
    }

    public function test_floating_shelf_with_empty_hidden_width_is_valid(): void
    {
        $inputs = [
            'pieces' => [[
                'type' => 'floating_shelf',
                'sets' => [[
                    'qty' => 3,
                    'length' => 96,
                    'width' => '',
                    'depth' => 12,
                    'height' => '',
                    'thickness' => 3,
                    'unit' => 'in',
                ]],
            ]],
        ];

        $hints = (new MillworkQuotationCalculator)->validationHints($inputs, false);
        $this->assertSame([], $hints);

        $out = (new MillworkPartBuilder)->explode($inputs['pieces'][0], 0.75, 0.018);
        $this->assertSame(3, $out['unit_count']);
        $this->assertNotEmpty($out['parts']);
    }

    public function test_include_helper_toggles_helper_labor_cost(): void
    {
        $base = [
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'finish' => 'none',
                'fastener' => 'screws',
                'sets' => [
                    ['qty' => 2, 'length' => 4, 'depth' => 1, 'unit' => 'ft'],
                ],
            ]],
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'include_install' => false,
        ];

        $withoutHelper = (new MillworkQuotationCalculator)->calculate(array_merge($base, [
            'include_helper' => false,
        ]), $this->settings());

        $withHelper = (new MillworkQuotationCalculator)->calculate(array_merge($base, [
            'include_helper' => true,
        ]), $this->settings());

        $hours = (float) $withoutHelper['summary']['total_hours'];
        $this->assertGreaterThan(0, $hours);
        $this->assertEqualsWithDelta($hours, (float) $withHelper['summary']['total_hours'], 0.01);

        $expectedWithout = round($hours * 80, 2);
        $expectedWith = round($hours * (80 + 40), 2);

        $this->assertEqualsWithDelta($expectedWithout, (float) $withoutHelper['summary']['labor_pre_hst'], 0.01);
        $this->assertEqualsWithDelta($expectedWith, (float) $withHelper['summary']['labor_pre_hst'], 0.01);
        $this->assertFalse($withoutHelper['summary']['include_helper']);
        $this->assertTrue($withHelper['summary']['include_helper']);
    }

    public function test_publish_validation_still_requires_a_sized_piece(): void
    {
        $hints = (new MillworkQuotationCalculator)->validationHints([
            'pieces' => [[
                'type' => 'floating_shelf',
                'sets' => [['qty' => 3, 'length' => '', 'width' => '', 'depth' => '', 'unit' => 'in']],
            ]],
        ], false);

        $this->assertNotEmpty($hints);
    }

    public function test_edge_banding_shopping_list_calculates_rolls_from_coverage_lf(): void
    {
        $settings = $this->settings();
        $cat = $settings->catalog;
        $cat[MillworkSettings::SLOT_EDGE_BANDING] = [[
            'id' => 'eb_test',
            'name' => '50ft Birch Veneer',
            'unit' => 'roll',
            'unit_price' => 16.98,
            'thickness_in' => 0.018,
            'coverage_lf' => 50.0,
        ]];
        $settings->catalog = $cat;

        // 2 shelves of 8 ft = 16 LF. With 8% waste = 17.28 LF. Fits in 1 roll (50 LF).
        $calc1 = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'sets' => [['qty' => 2, 'length' => 8, 'depth' => 1, 'unit' => 'ft']],
            ]],
            'edge_banding_id' => 'eb_test',
            'include_install' => false,
        ], $settings);

        $edgeRow1 = array_values(array_filter($calc1['materials'], fn ($r) => ($r['key'] ?? '') === 'edge'))[0] ?? null;
        $this->assertNotNull($edgeRow1);
        $this->assertSame('50ft Birch Veneer', $edgeRow1['label']);
        $this->assertSame(1.0, (float) $edgeRow1['qty']);
        $this->assertSame('roll', $edgeRow1['unit']);
        $this->assertEquals(16.98, (float) $edgeRow1['unit_price']);
        $this->assertEquals(16.98, (float) $edgeRow1['amount']);

        // 10 shelves of 8 ft = 80 LF. With 8% waste = 86.4 LF. Requires 2 rolls of 50 LF.
        $calc2 = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'shelf',
                'edge_banding' => true,
                'sets' => [['qty' => 10, 'length' => 8, 'depth' => 1, 'unit' => 'ft']],
            ]],
            'edge_banding_id' => 'eb_test',
            'include_install' => false,
        ], $settings);

        $edgeRow2 = array_values(array_filter($calc2['materials'], fn ($r) => ($r['key'] ?? '') === 'edge'))[0] ?? null;
        $this->assertNotNull($edgeRow2);
        $this->assertSame(2.0, (float) $edgeRow2['qty']);
        $this->assertSame('roll', $edgeRow2['unit']);
        $this->assertEquals(33.96, (float) $edgeRow2['amount']);
    }

    public function test_unselected_back_and_edge_banding_exclude_materials_and_have_null_objects(): void
    {
        $settings = $this->settings();
        $calc = (new MillworkQuotationCalculator)->calculate([
            'pieces' => [[
                'type' => 'kallax',
                'qty' => 1,
                'width' => 35,
                'height' => 15,
                'depth' => 15,
                'columns' => 3,
                'rows' => 1,
                'unit' => 'in',
                'finish' => 'none',
                'edge_banding' => false,
                'include_back' => false,
            ]],
            'include_install' => false,
        ], $settings);

        $this->assertNull($calc['back_sheet']);
        $this->assertNull($calc['edge_banding']);
        $this->assertSame('Unfinished', $calc['scope']['finish_label']);

        $materialKeys = array_map(fn ($r) => $r['key'] ?? '', $calc['materials']);
        $this->assertNotContains('edge', $materialKeys);
        $this->assertNotContains('paint', $materialKeys);
        $this->assertNotContains('stain', $materialKeys);
    }

    private function sizeKey(float $a, float $b): string
    {
        $lo = round(min($a, $b), 2);
        $hi = round(max($a, $b), 2);

        return $lo.'x'.$hi;
    }

    private function settings(): MillworkSettings
    {
        $s = new MillworkSettings();
        $s->owner_hourly_rate = 80;
        $s->helper_hourly_rate = 40;
        $s->hours_per_day = 8;
        $s->productivity = MillworkSettings::defaultProductivity();
        $s->catalog = MillworkSettings::defaultCatalog();
        $s->cut_settings = MillworkSettings::defaultCutSettings();

        return $s;
    }
}
