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

    public function test_assets_wire_finish_math_and_friendly_plan_errors(): void
    {
        $lib = file_get_contents(public_path('js/room-area-lib.js'));
        $this->assertNotFalse($lib);
        $this->assertStringContainsString('function flooringBoxes(', $lib);
        $this->assertStringContainsString('Math.ceil(roundTo(needed / box, 9))', $lib);
        $this->assertStringContainsString('function casingLf(', $lib);
        $this->assertStringContainsString('function netWallAreaSqFt(', $lib);
        $this->assertStringContainsString('function unionOpeningAreaRawSqFt(', $lib);
        $this->assertStringContainsString('netWallAreaSqFt(lengthFt, height, deductRaw)', $lib);
        $this->assertStringContainsString('LEGACY_OPENING_HEIGHT_IN', $lib);
        $this->assertStringContainsString('DEFAULT_OPENING_HEIGHT_IN', $lib);
        $this->assertStringContainsString('Math.abs(shifted) + 1e-9', $lib);
        $this->assertStringContainsString('function isUnlockLayersError(', $lib);
        $this->assertStringContainsString('function isSimplePolygon(', $lib);
        $this->assertStringContainsString('function clampOpeningOnWall(', $lib);
        $this->assertStringContainsString('function isTooLargeError(', $lib);
        $this->assertStringContainsString('function planUploadReason(', $lib);
        $this->assertStringContainsString('Wrong file type.', $lib);
        $this->assertStringContainsString('function parseWastePercent(', $lib);
        $this->assertStringContainsString('function perWallNetAreas(', $lib);
        $this->assertStringContainsString('function isWrongFileTypeError(', $lib);
        $this->assertStringContainsString('The server had a problem.', $lib);

        $php = file_get_contents(app_path('Services/RoomAreaFinishMath.php'));
        $this->assertNotFalse($php);
        $this->assertStringContainsString('ceil($this->roundTo($needed / $box, 9))', $php);
        $this->assertStringContainsString('abs($shifted) + 1e-9', $php);
        $this->assertStringContainsString('function unionOpeningAreaRawSqFt(', $php);
        $this->assertStringContainsString('net_area_sqft_exact', $php);
        $this->assertStringContainsString('$wall[\'net_area_sqft_exact\']', $php);
        $this->assertStringContainsString('is_string($rawSides)', $php);
        $this->assertStringContainsString("preg_match('/[eE]/', \$raw)", $php);
        $this->assertStringContainsString('LEGACY_OPENING_HEIGHT_IN', $php);

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
