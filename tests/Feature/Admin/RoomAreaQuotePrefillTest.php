<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaQuotePrefillTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_flooring_wizard_prefills_area_and_baseboard_from_room(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->get(route('admin.quotations.flooring.create', [
            'room_area_import_id' => 'room:'.$room->id,
        ]))
            ->assertOk()
            ->assertSee('name="square_footage"', false)
            ->assertSee('value="80"', false)
            ->assertSee('name="baseboard_linear_ft"', false)
            ->assertSee('value="30"', false)
            ->assertSee('Measurements filled from Room Area', false)
            ->assertSee('Kitchen', false);
    }

    public function test_painting_wizard_prefills_walls_plus_ceiling(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();
        $metrics = $room->layoutMetrics();
        $expected = rtrim(rtrim(number_format($metrics['wall_area_sqft'] + $metrics['area_sqft'], 2, '.', ''), '0'), '.');

        $this->get(route('admin.quotations.painting.create', [
            'room_area_import_id' => 'room:'.$room->id,
        ]))
            ->assertOk()
            ->assertSee('name="total_property_sqf"', false)
            ->assertSee('value="'.$expected.'"', false)
            ->assertSee('Measurements filled from Room Area', false);
    }

    public function test_trimming_wizard_enables_floor_wall_from_room(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $this->get(route('admin.quotations.trimming.create', [
            'room_area_import_id' => 'room:'.$room->id,
        ]))
            ->assertOk()
            ->assertSee('name="floor_lf"', false)
            ->assertSee('value="30"', false)
            ->assertSee('value="floor_wall"', false);
    }

    private function makeRoom(): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Prefill project',
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
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
                'objects' => [
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
                ],
            ],
        ]);
    }
}
