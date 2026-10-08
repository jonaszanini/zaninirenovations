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
        $this->assertStringContainsString('room-area-layers-locked', $js);
        $this->assertStringContainsString('room-area-dimensions-mode', $js);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.util-ra-unit-system', $css);
        $this->assertStringContainsString('.util-ra-templates-pop', $css);
        $this->assertStringContainsString('.util-ra-dim-mode', $css);
        $this->assertStringContainsString('.util-ra-canvas-wrap.is-layers-locked', $css);
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
