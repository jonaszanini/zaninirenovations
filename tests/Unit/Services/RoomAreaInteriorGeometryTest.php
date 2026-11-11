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
        // Centerline 10 ft 5.25 in × 8 ft 5.25 in; per-edge inset (3″ N/S, 7.5″ E/W) → interior ~9.8125×8.1875 ft ≈ 80.34 sf.
        $this->assertEqualsWithDelta(80.34, $area, 0.01);
        $plan = [
            'unit' => 'cm',
            'floor' => [
                'walls' => array_values($walls),
                'rooms' => [['id' => 'r1', 'walls' => ['n', 'e', 's', 'w']]],
            ],
        ];
        $metrics = $this->bridge->layoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $north = null;
        foreach ($metrics['walls'] ?? [] as $wall) {
            if (($wall['id'] ?? '') === 'n') {
                $north = $wall;
                break;
            }
        }
        $this->assertNotNull($north);
        $this->assertEqualsWithDelta(9.8127, (float) ($north['length_lf_exact'] ?? 0), 0.01, 'North thin wall interior-face span');
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

    public function test_boundary_wall_lengths_sum_to_interior_perimeter(): void
    {
        $hand0Path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $hand0 = json_decode((string) file_get_contents($hand0Path), true);
        $fixtures = [
            $this->bridge->layoutMetrics($this->rectPlan(10.0, 12.0), 'r1'),
            $this->bridge->layoutMetrics($this->lRoomPlan(), 'r_l'),
            $this->bridge->layoutMetrics($hand0, null, ['wall_height_ft' => 8.0]),
        ];
        foreach ($fixtures as $metrics) {
            $perimeter = (float) ($metrics['perimeter_lf'] ?? 0);
            $boundaryIds = $metrics['boundary_wall_ids'] ?? [];
            $sum = 0.0;
            foreach ($metrics['walls'] ?? [] as $wall) {
                if (! in_array($wall['id'] ?? '', $boundaryIds, true)) {
                    continue;
                }
                $sum += (float) ($wall['length_lf_exact'] ?? $wall['length_lf'] ?? 0);
            }
            $this->assertEqualsWithDelta($perimeter, $sum, 0.02, 'Boundary interior spans must sum to polygon perimeter');
        }
    }

    public function test_finish_baseboard_sides_follow_room_adjacency_on_hand0(): void
    {
        $hand0Path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $hand0 = json_decode((string) file_get_contents($hand0Path), true);
        $extensions = app(\App\Services\RoomAreaOpenPlan3DExtensions::class);
        $metrics = $this->bridge->layoutMetrics($hand0, null, ['wall_height_ft' => 8.0]);
        $finishes = [];
        foreach ($metrics['walls'] ?? [] as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $finishes[(string) ($wall['id'] ?? '')] = ['baseboard' => true];
        }
        $plan = array_merge($hand0, [
            'zanini' => ['wallFinishes' => $finishes],
        ]);
        $out = $extensions->applyToMetrics($metrics, $plan, [], OpenPlan3DMeasurementBridge::CM_PER_FOOT);
        $this->assertEqualsWithDelta(200.02, $out['finish_baseboard_lf'], 0.02, 'hand0 all finishes baseboard with interior butt deductions on stubs');
    }

    /**
     * @return array<string, mixed>
     */
    private function rectPlan(float $wFt, float $lFt): array
    {
        $cm = OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $w = $wFt * $cm;
        $l = $lFt * $cm;
        $t = 11.43;

        return [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $w, 'y' => 0], 'thickness' => $t],
                    ['id' => 'w2', 'start' => ['x' => $w, 'y' => 0], 'end' => ['x' => $w, 'y' => $l], 'thickness' => $t],
                    ['id' => 'w3', 'start' => ['x' => $w, 'y' => $l], 'end' => ['x' => 0, 'y' => $l], 'thickness' => $t],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => $l], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
                ],
                'rooms' => [['id' => 'r1', 'walls' => ['w1', 'w2', 'w3', 'w4']]],
            ],
        ];
    }

    public function test_collinear_north_split_interior_edge_lengths(): void
    {
        $cm = OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $t = 11.43;
        $plan = [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'n1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 3 * $cm, 'y' => 0], 'thickness' => $t],
                    ['id' => 'n2', 'start' => ['x' => 3 * $cm, 'y' => 0], 'end' => ['x' => 7 * $cm, 'y' => 0], 'thickness' => $t],
                    ['id' => 'n3', 'start' => ['x' => 7 * $cm, 'y' => 0], 'end' => ['x' => 10 * $cm, 'y' => 0], 'thickness' => $t],
                    ['id' => 'e', 'start' => ['x' => 10 * $cm, 'y' => 0], 'end' => ['x' => 10 * $cm, 'y' => 12 * $cm], 'thickness' => $t],
                    ['id' => 's', 'start' => ['x' => 10 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 12 * $cm], 'thickness' => $t],
                    ['id' => 'w', 'start' => ['x' => 0, 'y' => 12 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
                ],
                'rooms' => [['id' => 'r1', 'walls' => ['n1', 'n2', 'n3', 'e', 's', 'w']]],
            ],
        ];
        $metrics = $this->bridge->layoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
        $byId = [];
        foreach ($metrics['walls'] ?? [] as $wall) {
            $byId[(string) ($wall['id'] ?? '')] = (float) ($wall['length_lf'] ?? 0);
        }
        $this->assertEqualsWithDelta(2.81, $byId['n1'], 0.01);
        $this->assertEqualsWithDelta(4.0, $byId['n2'], 0.01);
        $this->assertEqualsWithDelta(2.81, $byId['n3'], 0.01);
        $this->assertEqualsWithDelta(42.5, $metrics['perimeter_lf'] ?? 0, 0.02);
    }

    /**
     * @return array<string, mixed>
     */
    private function lRoomPlan(): array
    {
        $cm = OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $t = 11.43;

        return [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 16 * $cm, 'y' => 0], 'thickness' => $t],
                    ['id' => 'w2', 'start' => ['x' => 16 * $cm, 'y' => 0], 'end' => ['x' => 16 * $cm, 'y' => 12 * $cm], 'thickness' => $t],
                    ['id' => 'w3', 'start' => ['x' => 16 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 10 * $cm, 'y' => 12 * $cm], 'thickness' => $t],
                    ['id' => 'w4', 'start' => ['x' => 10 * $cm, 'y' => 12 * $cm], 'end' => ['x' => 10 * $cm, 'y' => 16 * $cm], 'thickness' => $t],
                    ['id' => 'w5', 'start' => ['x' => 10 * $cm, 'y' => 16 * $cm], 'end' => ['x' => 0, 'y' => 16 * $cm], 'thickness' => $t],
                    ['id' => 'w6', 'start' => ['x' => 0, 'y' => 16 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => $t],
                ],
                'rooms' => [['id' => 'r_l', 'walls' => ['w1', 'w2', 'w3', 'w4', 'w5', 'w6']]],
            ],
        ];
    }
}
