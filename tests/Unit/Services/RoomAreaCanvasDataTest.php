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
    }
}
