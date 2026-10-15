<?php

namespace Tests\Unit\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\OpenPlan3DMeasurementBridge;
use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaDrawingImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomAreaDrawingImporterTest extends TestCase
{
    use RefreshDatabase;

    private RoomAreaDrawingImporter $importer;

    private OpenPlan3DMeasurementBridge $bridge;

    private RoomAreaProject $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bridge = app(OpenPlan3DMeasurementBridge::class);
        $this->importer = app(RoomAreaDrawingImporter::class);

        $this->project = RoomAreaProject::create([
            'name' => 'Importer Test Project',
            'scale_pixels_per_foot' => 20.0,
            'wall_height_ft' => 8.0,
        ]);
    }

    public function test_converts_legacy_point_list_drawing(): void
    {
        // 10ft x 10ft rectangle = 200px x 200px at 20 px/ft
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Legacy Room',
            'scale_pixels_per_foot' => 20.0,
            'wall_height_ft' => 8.0,
            'area_sqft' => 100.0,
            'vertices' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 200.0, 'y' => 0.0],
                ['x' => 200.0, 'y' => 200.0],
                ['x' => 0.0, 'y' => 200.0],
            ],
        ]);

        $result = $this->importer->convert($room);

        $this->assertTrue($result['ok']);
        $this->assertSame('converted', $result['status']);
        $this->assertEquals(100.0, $result['new_area']);
        $this->assertTrue($this->bridge->isPlan($result['plan']));
    }

    public function test_converts_grouped_canvas_drawing_with_doors_and_windows(): void
    {
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Grouped Room with Openings',
            'scale_pixels_per_foot' => 20.0,
            'wall_height_ft' => 9.0,
            'ceiling_height_ft' => 9.0,
            'area_sqft' => 300.0, // 15ft x 20ft = 300 sqft
            'vertices' => [
                'v' => 4,
                'groups' => [
                    [
                        'id' => 'g1',
                        'color' => '#024768',
                        'polygons' => [
                            [
                                'id' => 'p1',
                                'vertices' => [
                                    ['x' => 0.0, 'y' => 0.0],
                                    ['x' => 300.0, 'y' => 0.0], // 15ft
                                    ['x' => 300.0, 'y' => 400.0], // 20ft
                                    ['x' => 0.0, 'y' => 400.0],
                                ],
                            ],
                        ],
                    ],
                ],
                'objects' => [
                    [
                        'id' => 'd1',
                        'type' => 'door',
                        'wall_id' => 'wall_p1_0_1',
                        'width' => 36.0,
                        'height' => 80.0,
                        'offset' => 24.0,
                    ],
                ],
            ],
        ]);

        $result = $this->importer->convert($room);

        $this->assertTrue($result['ok']);
        $this->assertSame('converted', $result['status']);
        $this->assertEquals(300.0, $result['new_area']);
        $this->assertTrue($this->bridge->isPlan($result['plan']));

        $metrics = $this->bridge->layoutMetrics($result['plan']);
        $this->assertSame(1, $metrics['door_count']);
    }

    public function test_skips_room_already_in_openplan3d_format(): void
    {
        $plan = $this->bridge->fromCanvasData([
            'v' => 2,
            'groups' => [
                [
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [
                        [
                            'id' => 'p1',
                            'vertices' => [
                                ['x' => 0.0, 'y' => 0.0],
                                ['x' => 200.0, 'y' => 0.0],
                                ['x' => 200.0, 'y' => 200.0],
                                ['x' => 0.0, 'y' => 200.0],
                            ],
                        ],
                    ],
                ],
            ],
        ], 20.0);

        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Already openPlan3D',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 100.0,
            'vertices' => $plan,
        ]);

        $result = $this->importer->convert($room, force: false);

        $this->assertTrue($result['ok']);
        $this->assertSame('skipped', $result['status']);
        $this->assertStringContainsString('already in openPlan3D format', $result['message']);
    }

    public function test_fails_with_explicit_reason_when_scale_is_missing(): void
    {
        // Project with 0 scale and room with null scale
        $zeroProject = RoomAreaProject::create(['name' => 'Zero Scale Project', 'scale_pixels_per_foot' => 0]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $zeroProject->id,
            'name' => 'Unscaled Room',
            'scale_pixels_per_foot' => null,
            'area_sqft' => 0.0,
            'vertices' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 100.0, 'y' => 0.0],
                ['x' => 100.0, 'y' => 100.0],
                ['x' => 0.0, 'y' => 100.0],
            ],
        ]);

        $result = $this->importer->convert($room);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('no valid scale', $result['message']);
    }

    public function test_fails_with_explicit_reason_on_self_intersecting_polygon(): void
    {
        // Figure-8 bowtie self-intersecting polygon
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Bowtie Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 0.0,
            'vertices' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 200.0, 'y' => 200.0],
                ['x' => 200.0, 'y' => 0.0],
                ['x' => 0.0, 'y' => 200.0],
            ],
        ]);

        $result = $this->importer->convert($room);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('validation failed', $result['message']);
    }

    public function test_fails_with_explicit_reason_on_empty_drawing(): void
    {
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Empty Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 0.0,
            'vertices' => [],
        ]);

        $result = $this->importer->convert($room);

        $this->assertFalse($result['ok']);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Drawing data is empty', $result['message']);
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Dry Run Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 100.0,
            'vertices' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 200.0, 'y' => 0.0],
                ['x' => 200.0, 'y' => 200.0],
                ['x' => 0.0, 'y' => 200.0],
            ],
        ]);

        $report = $this->importer->run(dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['converted']);

        $room->refresh();
        $this->assertNull($room->legacy_canvas_data);
        $this->assertNull($room->openplan3d_import_meta);
        $this->assertTrue(array_is_list($room->vertices)); // Still old array of points
    }

    public function test_real_run_updates_database_and_preserves_legacy_canvas_backup(): void
    {
        $originalVertices = [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => 200.0, 'y' => 0.0],
            ['x' => 200.0, 'y' => 200.0],
            ['x' => 0.0, 'y' => 200.0],
        ];

        $room = RoomAreaRoom::create([
            'room_area_project_id' => $this->project->id,
            'name' => 'Real Run Room',
            'scale_pixels_per_foot' => 20.0,
            'area_sqft' => 100.0,
            'vertices' => $originalVertices,
        ]);

        $report = $this->importer->run(dryRun: false);

        $this->assertFalse($report['dry_run']);
        $this->assertSame(1, $report['converted']);
        $this->assertSame(0, $report['failed']);

        $room->refresh();
        $this->assertNotNull($room->legacy_canvas_data);
        $this->assertEquals($originalVertices, $room->legacy_canvas_data);
        $this->assertTrue($this->bridge->isPlan($room->vertices));
        $this->assertEquals(100.0, $room->area_sqft);

        $this->assertNotNull($room->openplan3d_import_meta);
        $this->assertEquals(100.0, $room->openplan3d_import_meta['original_area_sqft']);
        $this->assertEquals(100.0, $room->openplan3d_import_meta['converted_area_sqft']);
    }
}
