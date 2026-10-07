<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementRoomAreaImportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_layout_search_fills_openings_and_baseboard_from_room_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Import project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rec room',
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

        $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Rec']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'area_sqft' => 80,
                'perimeter_lf' => 36,
                'baseboard_lf' => 30,
                'door_count' => 1,
                'window_count' => 1,
                'opening_count' => 1,
                'drywall_openings' => 3,
            ]);
    }

    public function test_drywall_step_exposes_openings_and_baseboard_fields(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->assertSee('id="br-dw-open"', false)
            ->assertSee('name="drywall_openings"', false)
            ->assertSee('id="br-base"', false)
            ->assertSee('name="baseboard_lf"', false)
            ->assertSee('Doors, windows, and openings fill the openings count', false);
    }
}
