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

    public function test_waste_cleared_field_defaults_and_parses_percent_and_comma(): void
    {
        $this->assertSame(10.0, $this->math->normalizeWastePercent(null));
        $this->assertSame(10.0, $this->math->normalizeWastePercent(''));
        $this->assertSame(10.0, $this->math->normalizeWastePercent('   '));
        $this->assertSame(15.0, $this->math->normalizeWastePercent('15%'));
        $this->assertSame(7.5, $this->math->normalizeWastePercent('7,5'));
        $this->assertSame(75.0, $this->math->normalizeWastePercent(75));
        $this->assertSame(100.0, $this->math->normalizeWastePercent(100));
        $this->assertSame(100.0, $this->math->normalizeWastePercent(150));
        $this->assertFalse($this->math->parseWastePercent('abc')['ok']);
        $this->assertSame('Enter waste as a percent, like 10 or 15%.', $this->math->parseWastePercent('abc')['error']);
        $this->assertFalse($this->math->parseWastePercent(-5)['ok']);
    }

    public function test_box_below_minimum_is_zero_not_snapped(): void
    {
        $this->assertSame(0.0, $this->math->normalizeBoxSqFt(0.1));
        $this->assertSame(0.25, $this->math->normalizeBoxSqFt(0.25));
        $this->assertSame(0, $this->math->flooringBoxes(100, 10, 0.1));
        $this->assertSame(1, $this->math->flooringBoxes(0.25, 0, 0.25));
    }

    public function test_net_wall_area_subtracts_doors_windows_and_openings(): void
    {
        $door = $this->math->openingAreaSqFt(36, 80);
        $window = $this->math->openingAreaSqFt(36, 36);
        $opening = $this->math->openingAreaSqFt(36, 96);
        $this->assertEqualsWithDelta(20.0, $door, 0.01);
        $this->assertEqualsWithDelta(9.0, $window, 0.01);
        $this->assertEqualsWithDelta(24.0, $opening, 0.01);
        $this->assertSame(43.0, $this->math->netWallAreaSqFt(12, 8, $door + $window + $opening));
        $this->assertSame(0.0, $this->math->netWallAreaSqFt(3, 8, 40));
    }

    public function test_per_wall_net_areas_use_ceiling_height_exactly(): void
    {
        $canvas = $this->rectangleWithOpenings();
        $atEight = $this->math->perWallNetAreas($canvas, 12.0, 8.0);
        $atNine = $this->math->perWallNetAreas($canvas, 12.0, 9.0);

        $this->assertCount(4, $atEight);
        $this->assertSame('W1', $atEight[0]['label']);
        $this->assertEqualsWithDelta(12.0, $atEight[0]['length_lf'], 0.01);
        $this->assertEqualsWithDelta(10.0, $atEight[1]['length_lf'], 0.01);

        $doorDeduct = $this->math->openingAreaSqFt(36, 80);
        $windowDeduct = $this->math->openingAreaSqFt(48, 36);
        $openingDeduct = $this->math->openingAreaSqFt(36, 96);

        $this->assertSame(76.0, $atEight[0]['net_area_sqft']);
        $this->assertSame(68.0, $atEight[1]['net_area_sqft']);
        $this->assertSame(72.0, $atEight[2]['net_area_sqft']);
        $this->assertSame(80.0, $atEight[3]['net_area_sqft']);
        $this->assertSame(296.0, $this->math->sumNetWallArea($atEight));

        $this->assertSame(88.0, $atNine[0]['net_area_sqft']);
        $this->assertSame(78.0, $atNine[1]['net_area_sqft']);
        $this->assertSame(81.0, $atNine[2]['net_area_sqft']);
        $this->assertSame(90.0, $atNine[3]['net_area_sqft']);
        $this->assertSame(337.0, $this->math->sumNetWallArea($atNine));

        $this->assertEqualsWithDelta($doorDeduct, $atEight[0]['opening_area_sqft'], 0.01);
        $this->assertEqualsWithDelta($windowDeduct, $atEight[1]['opening_area_sqft'], 0.01);
        $this->assertEqualsWithDelta($openingDeduct, $atEight[2]['opening_area_sqft'], 0.01);
    }

    public function test_overlapping_openings_are_not_double_subtracted(): void
    {
        $canvas = $this->rectangleWithOpenings();
        $canvas['objects'] = [
            [
                'type' => 'door',
                'wall_id' => 'wall_p1_0_1',
                'width' => 36,
                'offset' => 12,
                'height' => 80,
            ],
            [
                'type' => 'door',
                'wall_id' => 'wall_p1_0_1',
                'width' => 36,
                'offset' => 36,
                'height' => 80,
            ],
        ];
        $walls = $this->math->perWallNetAreas($canvas, 12.0, 8.0);
        $union = $this->math->unionOpeningAreaSqFt($canvas['objects'], 8.0);
        $this->assertEqualsWithDelta(33.33, $union, 0.01);
        $this->assertNotEquals(40.0, $union);
        $this->assertSame(62.67, $walls[0]['net_area_sqft']);
    }

    public function test_opening_height_is_clamped_to_ceiling(): void
    {
        $this->assertSame(96.0, $this->math->objectHeightInches(['type' => 'opening', 'height' => 120], 'opening', 8.0));
        $this->assertSame(80.0, $this->math->objectHeightInches(['type' => 'door', 'height' => 80], 'door', 8.0));
        $this->assertSame(24.0, $this->math->unionOpeningAreaSqFt([
            ['type' => 'opening', 'width' => 36, 'height' => 120, 'offset' => 12],
        ], 8.0));
    }

    public function test_flooring_boxes_round_up_without_nudge(): void
    {
        $this->assertSame(6, $this->math->flooringBoxes(100, 10, 20));
        $this->assertSame(6, $this->math->flooringBoxes(100, 10, 19));
        $this->assertSame(1, $this->math->flooringBoxes(20, 0, 20));
        $this->assertSame(2, $this->math->flooringBoxes(20.01, 0, 20));
        $this->assertSame(0, $this->math->flooringBoxes(100, 10, 0));
        $this->assertSame(0, $this->math->flooringBoxes(0, 10, 20));
        $this->assertSame(3, $this->math->flooringBoxes(40, 5, 14));
        $this->assertSame(110.0, $this->math->flooringWithWasteSqFt(100, 10));
    }

    public function test_window_casing_is_four_sides_by_default(): void
    {
        $this->assertSame(12.0, $this->math->casingLf(36, 36, 4, 'window'));
        $this->assertSame(9.0, $this->math->casingLf(36, 36, 3, 'window'));
        $this->assertSame(0.0, $this->math->casingLf(36, 36, 2, 'window'));
        $this->assertSame(4, $this->math->defaultCasingSides('window'));
        $this->assertSame(4, $this->math->casingSidesForObject(['type' => 'WINDOW']));
        $this->assertSame(3, $this->math->casingSidesForObject(['type' => 'window', 'casing_sides' => 3]));
        $this->assertSame(4, $this->math->casingSidesForObject(['type' => 'window', 'casing_sides' => '']));
    }

    public function test_door_interior_exterior_and_cased_openings(): void
    {
        $this->assertEqualsWithDelta(32.67, $this->math->casingLf(36, 80, 2, 'door'), 0.01);
        $this->assertEqualsWithDelta(16.33, $this->math->casingLf(36, 80, 1, 'door'), 0.01);
        $this->assertSame(0.0, $this->math->casingLf(36, 80, 0, 'door'));
        $this->assertSame(2, $this->math->defaultCasingSides('door'));
        $this->assertSame(1, $this->math->defaultCasingSides('door', 'exterior'));
        $this->assertSame(2, $this->math->defaultCasingSides('opening'));
        $this->assertSame(2, $this->math->casingSidesForObject(['type' => 'door']));
        $this->assertSame(1, $this->math->casingSidesForObject(['type' => 'door', 'door_face' => 'exterior']));
        $this->assertSame(1, $this->math->casingSidesForObject(['type' => 'door', 'casing_sides' => 1]));
        $this->assertSame(2, $this->math->casingSidesForObject(['type' => 'opening']));
        $this->assertSame(0, $this->math->casingSidesForObject(['type' => 'opening', 'casing_sides' => 0]));
    }

    public function test_total_casing_uses_defaults_and_includes_openings(): void
    {
        $objects = [
            ['type' => 'door', 'width' => 36],
            ['type' => 'window', 'width' => 36, 'height' => 36],
            ['type' => 'opening', 'width' => 36],
            ['type' => 'door', 'width' => 32, 'height' => 80, 'casing_sides' => 1],
        ];
        $doorTwo = $this->math->casingLf(36, 80, 2, 'door');
        $windowFour = $this->math->casingLf(36, 36, 4, 'window');
        $openingTwo = $this->math->casingLf(36, 96, 2, 'opening');
        $doorOne = $this->math->casingLf(32, 80, 1, 'door');
        $this->assertEqualsWithDelta(
            $doorTwo + $windowFour + $openingTwo + $doorOne,
            $this->math->totalCasingLf($objects, 8.0),
            0.01
        );
        $this->assertSame(12.0, $this->math->casingLf(36, 36, 4, 'window'));
    }

    public function test_finish_metrics_bundle_uses_ceiling_for_net_walls(): void
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
        $this->assertSame(132.0, $metrics['flooring_with_waste_sqft']);
        $this->assertCount(4, $metrics['walls']);
        $this->assertSame(337.0, $metrics['net_wall_area_sqft']);
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
