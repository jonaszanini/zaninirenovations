<?php

namespace Tests\Unit\Services;

use App\Services\PolygonAreaService;
use InvalidArgumentException;
use Tests\TestCase;

class PolygonAreaServiceTest extends TestCase
{
    private PolygonAreaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PolygonAreaService;
    }

    public function test_shoelace_area_for_rectangle(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 10, 'y' => 0],
            ['x' => 10, 'y' => 5],
            ['x' => 0, 'y' => 5],
        ];

        $this->assertSame(50.0, $this->service->shoelaceArea($vertices));
    }

    public function test_shoelace_area_for_l_shape(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 4, 'y' => 0],
            ['x' => 4, 'y' => 2],
            ['x' => 2, 'y' => 2],
            ['x' => 2, 'y' => 5],
            ['x' => 0, 'y' => 5],
        ];

        $this->assertSame(14.0, $this->service->shoelaceArea($vertices));
    }

    public function test_rejects_self_intersecting_polygon(): void
    {
        $bowtie = [
            ['x' => 0, 'y' => 0],
            ['x' => 4, 'y' => 4],
            ['x' => 4, 'y' => 0],
            ['x' => 0, 'y' => 4],
        ];

        $this->assertFalse($this->service->isSimplePolygon($bowtie));
    }

    public function test_vertices_to_square_feet_with_scale(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 0],
            ['x' => 100, 'y' => 50],
            ['x' => 0, 'y' => 50],
        ];

        // 100 px = 10 ft → 10 px/ft → 100×50 px = 10×5 ft = 50 sq ft
        $this->assertSame(50.0, $this->service->verticesToSquareFeet($vertices, 10.0));
    }

    public function test_vertices_to_square_feet_throws_on_invalid_scale(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->verticesToSquareFeet([
            ['x' => 0, 'y' => 0],
            ['x' => 1, 'y' => 0],
            ['x' => 1, 'y' => 1],
        ], 0);
    }
}
