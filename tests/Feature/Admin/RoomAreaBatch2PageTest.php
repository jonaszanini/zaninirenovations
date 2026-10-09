<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaFinishMath;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaBatch2PageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_math_tab_exposes_finish_fields_and_help_tip(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-ceiling-height', false)
            ->assertSee('data-ra-floor-waste', false)
            ->assertSee('data-ra-floor-box', false)
            ->assertSee('data-ra-floor-dir="0"', false)
            ->assertSee('data-ra-floor-dir="90"', false)
            ->assertSee('data-ra-wall-list', false)
            ->assertSee('data-ra-live-ceiling-area', false)
            ->assertSee('data-ra-live-boxes', false)
            ->assertSee('data-ra-live-casing', false)
            ->assertSee('data-ra-object-casing', false)
            ->assertSee('data-ra-object-door-face', false)
            ->assertSee('Both sides')
            ->assertSee('Interior')
            ->getContent();

        $this->assertStringContainsString('data-ra-help-pop="finish"', $html);
        $this->assertStringContainsString('data-ra-help-pop-panel="finish"', $html);
        $this->assertStringContainsString('util-ra--fullpage', $html);
        $this->assertStringNotContainsString('admin-toolbar-lede', $html);
    }

    public function test_assets_wire_finish_math_and_friendly_plan_errors(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertNotFalse($js);
        $this->assertStringContainsString('function ceilingHeightFt()', $js);
        $this->assertStringContainsString('function perWallNetAreas(', $js);
        $this->assertStringContainsString('RA.perWallNetAreas(', $js);
        $this->assertStringContainsString('function finishSnapshot(', $js);
        $this->assertStringContainsString('function applyFinishSnapshot(', $js);
        $this->assertStringContainsString('parseWastePercent(', $js);
        $this->assertStringContainsString('Net wall area', $js);
        $this->assertStringContainsString('function redrawFlooringDirection(', $js);
        $this->assertStringContainsString('RA.flooringBoxes(', $js);
        $this->assertStringContainsString('RA.totalCasingLf(', $js);
        $this->assertStringContainsString('RA.friendlyPlanUploadError(e)', $js);
        $this->assertStringContainsString('RA.planUploadErrorAfterRoomSaved(err)', $js);
        $this->assertStringNotContainsString("friendlyNetworkError(err, 'Could not upload the floor plan. Check your connection and try again.')", $js);
        $this->assertStringNotContainsString("friendlyNetworkError(e, 'Could not upload the floor plan. Check your connection and try again.')", $js);

        $lib = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertNotFalse($lib);
        $this->assertStringContainsString('function flooringBoxes(', $lib);
        $this->assertStringContainsString('function casingLf(', $lib);
        $this->assertStringContainsString('function netWallAreaSqFt(', $lib);
        $this->assertStringContainsString('function planUploadReason(', $lib);
        $this->assertStringContainsString('Wrong file type.', $lib);
        $this->assertStringContainsString('function parseWastePercent(', $lib);
        $this->assertStringContainsString('function perWallNetAreas(', $lib);
        $this->assertStringContainsString('function isWrongFileTypeError(', $lib);
        $this->assertStringContainsString('The server had a problem.', $lib);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.util-ra-finish__grid', $css);
        $this->assertStringContainsString('grid-template-columns: 1fr 1fr', $css);

        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertNotFalse($dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-finish', $dark);
    }

    public function test_store_and_reload_persists_finish_takeoffs(): void
    {
        $this->actingAsAdmin();
        $layout = RoomAreaProject::create([
            'name' => 'Batch 2 finish',
            'scale_pixels_per_foot' => 12,
        ]);

        $canvas = [
            'v' => 3,
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
            'objects' => [
                [
                    'id' => 'obj_1',
                    'type' => 'door',
                    'subtype' => 'single',
                    'wall_id' => 'wall_p1_0_1',
                    'width' => 36,
                    'offset' => 24,
                    'casing_sides' => 2,
                ],
                [
                    'id' => 'obj_2',
                    'type' => 'window',
                    'subtype' => 'fixed',
                    'wall_id' => 'wall_p1_1_2',
                    'width' => 48,
                    'offset' => 24,
                    'height' => 36,
                    'casing_sides' => 4,
                ],
            ],
        ];

        $response = $this->postJson(route('admin.utilities.room-area.rooms.store', $layout), [
            'name' => 'Living',
            'canvas' => $canvas,
            'scale_pixels_per_foot' => 12,
            'wall_height_ft' => 8,
            'ceiling_height_ft' => 9,
            'flooring_waste_percent' => 10,
            'flooring_direction' => 90,
            'flooring_box_sqft' => 20,
        ])->assertOk()->assertJsonPath('ok', true);

        $room = RoomAreaRoom::query()->findOrFail($response->json('room.id'));
        $this->assertSame(9.0, (float) $room->ceiling_height_ft);
        $this->assertSame(10.0, (float) $room->flooring_waste_percent);
        $this->assertSame(90, (int) $room->flooring_direction);
        $this->assertSame(20.0, (float) $room->flooring_box_sqft);

        $metrics = $response->json('room.metrics');
        $this->assertEquals(9.0, $metrics['ceiling_height_ft']);
        $this->assertEquals(120.0, $metrics['ceiling_area_sqft']);
        $this->assertSame(7, $metrics['flooring_boxes']);
        $this->assertSame(90, $metrics['flooring_direction']);
        $this->assertCount(4, $metrics['walls']);
        $this->assertSame($metrics['net_wall_area_sqft'], $metrics['wall_area_sqft']);
        $this->assertGreaterThan(0, $metrics['net_wall_area_sqft']);
        $this->assertGreaterThan(0, $metrics['casing_lf']);

        $math = new RoomAreaFinishMath;
        $expectedBoxes = $math->flooringBoxes(120, 10, 20);
        $this->assertSame($expectedBoxes, $metrics['flooring_boxes']);
        $this->assertEqualsWithDelta(
            $math->casingLf(36, 80, 2, 'door') + $math->casingLf(48, 36, 4, 'window'),
            $metrics['casing_lf'],
            0.01
        );

        $doorWall = collect($metrics['walls'])->firstWhere('id', 'wall_p1_0_1');
        $this->assertNotNull($doorWall);
        $this->assertEqualsWithDelta(108.0 - $math->openingAreaSqFt(36, 80), $doorWall['net_area_sqft'], 0.05);
    }
}
