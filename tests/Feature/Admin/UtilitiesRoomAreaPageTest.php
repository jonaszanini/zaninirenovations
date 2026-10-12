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
            ->assertSee('data-ra-fullpage', false)
            ->assertSee('util-ra--fullpage', false)
            ->assertSee('id="ra-full-room-name"', false)
            ->assertSee('All utilities')
            ->assertSee('data-ra-save-room', false)
            ->assertSee('data-ra-zoom-in', false)
            ->assertSee('data-ra-zoom-out', false)
            ->assertSee('data-ra-zoom-100', false)
            ->assertSee('data-ra-zoom-reset', false)
            ->assertSee('data-ra-side', false)
            ->assertSee('data-ra-sheet-handle', false)
            ->assertSee('util-ra-full-header__back-label', false)
            ->assertSee('data-ra-project-select', false)
            ->assertSee('New project')
            ->assertSee('data-ra-link-client-project', false)
            ->assertSee('Testing full page room area')
            ->assertSee('admin-dark.css', false)
            ->assertSee('id="admin-app"', false)
            ->getContent();

        $this->assertStringContainsString(route('admin.utilities.index', [], false), $html);
        $this->assertStringContainsString('data-ra-fullpage="1"', $html);
        $this->assertStringNotContainsString('data-ra-lock-client-project', $html);
        $this->assertStringNotContainsString('admin-toolbar-lede', $html);
        $this->assertStringNotContainsString('Draw room outlines on a grid', $html);
    }

    public function test_utilities_index_room_area_card_opens_full_page_route(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.utilities.index'))
            ->assertOk()
            ->assertSee('Room Area')
            ->assertSee(route('admin.utilities.room-area', [], false), false);
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
            ->assertSee('data-ra-fullpage', false)
            ->assertSee('data-ra-lock-client-project="'.$project->id.'"', false)
            ->assertSee('Back to project')
            ->assertDontSee('All utilities');
    }

    public function test_standalone_full_page_assets_keep_picker_and_hide_admin_chrome(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString("get('ra_project')", $js);
        $this->assertStringContainsString("get('ra_room')", $js);
        $this->assertStringContainsString('syncGuideLinks', $js);
        $this->assertStringContainsString('data-ra-lock-client-project', $js);
        $this->assertStringContainsString('data-ra-fullpage', $js);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.admin-app:has(.util-ra--fullpage) .admin-sidebar', $css);
        $this->assertStringContainsString('.admin-app:has(.util-ra--fullpage) .admin-topbar', $css);
        $this->assertStringContainsString('.util-ra--fullpage[data-ra-lock-client-project] .util-ra-project-bar', $css);
        $this->assertStringContainsString('.util-ra--fullpage:not([data-ra-lock-client-project]) .util-ra-project-bar', $css);
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
            'scale_pixels_per_foot' => 10,
            'area_sqft' => 96,
            'wall_height_ft' => 8,
            'vertices' => $canvas,
        ]);

        $metrics = app(RoomAreaCanvasData::class)->layoutMetrics(
            $room->canvasData(),
            10,
            8
        );
        $room->update(['area_sqft' => $metrics['area_sqft']]);

        return $room->fresh();
    }

    private function makeRoomOnClientProject(ClientProject $clientProject, string $name): RoomAreaRoom
    {
        $layout = RoomAreaProject::create([
            'name' => $clientProject->name.' rooms',
            'client_project_id' => $clientProject->id,
            'scale_pixels_per_foot' => 10,
        ]);

        return $this->makeRoom($layout, $name);
    }
}
