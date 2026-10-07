<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaQuoteQuantities;
use Tests\TestCase;

class RoomAreaQuoteQuantitiesTest extends TestCase
{
    private RoomAreaQuoteQuantities $mapper;

    private RoomAreaCanvasData $canvas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new RoomAreaQuoteQuantities;
        $this->canvas = new RoomAreaCanvasData;
    }

    public function test_applies_waste_and_rounds_up_to_two_decimals(): void
    {
        $this->assertSame(88.0, $this->mapper->applyWaste(80, 10));
        $this->assertSame(36.67, $this->mapper->applyWaste(33.33, 10));
        $this->assertSame(10.0, $this->mapper->applyWaste(10, 0));
        $this->assertSame(0.0, $this->mapper->applyWaste(0, 15));
    }

    public function test_v2_room_without_objects_keeps_full_perimeter_and_wall_area(): void
    {
        $metrics = $this->canvas->layoutMetrics($this->rectangleCanvas(false), 12, 8);

        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(36.0, $metrics['perimeter_lf']);
        $this->assertSame(36.0, $metrics['baseboard_lf']);
        $this->assertSame(288.0, $metrics['wall_area_sqft']);
        $this->assertSame(0, $metrics['door_count']);
        $this->assertSame(0, $metrics['window_count']);

        $flooring = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_FLOORING, $metrics, 10);
        $this->assertSame(88.0, $flooring['qty']);
        $this->assertSame('sq ft', $flooring['unit']);

        $baseboard = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_BASEBOARD, $metrics, 0);
        $this->assertSame(36.0, $baseboard['qty']);
        $this->assertSame('linear ft', $baseboard['unit']);

        $paintCeiling = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_PAINT_CEILING, $metrics, 0);
        $this->assertSame(80.0, $paintCeiling['qty']);

        $drywallWalls = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_DRYWALL_WALLS, $metrics, 10);
        $this->assertSame(316.8, $drywallWalls['qty']);
    }

    public function test_v3_objects_reduce_baseboard_and_wall_area_before_waste(): void
    {
        $metrics = $this->canvas->layoutMetrics($this->rectangleCanvas(true), 12, 8);

        $this->assertSame(80.0, $metrics['area_sqft']);
        $this->assertSame(30.0, $metrics['baseboard_lf']);
        $this->assertLessThan($metrics['perimeter_lf'] * $metrics['wall_height_ft'], $metrics['wall_area_sqft']);

        $baseboard = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_BASEBOARD, $metrics, 0);
        $this->assertSame(30.0, $baseboard['qty']);

        $paintWalls = $this->mapper->line(RoomAreaQuoteQuantities::TRADE_PAINT_WALLS, $metrics, 0);
        $this->assertSame($metrics['wall_area_sqft'], $paintWalls['qty']);
    }

    /**
     * 10 ft × 8 ft rectangle at 12 px/ft.
     *
     * @return array<string, mixed>
     */
    private function rectangleCanvas(bool $withObjects): array
    {
        $canvas = [
            'v' => $withObjects ? 3 : 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 120, 'y' => 0],
                        ['x' => 120, 'y' => 96],
                        ['x' => 0, 'y' => 96],
                    ],
                ]],
            ]],
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
}
