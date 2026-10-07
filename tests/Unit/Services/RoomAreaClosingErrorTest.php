<?php

namespace Tests\Unit\Services;

use App\Services\RoomAreaClosingError;
use Tests\TestCase;

class RoomAreaClosingErrorTest extends TestCase
{
    private RoomAreaClosingError $closing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->closing = new RoomAreaClosingError;
    }

    public function test_perfect_rectangle_has_zero_gap(): void
    {
        $walls = [
            ['length_ft' => 12, 'heading_deg' => 0],
            ['length_ft' => 10, 'heading_deg' => 90],
            ['length_ft' => 12, 'heading_deg' => 180],
            ['length_ft' => 10, 'heading_deg' => 270],
        ];

        $result = $this->closing->analyze($walls);

        $this->assertEqualsWithDelta(0.0, $result['gap_inches'], 0.01);
        $this->assertSame('closed', $result['label']);
        $this->assertFalse($result['can_autoclose']);
        $this->assertCount(5, $result['points']);
    }

    public function test_short_last_wall_reports_gap_and_allows_autoclose(): void
    {
        $walls = [
            ['length_ft' => 12, 'heading_deg' => 0],
            ['length_ft' => 10, 'heading_deg' => 90],
            ['length_ft' => 12, 'heading_deg' => 180],
            ['length_ft' => 9.875, 'heading_deg' => 270],
        ];

        $result = $this->closing->analyze($walls);

        $this->assertEqualsWithDelta(1.5, $result['gap_inches'], 0.05);
        $this->assertSame('off by 1½"', $result['label']);
        $this->assertTrue($result['can_autoclose']);
    }

    public function test_large_gap_does_not_offer_autoclose(): void
    {
        $walls = [
            ['length_ft' => 12, 'heading_deg' => 0],
            ['length_ft' => 10, 'heading_deg' => 90],
            ['length_ft' => 12, 'heading_deg' => 180],
        ];

        $result = $this->closing->analyze($walls);

        $this->assertGreaterThan(6, $result['gap_inches']);
        $this->assertFalse($result['can_autoclose']);
        $this->assertStringStartsWith('off by ', $result['label']);
    }

    public function test_distribute_lands_last_point_on_start(): void
    {
        $points = [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => 12.0, 'y' => 0.0],
            ['x' => 12.0, 'y' => 10.0],
            ['x' => 0.0, 'y' => 10.0],
            ['x' => 0.0, 'y' => 0.125],
        ];

        $closed = $this->closing->distribute($points);

        $this->assertCount(4, $closed);
        $this->assertEqualsWithDelta(0.0, $closed[0]['x'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $closed[0]['y'], 0.0001);
        $last = $closed[count($closed) - 1];
        $this->assertGreaterThan(0.01, hypot($last['x'] - $closed[0]['x'], $last['y'] - $closed[0]['y']));
    }

    public function test_auto_square_snaps_near_right_angle(): void
    {
        $walls = [
            ['length_ft' => 10, 'heading_deg' => 2],
            ['length_ft' => 8, 'heading_deg' => 88],
            ['length_ft' => 10, 'heading_deg' => 182],
            ['length_ft' => 8, 'heading_deg' => 272],
        ];

        $result = $this->closing->autoSquare($walls);

        $this->assertSame(0.0, $result['walls'][0]['heading_deg']);
        $this->assertSame(90.0, $result['walls'][1]['heading_deg']);
        $this->assertSame(180.0, $result['walls'][2]['heading_deg']);
        $this->assertSame(270.0, $result['walls'][3]['heading_deg']);
        $this->assertCount(4, $result['points']);
    }

    public function test_next_heading_turns_right_left_and_custom(): void
    {
        $this->assertSame(90.0, $this->closing->nextHeading(0, 'right'));
        $this->assertSame(270.0, $this->closing->nextHeading(0, 'left'));
        $this->assertSame(45.0, $this->closing->nextHeading(0, 'custom', 45));
    }
}
