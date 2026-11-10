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
        // Centerline 10×8 ft shell; per-edge inset (3″ N/S, 7.5″ E/W) → interior ~9.8125×7.6875 ft ≈ 80.34 sf.
        $this->assertEqualsWithDelta(80.34, $area, 0.01);
    }

    public function test_wall_plan_edge_length_uses_interior_face_span_at_corners(): void
    {
        $t = 11.43;
        $len = 10.0 * OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $walls = [
            ['id' => 'a', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $len, 'y' => 0], 'thickness' => $t],
            ['id' => 'b', 'start' => ['x' => $len, 'y' => 0], 'end' => ['x' => $len, 'y' => $len], 'thickness' => $t],
            ['id' => 'c', 'start' => ['x' => $len, 'y' => $len], 'end' => ['x' => 0, 'y' => $len], 'thickness' => $t],
            ['id' => 'd', 'start' => ['x' => 0, 'y' => $len], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
        ];
        $edgeCm = $this->geom->wallPlanEdgeLengthCm($walls[0], $walls);
        $edgeFt = $edgeCm / OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        // 10 ft centerline − 4.5″ wall trim at both corners → 9.625 ft clear interior-face span.
        $this->assertEqualsWithDelta(9.63, $edgeFt, 0.01);
    }

    public function test_l_shaped_room_sixteen_by_twelve_with_six_by_four_return_matches_bridge(): void
    {
        $plan = [
            'format' => 'openplan3d',
            'units' => 'ft',
            'walls' => [
                ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 16, 'y' => 0], 'thickness' => 4.5, 'height' => 8],
                ['id' => 'w2', 'start' => ['x' => 16, 'y' => 0], 'end' => ['x' => 16, 'y' => 8], 'thickness' => 4.5, 'height' => 8],
                ['id' => 'w3', 'start' => ['x' => 16, 'y' => 8], 'end' => ['x' => 10, 'y' => 8], 'thickness' => 4.5, 'height' => 8],
                ['id' => 'w4', 'start' => ['x' => 10, 'y' => 8], 'end' => ['x' => 10, 'y' => 12], 'thickness' => 4.5, 'height' => 8],
                ['id' => 'w5', 'start' => ['x' => 10, 'y' => 12], 'end' => ['x' => 0, 'y' => 12], 'thickness' => 4.5, 'height' => 8],
                ['id' => 'w6', 'start' => ['x' => 0, 'y' => 12], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 4.5, 'height' => 8],
            ],
            'rooms' => [
                ['id' => 'r_l', 'name' => 'L Room', 'walls' => ['w1', 'w2', 'w3', 'w4', 'w5', 'w6']],
            ],
        ];
        $metrics = $this->bridge->layoutMetrics($plan, 'r_l', ['wall_height_ft' => 8.0]);
        $this->assertEqualsWithDelta(157.64, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(54.5, $metrics['perimeter_lf'], 0.01);
    }

    public function test_two_rooms_total_excludes_shared_partition_footprint(): void
    {
        $t = 10.0;
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
        $this->assertEqualsWithDelta(93.55, $left['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(93.55, $right['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(187.1, $total['area_sqft'], 0.01);
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
        $inside = $this->geom->interiorFloorAreaSqFt($poly, ['a', 'b', 'c', 'd'], $wallsById, array_values($wallsById));
        $this->assertEqualsWithDelta(92.64, $inside, 0.01);

        $plan = [
            'unit' => 'cm',
            'floor' => [
                'walls' => array_values($wallsById),
                'rooms' => [['id' => 'r1', 'walls' => ['a', 'b', 'c', 'd']]],
            ],
        ];
        $metrics = $this->bridge->layoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $this->assertGreaterThan($metrics['area_sqft'], $metrics['outside_area_sqft']);
        // Interior ring + half 4.5″ wall per edge → ~10.375 ft exterior span → 107.64 sf.
        $this->assertEqualsWithDelta(107.64, $metrics['outside_area_sqft'], 0.01);
    }
}
