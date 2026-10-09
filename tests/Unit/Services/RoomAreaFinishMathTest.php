<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaFinishMath;
use Tests\TestCase;

class RoomAreaFinishMathTest extends TestCase
{
    private RoomAreaFinishMath $math;

    protected function setUp(): void
    {
        parent::setUp();
        $this->math = new RoomAreaFinishMath;
    }

    public function test_ceiling_height_defaults_from_wall_height(): void
    {
        $this->assertSame(8.0, $this->math->ceilingHeightFt(null, 8));
        $this->assertSame(9.0, $this->math->ceilingHeightFt('', 9));
        $this->assertSame(8.5, $this->math->ceilingHeightFt(8.5, 8));
        $this->assertSame(20.0, $this->math->ceilingHeightFt(40, 8));
    }

    public function test_ceiling_area_matches_floor_area(): void
    {
        $this->assertSame(152.0, $this->math->ceilingAreaSqFt(152));
        $this->assertSame(0.0, $this->math->ceilingAreaSqFt(-4));
    }

    public function test_net_wall_area_subtracts_doors_windows_and_openings(): void
    {
        // 12 ft wall, 8 ft ceiling = 96 sf gross.
        // 36x80 door = 20 sf; 36x36 window = 9 sf; 36x96 opening = 24 sf.
        $door = $this->math->openingAreaSqFt(36, 80);
        $window = $this->math->openingAreaSqFt(36, 36);
        $opening = $this->math->openingAreaSqFt(36, 96);
        $this->assertEqualsWithDelta(20.0, $door, 0.01);
        $this->assertEqualsWithDelta(9.0, $window, 0.01);
        $this->assertEqualsWithDelta(24.0, $opening, 0.01);
        $this->assertSame(43.0, $this->math->netWallAreaSqFt(12, 8, $door + $window + $opening));
        $this->assertSame(0.0, $this->math->netWallAreaSqFt(3, 8, 40));
    }

    public function test_per_wall_net_areas_for_rectangle_with_openings(): void
    {
        $canvas = $this->rectangleWithOpenings();
        $walls = $this->math->perWallNetAreas($canvas, 12.0, 8.0);

        $this->assertCount(4, $walls);
        $this->assertSame('W1', $walls[0]['label']);
        $this->assertEqualsWithDelta(12.0, $walls[0]['length_lf'], 0.01);
        $this->assertEqualsWithDelta(10.0, $walls[1]['length_lf'], 0.01);

        $doorDeduct = $this->math->openingAreaSqFt(36, 80);
        $windowDeduct = $this->math->openingAreaSqFt(48, 36);
        $openingDeduct = $this->math->openingAreaSqFt(36, 96);

        $this->assertEqualsWithDelta($doorDeduct, $walls[0]['opening_area_sqft'], 0.01);
        $this->assertEqualsWithDelta(96.0 - $doorDeduct, $walls[0]['net_area_sqft'], 0.01);
        $this->assertEqualsWithDelta($windowDeduct, $walls[1]['opening_area_sqft'], 0.01);
        $this->assertEqualsWithDelta(80.0 - $windowDeduct, $walls[1]['net_area_sqft'], 0.01);
        $this->assertEqualsWithDelta($openingDeduct, $walls[2]['opening_area_sqft'], 0.01);
        $this->assertEqualsWithDelta(96.0 - $openingDeduct, $walls[2]['net_area_sqft'], 0.01);
        $this->assertSame(80.0, $walls[3]['net_area_sqft']);

        $this->assertEqualsWithDelta(
            $walls[0]['net_area_sqft'] + $walls[1]['net_area_sqft'] + $walls[2]['net_area_sqft'] + $walls[3]['net_area_sqft'],
            $this->math->sumNetWallArea($walls),
            0.01
        );
    }

    public function test_flooring_boxes_round_up(): void
    {
        $this->assertSame(6, $this->math->flooringBoxes(100, 10, 20));
        $this->assertSame(6, $this->math->flooringBoxes(100, 10, 19));
        $this->assertSame(1, $this->math->flooringBoxes(20, 0, 20));
        $this->assertSame(2, $this->math->flooringBoxes(20.01, 0, 20));
        $this->assertSame(0, $this->math->flooringBoxes(100, 10, 0));
        $this->assertSame(0, $this->math->flooringBoxes(0, 10, 20));
        $this->assertSame(3, $this->math->flooringBoxes(40, 5, 14));
    }

    public function test_casing_lf_both_sides_for_interior_doors(): void
    {
        // 36x80 door, two sides: 2 * (36 + 160) / 12 = 32.67
        $this->assertEqualsWithDelta(32.67, $this->math->casingLf(36, 80, 2), 0.01);
        $this->assertEqualsWithDelta(16.33, $this->math->casingLf(36, 80, 1), 0.01);
        $this->assertSame(0.0, $this->math->casingLf(36, 80, 0));
        $this->assertSame(2, $this->math->defaultCasingSides('door'));
        $this->assertSame(1, $this->math->defaultCasingSides('window'));
        $this->assertSame(0, $this->math->defaultCasingSides('opening'));
        $this->assertSame(2, $this->math->casingSidesForObject(['type' => 'door']));
        $this->assertSame(1, $this->math->casingSidesForObject(['type' => 'door', 'casing_sides' => 1]));
        $this->assertSame(0, $this->math->casingSidesForObject(['type' => 'window', 'casing_sides' => 0]));
    }

    public function test_total_casing_uses_defaults_and_overrides(): void
    {
        $objects = [
            ['type' => 'door', 'width' => 36],
            ['type' => 'window', 'width' => 48, 'height' => 36],
            ['type' => 'opening', 'width' => 36],
            ['type' => 'door', 'width' => 32, 'height' => 80, 'casing_sides' => 1],
        ];
        $doorTwo = $this->math->casingLf(36, 80, 2);
        $windowOne = $this->math->casingLf(48, 36, 1);
        $doorOne = $this->math->casingLf(32, 80, 1);
        $this->assertEqualsWithDelta($doorTwo + $windowOne + $doorOne, $this->math->totalCasingLf($objects, 8.0), 0.01);
    }

    public function test_finish_metrics_bundle(): void
    {
        $canvas = $this->rectangleWithOpenings();
        $metrics = $this->math->finishMetrics($canvas, 12.0, 8.0, 120.0, [
            'ceiling_height_ft' => 9,
            'flooring_waste_percent' => 10,
            'flooring_direction' => 90,
            'flooring_box_sqft' => 20,
        ]);

        $this->assertSame(9.0, $metrics['ceiling_height_ft']);
        $this->assertSame(120.0, $metrics['ceiling_area_sqft']);
        $this->assertSame(90, $metrics['flooring_direction']);
        $this->assertSame(7, $metrics['flooring_boxes']);
        $this->assertCount(4, $metrics['walls']);
        $this->assertGreaterThan(0, $metrics['net_wall_area_sqft']);
        $this->assertGreaterThan(0, $metrics['casing_lf']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rectangleWithOpenings(): array
    {
        return [
            'v' => 3,
            'groups' => [[
                'id' => 'g1',
                'color' => RoomAreaCanvasData::DEFAULT_GROUP_COLOR,
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
                [
                    'id' => 'obj_3',
                    'type' => 'opening',
                    'subtype' => 'archway',
                    'wall_id' => 'wall_p1_2_3',
                    'width' => 36,
                    'offset' => 24,
                ],
            ],
        ];
    }
}
