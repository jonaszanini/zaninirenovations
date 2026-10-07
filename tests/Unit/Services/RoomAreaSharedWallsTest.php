<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaSharedWalls;
use Tests\TestCase;

class RoomAreaSharedWallsTest extends TestCase
{
    private RoomAreaSharedWalls $shared;

    private RoomAreaCanvasData $canvas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shared = new RoomAreaSharedWalls;
        $this->canvas = new RoomAreaCanvasData;
    }

    public function test_detects_collinear_overlapping_partition_once(): void
    {
        $pairs = $this->shared->detect($this->adjacentRooms(), 12.0);

        $this->assertCount(1, $pairs);
        $this->assertEqualsWithDelta(8.0, $pairs[0]['overlap_lf'], 0.01);
        $this->assertSame(8.0, $this->shared->sharedLengthFeet($this->adjacentRooms(), 12.0));
    }

    public function test_ignores_separated_rooms(): void
    {
        $canvas = $this->adjacentRooms();
        $canvas['groups'][1]['polygons'][0]['vertices'] = [
            ['x' => 200, 'y' => 0],
            ['x' => 320, 'y' => 0],
            ['x' => 320, 'y' => 96],
            ['x' => 200, 'y' => 96],
        ];

        $this->assertSame([], $this->shared->detect($canvas, 12.0));
        $this->assertSame(0.0, $this->shared->sharedLengthFeet($canvas, 12.0));
    }

    public function test_framing_counts_shared_partition_once(): void
    {
        $metrics = $this->canvas->layoutMetrics($this->adjacentRooms(), 12.0, 8.0);

        $this->assertSame(160.0, $metrics['area_sqft']);
        $this->assertSame(72.0, $metrics['perimeter_lf']);
        $this->assertSame(8.0, $metrics['shared_wall_lf']);
        $this->assertSame(64.0, $metrics['framing_lf']);
        $this->assertSame(72.0, $metrics['baseboard_lf']);
        $this->assertSame(576.0, $metrics['wall_area_sqft']);
    }

    public function test_thickness_grows_footprint_using_exterior_walls_only(): void
    {
        $canvas = $this->adjacentRooms();
        $canvas['v'] = 4;
        $canvas['wall_thickness_in'] = 3.5;

        $metrics = $this->canvas->layoutMetrics($canvas, 12.0, 8.0);

        $this->assertSame(160.0, $metrics['area_sqft']);
        $this->assertSame(160.0, $metrics['inside_area_sqft']);
        $this->assertSame(8.0, $metrics['shared_wall_lf']);
        $this->assertSame(64.0, $metrics['framing_lf']);
        $this->assertEqualsWithDelta(176.33, $metrics['outside_area_sqft'], 0.02);
    }

    public function test_links_coincident_shared_vertices(): void
    {
        $links = $this->shared->linkedVertices($this->adjacentRooms(), 12.0);

        $this->assertCount(2, $links);
        $indexes = [];
        foreach ($links as $link) {
            $indexes[] = $link['a']['polygon_id'].':'.$link['a']['index'].'-'.$link['b']['polygon_id'].':'.$link['b']['index'];
        }
        $this->assertNotEmpty($indexes);
    }

    /**
     * Two 10×8 ft rooms sharing the 8 ft wall at x=120 (12 px/ft).
     *
     * @return array<string, mixed>
     */
    private function adjacentRooms(): array
    {
        return [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'name' => 'Kitchen',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ],
                [
                    'id' => 'g2',
                    'color' => '#2e7d32',
                    'polygons' => [[
                        'id' => 'p2',
                        'name' => 'Living',
                        'vertices' => [
                            ['x' => 120, 'y' => 0],
                            ['x' => 240, 'y' => 0],
                            ['x' => 240, 'y' => 96],
                            ['x' => 120, 'y' => 96],
                        ],
                    ]],
                ],
            ],
        ];
    }
}
