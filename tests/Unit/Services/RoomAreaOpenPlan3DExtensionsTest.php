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

        $this->assertSame(22.0, $out['bulkhead_framing_lf']);
        $this->assertSame(54.0, $out['bulkhead_drywall_sqft']);
        $this->assertSame(8.0, $out['bulkhead_corner_bead_lf']);
    }

    public function test_wall_finish_totals_use_boundary_and_partition_defaults(): void
    {
        $metrics = [
            'area_sqft' => 200.0,
            'inside_area_sqft' => 200.0,
            'ceiling_area_sqft' => 200.0,
            'casing_lf' => 12.5,
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
        $this->assertSame(20.0 + 16.0, $out['finish_baseboard_lf']);
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
