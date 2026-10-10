<?php

namespace Tests\Unit\Services;

use App\Models\RoomAreaRoom;
use App\Services\RoomAreaPlanSvg;
use Tests\TestCase;

class RoomAreaPlanSvgTest extends TestCase
{
    public function test_labels_a_twelve_by_ten_room_in_feet_and_inches(): void
    {
        $svg = new RoomAreaPlanSvg;
        $room = new RoomAreaRoom([
            'name' => 'Kitchen',
            'scale_pixels_per_foot' => 12,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
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
            ],
        ]);

        $this->assertSame(['12\'-0"', '10\'-0"', '12\'-0"', '10\'-0"'], $svg->edgeLabels($room));
        $markup = $svg->forRoom($room);
        $this->assertStringContainsString('<svg', $markup);
        $this->assertStringContainsString('12\'-0"', $markup);
        $this->assertStringContainsString('10\'-0"', $markup);
        $this->assertStringContainsString('#024768', $markup);
    }
}
