<?php

namespace Tests\Unit\Services;

use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\RoomAreaFinishMath;
use App\Services\RoomAreaOpenPlan3DExtensions;
use Tests\TestCase;

class RoomAreaOpenPlan3DExtensionsTest extends TestCase
{
    private OpenPlan3DMeasurementBridge $bridge;

    private RoomAreaOpenPlan3DExtensions $extensions;

    private RoomAreaFinishMath $finishMath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
        $this->extensions = app(RoomAreaOpenPlan3DExtensions::class);
        $this->finishMath = app(RoomAreaFinishMath::class);
    }

    public function test_partition_plan_uses_outer_polygon_when_stored_polygon_is_partial(): void
    {
        $plan = $this->partitionedRoomPlan();

        $metrics = $this->bridge->layoutMetrics($plan);

        $halfArea = $this->finishMath->roundTo((200.0 / OpenPlan3DMeasurementBridge::CM_PER_FOOT) * (300.0 / OpenPlan3DMeasurementBridge::CM_PER_FOOT), 2);
        $this->assertGreaterThan($halfArea + 1.0, $metrics['area_sqft']);
        $this->assertCount(4, $metrics['boundary_wall_ids']);
        $wallIds = array_column($metrics['walls'], 'id');
        $this->assertContains('wP', $wallIds);
    }

    public function test_excluded_area_zones_reduce_floor_metrics_and_recalc_boxes(): void
    {
        $plan = $this->simpleSquarePlan(300.0);
        $plan['zanini'] = [
            'excluded_area_zones' => [
                ['x1' => 0, 'y1' => 0, 'x2' => 100, 'y2' => 100],
            ],
        ];

        $planWithoutZones = $this->simpleSquarePlan(300.0);
        $baseline = $this->bridge->layoutMetrics($planWithoutZones, null, [
            'flooring_waste_percent' => 0,
            'flooring_box_sqft' => 20,
        ]);

        $metrics = $this->bridge->layoutMetrics($plan, null, [
            'flooring_waste_percent' => 0,
            'flooring_box_sqft' => 20,
        ]);

        $excluded = $this->extensions->rectsUnionAreaSqFt($plan['zanini']['excluded_area_zones']);
        $expected = $this->finishMath->roundTo($baseline['area_sqft'] - $excluded, 2);

        $this->assertSame($excluded, $metrics['excluded_area_sqft']);
        $this->assertSame($expected, $metrics['area_sqft']);
        $this->assertSame($expected, $metrics['inside_area_sqft']);
        $this->assertSame($expected, $metrics['ceiling_area_sqft']);
        $this->assertSame((int) ceil($expected / 20.0), $metrics['flooring_boxes']);
    }

    public function test_bulkhead_totals_aggregate_from_plan_zanini(): void
    {
        $metrics = [
            'area_sqft' => 100.0,
            'inside_area_sqft' => 100.0,
            'ceiling_area_sqft' => 100.0,
            'walls' => [],
            'casing_lf' => 0.0,
            'boundary_wall_ids' => [],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'bulkheads' => [
                    [
                        'rect' => [
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 304.8,
                            'y2' => 30.48,
                        ],
                        'drop_height_ft' => 2.0,
                        'width_ft' => 1.0,
                    ],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(27.0, $out['bulkhead_framing_lf']);
        $this->assertSame(30.0, $out['bulkhead_drywall_sqft']);
        $this->assertSame(10.0, $out['bulkhead_corner_bead_lf']);
    }

    public function test_bulkhead_corner_bead_and_drywall_for_10_by_2_drop_1_against_wall(): void
    {
        $takeoff = (new \ReflectionClass($this->extensions))
            ->getMethod('bulkheadTakeoff');
        $takeoff->setAccessible(true);
        /** @var array{framing_lf: float, drywall_sqft: float, corner_bead_lf: float} $out */
        $out = $takeoff->invoke($this->extensions, 10.0, 2.0, 1.0, true);
        $this->assertSame(30.0, $out['drywall_sqft']);
        $this->assertSame(10.0, $out['corner_bead_lf']);
    }

    public function test_measure_only_zone_does_not_change_floor_area(): void
    {
        $metrics = [
            'area_sqft' => 100.0,
            'inside_area_sqft' => 100.0,
            'ceiling_area_sqft' => 100.0,
            'room_polygon_cm' => [
                ['x' => 0, 'y' => 0],
                ['x' => 300, 'y' => 0],
                ['x' => 300, 'y' => 300],
                ['x' => 0, 'y' => 300],
            ],
            'walls' => [],
            'casing_lf' => 0.0,
            'boundary_wall_ids' => [],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'zones' => [
                    ['id' => 'z1', 'exclude' => false, 'x1' => 0, 'y1' => 0, 'x2' => 100, 'y2' => 100],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(100.0, $out['area_sqft']);
        $this->assertSame(0.0, $out['excluded_area_sqft']);
    }

    public function test_partition_drywall_one_face_side_a(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'casing_lf' => 0.0,
            'boundary_wall_ids' => ['w1', 'w2'],
            'walls' => [
                ['id' => 'w1', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
                ['id' => 'w2', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
                ['id' => 'w3', 'length_lf' => 8.0, 'net_area_sqft' => 128.0],
            ],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'wallFinishes' => [
                    'w3' => ['drywallFaces' => 'a'],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(224.0, $out['finish_drywall_sqft']);
        $this->assertSame(64.0, $this->extensions->drywallFinishAreaSqFt(128.0, ['drywallFaces' => 'a'], false));
        $this->assertSame(80.0, $this->extensions->drywallFinishAreaSqFt(80.0, ['drywallFaces' => 'a'], true));
    }

    public function test_explicit_partition_baseboard_counts_two_sides(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'casing_lf' => 0.0,
            'boundary_wall_ids' => ['w1'],
            'walls' => [
                ['id' => 'w1', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
                ['id' => 'w3', 'length_lf' => 8.0, 'net_area_sqft' => 64.0],
            ],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'rooms' => [
                    ['id' => 'r1', 'walls' => ['w1', 'w3']],
                    ['id' => 'r2', 'walls' => ['w3']],
                ],
            ],
            'zanini' => [
                'wallFinishes' => [
                    'w3' => ['baseboard' => true],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(26.0, $out['finish_baseboard_lf']);
    }

    public function test_wall_finish_totals_use_boundary_and_partition_defaults(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'casing_lf' => 12.5,
            'baseboard_lf' => 20.0,
            'boundary_wall_ids' => ['w1', 'w2'],
            'walls' => [
                [
                    'id' => 'w1',
                    'length_lf' => 10.0,
                    'net_area_sqft' => 80.0,
                ],
                [
                    'id' => 'w2',
                    'length_lf' => 10.0,
                    'net_area_sqft' => 80.0,
                ],
                [
                    'id' => 'w3',
                    'length_lf' => 8.0,
                    'net_area_sqft' => 128.0,
                ],
            ],
        ];
        $plan = ['format' => 'openplan3d', 'units' => 'cm'];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(160.0 + 128.0, $out['finish_drywall_sqft']);
        $this->assertSame(160.0, $out['finish_insulation_sqft']);
        $this->assertSame(8.0, $out['finish_stud_lf']);
        $this->assertSame(20.0, $out['finish_baseboard_lf']);
        $this->assertSame(12.5, $out['finish_casing_lf']);
        $this->assertArrayNotHasKey('finish_paint_sqft', $out);
    }

    public function test_paint_finish_is_independent_of_drywall_and_omitted_when_unset(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'casing_lf' => 0.0,
            'boundary_wall_ids' => ['w1', 'w2'],
            'walls' => [
                ['id' => 'w1', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
                ['id' => 'w2', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
                ['id' => 'w3', 'length_lf' => 8.0, 'net_area_sqft' => 128.0],
            ],
        ];
        $mixed = $this->extensions->applyToMetrics($metrics, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'wallFinishes' => [
                    'w1' => ['drywall' => true, 'paint' => true],
                    'w2' => ['drywall' => true, 'paint' => false],
                    'w3' => ['drywall' => false, 'paint' => true],
                ],
            ],
        ], [], 1.0);

        $this->assertSame(160.0, $mixed['finish_drywall_sqft']);
        $this->assertSame(208.0, $mixed['finish_paint_sqft']);

        $allOff = $this->extensions->applyToMetrics($metrics, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'wallFinishes' => [
                    'w1' => ['paint' => false],
                    'w2' => ['paint' => false],
                    'w3' => ['paint' => false],
                ],
            ],
        ], [], 1.0);
        $this->assertSame(0.0, $allOff['finish_paint_sqft']);
        $this->assertSame(288.0, $allOff['finish_drywall_sqft']);
        $this->assertSame(0, $allOff['paint_walls_marked']);
        $this->assertSame(3, $allOff['paint_walls_explicit_off']);
        $this->assertSame(0, $allOff['paint_walls_unassigned']);

        $legacyOneOff = $this->extensions->applyToMetrics($metrics, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'wallFinishes' => [
                    'w1' => ['drywall' => true, 'paint' => false],
                    'w2' => ['drywall' => true],
                    'w3' => ['drywall' => true],
                ],
            ],
        ], [], 1.0);
        $this->assertSame(0.0, $legacyOneOff['finish_paint_sqft']);
        $this->assertSame(1, $legacyOneOff['paint_walls_explicit_off']);
        $this->assertSame(2, $legacyOneOff['paint_walls_unassigned']);
        $this->assertSame(0, $legacyOneOff['paint_walls_marked']);
    }

    public function test_bulkhead_paint_uses_matching_finish_not_full_drywall(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'boundary_wall_ids' => ['w1'],
            'walls' => [
                ['id' => 'w1', 'length_lf' => 10.0, 'net_area_sqft' => 80.0],
            ],
            'baseboard_lf' => 10.0,
        ];
        $unassigned = $this->extensions->applyToMetrics($metrics, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'bulkheads' => [[
                    'rect' => ['x1' => 0, 'y1' => 0, 'x2' => 304.8, 'y2' => 30.48],
                    'drop_height_ft' => 2.0,
                    'against_wall' => true,
                ]],
            ],
        ], [], 1.0);
        $this->assertSame(30.0, $unassigned['bulkhead_drywall_sqft']);
        $this->assertArrayNotHasKey('finish_paint_sqft', $unassigned);
        $this->assertArrayNotHasKey('bulkhead_paint_sqft', $unassigned);
        $this->assertSame(1, $unassigned['paint_bulkheads_unassigned']);

        $painted = $this->extensions->applyToMetrics($metrics, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'bulkheads' => [[
                    'rect' => ['x1' => 0, 'y1' => 0, 'x2' => 304.8, 'y2' => 30.48],
                    'drop_height_ft' => 2.0,
                    'against_wall' => true,
                    'paint' => true,
                ]],
                'wallFinishes' => [
                    'w1' => ['paint' => false],
                ],
            ],
        ], [], 1.0);
        $this->assertSame(30.0, $painted['bulkhead_drywall_sqft']);
        $this->assertSame(30.0, $painted['bulkhead_paint_sqft']);
        $this->assertSame(30.0, $painted['finish_paint_sqft']);
        $this->assertSame(1, $painted['paint_bulkheads_marked']);
        $this->assertSame(0, $painted['paint_walls_marked']);
    }

    public function test_extract_and_merge_zanini_payload(): void
    {
        $plan = [
            'floors' => [
                [
                    'id' => 'f1',
                    'zanini' => ['bulkheads' => []],
                ],
            ],
            'activeFloorId' => 'f1',
        ];

        $this->assertSame(['bulkheads' => []], $this->extensions->extractZanini($plan));

        $merged = $this->extensions->mergeZaniniIntoPlan($plan, ['excluded_area_zones' => []]);
        $this->assertSame(['excluded_area_zones' => []], $merged['zanini']);
    }

    public function test_rect_inside_room_polygon_is_exact_for_ten_by_ten_feet(): void
    {
        $roomPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 304.8, 'y' => 0],
            ['x' => 304.8, 'y' => 304.8],
            ['x' => 0, 'y' => 304.8],
        ];
        $rect = ['x0' => 0, 'y0' => 0, 'x1' => 304.8, 'y1' => 304.8];

        $this->assertSame(100.0, $this->extensions->rectInsidePolygonAreaSqFt($rect, $roomPoly, 1.0));
    }

    public function test_overlapping_exclude_zones_union_before_subtracting(): void
    {
        $roomPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 304.8, 'y' => 0],
            ['x' => 304.8, 'y' => 304.8],
            ['x' => 0, 'y' => 304.8],
        ];
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'room_polygon_cm' => $roomPoly,
            'walls' => [],
            'casing_lf' => 0.0,
            'boundary_wall_ids' => [],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'zones' => [
                    ['id' => 'a', 'exclude' => true, 'x1' => 0, 'y1' => 0, 'x2' => 152.4, 'y2' => 304.8],
                    ['id' => 'b', 'exclude' => true, 'x1' => 76.2, 'y1' => 0, 'x2' => 228.6, 'y2' => 304.8],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(75.0, $out['excluded_area_sqft']);
    }

    public function test_merged_exclude_zones_union_into_target(): void
    {
        $roomPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 400, 'y' => 0],
            ['x' => 400, 'y' => 400],
            ['x' => 0, 'y' => 400],
        ];
        $metrics = [
            'area_sqft' => 400.0,
            'inside_area_sqft' => 400.0,
            'ceiling_area_sqft' => 400.0,
            'room_polygon_cm' => $roomPoly,
            'walls' => [],
            'casing_lf' => 0.0,
            'boundary_wall_ids' => [],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'zones' => [
                    ['id' => 't', 'exclude' => true, 'x1' => 0, 'y1' => 0, 'x2' => 100, 'y2' => 200],
                    ['id' => 's', 'exclude' => true, 'x1' => 100, 'y1' => 0, 'x2' => 200, 'y2' => 100, 'mergedInto' => 't'],
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);
        $lShapeUnion = $this->extensions->rectangleUnionAreaSqFt([
            ['x0' => 0, 'y0' => 0, 'x1' => 100, 'y1' => 200],
            ['x0' => 100, 'y0' => 0, 'x1' => 200, 'y1' => 100],
        ], 1.0);

        $this->assertSame($lShapeUnion, $out['excluded_area_sqft']);
    }

    public function test_merged_overlapping_zones_union_area_175_sqft(): void
    {
        $ft = RoomAreaOpenPlan3DExtensions::CM_PER_FOOT;
        $roomPoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 50 * $ft, 'y' => 0],
            ['x' => 50 * $ft, 'y' => 50 * $ft],
            ['x' => 0, 'y' => 50 * $ft],
        ];
        $zoneA = ['x1' => 20 * $ft, 'y1' => 20 * $ft, 'x2' => 30 * $ft, 'y2' => 30 * $ft];
        $zoneB = ['x1' => 25 * $ft, 'y1' => 25 * $ft, 'x2' => 35 * $ft, 'y2' => 35 * $ft];
        $metrics = [
            'area_sqft' => 2500.0,
            'inside_area_sqft' => 2500.0,
            'ceiling_area_sqft' => 2500.0,
            'room_polygon_cm' => $roomPoly,
            'walls' => [],
            'casing_lf' => 0.0,
            'boundary_wall_ids' => [],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => [
                'zones' => [
                    array_merge(['id' => 't', 'exclude' => true, 'rects' => [$zoneA, $zoneB]], $zoneA),
                    array_merge(['id' => 's', 'exclude' => true, 'mergedInto' => 't'], $zoneB),
                ],
            ],
        ];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(175.0, $out['excluded_area_sqft']);
    }

    public function test_two_saved_rooms_floor_finishes_match_shell(): void
    {
        $ft = OpenPlan3DMeasurementBridge::CM_PER_FOOT;
        $walls = [
            ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 20 * $ft, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
            ['id' => 'w2', 'start' => ['x' => 20 * $ft, 'y' => 0], 'end' => ['x' => 20 * $ft, 'y' => 10 * $ft], 'thickness' => 11.43, 'height' => 243.84],
            ['id' => 'w3', 'start' => ['x' => 20 * $ft, 'y' => 10 * $ft], 'end' => ['x' => 0, 'y' => 10 * $ft], 'thickness' => 11.43, 'height' => 243.84],
            ['id' => 'w4', 'start' => ['x' => 0, 'y' => 10 * $ft], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
            ['id' => 'p1', 'start' => ['x' => 10 * $ft, 'y' => 0], 'end' => ['x' => 10 * $ft, 'y' => 10 * $ft], 'thickness' => 11.43, 'height' => 243.84],
        ];
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'walls' => $walls,
                'rooms' => [
                    ['id' => 'ra', 'walls' => ['w1', 'p1', 'w4', 'w3']],
                    ['id' => 'rb', 'walls' => ['w1', 'w2', 'w3', 'p1']],
                ],
            ],
        ];
        $bridge = app(OpenPlan3DMeasurementBridge::class);
        $metrics = $bridge->layoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $boundary = array_flip(array_map('strval', $metrics['boundary_wall_ids'] ?? []));
        $finishes = [];
        foreach ($metrics['walls'] ?? [] as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $wallId = (string) ($wall['id'] ?? '');
            $isBoundary = isset($boundary[$wallId]);
            $finishes[$wallId] = [
                'drywall' => true,
                'baseboard' => true,
                'insulation' => $isBoundary,
                'studs' => ! $isBoundary,
            ];
        }
        $out = $this->extensions->applyToMetrics(
            $metrics,
            array_merge($plan, ['zanini' => ['wallFinishes' => $finishes]]),
            [],
            $ft
        );

        $this->assertSame(616.0, $out['finish_drywall_sqft']);
        $this->assertSame(462.0, $out['finish_insulation_sqft']);
        $this->assertSame(77.0, $out['finish_baseboard_lf']);
    }

    public function test_hand0_all_finishes_baseboard_and_drywall_pins(): void
    {
        $hand0Path = base_path('tests/fixtures/openplan3d-hand0-drawn-plan.json');
        $hand0 = json_decode((string) file_get_contents($hand0Path), true);
        $metrics = app(OpenPlan3DMeasurementBridge::class)->layoutMetrics($hand0, null, ['wall_height_ft' => 8.0]);
        $finishes = [];
        $boundary = array_flip(array_map('strval', $metrics['boundary_wall_ids'] ?? []));
        foreach ($metrics['walls'] ?? [] as $wall) {
            if (! is_array($wall)) {
                continue;
            }
            $wallId = (string) ($wall['id'] ?? '');
            $isBoundary = isset($boundary[$wallId]);
            $finishes[$wallId] = [
                'drywall' => true,
                'baseboard' => true,
                'insulation' => $isBoundary,
                'studs' => ! $isBoundary,
            ];
        }
        $plan = array_merge($hand0, [
            'format' => 'openplan3d',
            'units' => 'cm',
            'zanini' => ['wallFinishes' => $finishes],
        ]);
        $out = $this->extensions->applyToMetrics($metrics, $plan, [], OpenPlan3DMeasurementBridge::CM_PER_FOOT);

        $this->assertEqualsWithDelta(199.53, $out['finish_baseboard_lf'], 0.02);
        $this->assertEqualsWithDelta(1596.22, $out['finish_drywall_sqft'], 1.5);
    }

    public function test_legacy_baseboard_lf_preserved_when_no_wall_finishes(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'baseboard_lf' => 171.99,
            'casing_lf' => 0.0,
            'boundary_wall_ids' => ['w1', 'w2'],
            'walls' => [
                ['id' => 'w1', 'length_lf' => 55.08, 'net_area_sqft' => 80.0],
                ['id' => 'w2', 'length_lf' => 55.08, 'net_area_sqft' => 80.0],
                ['id' => 'w3', 'length_lf' => 55.08, 'net_area_sqft' => 128.0],
            ],
        ];
        $plan = ['format' => 'openplan3d', 'units' => 'cm'];

        $out = $this->extensions->applyToMetrics($metrics, $plan, [], 1.0);

        $this->assertSame(171.99, $out['finish_baseboard_lf']);
    }

    /**
     * @return array<string, mixed>
     */
    private function simpleSquarePlan(float $sizeCm): array
    {
        return [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floors' => [
                [
                    'id' => 'f1',
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $sizeCm, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'w2', 'start' => ['x' => $sizeCm, 'y' => 0], 'end' => ['x' => $sizeCm, 'y' => $sizeCm], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'w3', 'start' => ['x' => $sizeCm, 'y' => $sizeCm], 'end' => ['x' => 0, 'y' => $sizeCm], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => $sizeCm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                    ],
                    'rooms' => [
                        [
                            'id' => 'r1',
                            'walls' => ['w1', 'w2', 'w3', 'w4'],
                            'polygon' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => $sizeCm, 'y' => 0],
                                ['x' => $sizeCm, 'y' => $sizeCm],
                                ['x' => 0, 'y' => $sizeCm],
                            ],
                        ],
                    ],
                ],
            ],
            'activeFloorId' => 'f1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function partitionedRoomPlan(): array
    {
        return [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floors' => [
                [
                    'id' => 'f1',
                    'walls' => [
                        ['id' => 'wN', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 400, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'wE', 'start' => ['x' => 400, 'y' => 0], 'end' => ['x' => 400, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'wS', 'start' => ['x' => 400, 'y' => 300], 'end' => ['x' => 0, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'wW', 'start' => ['x' => 0, 'y' => 300], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 11.43, 'height' => 243.84],
                        ['id' => 'wP', 'start' => ['x' => 200, 'y' => 0], 'end' => ['x' => 200, 'y' => 300], 'thickness' => 11.43, 'height' => 243.84],
                    ],
                    'rooms' => [
                        [
                            'id' => 'r1',
                            'walls' => ['wN', 'wE', 'wS', 'wW', 'wP'],
                            'polygon' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 200, 'y' => 0],
                                ['x' => 200, 'y' => 300],
                                ['x' => 0, 'y' => 300],
                            ],
                        ],
                    ],
                ],
            ],
            'activeFloorId' => 'f1',
        ];
    }
}
