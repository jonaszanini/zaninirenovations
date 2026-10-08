<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaGuidePageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_open_room_area_guide(): void
    {
        $this->get(route('admin.utilities.room-area.guide'))
            ->assertRedirect();
    }

    public function test_admin_can_open_utilities_room_area_guide(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.utilities.room-area.guide'))
            ->assertOk()
            ->assertViewIs('admin.utilities.room_area_guide')
            ->assertSee('data-ra-guide', false)
            ->assertSee('data-ra-guide-back', false)
            ->assertSee('Back to editor')
            ->assertSee('Room Area guide')
            ->assertSee('Open Room Area from a project')
            ->assertSee('Calibrate scale or type wall lengths')
            ->assertSee('Close the shape and add doors and windows')
            ->assertSee('Check Math')
            ->assertSee('Save rooms, then quote')
            ->assertSee('Zoom, pan, Fit, and Panel')
            ->assertSee('data-ra-guide-samples', false)
            ->assertSee('data-ra-guide-chip="guide"', false)
            ->assertSee('data-ra-guide-chip="add-rect"', false)
            ->assertSee('data-ra-guide-chip="objects"', false)
            ->assertSee('data-ra-guide-chip="walls"', false)
            ->assertSee('data-ra-guide-chip="math"', false)
            ->assertSee('data-ra-guide-chip="save"', false)
            ->assertSee('data-ra-guide-chip="zoom"', false)
            ->assertSee('data-ra-guide-chip="fit"', false)
            ->assertSee('data-ra-guide-chip="panel"', false)
            ->assertSee('data-ra-guide-chip="r90"', false)
            ->assertSee('util-ra-guide__sample', false)
            ->assertSee(route('admin.utilities.room-area', [], false), false)
            ->assertSee('admin-dark.css', false);
    }

    public function test_standalone_guide_back_keeps_selected_project(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'On-site layout',
            'scale_pixels_per_foot' => 10,
        ]);
        $room = $this->makeStandaloneRoom($layout, 'Office');

        $editorUrl = route('admin.utilities.room-area', ['ra_project' => $layout->id], false);
        $guideUrl = route('admin.utilities.room-area.guide', ['ra_project' => $layout->id], false);
        $guideWithRoom = route('admin.utilities.room-area.guide', [
            'ra_project' => $layout->id,
            'ra_room' => $room->id,
        ], false);

        $this->get(route('admin.utilities.room-area', ['ra_project' => $layout->id]))
            ->assertOk()
            ->assertSee($guideUrl, false);

        $this->get($guideUrl)
            ->assertOk()
            ->assertSee('data-ra-guide-back', false)
            ->assertSee($editorUrl, false);

        $this->get($guideWithRoom)
            ->assertOk()
            ->assertSee('ra_project='.$layout->id, false)
            ->assertSee('ra_room='.$room->id, false);
    }

    public function test_admin_can_open_client_project_guide_and_return_to_editor(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Kitchen remodel']);
        $room = $this->makeRoom($project, 'Kitchen');

        $editorUrl = route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ], false);

        $this->get(route('admin.client-projects.utilities.room-area.guide', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))
            ->assertOk()
            ->assertViewIs('admin.utilities.room_area_guide')
            ->assertSee('data-ra-guide', false)
            ->assertSee('Back to editor')
            ->assertSee($editorUrl, false)
            ->assertSee('12\' 6"', false)
            ->assertSee('Quote all');
    }

    public function test_guide_for_room_from_another_project_returns_404(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $other = ClientProject::factory()->create();
        $room = $this->makeRoom($other, 'Other room');

        $this->get(route('admin.client-projects.utilities.room-area.guide', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))->assertNotFound();
    }

    public function test_guide_view_is_admin_only_and_hides_sidebar_chrome(): void
    {
        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.admin-app:has(.util-ra-guide) .admin-sidebar', $css);
        $this->assertStringContainsString('.admin-app:has(.util-ra-guide) .admin-topbar', $css);
        $this->assertStringContainsString('.util-ra-guide__steps', $css);
        $this->assertStringContainsString('.util-ra-guide__samples', $css);
        $this->assertStringContainsString('.util-ra-guide__step', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1.2fr) minmax(16rem, 0.9fr)', $css);

        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-guide', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-guide__body', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-guide__samples', $dark);
    }

    private function makeStandaloneRoom(RoomAreaProject $layout, string $name): RoomAreaRoom
    {
        return RoomAreaRoom::create([
            'room_area_project_id' => $layout->id,
            'name' => $name,
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 96,
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
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 80],
                            ['x' => 0, 'y' => 80],
                        ],
                    ]],
                ]],
            ],
        ]);
    }

    private function makeRoom(ClientProject $clientProject, string $name): RoomAreaRoom
    {
        $layout = RoomAreaProject::create([
            'name' => $clientProject->name.' rooms',
            'client_project_id' => $clientProject->id,
            'scale_pixels_per_foot' => 10,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $layout->id,
            'name' => $name,
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 96,
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
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 80],
                            ['x' => 0, 'y' => 80],
                        ],
                    ]],
                ]],
            ],
        ]);
    }
}
