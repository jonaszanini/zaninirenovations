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
        $response->assertSee('data-hotkey-save', false);
        $response->assertSee('id="openplan3d-metrics-ribbon"', false);
        $response->assertSee('id="openplan3d-settings-drawer"', false);
        $response->assertSee('openplan3d-measurement-bridge.js', false);
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
        $response->assertSee('openplan3d-measurement-bridge.js', false);
    }
}

