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
}
