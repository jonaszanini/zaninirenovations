<?php

namespace Tests\Unit\Services;

use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaPhaseTotals;
use Tests\TestCase;

class RoomAreaPhaseTotalsTest extends TestCase
{
    public function test_sums_area_perimeter_net_wall_flooring_and_casing(): void
    {
        $kitchen = $this->room('Kitchen', 144, 120, [
            'id' => 'obj_1',
            'type' => 'door',
            'subtype' => 'single',
            'wall_id' => 'wall_p1_0_1',
            'width' => 36,
            'offset' => 12,
        ]);
        $bath = $this->room('Bath', 96, 96);

        $totals = (new RoomAreaPhaseTotals)->sum([$kitchen, $bath]);

        $this->assertEquals(184.0, $totals['area_sqft']);
        $this->assertEquals(76.0, $totals['perimeter_lf']);
        $this->assertEquals(588.0, $totals['net_wall_area_sqft']);
        $this->assertEquals(202.4, $totals['flooring_with_waste_sqft']);
        $this->assertEquals(32.67, $totals['casing_lf']);
        $this->assertSame(2, $totals['room_count']);
        $this->assertSame($kitchen->layoutMetrics(), $kitchen->layoutMetrics());
        $this->assertEquals(332.0, $kitchen->layoutMetrics()['net_wall_area_sqft']);
        $this->assertEquals(32.67, $kitchen->layoutMetrics()['casing_lf']);
        $this->assertEquals(256.0, $bath->layoutMetrics()['net_wall_area_sqft']);
        $this->assertEquals(0.0, $bath->layoutMetrics()['casing_lf']);
    }

    public function test_project_total_sums_rounded_phase_totals(): void
    {
        $kitchen = $this->room('Kitchen', 144, 120, [
            'id' => 'obj_1',
            'type' => 'door',
            'subtype' => 'single',
            'wall_id' => 'wall_p1_0_1',
            'width' => 36,
            'offset' => 12,
        ]);
        $kitchen->phase_id = 1;
        $bath = $this->room('Bath', 96, 96);
        $phase = new RoomAreaPhase(['name' => 'Rough-in', 'sort_order' => 1]);
        $phase->id = 1;
        $project = new RoomAreaProject(['name' => 'Maple kitchen']);
        $project->setRelation('phases', collect([$phase]));
        $project->setRelation('rooms', collect([$kitchen, $bath]));

        $grouped = (new RoomAreaPhaseTotals)->grouped($project);

        $this->assertEquals(332.0, $grouped['phases'][0]['totals']['net_wall_area_sqft']);
        $this->assertEquals(32.67, $grouped['phases'][0]['totals']['casing_lf']);
        $this->assertEquals(256.0, $grouped['unassigned']['totals']['net_wall_area_sqft']);
        $this->assertEquals(0.0, $grouped['unassigned']['totals']['casing_lf']);
        $this->assertEquals(184.0, $grouped['project']['area_sqft']);
        $this->assertEquals(76.0, $grouped['project']['perimeter_lf']);
        $this->assertEquals(588.0, $grouped['project']['net_wall_area_sqft']);
        $this->assertEquals(202.4, $grouped['project']['flooring_with_waste_sqft']);
        $this->assertEquals(32.67, $grouped['project']['casing_lf']);
        $this->assertSame(
            $grouped['phases'][0]['totals']['net_wall_area_sqft'] + $grouped['unassigned']['totals']['net_wall_area_sqft'],
            $grouped['project']['net_wall_area_sqft']
        );
    }

    public function test_allocated_room_rows_sum_exactly_to_phase_total(): void
    {
        $rooms = [
            ['metrics' => [
                'area_sqft' => 10.004,
                'perimeter_lf' => 1.005,
                'net_wall_area_sqft' => 3.333,
                'flooring_with_waste_sqft' => 0.0,
                'casing_lf' => 0.0,
            ]],
            ['metrics' => [
                'area_sqft' => 10.004,
                'perimeter_lf' => 1.005,
                'net_wall_area_sqft' => 3.333,
                'flooring_with_waste_sqft' => 0.0,
                'casing_lf' => 0.0,
            ]],
        ];
        $totals = new RoomAreaPhaseTotals;
        $phase = $totals->sum($rooms);
        $allocated = $totals->allocateRoomMetrics($rooms);

        $this->assertEquals(20.01, $phase['area_sqft']);
        $this->assertEquals(10.01, $allocated[0]['area_sqft']);
        $this->assertEquals(10.00, $allocated[1]['area_sqft']);
        $this->assertEquals(
            $phase['area_sqft'],
            round($allocated[0]['area_sqft'] + $allocated[1]['area_sqft'], 2)
        );
        $this->assertEquals(
            $phase['net_wall_area_sqft'],
            round($allocated[0]['net_wall_area_sqft'] + $allocated[1]['net_wall_area_sqft'], 2)
        );
        $this->assertEquals(
            $phase['perimeter_lf'],
            round($allocated[0]['perimeter_lf'] + $allocated[1]['perimeter_lf'], 2)
        );
    }

    public function test_largest_remainder_keeps_identical_rooms_equal_when_possible(): void
    {
        $rooms = [
            ['metrics' => [
                'area_sqft' => 10.001,
                'perimeter_lf' => 0,
                'net_wall_area_sqft' => 0,
                'flooring_with_waste_sqft' => 0,
                'casing_lf' => 0,
            ]],
            ['metrics' => [
                'area_sqft' => 10.001,
                'perimeter_lf' => 0,
                'net_wall_area_sqft' => 0,
                'flooring_with_waste_sqft' => 0,
                'casing_lf' => 0,
            ]],
        ];
        $totals = new RoomAreaPhaseTotals;
        $allocated = $totals->allocateRoomMetrics($rooms);

        $this->assertEquals(10.00, $allocated[0]['area_sqft']);
        $this->assertEquals(10.00, $allocated[1]['area_sqft']);
        $this->assertEquals(20.00, $totals->sum($rooms)['area_sqft']);
    }

    public function test_largest_remainder_gives_pennies_to_larger_fractions(): void
    {
        $rooms = [
            ['metrics' => [
                'area_sqft' => 10.009,
                'perimeter_lf' => 0,
                'net_wall_area_sqft' => 0,
                'flooring_with_waste_sqft' => 0,
                'casing_lf' => 0,
            ]],
            ['metrics' => [
                'area_sqft' => 10.001,
                'perimeter_lf' => 0,
                'net_wall_area_sqft' => 0,
                'flooring_with_waste_sqft' => 0,
                'casing_lf' => 0,
            ]],
        ];
        $totals = new RoomAreaPhaseTotals;
        $allocated = $totals->allocateRoomMetrics($rooms);

        $this->assertEquals(10.01, $allocated[0]['area_sqft']);
        $this->assertEquals(10.00, $allocated[1]['area_sqft']);
        $this->assertEquals(20.01, $totals->sum($rooms)['area_sqft']);
    }

    public function test_empty_sum_is_zero(): void
    {
        $this->assertSame([
            'area_sqft' => 0.0,
            'perimeter_lf' => 0.0,
            'net_wall_area_sqft' => 0.0,
            'flooring_with_waste_sqft' => 0.0,
            'casing_lf' => 0.0,
            'room_count' => 0,
        ], (new RoomAreaPhaseTotals)->empty());
    }

    private function room(string $name, float $widthPx, float $heightPx, ?array $door = null): RoomAreaRoom
    {
        return new RoomAreaRoom([
            'name' => $name,
            'scale_pixels_per_foot' => 12,
            'area_sqft' => ($widthPx / 12) * ($heightPx / 12),
            'wall_height_ft' => 8,
            'flooring_waste_percent' => 10,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $widthPx, 'y' => 0],
                            ['x' => $widthPx, 'y' => $heightPx],
                            ['x' => 0, 'y' => $heightPx],
                        ],
                    ]],
                ]],
                'objects' => $door ? [$door] : [],
            ],
        ]);
    }
}
