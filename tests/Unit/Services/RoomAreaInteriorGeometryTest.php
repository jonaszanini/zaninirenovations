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
        $this->assertEqualsWithDelta(168.0, $area, 0.1);
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
        // 10 ft × 8 ft clear interior with 3″ north/south and 7.5″ east/west walls (per-edge inset).
        $this->assertEqualsWithDelta(80.34, $area, 0.1);
    }

    public function test_wall_plan_edge_length_uses_clear_span(): void
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
        $this->assertEqualsWithDelta(11.62, $edgeFt, 0.1);
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
        // 10 ft centerline rooms, 4.5″ walls → ~9.625 ft interior face each; sum excludes partition strip once.
        $this->assertEqualsWithDelta(92.64, $left['area_sqft'], 0.15);
        $this->assertEqualsWithDelta(92.64, $right['area_sqft'], 0.15);
        $this->assertEqualsWithDelta(185.28, $total['area_sqft'], 0.15);
    }

    public function test_exterior_area_exceeds_interior_for_uniform_walls(): void
    {
        $t = 11.43;
        $w = 10.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT + $t;
        $poly = [['x' => 0, 'y' => 0], ['x' => $w, 'y' => 0], ['x' => $w, 'y' => $w], ['x' => 0, 'y' => $w]];
        $wallsById = [
            'a' => ['id' => 'a', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $w, 'y' => 0], 'thickness' => $t],
            'b' => ['id' => 'b', 'start' => ['x' => $w, 'y' => 0], 'end' => ['x' => $w, 'y' => $w], 'thickness' => $t],
            'c' => ['id' => 'c', 'start' => ['x' => $w, 'y' => $w], 'end' => ['x' => 0, 'y' => $w], 'thickness' => $t],
            'd' => ['id' => 'd', 'start' => ['x' => 0, 'y' => $w], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
        ];
        $inner = $this->geom->interiorPolygonCm($poly, ['a', 'b', 'c', 'd'], $wallsById);
        $outer = $this->geom->exteriorFloorAreaSqFt($inner, ['a', 'b', 'c', 'd'], $wallsById);
        $inside = $this->geom->interiorFloorAreaSqFt($poly, ['a', 'b', 'c', 'd'], $wallsById, array_values($wallsById));
        $this->assertGreaterThan($inside, $outer);
        $this->assertEqualsWithDelta(107.64, $outer, 0.15);
    }
}
