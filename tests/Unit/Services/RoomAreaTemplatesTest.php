<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaTemplates;
use Tests\TestCase;

class RoomAreaTemplatesTest extends TestCase
{
    private RoomAreaTemplates $templates;

    private RoomAreaCanvasData $canvas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->templates = new RoomAreaTemplates;
        $this->canvas = new RoomAreaCanvasData;
    }

    public function test_rectangle_12_by_10_matches_batch1_math_fixture(): void
    {
        $feet = $this->templates->rectangle(12, 10);
        $metrics = $this->metricsFromFeet($feet);

        $this->assertEqualsWithDelta(120.0, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(44.0, $metrics['perimeter_lf'], 0.01);
        $this->assertEqualsWithDelta(44.0, $metrics['baseboard_lf'], 0.01);
        $this->assertEqualsWithDelta(352.0, $metrics['wall_area_sqft'], 0.01);
    }

    public function test_l_16_by_12_return_6_by_4_matches_batch1_math_fixture(): void
    {
        $feet = $this->templates->lShape(16, 12, 6, 4);
        $metrics = $this->metricsFromFeet($feet);

        $this->assertCount(6, $feet);
        $this->assertEqualsWithDelta(152.0, $metrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(56.0, $metrics['perimeter_lf'], 0.01);
        $this->assertEqualsWithDelta(56.0, $metrics['baseboard_lf'], 0.01);
        $this->assertEqualsWithDelta(448.0, $metrics['wall_area_sqft'], 0.01);
    }

    public function test_u_and_t_shapes_are_closed_simple_polygons(): void
    {
        $u = $this->templates->uShape(16, 12, 8, 8);
        $t = $this->templates->tShape(16, 4, 6, 8);

        $this->assertCount(8, $u);
        $this->assertCount(8, $t);

        $uMetrics = $this->metricsFromFeet($u);
        $tMetrics = $this->metricsFromFeet($t);

        $this->assertEqualsWithDelta(16 * 12 - 8 * 8, $uMetrics['area_sqft'], 0.01);
        $this->assertEqualsWithDelta(16 * 4 + 6 * 8, $tMetrics['area_sqft'], 0.01);
        $this->assertGreaterThan(0, $uMetrics['perimeter_lf']);
        $this->assertGreaterThan(0, $tMetrics['perimeter_lf']);
    }

    public function test_alcove_inserts_four_vertices_on_a_rectangle(): void
    {
        $rect = $this->templates->rectangle(12, 10);
        $withAlcove = $this->templates->insertAlcove($rect, 0, 4, 2, true);

        $this->assertCount(8, $withAlcove);

        $before = $this->metricsFromFeet($rect);
        $after = $this->metricsFromFeet($withAlcove);
        $this->assertEqualsWithDelta($before['area_sqft'] + 8.0, $after['area_sqft'], 0.05);
        $this->assertGreaterThan($before['perimeter_lf'], $after['perimeter_lf']);
    }

    public function test_l_from_overall_keeps_6_by_4_return(): void
    {
        $feet = $this->templates->fromKind('l', 16, 12);
        $metrics = $this->metricsFromFeet($feet);
        $this->assertEqualsWithDelta(152.0, $metrics['area_sqft'], 0.01);
    }

    public function test_t_from_overall_uses_typed_length(): void
    {
        $feet = $this->templates->fromKind('t', 16, 12);
        $maxY = max(array_map(static fn (array $p) => $p['y'], $feet));
        $this->assertEqualsWithDelta(12.0, $maxY, 0.01);
        $this->assertEqualsWithDelta(16 * 4 + 6 * 8, $this->metricsFromFeet($feet)['area_sqft'], 0.01);
    }

    public function test_alcove_on_u_l_t_inner_walls_adds_area(): void
    {
        $u = $this->templates->uShape(16, 12, 8, 8);
        $l = $this->templates->lFromOverall(16, 12);
        $t = $this->templates->tFromOverall(16, 12);

        $this->assertEqualsWithDelta(8.0, $this->alcoveDelta($u, 4, 4, 2), 0.05);
        $this->assertEqualsWithDelta(8.0, $this->alcoveDelta($u, 3, 4, 2), 0.05);
        $this->assertEqualsWithDelta(8.0, $this->alcoveDelta($u, 5, 4, 2), 0.05);
        $this->assertEqualsWithDelta(7.2, $this->alcoveDelta($l, 3, 4, 2), 0.05);
        $this->assertEqualsWithDelta(8.0, $this->alcoveDelta($t, 2, 4, 2), 0.05);
        $this->assertEqualsWithDelta(8.0, $this->alcoveDelta($t, 6, 4, 2), 0.05);
    }

    /**
     * @param  list<array{x: float, y: float}>  $feet
     */
    private function alcoveDelta(array $feet, int $wall, float $width, float $depth): float
    {
        $before = $this->metricsFromFeet($feet)['area_sqft'];
        $after = $this->metricsFromFeet($this->templates->insertAlcove($feet, $wall, $width, $depth, true))['area_sqft'];

        return $after - $before;
    }

    /**
     * @param  list<array{x: float, y: float}>  $feet
     * @return array<string, mixed>
     */
    private function metricsFromFeet(array $feet): array
    {
        $ppf = 12.0;
        $canvas = [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => array_map(static function (array $pt) use ($ppf) {
                        return ['x' => $pt['x'] * $ppf, 'y' => $pt['y'] * $ppf];
                    }, $feet),
                ]],
            ]],
            'objects' => [],
        ];

        return $this->canvas->layoutMetrics($canvas, $ppf, 8.0);
    }
}
