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

    public function test_layout_search_exposes_bulkhead_and_partition_for_basement_import(): void
    {
        $this->actingAsAdmin();

        $cm = 30.48;
        $project = RoomAreaProject::create([
            'name' => 'Bulkhead partition project',
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Living',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 600,
            'wall_height_ft' => 8,
            'vertices' => [
                'format' => 'openplan3d',
                'units' => 'cm',
                'floor' => [
                    'walls' => [
                        ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w2', 'start' => ['x' => 30 * $cm, 'y' => 0], 'end' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w3', 'start' => ['x' => 30 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 20 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'w4', 'start' => ['x' => 0, 'y' => 20 * $cm], 'end' => ['x' => 0, 'y' => 0], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p1', 'start' => ['x' => 14 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 14 * $cm, 'y' => 8 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                        ['id' => 'p2', 'start' => ['x' => 22 * $cm, 'y' => 20 * $cm], 'end' => ['x' => 22 * $cm, 'y' => 12 * $cm], 'thickness' => 10, 'height' => 8 * $cm],
                    ],
                    'rooms' => [[
                        'id' => 'r1',
                        'name' => 'Living',
                        'walls' => ['w1', 'w2', 'w3', 'w4', 'p1', 'p2'],
                    ]],
                ],
                'zanini' => [
                    'bulkheads' => [[
                        'rect' => [
                            'x1' => 0,
                            'y1' => 0,
                            'x2' => 10 * $cm,
                            'y2' => 1 * $cm,
                        ],
                        'drop_height_ft' => 2.0,
                        'against_wall' => true,
                    ]],
                ],
            ],
        ]);

        $this->get(route('admin.utilities.room-area.layouts', ['q' => 'Living']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'room:'.$room->id,
                'partition_lf' => 20,
                'bulkhead_lf' => 27,
                'bulkhead_framing_lf' => 27,
                'bulkhead_drywall_sqft' => 30,
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
            ->assertSee('name="drywall_door_openings"', false)
            ->assertSee('name="drywall_sheet_size"', false)
            ->assertSee('Opening area', false);
    }
}
