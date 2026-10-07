<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaCanvasData;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaCanvasObjectsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @return array<string, mixed>
     */
    private function rectangleCanvas(bool $withObjects = true): array
    {
        $canvas = [
            'v' => $withObjects ? 3 : 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'name' => 'Living Room',
                            'vertices' => [
                                ['x' => 0, 'y' => 0],
                                ['x' => 120, 'y' => 0],
                                ['x' => 120, 'y' => 80],
                                ['x' => 0, 'y' => 80],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($withObjects) {
            $canvas['objects'] = [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                    'swing' => 'right_out',
                    'hinge' => 'right',
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_2_3',
                    'width' => 48,
                    'offset' => 12,
                    'height' => 48,
                    'sill_height' => 36,
                ],
            ];
        }

        return $canvas;
    }

    public function test_store_room_persists_v3_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Object save project',
            'scale_pixels_per_foot' => 10,
        ]);

        $response = $this->postJson(route('admin.utilities.room-area.rooms.store', $project), [
            'name' => 'Main floor',
            'canvas' => $this->rectangleCanvas(true),
            'canvas_width' => 900,
            'canvas_height' => 640,
            'scale_pixels_per_foot' => 10,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $room = RoomAreaRoom::query()->first();
        $this->assertNotNull($room);

        $canvas = app(RoomAreaCanvasData::class)->normalize($room->vertices);
        $this->assertSame(3, $canvas['v']);
        $this->assertCount(2, $canvas['objects']);
        $this->assertSame('door', $canvas['objects'][0]['type']);
        $this->assertSame(36.0, $canvas['objects'][0]['width']);
        $this->assertSame('window', $canvas['objects'][1]['type']);
        $this->assertSame('wall_p1_2_3', $canvas['objects'][1]['wall_id']);

        $response->assertJsonPath('room.canvas.v', 3);
        $response->assertJsonPath('room.canvas.objects.0.type', 'door');
    }

    public function test_update_room_round_trips_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Object update project',
            'scale_pixels_per_foot' => 10,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Office',
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 96,
            'vertices' => $this->rectangleCanvas(false),
        ]);

        $updated = $this->rectangleCanvas(true);
        $updated['objects'][0]['width'] = 32;
        $updated['objects'][0]['offset'] = 18;

        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Office',
            'canvas' => $updated,
            'scale_pixels_per_foot' => 10,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('room.canvas.objects.0.width', 32)
            ->assertJsonPath('room.canvas.objects.0.offset', 18);

        $fresh = $room->fresh();
        $canvas = app(RoomAreaCanvasData::class)->normalize($fresh->vertices);
        $this->assertSame(3, $canvas['v']);
        $this->assertSame(32.0, $canvas['objects'][0]['width']);
        $this->assertSame(18.0, $canvas['objects'][0]['offset']);
    }

    public function test_v2_rooms_load_without_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Legacy project',
            'scale_pixels_per_foot' => 10,
        ]);
        RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Legacy room',
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 96,
            'vertices' => $this->rectangleCanvas(false),
        ]);

        $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-palette', false)
            ->assertSee('data-ra-object-edit', false)
            ->assertSee('data-ra-dimensions-toggle', false)
            ->assertViewHas('projectsJson', function ($projects) {
                $canvas = $projects[0]['rooms'][0]['canvas'] ?? null;

                return is_array($canvas)
                    && ($canvas['v'] ?? null) === 2
                    && ! array_key_exists('objects', $canvas);
            });
    }

    public function test_room_area_page_includes_touch_and_keyboard_controls(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-shortcuts-toggle', false)
            ->assertSee('data-ra-shortcuts-popover', false)
            ->assertSee('data-ra-redo', false)
            ->assertSee('data-ra-split-wall', false)
            ->assertSee('Split wall')
            ->assertSee('Keyboard shortcuts')
            ->assertSee('Pinch to zoom')
            ->assertSee('fa-paint-brush', false)
            ->assertSee('Start a new color group')
            ->assertSee('data-ra-palette-toggle', false)
            ->assertSee('data-ra-walls-toggle', false)
            ->assertSee('data-ra-palette-stairs', false)
            ->assertSee('data-ra-thickness', false)
            ->assertSee('Objects')
            ->assertSee('Wall thickness')
            ->assertDontSee('fa-palette')
            ->assertDontSee('On a phone, type wall lengths below instead of dragging corners.');
    }

    public function test_room_area_assets_counter_scale_labels_and_compact_panels(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('return base / uiScale();', $js);
        $this->assertStringNotContainsString('Math.min(uiScale(), 1)', $js);
        $this->assertStringContainsString('buildAreaNameLabels', $js);
        $this->assertStringContainsString('caption.rotation(-place.angle * 180 / Math.PI)', $js);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.util-ra-palette:not(.is-open) .util-ra-palette__body', $css);
        $this->assertStringContainsString('.util-ra-palette.is-open .util-ra-palette__group::after', $css);
        $this->assertStringContainsString('max-width: 110px', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr))', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.util-ra-palette__toggle\s*\{\s*display:\s*none/i',
            $css
        );
    }

    public function test_store_room_persists_v4_thickness_and_free_objects(): void
    {
        $this->actingAsAdmin();

        $project = RoomAreaProject::create([
            'name' => 'Phase 5 save project',
            'scale_pixels_per_foot' => 12,
        ]);

        $canvas = $this->rectangleCanvas(false);
        $canvas['v'] = 4;
        $canvas['wall_thickness_in'] = 3.5;
        $canvas['objects'] = [
            [
                'id' => 'cab_1',
                'type' => 'cabinet',
                'subtype' => 'base',
                'x' => 40,
                'y' => 30,
                'width' => 36,
                'depth' => 24,
                'rotation' => 0,
            ],
            [
                'id' => 'st_1',
                'type' => 'stairs',
                'subtype' => 'straight',
                'x' => 70,
                'y' => 50,
                'width' => 36,
                'depth' => 120,
                'rotation' => 90,
            ],
        ];

        $this->postJson(route('admin.utilities.room-area.rooms.store', $project), [
            'name' => 'Kitchen',
            'canvas' => $canvas,
            'canvas_width' => 900,
            'canvas_height' => 640,
            'scale_pixels_per_foot' => 12,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('room.canvas.v', 4)
            ->assertJsonPath('room.canvas.wall_thickness_in', 3.5)
            ->assertJsonPath('room.canvas.objects.0.type', 'cabinet')
            ->assertJsonPath('room.canvas.objects.1.type', 'stairs');

        $project->refresh();
        $this->assertEqualsWithDelta(3.5, (float) $project->wall_thickness_in, 0.01);
    }
}
