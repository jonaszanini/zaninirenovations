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
        $unionRect = ['x0' => 0, 'y0' => 0, 'x1' => 200, 'y1' => 200];

        $this->assertSame(
            $this->extensions->rectInsidePolygonAreaSqFt($unionRect, $roomPoly, 1.0),
            $out['excluded_area_sqft']
        );
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
