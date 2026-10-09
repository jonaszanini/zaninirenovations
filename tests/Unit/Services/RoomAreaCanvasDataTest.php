<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaCanvasData;
use Tests\TestCase;

class RoomAreaCanvasDataTest extends TestCase
{
    private RoomAreaCanvasData $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RoomAreaCanvasData;
    }

    public function test_normalizes_legacy_v1_vertices_list(): void
    {
        $legacy = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 0],
            ['x' => 100, 'y' => 50],
            ['x' => 0, 'y' => 50],
        ];

        $canvas = $this->service->normalize($legacy);

        $this->assertSame(2, $canvas['v']);
        $this->assertCount(1, $canvas['groups']);
        $this->assertSame('g1', $canvas['groups'][0]['id']);
        $this->assertSame(RoomAreaCanvasData::DEFAULT_GROUP_COLOR, $canvas['groups'][0]['color']);
        $this->assertCount(1, $canvas['groups'][0]['polygons']);
        $this->assertCount(4, $canvas['groups'][0]['polygons'][0]['vertices']);
    }

    public function test_normalizes_v2_multi_group_canvas(): void
    {
        $raw = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        ['id' => 'p1', 'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 10, 'y' => 0],
                            ['x' => 10, 'y' => 10],
                        ]],
                    ],
                ],
                [
                    'id' => 'g2',
                    'color' => '#e65100',
                    'polygons' => [
                        ['id' => 'p2', 'vertices' => [
                            ['x' => 20, 'y' => 0],
                            ['x' => 30, 'y' => 0],
                            ['x' => 30, 'y' => 10],
                        ]],
                    ],
                ],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertCount(2, $canvas['groups']);
        $this->assertSame('#e65100', $canvas['groups'][1]['color']);
    }

    public function test_preserves_optional_polygon_name(): void
    {
        $raw = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'name' => "  Kitchen  island  \n",
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 10, 'y' => 0],
                                ['x' => 10, 'y' => 10],
                            ],
                        ],
                        [
                            'id' => 'p2',
                            'name' => '   ',
                            'vertices' => [
                                ['x' => 20, 'y' => 0],
                                ['x' => 30, 'y' => 0],
                                ['x' => 30, 'y' => 10],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertSame('Kitchen island', $canvas['groups'][0]['polygons'][0]['name']);
        $this->assertArrayNotHasKey('name', $canvas['groups'][0]['polygons'][1]);
    }

    public function test_validates_multi_polygon_totals(): void
    {
        $canvas = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 100, 'y' => 0],
                                ['x' => 100, 'y' => 50],
                                ['x' => 0, 'y' => 50],
                            ],
                        ],
                        [
                            'id' => 'p2',
                            'vertices' => [
                                ['x' => 200, 'y' => 0],
                                ['x' => 300, 'y' => 0],
                                ['x' => 300, 'y' => 50],
                                ['x' => 200, 'y' => 50],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->validate($canvas, 10.0);

        $this->assertTrue($result['valid']);
        $this->assertSame(100.0, $result['area_sqft']);
        $this->assertCount(1, $result['group_summaries']);
        $this->assertSame(100.0, $result['group_summaries'][0]['area_sqft']);
        $this->assertSame(2, $result['group_summaries'][0]['polygon_count']);
    }

    public function test_rejects_empty_canvas_on_save(): void
    {
        $result = $this->service->validate($this->service->emptyCanvas(), 10.0);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('At least one closed area', $result['message']);
    }

    public function test_normalizes_v2_background_layout(): void
    {
        $raw = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        ['id' => 'p1', 'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 10, 'y' => 0],
                            ['x' => 10, 'y' => 10],
                        ]],
                    ],
                ],
            ],
            'background' => [
                'x' => 12.5,
                'y' => 40,
                'width' => 800,
                'height' => 600,
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertSame(12.5, $canvas['background']['x']);
        $this->assertSame(40.0, $canvas['background']['y']);
        $this->assertSame(800.0, $canvas['background']['width']);
        $this->assertSame(600.0, $canvas['background']['height']);
    }

    public function test_rejects_self_intersecting_polygon_in_canvas(): void
    {
        $canvas = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 40, 'y' => 40],
                                ['x' => 40, 'y' => 0],
                                ['x' => 0, 'y' => 40],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->service->validate($canvas, 10.0);

        $this->assertFalse($result['valid']);
    }

    public function test_normalizes_v3_canvas_with_objects(): void
    {
        $raw = [
            'v' => 3,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'name' => 'Kitchen',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 120, 'y' => 0],
                                ['x' => 120, 'y' => 80],
                                ['x' => 0, 'y' => 80],
                            ],
                        ],
                    ],
                ],
            ],
            'objects' => [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                    'swing' => 'left_in',
                    'hinge' => 'left',
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'wall_id' => 'wall_p1_2_3',
                    'width' => 48,
                    'offset' => 12,
                    'height' => 48,
                    'sill_height' => 36,
                ],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertSame(3, $canvas['v']);
        $this->assertSame('Kitchen', $canvas['groups'][0]['polygons'][0]['name']);
        $this->assertCount(2, $canvas['objects']);
        $this->assertSame('obj_1', $canvas['objects'][0]['id']);
        $this->assertSame('door', $canvas['objects'][0]['type']);
        $this->assertSame('single', $canvas['objects'][0]['subtype']);
        $this->assertSame('wall_p1_0_1', $canvas['objects'][0]['wall_id']);
        $this->assertSame(36.0, $canvas['objects'][0]['width']);
        $this->assertSame(24.0, $canvas['objects'][0]['offset']);
        $this->assertSame('left_in', $canvas['objects'][0]['swing']);
        $this->assertSame('left', $canvas['objects'][0]['hinge']);
        $this->assertSame('window', $canvas['objects'][1]['type']);
        $this->assertSame('fixed', $canvas['objects'][1]['subtype']);
        $this->assertSame(48.0, $canvas['objects'][1]['height']);
        $this->assertSame(36.0, $canvas['objects'][1]['sill_height']);
    }

    public function test_v2_canvas_without_objects_stays_v2(): void
    {
        $raw = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        ['id' => 'p1', 'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 10, 'y' => 0],
                            ['x' => 10, 'y' => 10],
                        ]],
                    ],
                ],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertSame(2, $canvas['v']);
        $this->assertArrayNotHasKey('objects', $canvas);
    }

    public function test_v2_canvas_with_objects_promotes_to_v3(): void
    {
        $raw = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        ['id' => 'p1', 'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 10, 'y' => 0],
                            ['x' => 10, 'y' => 10],
                        ]],
                    ],
                ],
            ],
            'objects' => [
                [
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 32,
                    'offset' => 8,
                ],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertSame(3, $canvas['v']);
        $this->assertCount(1, $canvas['objects']);
        $this->assertSame('obj_1', $canvas['objects'][0]['id']);
        $this->assertSame(32.0, $canvas['objects'][0]['width']);
    }

    public function test_drops_invalid_and_orphaned_objects(): void
    {
        $raw = [
            'v' => 3,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        ['id' => 'p1', 'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 10, 'y' => 0],
                            ['x' => 10, 'y' => 10],
                        ]],
                    ],
                ],
            ],
            'objects' => [
                ['type' => 'sofa', 'wall_id' => 'wall_p1_0_1', 'width' => 36],
                ['type' => 'door', 'wall_id' => 'not-a-wall', 'width' => 36],
                ['type' => 'door', 'wall_id' => 'wall_p9_0_1', 'width' => 36],
                ['type' => 'door', 'wall_id' => 'wall_p1_0_1', 'width' => 2],
                ['type' => 'window', 'wall_id' => 'wall_p1_1_2', 'width' => 24, 'offset' => -4],
            ],
        ];

        $canvas = $this->service->normalize($raw);

        $this->assertCount(1, $canvas['objects']);
        $this->assertSame('window', $canvas['objects'][0]['type']);
        $this->assertSame(0.0, $canvas['objects'][0]['offset']);
    }

    public function test_layout_metrics_uses_real_perimeter_not_square(): void
    {
        $canvas = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 4, 'y' => 0],
                                ['x' => 4, 'y' => 2],
                                ['x' => 2, 'y' => 2],
                                ['x' => 2, 'y' => 5],
                                ['x' => 0, 'y' => 5],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $metrics = $this->service->layoutMetrics($canvas, 1.0);

        $this->assertSame(14.0, $metrics['area_sqft']);
        $this->assertSame(18.0, $metrics['perimeter_lf']);
        $this->assertSame(5, $metrics['inside_corners']);
        $this->assertSame(1, $metrics['outside_corners']);
        $this->assertSame(1, $metrics['polygon_count']);
        $this->assertGreaterThan(4 * sqrt(14.0), $metrics['perimeter_lf']);
        $this->assertSame(18.0, $metrics['baseboard_lf']);
        $this->assertSame(8.0, $metrics['wall_height_ft']);
        $this->assertSame(144.0, $metrics['wall_area_sqft']);
        $this->assertSame(0, $metrics['door_count']);
        $this->assertSame(0, $metrics['window_count']);
        $this->assertSame(0, $metrics['opening_count']);
        $this->assertSame(0, $metrics['drywall_openings']);
    }

    public function test_rect_12_by_10_with_door_and_window_keeps_batch0_math(): void
    {
        $canvas = [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 144, 'y' => 0],
                        ['x' => 144, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
            'objects' => [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_1_2',
                    'width' => 48,
                    'offset' => 24,
                    'height' => 36,
                ],
            ],
        ];

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);

        $this->assertEqualsWithDelta(120.0, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(44.0, $metrics['perimeter_lf'], 0.01);
        $this->assertEqualsWithDelta(41.0, $metrics['baseboard_lf'], 0.01);
        $this->assertEqualsWithDelta(320.0, $metrics['wall_area_sqft'], 0.01);
    }

    public function test_editor_prefs_round_trip_and_do_not_change_math(): void
    {
        $canvas = [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 144, 'y' => 0],
                        ['x' => 144, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
            'objects' => [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_1_2',
                    'width' => 48,
                    'offset' => 24,
                    'height' => 36,
                ],
            ],
            'editor' => [
                'layers_locked' => true,
                'dimensions_mode' => 'main',
                'unit_system' => 'metric',
                'angle_snap' => false,
                'template' => 'rect',
                'unknown' => 'drop-me',
            ],
        ];

        $normalized = $this->service->normalize($canvas);
        $this->assertSame([
            'layers_locked' => true,
            'dimensions_mode' => 'main',
            'unit_system' => 'metric',
            'angle_snap' => false,
            'template' => 'rect',
        ], $normalized['editor']);

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);
        $this->assertEqualsWithDelta(120.0, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(44.0, $metrics['perimeter_lf'], 0.01);
        $this->assertEqualsWithDelta(41.0, $metrics['baseboard_lf'], 0.01);
        $this->assertEqualsWithDelta(320.0, $metrics['wall_area_sqft'], 0.01);
    }

    public function test_old_rooms_without_editor_prefs_still_normalize(): void
    {
        $legacy = [
            ['x' => 0, 'y' => 0],
            ['x' => 144, 'y' => 0],
            ['x' => 144, 'y' => 120],
            ['x' => 0, 'y' => 120],
        ];

        $canvas = $this->service->normalize($legacy);
        $this->assertArrayNotHasKey('editor', $canvas);
        $this->assertSame(2, $canvas['v']);
        $this->assertCount(4, $canvas['groups'][0]['polygons'][0]['vertices']);

        $v2 = $this->service->normalize([
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => $legacy,
                ]],
            ]],
        ]);
        $this->assertArrayNotHasKey('editor', $v2);
    }

    public function test_l_16_by_12_notch_6_by_4_with_door_and_window_keeps_batch0_math(): void
    {
        $canvas = [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 192, 'y' => 0],
                        ['x' => 192, 'y' => 144],
                        ['x' => 120, 'y' => 144],
                        ['x' => 120, 'y' => 96],
                        ['x' => 0, 'y' => 96],
                    ],
                ]],
            ]],
            'objects' => [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_1_2',
                    'width' => 48,
                    'offset' => 24,
                    'height' => 36,
                ],
            ],
        ];

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);

        $this->assertEqualsWithDelta(152.0, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(56.0, $metrics['perimeter_lf'], 0.01);
        $this->assertEqualsWithDelta(53.0, $metrics['baseboard_lf'], 0.01);
        $this->assertEqualsWithDelta(416.0, $metrics['wall_area_sqft'], 0.01);
    }

    public function test_v2_rooms_without_objects_do_not_subtract_from_baseboard_or_wall_area(): void
    {
        $canvas = $this->rectangleCanvas(false);

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);

        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(36.0, $metrics['perimeter_lf']);
        $this->assertSame(36.0, $metrics['baseboard_lf']);
        $this->assertSame(288.0, $metrics['wall_area_sqft']);
        $this->assertSame(0, $metrics['drywall_openings']);
    }

    public function test_wall_metrics_subtract_doors_and_openings_from_baseboard_but_not_windows(): void
    {
        $canvas = $this->rectangleCanvas(true);

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);

        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(36.0, $metrics['perimeter_lf']);
        $this->assertSame(1, $metrics['door_count']);
        $this->assertSame(1, $metrics['window_count']);
        $this->assertSame(1, $metrics['opening_count']);
        $this->assertSame(3, $metrics['drywall_openings']);
        // 36" door + 36" opening = 6 lf; 48" window does not reduce baseboard.
        $this->assertSame(30.0, $metrics['baseboard_lf']);
        // Net wall area: ceiling-height walls minus door, window, and opening.
        $this->assertEqualsWithDelta(232.0, $metrics['wall_area_sqft'], 0.01);
        $this->assertEqualsWithDelta(232.0, $metrics['net_wall_area_sqft'], 0.01);
    }

    public function test_wall_metrics_use_object_height_when_present(): void
    {
        $canvas = $this->rectangleCanvas(true);
        $canvas['objects'][0]['height'] = 84;
        $canvas['objects'][1]['height'] = 24;

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 9.0);

        $doorArea = (36 / 12) * (84 / 12);
        $windowArea = (48 / 12) * (24 / 12);
        $openingArea = (36 / 12) * (9.0);
        $this->assertEqualsWithDelta(36.0 * 9.0 - $doorArea - $windowArea - $openingArea, $metrics['wall_area_sqft'], 0.01);
        $this->assertSame($metrics['net_wall_area_sqft'], $metrics['wall_area_sqft']);
        $this->assertSame(9.0, $metrics['wall_height_ft']);
    }

    public function test_wall_thickness_off_keeps_inside_equal_to_outside(): void
    {
        $metrics = $this->service->layoutMetrics($this->rectangleCanvas(false), 12.0, 8.0);

        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(80.0, $metrics['inside_area_sqft']);
        $this->assertSame(80.0, $metrics['outside_area_sqft']);
        $this->assertSame(0.0, $metrics['wall_thickness_in']);
        $this->assertSame(36.0, $metrics['framing_lf']);
    }

    public function test_wall_thickness_grows_footprint_but_quotes_inside_area(): void
    {
        $canvas = $this->rectangleCanvas(false);
        $canvas['v'] = 4;
        $canvas['wall_thickness_in'] = 6;

        $normalized = $this->service->normalize($canvas);
        $this->assertSame(4, $normalized['v']);
        $this->assertSame(6.0, $normalized['wall_thickness_in']);

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);
        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(80.0, $metrics['inside_area_sqft']);
        $this->assertSame(6.0, $metrics['wall_thickness_in']);
        $this->assertEqualsWithDelta(98.0, $metrics['outside_area_sqft'], 0.01);
    }

    public function test_normalizes_free_objects_and_cabinet_linear_feet(): void
    {
        $raw = $this->rectangleCanvas(false);
        $raw['v'] = 4;
        $raw['objects'] = [
            [
                'id' => 'cab_1',
                'type' => 'cabinet',
                'subtype' => 'base',
                'x' => 40,
                'y' => 40,
                'width' => 36,
                'depth' => 24,
                'rotation' => 0,
            ],
            [
                'id' => 'cab_2',
                'type' => 'cabinet',
                'subtype' => 'upper',
                'x' => 80,
                'y' => 20,
                'width' => 24,
                'depth' => 12,
                'rotation' => 90,
            ],
            [
                'id' => 'st_1',
                'type' => 'stairs',
                'subtype' => 'straight',
                'x' => 60,
                'y' => 70,
                'width' => 36,
                'depth' => 120,
                'rotation' => 0,
            ],
        ];

        $canvas = $this->service->normalize($raw);
        $this->assertSame(4, $canvas['v']);
        $this->assertCount(3, $canvas['objects']);
        $this->assertSame('cabinet', $canvas['objects'][0]['type']);
        $this->assertSame('stairs', $canvas['objects'][2]['type']);
        $this->assertArrayNotHasKey('wall_id', $canvas['objects'][0]);

        $metrics = $this->service->layoutMetrics($canvas, 12.0, 8.0);
        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(3.0, $metrics['cabinet_base_lf']);
        $this->assertSame(2.0, $metrics['cabinet_upper_lf']);
        $this->assertSame(1, $metrics['stairs_count']);
        $this->assertSame(12, $metrics['stairs_treads']);
        $this->assertSame(10.0, $metrics['stairs_run_lf']);
        $this->assertSame('standard', $metrics['stairs_quote_type']);
    }

    public function test_v2_canvas_without_thickness_stays_v2(): void
    {
        $canvas = $this->service->normalize($this->rectangleCanvas(false));
        $this->assertSame(2, $canvas['v']);
        $this->assertArrayNotHasKey('wall_thickness_in', $canvas);
    }

    /**
     * @return array<string, mixed>
     */
    private function rectangleCanvas(bool $withObjects): array
    {
        $canvas = [
            'v' => $withObjects ? 3 : 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 120, 'y' => 0],
                                ['x' => 120, 'y' => 96],
                                ['x' => 0, 'y' => 96],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($withObjects) {
            $canvas['objects'] = [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 12,
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_1_2',
                    'width' => 48,
                    'offset' => 12,
                    'height' => 36,
                ],
                [
                    'id' => 'obj_3',
                    'type' => 'opening',
                    'subtype' => 'archway',
                    'wall_id' => 'wall_p1_2_3',
                    'width' => 36,
                    'offset' => 12,
                ],
            ];
        }

        return $canvas;
    }

    public function test_locked_geometry_ignores_name_and_canvas_resize_but_rejects_unlock_plus_move(): void
    {
        $existing = $this->service->normalize([
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'name' => 'Kitchen',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 144, 'y' => 0],
                        ['x' => 144, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
            'editor' => ['layers_locked' => true],
        ]);

        $renamed = $existing;
        $renamed['groups'][0]['polygons'][0]['name'] = 'Dining';
        $this->assertNull($this->service->lockedGeometryChangeMessage($existing, $renamed, 12.0, 12.0));

        $resized = $existing;
        foreach ($resized['groups'][0]['polygons'][0]['vertices'] as $i => $vertex) {
            $resized['groups'][0]['polygons'][0]['vertices'][$i]['x'] = $vertex['x'] * (803 / 800);
            $resized['groups'][0]['polygons'][0]['vertices'][$i]['y'] = $vertex['y'] * (803 / 800);
        }
        $this->assertNull($this->service->lockedGeometryChangeMessage($existing, $resized, 12.0, 12.0 * (803 / 800)));

        $moved = $existing;
        $moved['editor']['layers_locked'] = false;
        $moved['groups'][0]['polygons'][0]['vertices'][1]['x'] = 200;
        $this->assertSame(
            'Unlock layers to change walls or areas.',
            $this->service->lockedGeometryChangeMessage($existing, $moved, 12.0, 12.0)
        );

        $recal = $existing;
        $this->assertSame(
            'Unlock layers to recalibrate scale.',
            $this->service->lockedGeometryChangeMessage($existing, $recal, 12.0, 18.0)
        );
    }

    public function test_preserve_editor_lock_when_editor_omitted(): void
    {
        $existing = ['editor' => ['layers_locked' => true, 'dimensions_mode' => 'main']];
        $incoming = $this->service->normalize([
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 144, 'y' => 0],
                        ['x' => 144, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
        ]);
        $merged = $this->service->preserveEditorLock($incoming, $existing, ['groups' => $incoming['groups']]);
        $this->assertTrue($merged['editor']['layers_locked']);
        $this->assertSame('main', $merged['editor']['dimensions_mode']);
    }

    public function test_preserve_editor_lock_unless_explicitly_unlocked(): void
    {
        $existing = $this->lockedRect();
        $incoming = $existing;
        $incoming['editor'] = [];
        $keptEmpty = $this->service->preserveEditorLock($incoming, $existing, ['editor' => []]);
        $this->assertTrue($keptEmpty['editor']['layers_locked']);

        $incomingMode = $existing;
        $incomingMode['editor'] = ['dimensions_mode' => 'area'];
        $keptMode = $this->service->preserveEditorLock($incomingMode, $existing, ['editor' => ['dimensions_mode' => 'area']]);
        $this->assertTrue($keptMode['editor']['layers_locked']);
        $this->assertSame('area', $keptMode['editor']['dimensions_mode']);

        $unlocked = $existing;
        $unlocked['editor'] = ['layers_locked' => false];
        $cleared = $this->service->preserveEditorLock($unlocked, $existing, ['editor' => ['layers_locked' => false]]);
        $this->assertFalse($cleared['editor']['layers_locked'] ?? false);
    }

    public function test_restore_stored_geometry_within_lock_tolerance(): void
    {
        $existing = $this->lockedRect();
        $nudged = $existing;
        $nudged['groups'][0]['polygons'][0]['vertices'][1]['x'] = 144.5; // 0.5px = 0.0417 ft at 12 px/ft
        $restored = $this->service->restoreStoredGeometryIfWithinTolerance($nudged, $existing, 12.0, 12.0);
        $this->assertSame(144.0, $restored['groups'][0]['polygons'][0]['vertices'][1]['x']);
        $this->assertNull($this->service->lockedGeometryChangeMessage($existing, $restored, 12.0, 12.0));
    }

    public function test_locked_resize_scales_stored_vertices_and_background(): void
    {
        $existing = $this->lockedRect();
        $existing['background'] = ['x' => 10, 'y' => 20, 'width' => 400, 'height' => 300];

        foreach ([0.5, 0.9, 1.02, 1.1, 1.5] as $factor) {
            $incoming = $existing;
            foreach ($incoming['groups'][0]['polygons'][0]['vertices'] as $i => $vertex) {
                $incoming['groups'][0]['polygons'][0]['vertices'][$i]['x'] = $vertex['x'] * $factor;
                $incoming['groups'][0]['polygons'][0]['vertices'][$i]['y'] = $vertex['y'] * $factor;
            }
            $incoming['background'] = [
                'x' => 10 * $factor,
                'y' => 20 * $factor,
                'width' => 400 * $factor,
                'height' => 300 * $factor,
            ];
            $this->assertNull(
                $this->service->lockedGeometryChangeMessage($existing, $incoming, 12.0, 12.0 * $factor),
                'factor '.$factor
            );
            $restored = $this->service->restoreStoredGeometryIfWithinTolerance($incoming, $existing, 12.0, 12.0 * $factor);
            $this->assertEqualsWithDelta(144.0 * $factor, $restored['groups'][0]['polygons'][0]['vertices'][1]['x'], 0.0001);
            $this->assertEqualsWithDelta(400.0 * $factor, $restored['background']['width'], 0.01);
            $this->assertNull($this->service->lockedGeometryChangeMessage($existing, $restored, 12.0, 12.0 * $factor));
        }
    }

    public function test_locked_background_layout_change_is_rejected(): void
    {
        $existing = $this->lockedRect();
        $existing['background'] = ['x' => 10, 'y' => 20, 'width' => 400, 'height' => 300];
        $moved = $existing;
        $moved['background']['x'] = 80;
        $this->assertSame(
            'Unlock layers to change the floor plan.',
            $this->service->lockedGeometryChangeMessage($existing, $moved, 12.0, 12.0)
        );

        $restored = $this->service->restoreStoredGeometryIfWithinTolerance($moved, $existing, 12.0, 12.0);
        $this->assertSame(
            'Unlock layers to change the floor plan.',
            $this->service->lockedGeometryChangeMessage($existing, $restored, 12.0, 12.0)
        );
    }

    public function test_scale_factor_rounds_incoming_scale_to_four_decimals(): void
    {
        $existing = $this->lockedRect();
        $existing['background'] = ['x' => 24, 'y' => 36, 'width' => 480, 'height' => 360];
        $incoming = $existing;
        foreach ($incoming['groups'][0]['polygons'][0]['vertices'] as $i => $vertex) {
            $incoming['groups'][0]['polygons'][0]['vertices'][$i]['x'] = $vertex['x'] * 1.0137;
            $incoming['groups'][0]['polygons'][0]['vertices'][$i]['y'] = $vertex['y'] * 1.0137;
        }
        $incoming['background'] = [
            'x' => 24 * 1.0137,
            'y' => 36 * 1.0137,
            'width' => 480 * 1.0137,
            'height' => 360 * 1.0137,
        ];
        $restored = $this->service->restoreStoredGeometryIfWithinTolerance(
            $incoming,
            $existing,
            12.0,
            12.0 * 1.0137
        );
        $roundedIncoming = round(12.0 * 1.0137, 4);
        $this->assertEqualsWithDelta(144.0 * ($roundedIncoming / 12.0), $restored['groups'][0]['polygons'][0]['vertices'][1]['x'], 0.0001);
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedRect(): array
    {
        return $this->service->normalize([
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 144, 'y' => 0],
                        ['x' => 144, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
            'editor' => ['layers_locked' => true],
        ]);
    }
}
