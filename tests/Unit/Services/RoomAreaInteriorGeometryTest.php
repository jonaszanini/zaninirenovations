<?php

namespace Tests\Unit\Services;

use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\RoomAreaInteriorGeometry;
use Tests\TestCase;

class RoomAreaInteriorGeometryTest extends TestCase
{
    private RoomAreaInteriorGeometry $geom;

    private OpenPlan3DMeasurementBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geom = app(RoomAreaInteriorGeometry::class);
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
    }

    public function test_uniform_rectangle_interior_area_twelve_by_fourteen_feet(): void
    {
        $t = 11.43;
        $w = 12.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT + $t;
        $h = 14.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT + $t;
        $walls = [
            ['id' => 'n', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $w, 'y' => 0], 'thickness' => $t],
            ['id' => 'e', 'start' => ['x' => $w, 'y' => 0], 'end' => ['x' => $w, 'y' => $h], 'thickness' => $t],
            ['id' => 's', 'start' => ['x' => $w, 'y' => $h], 'end' => ['x' => 0, 'y' => $h], 'thickness' => $t],
            ['id' => 'w', 'start' => ['x' => 0, 'y' => $h], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
        ];
        $poly = [
            ['x' => 0, 'y' => 0],
            ['x' => $w, 'y' => 0],
            ['x' => $w, 'y' => $h],
            ['x' => 0, 'y' => $h],
        ];
        $wallsById = ['n' => $walls[0], 'e' => $walls[1], 's' => $walls[2], 'w' => $walls[3]];
        $area = $this->geom->interiorFloorAreaSqFt($poly, ['n', 'e', 's', 'w'], $wallsById, $walls);
        $this->assertEqualsWithDelta(168.0, $area, 0.01);
    }

    public function test_mixed_thickness_rectangle_uses_per_edge_inset(): void
    {
        $thin = 7.62;
        $thick = 19.05;
        $w = 10.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT + ($thin + $thick) / 2;
        $h = 8.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT + ($thin + $thick) / 2;
        $walls = [
            ['id' => 'n', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $w, 'y' => 0], 'thickness' => $thin],
            ['id' => 'e', 'start' => ['x' => $w, 'y' => 0], 'end' => ['x' => $w, 'y' => $h], 'thickness' => $thick],
            ['id' => 's', 'start' => ['x' => $w, 'y' => $h], 'end' => ['x' => 0, 'y' => $h], 'thickness' => $thin],
            ['id' => 'w', 'start' => ['x' => 0, 'y' => $h], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $thick],
        ];
        $poly = [
            ['x' => 0, 'y' => 0],
            ['x' => $w, 'y' => 0],
            ['x' => $w, 'y' => $h],
            ['x' => 0, 'y' => $h],
        ];
        $wallsById = ['n' => $walls[0], 'e' => $walls[1], 's' => $walls[2], 'w' => $walls[3]];
        $area = $this->geom->interiorFloorAreaSqFt($poly, ['n', 'e', 's', 'w'], $wallsById, $walls);
        // Centerline 10×8 ft shell; 3″ north/south and 7.5″ east/west → clear interior 9.8125×8.1875 = 80.34 sf.
        $this->assertEqualsWithDelta(80.34, $area, 0.01);
    }

    public function test_wall_plan_edge_length_uses_centerline_without_corner_trim(): void
    {
        $t = 11.43;
        $len = 12.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $walls = [
            ['id' => 'a', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $len, 'y' => 0], 'thickness' => $t],
            ['id' => 'b', 'start' => ['x' => $len, 'y' => 0], 'end' => ['x' => $len, 'y' => $len], 'thickness' => $t],
            ['id' => 'c', 'start' => ['x' => $len, 'y' => $len], 'end' => ['x' => 0, 'y' => $len], 'thickness' => $t],
            ['id' => 'd', 'start' => ['x' => 0, 'y' => $len], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
        ];
        $edgeCm = $this->geom->wallPlanEdgeLengthCm($walls[0], $walls);
        $edgeFt = $edgeCm / OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $this->assertEqualsWithDelta(12.0, $edgeFt, 0.01);
    }

    public function test_l_shaped_room_one_hand_computed_252_53_sf(): void
    {
        $ft = static fn (float $feet, float $inches = 0.0): float => $feet + $inches / 12.0;
        $mainW = $ft(14, 3);
        $mainH = $ft(12, 1);
        $legW = $ft(10, 10);
        $legH = $ft(7, 5);
        $outerH = $mainH + $legH;
        $interiorRingFt = [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $mainW, 'y' => 0.0],
            ['x' => $mainW, 'y' => $mainH],
            ['x' => $legW, 'y' => $mainH],
            ['x' => $legW, 'y' => $outerH],
            ['x' => 0.0, 'y' => $outerH],
        ];
        $cmPerFt = OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $ringCm = array_map(
            static fn (array $pt): array => ['x' => $pt['x'] * $cmPerFt, 'y' => $pt['y'] * $cmPerFt],
            $interiorRingFt
        );
        $perimeter = $this->geom->planRingPerimeterLf($ringCm, 1.0);
        $verticesFt = array_map(
            static fn (array $pt): array => ['x' => $pt['x'], 'y' => $pt['y']],
            $interiorRingFt
        );
        $arithmetic = round($mainW * $mainH + $legW * $legH, 2);
        $rawArea = 0.0;
        $n = count($interiorRingFt);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $rawArea += $interiorRingFt[$i]['x'] * $interiorRingFt[$j]['y'];
            $rawArea -= $interiorRingFt[$j]['x'] * $interiorRingFt[$i]['y'];
        }
        $polygonArea = round(abs($rawArea) * 0.5, 2);
        // 14′3″×12′1″ (172.19 sf) + 10′10″×7′5″ leg (80.34 sf) = 252.53 sf.
        $this->assertEqualsWithDelta(252.53, $arithmetic, 0.01);
        $this->assertEqualsWithDelta(252.53, $polygonArea, 0.01);
        $edgeSum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $edgeSum += hypot($interiorRingFt[$j]['x'] - $interiorRingFt[$i]['x'], $interiorRingFt[$j]['y'] - $interiorRingFt[$i]['y']);
        }
        $this->assertEqualsWithDelta(round($edgeSum, 2), $perimeter, 0.01);
    }

    public function test_two_rooms_total_excludes_shared_partition_footprint(): void
    {
        $t = 11.43;
        $span = 10.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $walls = [
            ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $span, 'y' => 0], 'thickness' => $t],
            ['id' => 'w2', 'start' => ['x' => $span, 'y' => 0], 'end' => ['x' => $span, 'y' => $span], 'thickness' => $t],
            ['id' => 'w3', 'start' => ['x' => $span, 'y' => $span], 'end' => ['x' => 0, 'y' => $span], 'thickness' => $t],
            ['id' => 'w4', 'start' => ['x' => 0, 'y' => $span], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
            ['id' => 'w5', 'start' => ['x' => $span, 'y' => 0], 'end' => ['x' => 2 * $span, 'y' => 0], 'thickness' => $t],
            ['id' => 'w6', 'start' => ['x' => 2 * $span, 'y' => 0], 'end' => ['x' => 2 * $span, 'y' => $span], 'thickness' => $t],
            ['id' => 'w7', 'start' => ['x' => 2 * $span, 'y' => $span], 'end' => ['x' => $span, 'y' => $span], 'thickness' => $t],
        ];
        $plan = [
            'unit' => 'cm',
            'floor' => [
                'walls' => $walls,
                'rooms' => [
                    [
                        'id' => 'r1',
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $span, 'y' => 0],
                            ['x' => $span, 'y' => $span],
                            ['x' => 0, 'y' => $span],
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'polygon' => [
                            ['x' => $span, 'y' => 0],
                            ['x' => 2 * $span, 'y' => 0],
                            ['x' => 2 * $span, 'y' => $span],
                            ['x' => $span, 'y' => $span],
                        ],
                    ],
                ],
            ],
        ];
        $left = $this->bridge->layoutMetrics($plan, 'r1');
        $right = $this->bridge->layoutMetrics($plan, 'r2');
        $total = $this->bridge->layoutMetrics($plan);
        $this->assertEqualsWithDelta(92.64, $left['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(92.64, $right['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(185.28, $total['area_sqft'], 0.01);
    }

    public function test_exterior_area_exceeds_interior_for_uniform_walls(): void
    {
        $t = 11.43;
        $w = 10.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $poly = [['x' => 0, 'y' => 0], ['x' => $w, 'y' => 0], ['x' => $w, 'y' => $w], ['x' => 0, 'y' => $w]];
        $wallsById = [
            'a' => ['id' => 'a', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $w, 'y' => 0], 'thickness' => $t],
            'b' => ['id' => 'b', 'start' => ['x' => $w, 'y' => 0], 'end' => ['x' => $w, 'y' => $w], 'thickness' => $t],
            'c' => ['id' => 'c', 'start' => ['x' => $w, 'y' => $w], 'end' => ['x' => 0, 'y' => $w], 'thickness' => $t],
            'd' => ['id' => 'd', 'start' => ['x' => 0, 'y' => $w], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
        ];
        $inner = $this->geom->interiorPolygonCm($poly, ['a', 'b', 'c', 'd'], $wallsById);
        $inside = $this->geom->interiorFloorAreaSqFt($poly, ['a', 'b', 'c', 'd'], $wallsById, array_values($wallsById));
        $outer = $this->geom->exteriorFloorAreaSqFt($inner, ['a', 'b', 'c', 'd'], $wallsById, 1.0, true);
        $this->assertGreaterThan($inside, $outer);
        $this->assertEqualsWithDelta(92.64, $inside, 0.01);
        $this->assertEqualsWithDelta(115.56, $outer, 0.01);
    }
}
