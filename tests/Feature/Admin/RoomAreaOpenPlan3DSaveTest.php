<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\User;
use App\Services\OpenPlan3DMeasurementBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAreaOpenPlan3DSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private RoomAreaProject $project;

    private OpenPlan3DMeasurementBridge $bridge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->project = RoomAreaProject::create([
            'name' => 'Save Test Project',
            'scale_pixels_per_foot' => 20.0,
            'wall_height_ft' => 8.0,
        ]);
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
    }

    private function sampleOpenPlan(float $widthFt = 10.0, float $lengthFt = 12.0): array
    {
        // Convert to cm
        $wCm = $widthFt * 30.48;
        $lCm = $lengthFt * 30.48;

        return [
            'unit' => 'cm',
            'version' => '1.0',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $wCm, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w2', 'start' => ['x' => $wCm, 'y' => 0], 'end' => ['x' => $wCm, 'y' => $lCm], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w3', 'start' => ['x' => $wCm, 'y' => $lCm], 'end' => ['x' => 0, 'y' => $lCm], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => $lCm], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [
                    [
                        'id' => 'r1',
                        'name' => 'Main Room',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $wCm, 'y' => 0],
                            ['x' => $wCm, 'y' => $lCm],
                            ['x' => 0, 'y' => $lCm],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_stores_room_with_openplan3d_plan(): void
    {
        $plan = $this->sampleOpenPlan(10.0, 15.0); // 150 sqft

        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'OpenPlan Room',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('room.name', 'OpenPlan Room');
        $response->assertJsonPath('room.is_openplan3d', true);
        $this->assertEquals(150.0, $response->json('room.area_sqft'));

        $this->assertDatabaseHas('room_area_rooms', [
            'name' => 'OpenPlan Room',
            'room_area_project_id' => $this->project->id,
            'area_sqft' => 150.0,
        ]);
    }

    public function test_updates_legacy_room_with_openplan_and_backs_up_legacy_canvas(): void
    {
        $legacyCanvas = [
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0.0, 'y' => 0.0],
                                ['x' => 200.0, 'y' => 0.0],
                                ['x' => 200.0, 'y' => 200.0],
                                ['x' => 0.0, 'y' => 200.0],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Original Legacy Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 100.0,
            'vertices' => $legacyCanvas,
        ]);

        $newPlan = $this->sampleOpenPlan(12.0, 12.0); // 144 sqft

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => 'Updated to OpenPlan',
                'plan' => $newPlan,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('room.name', 'Updated to OpenPlan');
        $response->assertJsonPath('room.is_openplan3d', true);
        $this->assertEquals(144.0, $response->json('room.area_sqft'));

        $room->refresh();
        $this->assertNotNull($room->legacy_canvas_data);
        $this->assertEquals($legacyCanvas, $room->legacy_canvas_data);
        $this->assertTrue($this->bridge->isPlan($room->vertices));
        $this->assertEquals(144.0, $room->area_sqft);
    }

    public function test_rejects_empty_plan_with_422(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'Invalid Room',
                'plan' => [
                    'floor' => [
                        'walls' => [],
                        'rooms' => [],
                    ],
                ],
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
    }

    public function test_renders_openplan3d_editor_view_with_toolbar_metrics_and_hotkey_save(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('admin.utilities.room-area', ['engine' => 'openplan3d'])
        );

        $response->assertOk();
        $response->assertSee('data-room-area-openplan3d', false);
        $response->assertSee('id="openplan3d-save-btn"', false);
        $response->assertSee('id="openplan3d-noscale-warning"', false);
        $response->assertSee('id="openplan3d-geometry-warning"', false);
        $response->assertSee('data-hotkey-save', false);
        $response->assertSee('data-ra-save-room', false);
        $response->assertSee('id="openplan3d-metrics-ribbon"', false);
        $response->assertSee('id="openplan3d-settings-drawer"', false);
        $response->assertSee('openplan3d-measurement-bridge.js', false);
        $response->assertSee('openplan3d-host.js', false);
        $response->assertSee('id="openplan3d-modal"', false);
        $response->assertSee('Link to a project');
        $response->assertSee('Keep standalone');
        $response->assertDontSee('data-ra-help-guide', false);
    }

    public function test_renders_client_project_openplan3d_editor_view(): void
    {
        $clientProject = \App\Models\ClientProject::factory()->create([
            'name' => 'CP Test OpenPlan',
        ]);

        $response = $this->actingAs($this->user)->get(
            route('admin.client-projects.utilities.room-area', [
                'client_project' => $clientProject,
                'engine' => 'openplan3d',
            ])
        );

        $response->assertOk();
        $response->assertSee('data-room-area-openplan3d', false);
        $response->assertSee('id="openplan3d-save-btn"', false);
        $response->assertSee('data-hotkey-save', false);
        $response->assertSee('data-ra-save-room', false);
        $response->assertSee('openplan3d-measurement-bridge.js', false);
        $response->assertSee('openplan3d-host.js', false);
        $response->assertSee('id="openplan3d-modal"', false);
        $response->assertDontSee('data-ra-help-guide', false);
        $response->assertSee('data-bound-client-project="'.$clientProject->id.'"', false);
    }

    public function test_stores_two_room_plan_using_whole_plan_area(): void
    {
        $plan = $this->twoRoomPlan();

        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'Whole Floor',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $this->assertEquals(200.0, $response->json('room.area_sqft'));
        $this->assertDatabaseHas('room_area_rooms', [
            'name' => 'Whole Floor',
            'room_area_project_id' => $this->project->id,
            'area_sqft' => 200.0,
        ]);
    }

    public function test_rejects_zero_area_overwrite_of_positive_room_without_confirm(): void
    {
        $room = $this->storeSampleRoom(150.0);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => $room->name,
                'plan' => $this->zeroAreaPlan(),
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('code', 'zero_area_overwrite');

        $room->refresh();
        $this->assertEquals(150.0, (float) $room->area_sqft);
        $this->assertTrue($this->bridge->isPlan($room->vertices));
    }

    public function test_allows_zero_area_overwrite_when_confirm_flag_is_sent(): void
    {
        $room = $this->storeSampleRoom(150.0);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => $room->name,
                'plan' => $this->zeroAreaPlan(),
                'confirm_zero_area' => true,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $room->refresh();
        $this->assertEquals(0.0, (float) $room->area_sqft);
    }

    public function test_confirm_zero_area_still_rejects_plan_with_no_room_or_wall(): void
    {
        $room = $this->storeSampleRoom(150.0);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => $room->name,
                'plan' => [
                    'format' => 'openplan3d',
                    'units' => 'cm',
                    'floor' => [
                        'walls' => [],
                        'rooms' => [],
                    ],
                ],
                'confirm_zero_area' => true,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('at least one closed room or wall', $response->getContent());

        $room->refresh();
        $this->assertEquals(150.0, (float) $room->area_sqft);
        $this->assertTrue($this->bridge->isPlan($room->vertices));
    }

    public function test_empty_room_object_without_polygon_is_rejected_even_with_confirm(): void
    {
        $room = $this->storeSampleRoom(150.0);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => $room->name,
                'plan' => [
                    'format' => 'openplan3d',
                    'units' => 'cm',
                    'floor' => [
                        'walls' => [],
                        'rooms' => [(object) []],
                    ],
                ],
                'confirm_zero_area' => true,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('at least one closed room or wall', $response->getContent());

        $room->refresh();
        $this->assertEquals(150.0, (float) $room->area_sqft);
        $this->assertTrue($this->bridge->isPlan($room->vertices));
    }

    public function test_three_point_room_with_no_walls_is_accepted(): void
    {
        $plan = [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'walls' => [],
                'rooms' => [
                    [
                        'id' => 'tri',
                        'name' => 'Triangle',
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 304.8, 'y' => 0],
                            ['x' => 0, 'y' => 304.8],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'Triangle Room',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertDatabaseHas('room_area_rooms', [
            'name' => 'Triangle Room',
            'room_area_project_id' => $this->project->id,
        ]);
    }

    public function test_rejects_self_intersecting_plan_with_422(): void
    {
        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $plan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];

        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Bowtie', 'plan' => $plan]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('crosses itself', $response->getContent());
        $this->assertDatabaseMissing('room_area_rooms', ['name' => 'Bowtie']);
    }

    public function test_update_unchanged_self_intersecting_plan_returns_422_not_500(): void
    {
        $bowtiePoly = [
            ['x' => 0, 'y' => 0],
            ['x' => 304.8, 'y' => 304.8],
            ['x' => 304.8, 'y' => 0],
            ['x' => 0, 'y' => 304.8],
        ];
        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $plan['floor']['rooms'][0]['polygon'] = $bowtiePoly;

        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Basement',
            'vertices' => $plan,
            'area_sqft' => 0.0,
            'wall_height_ft' => 8.0,
        ]);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => 'Basement',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('crosses itself', $response->getContent());
    }

    public function test_update_accepts_collinear_midpoint_on_rectangle_edge(): void
    {
        $wCm = 10.0 * 30.48;
        $lCm = 12.0 * 30.48;
        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $plan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => $wCm / 2, 'y' => 0],
            ['x' => $wCm, 'y' => 0],
            ['x' => $wCm, 'y' => $lCm],
            ['x' => 0, 'y' => $lCm],
        ];

        $room = $this->storeSampleRoom(120.0, $this->sampleOpenPlan(10.0, 12.0));

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => 'OpenPlan Room',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertEquals(120.0, $response->json('room.area_sqft'));
    }

    public function test_rejects_nan_and_impossible_sizes_with_422(): void
    {
        $nan = $this->sampleOpenPlan();
        $nan['floor']['walls'][0]['start']['x'] = 'NaN';
        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'NaN Room', 'plan' => $nan]
        )->assertStatus(422)->assertJsonPath('ok', false);

        $huge = $this->sampleOpenPlan(20000, 20000);
        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Huge Room', 'plan' => $huge]
        )->assertStatus(422)->assertJsonPath('ok', false);

        $this->assertDatabaseMissing('room_area_rooms', ['name' => 'NaN Room']);
        $this->assertDatabaseMissing('room_area_rooms', ['name' => 'Huge Room']);
    }

    public function test_rejects_non_plan_arrays_in_store_and_update_via_bridge_is_plan(): void
    {
        $nonPlan = ['random' => 'data', 'walls' => 'not-an-array'];

        // Store
        $storeResponse = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Non Plan Room', 'plan' => $nonPlan]
        );
        $storeResponse->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'The provided plan data is not a valid OpenPlan3D model.');
        $this->assertDatabaseMissing('room_area_rooms', ['name' => 'Non Plan Room']);

        // Update
        $existingRoom = $this->storeSampleRoom(150.0);
        $updateResponse = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $existingRoom),
            ['name' => 'Updated Room', 'plan' => $nonPlan]
        );
        $updateResponse->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'The provided plan data is not a valid OpenPlan3D model.');
    }

    public function test_rejects_real_plan_with_unsupported_unit_and_accepts_whitelisted_units(): void
    {
        // Unsupported unit on store -> 422 from validator
        $invalidUnitPlan = $this->sampleOpenPlan(10.0, 12.0);
        $invalidUnitPlan['unit'] = 'yards';

        $invalidResponse = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Yards Room', 'plan' => $invalidUnitPlan]
        );
        $invalidResponse->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Invalid plan unit. Allowed units are cm, m, mm, in, ft.');
        $this->assertDatabaseMissing('room_area_rooms', ['name' => 'Yards Room']);

        // Whitelisted unit 'mm' on store -> succeeds
        $mmPlan = $this->sampleOpenPlan(10.0, 12.0);
        $mmPlan['unit'] = 'mm';
        // Convert cm to mm (* 10)
        foreach ($mmPlan['floor']['walls'] as &$w) {
            $w['start']['x'] *= 10;
            $w['start']['y'] *= 10;
            $w['end']['x'] *= 10;
            $w['end']['y'] *= 10;
            $w['height'] *= 10;
            $w['thickness'] *= 10;
        }
        unset($w);
        foreach ($mmPlan['floor']['rooms'][0]['polygon'] as &$pt) {
            $pt['x'] *= 10;
            $pt['y'] *= 10;
        }
        unset($pt);

        $validResponse = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'MM Room', 'plan' => $mmPlan]
        );
        $validResponse->assertStatus(200)->assertJsonPath('ok', true);
        $this->assertDatabaseHas('room_area_rooms', ['name' => 'MM Room']);
    }

    public function test_collinear_three_point_room_names_room_in_error(): void
    {
        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $wCm = 10.0 * 30.48;
        $plan['floor']['rooms'][0]['name'] = 'Hallway strip';
        $plan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => $wCm / 2, 'y' => 0],
            ['x' => $wCm, 'y' => 0],
        ];

        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Strip', 'plan' => $plan]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('ok', false);
        $this->assertStringContainsString('Hallway strip', $response->getContent());
        $this->assertStringContainsString('non-collinear', $response->getContent());
    }

    public function test_get_room_area_payload_survives_stored_bowtie_room(): void
    {
        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $plan['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 10.0 * 30.48, 'y' => 12.0 * 30.48],
            ['x' => 10.0 * 30.48, 'y' => 0],
            ['x' => 0, 'y' => 12.0 * 30.48],
        ];
        $this->storeSampleRoom(120.0, $this->sampleOpenPlan(10.0, 12.0));
        RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Broken Bowtie',
            'vertices' => $plan,
            'area_sqft' => 0.0,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.utilities.room-area'));
        $response->assertOk();
        $response->assertSee('Broken Bowtie', false);
        $response->assertSee('geometry_unmeasurable', false);
        $response->assertSee('id="openplan3d-geometry-warning"', false);
    }

    public function test_get_room_area_payload_survives_stored_canvas_v2_bowtie(): void
    {
        RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Canvas Bowtie',
            'vertices' => $this->bowtieCanvasV2(),
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 99.0,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.utilities.room-area'));
        $response->assertOk();
        $response->assertSee('Canvas Bowtie', false);
        $response->assertSee('geometry_unmeasurable', false);
        $response->assertSee('id="openplan3d-geometry-warning"', false);
    }

    public function test_client_project_room_area_survives_stored_canvas_v2_bowtie(): void
    {
        $clientProject = \App\Models\ClientProject::factory()->create(['name' => 'CP Canvas Bowtie']);
        $this->project->update(['client_project_id' => $clientProject->id]);
        RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Canvas Bowtie CP',
            'vertices' => $this->bowtieCanvasV2(),
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 0.0,
        ]);

        $response = $this->actingAs($this->user)->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $clientProject,
            'engine' => 'openplan3d',
        ]));
        $response->assertOk();
        $response->assertSee('Canvas Bowtie CP', false);
        $response->assertSee('geometry_unmeasurable', false);
    }

    public function test_canvas_v2_bowtie_sibling_does_not_block_valid_room_save(): void
    {
        $good = $this->storeSampleRoom(120.0, $this->sampleOpenPlan(10.0, 12.0));
        RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Canvas bowtie sibling',
            'vertices' => $this->bowtieCanvasV2(),
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 0.0,
        ]);

        $plan = $this->sampleOpenPlan(10.0, 12.0);
        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $good),
            [
                'name' => 'Stored Room',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk()->assertJsonPath('ok', true);
    }

    public function test_layouts_search_survives_invalid_sibling_room(): void
    {
        $good = $this->storeSampleRoom(120.0, $this->sampleOpenPlan(10.0, 12.0));
        $bowtie = $this->sampleOpenPlan(10.0, 12.0);
        $bowtie['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];
        RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Bad sibling',
            'vertices' => $bowtie,
            'area_sqft' => 99.0,
        ]);

        $response = $this->actingAs($this->user)->getJson(route('admin.utilities.room-area.layouts', [
            'q' => 'Save Test',
        ]));

        $response->assertOk();
        $this->assertTrue(collect($response->json())->contains(
            fn ($opt) => str_contains((string) ($opt['label'] ?? ''), $good->name)
        ));
        $this->assertTrue(collect($response->json())->contains(
            fn ($opt) => ($opt['geometry_unmeasurable'] ?? false) === true
                && str_contains((string) ($opt['meta'] ?? ''), 'ⓘ')
        ));
    }

    public function test_sibling_with_bowtie_does_not_block_valid_room_save(): void
    {
        $this->storeSampleRoom(120.0, $this->sampleOpenPlan(10.0, 12.0));
        $bowtie = $this->sampleOpenPlan(10.0, 12.0);
        $bowtie['floor']['rooms'][0]['polygon'] = [
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 100, 'y' => 0],
            ['x' => 0, 'y' => 100],
        ];
        $broken = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Broken sibling',
            'vertices' => $bowtie,
            'area_sqft' => 0.0,
        ]);

        $good = RoomAreaRoom::where('name', 'Stored Room')->firstOrFail();
        $plan = $this->sampleOpenPlan(10.0, 12.0);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $good),
            [
                'name' => 'Stored Room',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $broken->refresh();
        $this->assertTrue($this->bridge->isPlan($broken->vertices));
    }

    public function test_rejects_huge_and_non_finite_coordinates_on_plan_canvas_and_validate(): void
    {
        $hugePlan = $this->sampleOpenPlan(10.0, 12.0);
        $hugePlan['floor']['walls'][0]['start']['x'] = 1e12;
        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Huge plan', 'plan' => $hugePlan]
        )->assertStatus(422)->assertJsonPath('ok', false);

        $nonFinitePlan = $this->sampleOpenPlan(10.0, 12.0);
        $nonFinitePlan['floor']['walls'][0]['start']['x'] = '1e300';
        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            ['name' => 'Non finite plan', 'plan' => $nonFinitePlan]
        )->assertStatus(422)->assertJsonPath('ok', false);

        $scale = 12.0;
        $hugeCanvas = [
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 1e12, 'y' => 0],
                        ['x' => 1e12, 'y' => 120],
                        ['x' => 0, 'y' => 120],
                    ],
                ]],
            ]],
        ];

        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'Huge canvas',
                'scale_pixels_per_foot' => $scale,
                'canvas' => $hugeCanvas,
            ]
        )->assertStatus(422)->assertJsonPath('ok', false);

        $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.validate'),
            [
                'scale_pixels_per_foot' => $scale,
                'canvas' => $hugeCanvas,
            ]
        )->assertStatus(422)->assertJsonPath('ok', false);
    }

    private function storeSampleRoom(float $areaSqft, ?array $plan = null): RoomAreaRoom
    {
        $plan ??= $this->sampleOpenPlan(10.0, 15.0);
        $metrics = $this->bridge->layoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $this->assertEquals($areaSqft, $metrics['area_sqft']);

        return RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Stored Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => $metrics['area_sqft'],
            'vertices' => $plan,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function bowtieCanvasV2(): array
    {
        return [
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => [
                        ['x' => 0, 'y' => 0],
                        ['x' => 120, 'y' => 144],
                        ['x' => 120, 'y' => 0],
                        ['x' => 0, 'y' => 144],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function twoRoomPlan(): array
    {
        $ten = 10.0 * 30.48;

        return [
            'unit' => 'cm',
            'version' => '1.0',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => $ten, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w2', 'start' => ['x' => $ten, 'y' => 0], 'end' => ['x' => $ten, 'y' => $ten], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w3', 'start' => ['x' => $ten, 'y' => $ten], 'end' => ['x' => 0, 'y' => $ten], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w4', 'start' => ['x' => 0, 'y' => $ten], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w5', 'start' => ['x' => $ten, 'y' => 0], 'end' => ['x' => $ten * 2, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w6', 'start' => ['x' => $ten * 2, 'y' => 0], 'end' => ['x' => $ten * 2, 'y' => $ten], 'height' => 243.84, 'thickness' => 10],
                    ['id' => 'w7', 'start' => ['x' => $ten * 2, 'y' => $ten], 'end' => ['x' => $ten, 'y' => $ten], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [
                    [
                        'id' => 'r1',
                        'name' => 'Kitchen',
                        'walls' => ['w1', 'w2', 'w3', 'w4'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $ten, 'y' => 0],
                            ['x' => $ten, 'y' => $ten],
                            ['x' => 0, 'y' => $ten],
                        ],
                    ],
                    [
                        'id' => 'r2',
                        'name' => 'Bath',
                        'walls' => ['w5', 'w6', 'w7', 'w2'],
                        'polygon' => [
                            ['x' => $ten, 'y' => 0],
                            ['x' => $ten * 2, 'y' => 0],
                            ['x' => $ten * 2, 'y' => $ten],
                            ['x' => $ten, 'y' => $ten],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function zeroAreaPlan(): array
    {
        return [
            'format' => 'openplan3d',
            'units' => 'cm',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 0, 'y' => 0], 'height' => 243.84, 'thickness' => 10],
                ],
                'rooms' => [
                    [
                        'id' => 'empty',
                        'name' => 'Untitled Project',
                        'walls' => ['w1'],
                        'polygon' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 0, 'y' => 0],
                            ['x' => 0, 'y' => 0],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_l_shaped_room_with_two_partitions_saves_successfully_with_correct_metrics(): void
    {
        $cmPerFt = 30.48;
        $plan = [
            'unit' => 'cm',
            'version' => '1.0',
            'floor' => [
                'walls' => [
                    ['id' => 'w1', 'start' => ['x' => 0, 'y' => 0], 'end' => ['x' => 20 * $cmPerFt, 'y' => 0], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    ['id' => 'w2', 'start' => ['x' => 20 * $cmPerFt, 'y' => 0], 'end' => ['x' => 20 * $cmPerFt, 'y' => 10 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    ['id' => 'w3', 'start' => ['x' => 20 * $cmPerFt, 'y' => 10 * $cmPerFt], 'end' => ['x' => 10 * $cmPerFt, 'y' => 10 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    ['id' => 'w4', 'start' => ['x' => 10 * $cmPerFt, 'y' => 10 * $cmPerFt], 'end' => ['x' => 10 * $cmPerFt, 'y' => 20 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    ['id' => 'w5', 'start' => ['x' => 10 * $cmPerFt, 'y' => 20 * $cmPerFt], 'end' => ['x' => 0, 'y' => 20 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    ['id' => 'w6', 'start' => ['x' => 0, 'y' => 20 * $cmPerFt], 'end' => ['x' => 0, 'y' => 0], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    // Partition 1: T-joined to w1 at (5, 0), extends to (5, 6) (6 ft)
                    ['id' => 'p1', 'start' => ['x' => 5 * $cmPerFt, 'y' => 0], 'end' => ['x' => 5 * $cmPerFt, 'y' => 6 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                    // Partition 2: T-joined to p1 at (5, 3), extends to (11, 3) (6 ft, free-ended)
                    ['id' => 'p2', 'start' => ['x' => 5 * $cmPerFt, 'y' => 3 * $cmPerFt], 'end' => ['x' => 11 * $cmPerFt, 'y' => 3 * $cmPerFt], 'height' => 8 * $cmPerFt, 'thickness' => 10],
                ],
                'rooms' => [],
            ],
        ];

        // 1. Verify bridge layoutMetrics directly
        $metrics = $this->bridge->layoutMetrics($plan, null, ['wall_height_ft' => 8.0]);
        $this->assertFalse(!empty($metrics['geometry_unmeasurable']), 'Should not be marked geometry_unmeasurable');
        $this->assertEquals(300.0, $metrics['area_sqft'], 'Floor area matches L-shape without partitions (300 sq ft)');
        $this->assertEquals(80.0, $metrics['perimeter_lf'], 'Perimeter matches outer loop (80 lf)');
        $this->assertEquals(832.0, $metrics['net_wall_area_sqft'], 'Net wall area includes both faces of partitions (832 sq ft)');

        // 2. Post to store room endpoint and verify save succeeds
        $response = $this->actingAs($this->user)->postJson(
            route('admin.utilities.room-area.rooms.store', $this->project),
            [
                'name' => 'L Room with Partitions',
                'plan' => $plan,
                'wall_height_ft' => 8.0,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertEquals(300.0, $response->json('room.area_sqft'));
        $this->assertEquals(832.0, $response->json('room.metrics.net_wall_area_sqft'));
    }

    public function test_update_room_detects_conflict_when_server_has_newer_revision(): void
    {
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Initial Room',
            'vertices' => $this->sampleOpenPlan(10.0, 10.0),
            'area_sqft' => 100.0,
            'wall_height_ft' => 8.0,
        ]);

        $staleRevision = (string) ($room->updated_at->valueOf() - 5000);

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => 'Attempted Stale Update',
                'plan' => $this->sampleOpenPlan(10.0, 10.0),
                'expected_revision' => $staleRevision,
            ]
        );

        $response->assertStatus(409);
        $response->assertJsonPath('ok', false);
        $response->assertJsonPath('code', 'conflict');
    }

    public function test_update_room_succeeds_when_expected_revision_matches(): void
    {
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Initial Room',
            'vertices' => $this->sampleOpenPlan(10.0, 10.0),
            'area_sqft' => 100.0,
            'wall_height_ft' => 8.0,
        ]);

        $currentRevision = (string) $room->updated_at->valueOf();

        $response = $this->actingAs($this->user)->putJson(
            route('admin.utilities.room-area.rooms.update', $room),
            [
                'name' => 'Valid Updated Room',
                'plan' => $this->sampleOpenPlan(10.0, 10.0),
                'expected_revision' => $currentRevision,
            ]
        );

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('room.name', 'Valid Updated Room');
    }
}
