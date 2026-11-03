<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaCanvasData;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class UtilitiesRoomAreaPageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_open_standalone_room_area_page(): void
    {
        $this->get(route('admin.utilities.room-area'))
            ->assertRedirect();
    }

    public function test_admin_opens_standalone_room_area_as_full_page_editor(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Testing full page room area',
            'scale_pixels_per_foot' => 10,
        ]);
        $this->makeRoom($layout, 'Office');

        $html = $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertViewIs('admin.utilities.room_area')
            ->assertSee('data-room-area-openplan3d', false)
            ->assertSee('vendor/openplan3d-h1/editor/index.html', false)
            ->assertSee('src="/vendor/openplan3d-h1/editor/index.html', false)
            ->assertSee('All utilities')
            ->assertSee('id="openplan3d-save-btn"', false)
            ->assertSee('id="openplan3d-project-select"', false)
            ->assertSee('id="openplan3d-room-select"', false)
            ->assertSee('id="openplan3d-modal"', false)
            ->assertSee('Link to a project')
            ->assertSee('Keep standalone')
            ->assertDontSee('data-ra-help-guide', false)
            ->assertDontSee('openplan3d-btn-label">Guide', false)
            ->assertSee('Testing full page room area')
            ->assertSee('admin-dark.css', false)
            ->assertSee('id="admin-app"', false)
            ->assertSee('admin-body--fullscreen', false)
            ->assertSee('admin-app--fullscreen', false)
            ->assertDontSee('class="admin-sidebar"', false)
            ->assertDontSee('class="admin-topbar"', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.utilities.index', [], false), $html);
        $this->assertStringNotContainsString('admin-toolbar-lede', $html);
        $this->assertStringContainsString('data-bound-client-project=""', $html);
    }

    public function test_utilities_index_room_area_card_opens_full_page_route(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.utilities.index'))
            ->assertOk()
            ->assertSee('Room Area')
            ->assertSee(route('admin.utilities.room-area', [], false), false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener"', false);
    }

    public function test_unlinked_quote_picker_backs_to_standalone_full_page(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Unlinked layout',
            'scale_pixels_per_foot' => 10,
        ]);
        $room = $this->makeRoom($layout, 'Hall');

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee(route('admin.utilities.room-area', ['ra_project' => $layout->id], false), false);
    }

    public function test_client_project_room_area_stays_locked_full_page(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Kitchen remodel']);
        $this->makeRoomOnClientProject($project, 'Kitchen');

        $this->get(route('admin.client-projects.utilities.room-area', $project))
            ->assertOk()
            ->assertViewIs('admin.client_projects.room_area')
            ->assertSee('data-room-area-openplan3d', false)
            ->assertSee('Back to project')
            ->assertDontSee('All utilities');
    }

    private function makeRoom(RoomAreaProject $layout, string $name): RoomAreaRoom
    {
        $canvas = [
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
            'objects' => [],
        ];

        $room = RoomAreaRoom::create([
            'room_area_project_id' => $layout->id,
            'name' => $name,
            'canvas' => $canvas,
            'vertices' => $canvas['groups'][0]['polygons'][0]['vertices'],
            'area_sqft' => 66.67,
            'perimeter_lf' => 33.33,
            'wall_area_sqft' => 266.67,
            'baseboard_lf' => 33.33,
        ]);

        return $room;
    }

    private function makeRoomOnClientProject(ClientProject $project, string $name): RoomAreaRoom
    {
        $layout = RoomAreaProject::create([
            'name' => 'CP Layout',
            'client_project_id' => $project->id,
            'scale_pixels_per_foot' => 10,
        ]);

        return $this->makeRoom($layout, $name);
    }
}
