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
        foreach ($out['parts'] as $part) {
            $qty += $part->quantity;
        }
        $this->assertGreaterThanOrEqual(4, $qty);
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
