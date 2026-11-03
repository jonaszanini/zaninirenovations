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

    public function test_vertices_to_square_feet_snaps_near_integer_feet_to_exact_area(): void
    {
        // 12 ft sides with slight pixel drift (~0.15%) should still yield 144 sq ft.
        $ppf = 100.0;
        $sidePx = 12 * $ppf + 1.8;
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => $sidePx, 'y' => 0],
            ['x' => $sidePx, 'y' => $sidePx],
            ['x' => 0, 'y' => $sidePx],
        ];

        $this->assertSame(144.0, $this->service->verticesToSquareFeet($vertices, $ppf));
    }

    public function test_rectangle_perimeter_and_inside_corners(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 0],
            ['x' => 100, 'y' => 50],
            ['x' => 0, 'y' => 50],
        ];

        $this->assertSame(30.0, $this->service->verticesToPerimeterFeet($vertices, 10.0));
        $this->assertSame(['inside' => 4, 'outside' => 0], $this->service->cornerCounts($vertices));
    }

    public function test_l_shape_perimeter_and_outside_corner(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 4, 'y' => 0],
            ['x' => 4, 'y' => 2],
            ['x' => 2, 'y' => 2],
            ['x' => 2, 'y' => 5],
            ['x' => 0, 'y' => 5],
        ];

        $this->assertSame(18.0, $this->service->verticesToPerimeterFeet($vertices, 1.0));
        $this->assertSame(['inside' => 5, 'outside' => 1], $this->service->cornerCounts($vertices));
    }

    public function test_collinear_vertex_is_not_a_corner(): void
    {
        $vertices = [
            ['x' => 0, 'y' => 0],
            ['x' => 5, 'y' => 0],
            ['x' => 10, 'y' => 0],
            ['x' => 10, 'y' => 5],
            ['x' => 0, 'y' => 5],
        ];

        $this->assertSame(['inside' => 4, 'outside' => 0], $this->service->cornerCounts($vertices));
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

    public function test_vertices_to_square_feet_throws_clear_message_on_self_intersecting_polygon(): void
    {
        $bowtie = [
            ['x' => 0, 'y' => 0],
            ['x' => 4, 'y' => 4],
            ['x' => 4, 'y' => 0],
            ['x' => 0, 'y' => 4],
        ];

        try {
            $this->service->verticesToSquareFeet($bowtie, 1.0);
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('crosses itself', $e->getMessage());
        }
    }

    public function test_normalize_ring_treats_collinear_midpoint_as_non_intersecting_rectangle(): void
    {
        $withMidpoint = [
            ['x' => 0, 'y' => 0],
            ['x' => 5, 'y' => 0],
            ['x' => 10, 'y' => 0],
            ['x' => 10, 'y' => 5],
            ['x' => 0, 'y' => 5],
        ];

        $normalized = $this->service->normalizeRing($withMidpoint);
        $this->assertTrue($this->service->isSimplePolygon($normalized));
        $this->assertSame(50.0, $this->service->verticesToSquareFeet($withMidpoint, 1.0));
    }

    public function test_layout_metrics_rejects_self_intersecting_plan(): void
    {
        $bridge = app(\App\Services\OpenPlan3DMeasurementBridge::class);
        $plan = [
            'unit' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 100, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w2', 'start' => ['x' => 100, 'y' => 0], 'end' => ['x' => 100, 'y' => 100], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w3', 'start' => ['x' => 100, 'y' => 100], 'end' => ['x' => 0, 'y' => 100], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => 100], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [[
                    'id' => 'r1',
                    'name' => 'Bowtie',
                    'walls' => ['w1', 'w2', 'w3', 'w4'],
                    'polygon' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 100, 'y' => 100],
                        ['x' => 100, 'y' => 0],
                        ['x' => 0, 'y' => 100],
                    ],
                ]],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('crosses itself');
        $bridge->layoutMetrics($plan, 'r1', ['wall_height_ft' => 8.0]);
    }
}
