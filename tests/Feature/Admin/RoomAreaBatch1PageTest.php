<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaBatch1PageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_standalone_full_page_exposes_batch1_room_tools(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-fullpage', false)
            ->assertSee('Room tools', false)
            ->assertSee('data-ra-undo-vertex', false)
            ->assertSee('data-ra-redo', false)
            ->assertSee('data-ra-templates-toggle', false)
            ->assertSee('data-ra-template="rect"', false)
            ->assertSee('data-ra-template="l"', false)
            ->assertSee('data-ra-template="u"', false)
            ->assertSee('data-ra-template="t"', false)
            ->assertSee('data-ra-alcove', false)
            ->assertSee('Add alcove / bump-out')
            ->assertSee('data-ra-lock-layers', false)
            ->assertSee('data-ra-dimensions-mode', false)
            ->assertSee('data-ra-snap-toggle', false)
            ->assertSee('data-ra-unit-system="imperial"', false)
            ->assertSee('data-ra-unit-system="metric"', false)
            ->assertSee('All dims')
            ->assertSee('Main only')
            ->assertSee('Area only')
            ->assertSee('Dims off')
            ->assertDontSee('Coming soon')
            ->getContent();

        $this->assertStringContainsString('data-ra-area-unit', $html);
        $this->assertStringContainsString('data-ra-linear-unit', $html);
        $this->assertStringContainsString('data-ra-live-dual', $html);
        $this->assertStringContainsString('Disable angle snap', $html);
        $this->assertStringContainsString('Imperial', $html);
        $this->assertStringContainsString('Metric', $html);
        $this->assertStringContainsString('ft-in-frac', $html);
        $this->assertStringNotContainsString('>Meter</option>', $html);
        $this->assertStringContainsString('data-ra-wall-area-label', $html);
    }

    public function test_client_project_full_page_keeps_batch0_groups_and_batch1_controls(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Kitchen remodel']);
        $this->makeRoom($project, 'Kitchen');

        $this->get(route('admin.client-projects.utilities.room-area', $project))
            ->assertOk()
            ->assertSee('data-ra-lock-client-project="'.$project->id.'"', false)
            ->assertSee('util-ra-tool-group--view', false)
            ->assertSee('util-ra-tool-group--draw', false)
            ->assertSee('util-ra-tool-group--measure', false)
            ->assertSee('util-ra-tool-group--room-tools', false)
            ->assertSee('Draw / Edit')
            ->assertSee('Measure / Units')
            ->assertSee('Room tools')
            ->assertSee('data-ra-help-guide', false)
            ->assertSee('data-ra-templates-popover', false)
            ->assertSee('data-ra-lock-layers', false);
    }

    public function test_batch1_assets_wire_history_snap_lock_templates_and_units(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('var UNDO_MAX = 50;', $js);
        $this->assertStringContainsString('function undoVertex()', $js);
        $this->assertStringContainsString('function redoAction()', $js);
        $this->assertStringContainsString('function applyRoomTemplate(', $js);
        $this->assertStringContainsString('function addAlcove(', $js);
        $this->assertStringContainsString('function setLayersLocked(', $js);
        $this->assertStringContainsString('function setDimensionsMode(', $js);
        $this->assertStringContainsString('function shouldAngleSnap(', $js);
        $this->assertStringContainsString('function detectExplicitLengthUnit(', $js);
        $this->assertStringContainsString('function parseMmInput(', $js);
        $this->assertStringContainsString('function setUnitSystem(', $js);
        $this->assertStringContainsString('geometryLocked()', $js);
        $this->assertStringContainsString('openingCornerHint', $js);
        $this->assertStringContainsString('drawOverallDimensionLabel', $js);
        $this->assertStringContainsString('clearShape({ force: true })', $js);
        $this->assertStringContainsString('applyEditorPrefs(defaultEditorPrefs(), true)', $js);
        $this->assertStringNotContainsString('room-area-layers-locked', $js);
        $this->assertStringContainsString("els.templatesPopover.style.position = 'fixed'", $js);
        $this->assertStringContainsString('function serializeEditorPrefs()', $js);
        $this->assertStringContainsString('function applyEditorPrefs(', $js);
        $this->assertStringNotContainsString('window.__roomAreaQA', $js);
        $this->assertStringContainsString('RoomAreaLib', $js);

        $lib = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertNotFalse($lib);
        $this->assertStringContainsString('function parseLengthInput', $lib);
        $this->assertStringContainsString('function insertAlcove', $lib);
        $this->assertStringContainsString('function shapeFromKind', $lib);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.util-ra-unit-system', $css);
        $this->assertStringContainsString('.util-ra-templates-pop', $css);
        $this->assertStringContainsString('.util-ra-dim-mode', $css);
        $this->assertStringContainsString('.util-ra-canvas-wrap.is-layers-locked', $css);
        $this->assertStringNotContainsString('flex: 0 0 7.5rem;', $css);
        $this->assertStringNotContainsString('flex: 0 0 8.25rem;', $css);
        $html = file_get_contents(resource_path('views/admin/utilities/_room_area_tool.blade.php'));
        $this->assertNotFalse($html);
        $this->assertStringNotContainsString("'tipId' => 'units-format-fp'", $html);
        $this->assertStringNotContainsString("'tipId' => 'dims'", $html);
        $this->assertStringNotContainsString("'tipId' => 'snap'", $html);
        $this->assertStringNotContainsString("'tipId' => 'templates'", $html);
        $this->assertStringNotContainsString("'tipId' => 'lock'", $html);
        $this->assertStringNotContainsString("'tipId' => 'objects-snap'", $html);
        $this->assertStringContainsString("'tipId' => 'draw'", $html);
        $this->assertStringContainsString("'tipId' => 'plan'", $html);
        $this->assertStringNotContainsString('title="Recalibrate scale so this wall matches', $html);
        $help = file_get_contents(resource_path('views/admin/utilities/_room_area_help_pop.blade.php'));
        $this->assertStringContainsString('role="region"', $help);
        $this->assertStringNotContainsString('role="tooltip"', $help);
    }

    public function test_saved_room_editor_prefs_round_trip(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Batch 1 prefs',
            'scale_pixels_per_foot' => 12,
        ]);

        $canvas = [
            'v' => 2,
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
            'editor' => [
                'layers_locked' => true,
                'dimensions_mode' => 'area',
                'unit_system' => 'metric',
                'angle_snap' => false,
                'template' => 'l',
            ],
        ];

        $store = $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Office',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('ok', true);

        $store->assertJsonPath('room.canvas.editor.layers_locked', true)
            ->assertJsonPath('room.canvas.editor.dimensions_mode', 'area')
            ->assertJsonPath('room.canvas.editor.unit_system', 'metric')
            ->assertJsonPath('room.canvas.editor.angle_snap', false)
            ->assertJsonPath('room.canvas.editor.template', 'l');

        $roomId = $store->json('room.id');
        $this->assertNotEmpty($roomId);

        $room = RoomAreaRoom::query()->findOrFail($roomId);
        $this->assertSame('l', $room->vertices['editor']['template'] ?? null);
        $this->assertTrue($room->vertices['editor']['layers_locked'] ?? false);

        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Office',
            'canvas' => $room->vertices,
            'scale_pixels_per_foot' => 12,
        ])
            ->assertOk()
            ->assertJsonPath('room.canvas.editor.template', 'l')
            ->assertJsonPath('room.canvas.editor.unit_system', 'metric');
    }

    public function test_old_saved_room_without_editor_prefs_still_loads(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Legacy kitchen']);
        $room = $this->makeRoom($project, 'Kitchen');

        $this->assertArrayNotHasKey('editor', $room->vertices);

        $this->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))
            ->assertOk()
            ->assertSee('data-ra-initial-room-id="'.$room->id.'"', false)
            ->assertSee('Kitchen');

        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Kitchen',
            'canvas' => $room->vertices,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_locked_room_rejects_geometry_and_scale_changes(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Locked layout',
            'scale_pixels_per_foot' => 12,
        ]);
        $canvas = [
            'v' => 2,
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
            'editor' => [
                'layers_locked' => true,
                'dimensions_mode' => 'all',
                'unit_system' => 'imperial',
                'angle_snap' => true,
            ],
        ];
        $store = $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Locked',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
        ])->assertOk();
        $room = RoomAreaRoom::query()->findOrFail($store->json('room.id'));

        $moved = $canvas;
        $moved['groups'][0]['polygons'][0]['vertices'][1]['x'] = 200;
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $moved,
            'scale_pixels_per_foot' => 12,
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 18,
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $unlocked = $canvas;
        $unlocked['editor']['layers_locked'] = false;
        $unlocked['groups'][0]['polygons'][0]['vertices'][1]['x'] = 200;
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $unlocked,
            'scale_pixels_per_foot' => 12,
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $named = $canvas;
        $named['groups'][0]['polygons'][0]['name'] = 'Office zone';
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $named,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('ok', true);

        $resized = $canvas;
        $resized['groups'][0]['polygons'][0]['name'] = 'Office zone';
        foreach ($resized['groups'][0]['polygons'][0]['vertices'] as $i => $vertex) {
            $resized['groups'][0]['polygons'][0]['vertices'][$i]['x'] = $vertex['x'] * (803 / 800);
            $resized['groups'][0]['polygons'][0]['vertices'][$i]['y'] = $vertex['y'] * (803 / 800);
        }
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $resized,
            'scale_pixels_per_foot' => 12 * (803 / 800),
            'canvas_width' => 803,
            'canvas_height' => 803,
        ])->assertOk()->assertJsonPath('ok', true);

        $noEditor = $canvas;
        unset($noEditor['editor']);
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $noEditor,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('room.canvas.editor.layers_locked', true);

        $this->deleteJson(route('admin.utilities.room-area.rooms.destroy', $room))
            ->assertStatus(422)->assertJsonPath('ok', false);

        $this->deleteJson(route('admin.utilities.room-area.rooms.background.destroy', $room))
            ->assertStatus(422)->assertJsonPath('ok', false);

        $this->putJson(route('admin.utilities.room-area.projects.update', $layout), [
            'scale_pixels_per_foot' => 18,
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }

    private function makeRoom(ClientProject $clientProject, string $name): RoomAreaRoom
    {
        $layout = RoomAreaProject::create([
            'name' => $clientProject->name.' rooms',
            'client_project_id' => $clientProject->id,
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $layout->id,
            'name' => $name,
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 120,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'name' => $name,
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
    }
}
