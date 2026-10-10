<?php

namespace Tests\Unit\Services;

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

        $this->assertEquals(120.0 + 64.0, $totals['area_sqft']);
        $this->assertEquals(44.0 + 32.0, $totals['perimeter_lf']);
        $this->assertEquals(
            $kitchen->layoutMetrics()['net_wall_area_sqft'] + $bath->layoutMetrics()['net_wall_area_sqft'],
            $totals['net_wall_area_sqft']
        );
        $this->assertEquals(132.0 + 70.4, $totals['flooring_with_waste_sqft']);
        $this->assertEquals(
            $kitchen->layoutMetrics()['casing_lf'] + $bath->layoutMetrics()['casing_lf'],
            $totals['casing_lf']
        );
        $this->assertSame(2, $totals['room_count']);
        $this->assertGreaterThan(0, $totals['casing_lf']);
        $this->assertGreaterThan(0, $totals['net_wall_area_sqft']);
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
