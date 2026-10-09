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
        $this->assertStringContainsString('if (isPhoneChrome()) return;', $js);
        $this->assertStringContainsString('els.moreSheet.scrollTop += (secBox.top - sheetBox.top) - 8;', $js);
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
        $this->assertStringContainsString('data-ra-phone-bar', $html);
        $this->assertStringContainsString('data-ra-more-toggle', $html);
        $this->assertStringContainsString('data-ra-more-sheet', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal', $html);
        $this->assertStringContainsString('Expand or collapse panel', $html);
        $this->assertStringContainsString('is-overflowing::after', $css);
        $this->assertStringContainsString("describeParsedLength(feet)", $js);
        $this->assertStringContainsString('Floor plan upload failed. The room itself was saved. Save again to retry.', $js);
        $this->assertStringContainsString('Could not delete room. Check your connection and try again.', $js);
        $this->assertStringContainsString('Could not delete project. Check your connection and try again.', $js);
        $this->assertStringContainsString('Could not delete photo. Check your connection and try again.', $js);
        $this->assertStringContainsString('Could not delete floor plan. Check your connection and try again.', $js);
        $this->assertStringContainsString('Unlock layers to move the floor plan.', $js);
        $this->assertStringContainsString("root.style.setProperty('--ra-sheet-h', 'auto')", $js);
        $this->assertStringContainsString('function readFetchJson(', $js);
        $this->assertStringContainsString('return readFetchJson(res, \'Could not delete project.\');', $js);
        $this->assertStringNotContainsString('return res.json().then(function (data) {'."\n".'            return { ok: res.ok, data: data };', $js);
        $this->assertStringContainsString(':not([data-ra-more-close])', $css);
        $this->assertStringContainsString('.util-ra--fullpage.is-more-open .util-ra-more-sheet .util-ra-more-sheet__head .admin-btn.util-ra-icon-btn', $css);
        $this->assertStringContainsString('.util-ra--fullpage:not(.is-sheet-expanded):not(.is-dock-open) .util-ra-side', $css);
        $this->assertStringContainsString('is-sheet-expanded', $js);
        $this->assertStringContainsString('data-ra-sheet-total', $html);
        $this->assertStringContainsString('data-ra-more-templates', $html);
        $this->assertStringContainsString('data-ra-sheet-dock', $html);
        $this->assertStringContainsString('util-ra-more-btn-label', $html);
        $this->assertStringContainsString('Apply length', $html);
        $this->assertStringContainsString('util-ra-more-block--units', $html);
        $this->assertStringContainsString('util-ra-more-block--view', $html);
        $this->assertStringContainsString('util-ra-more-block--measure', $html);
        $this->assertStringContainsString('>Fit</span>', $html);
        $this->assertStringContainsString('>Calibrate</span>', $html);
        $this->assertStringContainsString('state.editingRoomId = savedRoom.id', $js);
        $this->assertStringNotContainsString('title="Recalibrate scale so this wall matches', $html);
        $help = file_get_contents(resource_path('views/admin/utilities/_room_area_help_pop.blade.php'));
        $this->assertStringContainsString('role="region"', $help);
        $this->assertStringNotContainsString('role="tooltip"', $help);
        $this->assertStringContainsString('<div class="util-ra-help">', $help);
        $this->assertStringNotContainsString('<span class="util-ra-help">', $help);
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

        $emptyEditor = $canvas;
        $emptyEditor['editor'] = [];
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $emptyEditor,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('room.canvas.editor.layers_locked', true);

        $dimsOnly = $canvas;
        $dimsOnly['editor'] = ['dimensions_mode' => 'main'];
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $dimsOnly,
            'scale_pixels_per_foot' => 12,
        ])->assertOk()->assertJsonPath('room.canvas.editor.layers_locked', true);

        $nudge = $canvas;
        $nudge['groups'][0]['polygons'][0]['vertices'][1]['x'] = 144.5;
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $nudge,
            'scale_pixels_per_foot' => 12,
        ])->assertOk();
        $room->refresh();
        $this->assertSame(144, (int) $room->vertices['groups'][0]['polygons'][0]['vertices'][1]['x']);

        $movedBg = $room->vertices;
        $movedBg['background'] = ['x' => 10, 'y' => 10, 'width' => 400, 'height' => 300];
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $movedBg,
            'scale_pixels_per_foot' => 12,
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $withPlan = $room->vertices;
        $withPlan['background'] = ['x' => 24, 'y' => 36, 'width' => 480, 'height' => 360];
        $withPlan['editor']['layers_locked'] = false;
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $withPlan,
            'scale_pixels_per_foot' => 12,
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $room->refresh();
        $room->update(['vertices' => array_merge($room->vertices, [
            'background' => ['x' => 24, 'y' => 36, 'width' => 480, 'height' => 360],
        ])]);
        $shiftedPlan = $room->vertices;
        $shiftedPlan['background'] = ['x' => 80, 'y' => 36, 'width' => 480, 'height' => 360];
        $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
            'name' => 'Locked',
            'canvas' => $shiftedPlan,
            'scale_pixels_per_foot' => 12,
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Unlock layers to change the floor plan.');

        $this->deleteJson(route('admin.utilities.room-area.projects.destroy', $layout))
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_locked_room_resize_keeps_feet_and_allows_objects(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Resize lock',
            'scale_pixels_per_foot' => 12,
        ]);
        $baseVerts = [
            ['x' => 0, 'y' => 0],
            ['x' => 192, 'y' => 0],
            ['x' => 192, 'y' => 144],
            ['x' => 120, 'y' => 144],
            ['x' => 120, 'y' => 96],
            ['x' => 0, 'y' => 96],
        ];
        $canvas = [
            'v' => 2,
            'groups' => [[
                'id' => 'g1',
                'color' => '#024768',
                'polygons' => [[
                    'id' => 'p1',
                    'vertices' => $baseVerts,
                ]],
            ]],
            'background' => ['x' => 24, 'y' => 36, 'width' => 480, 'height' => 360],
            'editor' => ['layers_locked' => true],
        ];
        $store = $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Locked',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
        ])->assertOk();
        $room = RoomAreaRoom::query()->findOrFail($store->json('room.id'));

        $scaleCanvas = function (array $source, float $factor): array {
            $scaled = $source;
            foreach ($scaled['groups'][0]['polygons'][0]['vertices'] as $i => $vertex) {
                $scaled['groups'][0]['polygons'][0]['vertices'][$i]['x'] = $vertex['x'] * $factor;
                $scaled['groups'][0]['polygons'][0]['vertices'][$i]['y'] = $vertex['y'] * $factor;
            }
            $scaled['background'] = [
                'x' => ($source['background']['x'] ?? 0) * $factor,
                'y' => ($source['background']['y'] ?? 0) * $factor,
                'width' => ($source['background']['width'] ?? 0) * $factor,
                'height' => ($source['background']['height'] ?? 0) * $factor,
            ];

            return $scaled;
        };

        $assertFeet = function (RoomAreaRoom $room) {
            $scale = (float) $room->scale_pixels_per_foot;
            $verts = $room->vertices['groups'][0]['polygons'][0]['vertices'];
            $this->assertCount(6, $verts);
            $this->assertEqualsWithDelta(16.0, $verts[1]['x'] / $scale, 0.001);
            $this->assertEqualsWithDelta(12.0, $verts[2]['y'] / $scale, 0.001);
            $this->assertEqualsWithDelta(10.0, $verts[3]['x'] / $scale, 0.001);
            $this->assertEqualsWithDelta(8.0, $verts[4]['y'] / $scale, 0.001);
            $bg = $room->vertices['background'];
            $this->assertEqualsWithDelta(2.0, $bg['x'] / $scale, 0.001);
            $this->assertEqualsWithDelta(3.0, $bg['y'] / $scale, 0.001);
            $this->assertEqualsWithDelta(40.0, $bg['width'] / $scale, 0.001);
            $this->assertEqualsWithDelta(30.0, $bg['height'] / $scale, 0.001);
        };

        foreach ([0.5, 0.9, 1.02, 1.1, 1.5] as $factor) {
            $scaled = $scaleCanvas($canvas, $factor);
            $scaled['objects'] = [[
                'id' => 'obj_door',
                'type' => 'door',
                'subtype' => 'single',
                'wall_id' => 'wall_p1_0_1',
                'width' => 36,
                'offset' => 24,
                'swing' => 'right_out',
                'hinge' => 'right',
            ]];
            $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
                'name' => 'Locked',
                'canvas' => $scaled,
                'scale_pixels_per_foot' => 12 * $factor,
                'canvas_width' => (int) round(800 * $factor),
                'canvas_height' => (int) round(800 * $factor),
            ])->assertOk()->assertJsonPath('ok', true);
            $room->refresh();
            $assertFeet($room);
            $this->assertSame('door', $room->vertices['objects'][0]['type'] ?? null);
        }

        $working = $room->vertices;
        $workingScale = (float) $room->scale_pixels_per_foot;
        for ($i = 0; $i < 40; $i++) {
            $step = ($i % 2 === 0) ? 1.0137 : (1 / 1.0137);
            $scaled = $scaleCanvas($working, $step);
            $this->putJson(route('admin.utilities.room-area.rooms.update', $room), [
                'name' => 'Locked',
                'canvas' => $scaled,
                'scale_pixels_per_foot' => $workingScale * $step,
            ])->assertOk()->assertJsonPath('ok', true);
            $room->refresh();
            $assertFeet($room);
            $working = $room->vertices;
            $workingScale = (float) $room->scale_pixels_per_foot;
        }
        $this->assertEqualsWithDelta(16.0, $working['groups'][0]['polygons'][0]['vertices'][1]['x'] / $workingScale, 0.001);
        $this->assertEqualsWithDelta(2.0, $working['background']['x'] / $workingScale, 0.001);
    }

    public function test_unlocked_project_can_be_deleted(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Temp delete',
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
        ];
        $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Office',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
        ])->assertOk();

        $this->deleteJson(route('admin.utilities.room-area.projects.destroy', $layout))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertFalse(RoomAreaProject::query()->whereKey($layout->id)->exists());
        $this->assertSame(0, RoomAreaRoom::query()->where('room_area_project_id', $layout->id)->count());
    }

    public function test_locked_new_room_allows_first_background_upload(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Locked plan',
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
            'editor' => ['layers_locked' => true],
        ];
        $store = $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Studio',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
        ])->assertOk();
        $room = RoomAreaRoom::query()->findOrFail($store->json('room.id'));
        $this->assertNull($room->background_path);

        $file = \Illuminate\Http\UploadedFile::fake()->image('plan.png', 24, 24);
        $this->post(route('admin.utilities.room-area.rooms.background.upload', $room), [
            'background' => $file,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('ok', true);

        $room->refresh();
        $this->assertNotEmpty($room->background_path);

        $this->post(route('admin.utilities.room-area.rooms.background.upload', $room), [
            'background' => \Illuminate\Http\UploadedFile::fake()->image('plan2.png', 24, 24),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
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
