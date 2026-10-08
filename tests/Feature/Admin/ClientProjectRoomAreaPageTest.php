<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaQuoteQuantities;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectRoomAreaPageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_open_client_project_room_area_page(): void
    {
        $project = ClientProject::factory()->create();

        $this->get(route('admin.client-projects.utilities.room-area', $project))
            ->assertRedirect();
    }

    public function test_admin_can_open_full_page_room_area_for_project(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create(['name' => 'Kitchen remodel']);
        $room = $this->makeRoom($project, 'Kitchen');

        $this->get(route('admin.client-projects.utilities.room-area', $project))
            ->assertOk()
            ->assertViewIs('admin.client_projects.room_area')
            ->assertSee('data-ra-fullpage', false)
            ->assertSee('id="ra-full-room-name"', false)
            ->assertSee('Back to project')
            ->assertSee('data-ra-save-room', false)
            ->assertSee('data-ra-zoom-in', false)
            ->assertSee('data-ra-zoom-out', false)
            ->assertSee('data-ra-zoom-100', false)
            ->assertSee('data-ra-zoom-reset', false)
            ->assertSee('data-ra-side', false)
            ->assertSee('data-ra-sheet-handle', false)
            ->assertSee('util-ra-full-header__back-label', false)
            ->assertSee('Kitchen remodel')
            ->assertSee('Kitchen')
            ->assertSee('admin-dark.css', false)
            ->assertSee('id="admin-app"', false)
            ->assertSee('util-ra--chrome-v2', false)
            ->assertSee('data-ra-chrome', false)
            ->assertSee('data-ra-help-guide', false)
            ->assertSee('data-ra-first-tip', false)
            ->assertSee('util-ra-tool-group--view', false)
            ->assertSee('util-ra-tool-group--draw', false)
            ->assertSee('util-ra-tool-group--measure', false)
            ->assertSee('util-ra-tool-group--room-tools', false)
            ->assertSee('Draw / Edit')
            ->assertSee('Measure / Units')
            ->assertSee('Room tools')
            ->assertSee('Objects / Walls')
            ->assertSee('Coming soon')
            ->assertSee('data-ra-coming-soon="templates"', false)
            ->assertSee('data-ra-coming-soon="lock-layers"', false)
            ->assertSee(route('admin.client-projects.utilities.room-area.guide', $project, false), false);
    }

    public function test_admin_can_open_existing_room_on_full_page(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $room = $this->makeRoom($project, 'Dining');

        $this->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))
            ->assertOk()
            ->assertSee('data-ra-initial-room-id="'.$room->id.'"', false)
            ->assertSee('Dining')
            ->assertSee('data-ra-type-length', false)
            ->assertSee('data-ra-live-baseboard', false)
            ->assertSee('data-ra-photos', false);
    }

    public function test_missing_client_project_returns_404(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/client-projects/999999/utilities/room-area')
            ->assertNotFound();
    }

    public function test_room_from_another_project_returns_404(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $other = ClientProject::factory()->create();
        $room = $this->makeRoom($other, 'Other room');

        $this->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))->assertNotFound();
    }

    public function test_missing_room_returns_404(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $this->makeRoom($project, 'Kitchen');

        $this->get('/admin/client-projects/'.$project->id.'/utilities/room-area/999999')
            ->assertNotFound();
    }

    public function test_page_payload_keeps_saved_room_metrics_and_quote_quantities(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $room = $this->makeRoom($project, 'Living');

        $before = $room->layoutMetrics();
        $this->assertGreaterThan(0, $before['baseboard_lf']);
        $this->assertGreaterThan(0, $before['wall_area_sqft']);

        $response = $this->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]));
        $response->assertOk();

        $projectsJson = $response->viewData('projectsJson');
        $payloadRoom = $projectsJson[0]['rooms'][0];
        $this->assertEqualsWithDelta($before['baseboard_lf'], $payloadRoom['metrics']['baseboard_lf'], 0.001);
        $this->assertEqualsWithDelta($before['wall_area_sqft'], $payloadRoom['metrics']['wall_area_sqft'], 0.001);
        $this->assertEqualsWithDelta($before['area_sqft'], $payloadRoom['metrics']['area_sqft'], 0.001);

        $after = $room->fresh()->layoutMetrics();
        $this->assertEqualsWithDelta($before['baseboard_lf'], $after['baseboard_lf'], 0.001);
        $this->assertEqualsWithDelta($before['wall_area_sqft'], $after['wall_area_sqft'], 0.001);

        $mapper = app(RoomAreaQuoteQuantities::class);
        $flooring = $mapper->line(RoomAreaQuoteQuantities::TRADE_FLOORING, $after, 0);
        $baseboard = $mapper->line(RoomAreaQuoteQuantities::TRADE_BASEBOARD, $after, 0);
        $this->assertNotNull($flooring);
        $this->assertNotNull($baseboard);

        $this->get(route('admin.utilities.room-area.quote', ['layout' => 'room:'.$room->id]))
            ->assertOk()
            ->assertSee('Create quote', false)
            ->assertSee('lf perimeter', false)
            ->assertSee(route('admin.client-projects.utilities.room-area', [
                'client_project' => $project,
                'room_area_room' => $room,
            ], false), false);

        $this->get(route('admin.quotations.flooring.create', [
            'room_area_import_id' => 'room:'.$room->id,
        ]))
            ->assertOk()
            ->assertSee('Measurements filled from Room Area', false);
    }

    public function test_utilities_tab_buttons_open_full_page_not_canvas(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $room = $this->makeRoom($project, 'Office');

        $html = $this->get(route('admin.client-projects.show', $project).'#utilities')
            ->assertOk()
            ->getContent();

        $pageUrl = route('admin.client-projects.utilities.room-area', $project, false);
        $roomUrl = route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ], false);

        $this->assertStringContainsString($pageUrl, $html);
        $this->assertStringContainsString($roomUrl, $html);
        $this->assertStringContainsString('Open Room Area tool', $html);
        $this->assertStringNotContainsString('data-ra-canvas-wrap', $html);
        $this->assertStringNotContainsString('id="ra-konva-container"', $html);
    }

    public function test_room_area_js_zooms_without_requiring_shift(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);
        $this->assertStringNotContainsString('!e.shiftKey) return', $js);
        $this->assertStringContainsString('data-ra-fullpage', $js);
        $this->assertStringContainsString('bindSidePanel', $js);
        $this->assertStringContainsString('bindSheetHandle', $js);
        $this->assertStringContainsString('bindHelpPops', $js);
        $this->assertStringContainsString('bindFirstTip', $js);
        $this->assertStringContainsString('zr-room-area-guide-tip-v1', $js);
        $this->assertStringContainsString('drawGrid();', $js);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('width: 25rem', $css);
        $this->assertStringContainsString('--ra-sheet-h', $css);
        $this->assertStringContainsString('min-height: 45vh', $css);
        $this->assertStringContainsString('.admin-app:has(.util-ra--fullpage) .admin-sidebar', $css);
        $this->assertStringContainsString('.admin-app:has(.util-ra--fullpage) .admin-topbar', $css);
        $this->assertStringContainsString('.admin-app:has(.util-ra-guide) .admin-sidebar', $css);
        $this->assertStringContainsString('.util-ra-help-pop', $css);
        $this->assertStringContainsString('.util-ra-first-tip', $css);
        $this->assertStringContainsString('.util-ra-tool-group--measure', $css);
        $this->assertStringContainsString('.util-ra--fullpage .util-ra-canvas-toolbar .util-ra-scale-status', $css);
    }

    public function test_full_page_editor_chrome_includes_help_link_and_grouped_tools(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $room = $this->makeRoom($project, 'Bath');

        $html = $this->get(route('admin.client-projects.utilities.room-area', [
            'client_project' => $project,
            'room_area_room' => $room,
        ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-ra-help-guide', $html);
        $this->assertStringContainsString('util-ra-full-header__help', $html);
        $this->assertStringContainsString('Guide', $html);
        $this->assertStringContainsString('data-ra-help-pop="view"', $html);
        $this->assertStringContainsString('data-ra-help-pop="measure"', $html);
        $this->assertStringContainsString('data-ra-help-pop="objects"', $html);
        $this->assertStringContainsString('data-ra-first-tip-dismiss', $html);
        $guideUrl = route('admin.client-projects.utilities.room-area.guide', [
            'client_project' => $project,
            'room_area_room' => $room,
        ], false);
        $this->assertStringContainsString($guideUrl, $html);
    }

    /**
     * 12' x 8' rectangle at 10 px/ft with a 3' door.
     */
    private function makeRoom(ClientProject $clientProject, string $name): RoomAreaRoom
    {
        $layout = RoomAreaProject::create([
            'name' => $clientProject->name.' rooms',
            'client_project_id' => $clientProject->id,
            'scale_pixels_per_foot' => 10,
        ]);

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
            'objects' => [[
                'id' => 'obj_1',
                'type' => 'door',
                'subtype' => 'single',
                'wall_id' => 'wall_p1_0_1',
                'width' => 36,
                'offset' => 24,
                'swing' => 'right_out',
                'hinge' => 'right',
            ]],
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
}
