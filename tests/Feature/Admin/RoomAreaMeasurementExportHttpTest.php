<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\RoomAreaMeasurementExport;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use ZipArchive;

class RoomAreaMeasurementExportHttpTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_csv_lists_rooms_columns_and_phase_totals(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProject();

        $response = $this->get(route('admin.utilities.room-area.projects.export.csv', $project));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->getContent();
        $header = str_getcsv(strtok($csv, "\n"));
        $this->assertSame(RoomAreaMeasurementExport::COLUMNS, $header);
        $this->assertStringContainsString('Kitchen', $csv);
        $this->assertStringContainsString('Bath', $csv);
        $this->assertStringContainsString('Rough-in', $csv);
        $this->assertStringContainsString('Unassigned', $csv);
        $this->assertStringContainsString('Project total', $csv);
        $this->assertStringContainsString('Maple kitchen', $csv);
        $this->assertStringContainsString('Pat Owner', $csv);
        $this->assertMatchesRegularExpression('/Rough-in,Kitchen,120(\.0+)?,44/', $csv);

        $rows = app(RoomAreaMeasurementExport::class)->rows($project);
        $this->assertSame(RoomAreaMeasurementExport::COLUMNS[0], 'Phase');
        $this->assertSame('Rough-in', $rows[0][0]);
        $this->assertSame('Kitchen', $rows[0][1]);
        $this->assertEquals(120.0, $rows[0][2]);
        $this->assertEquals(44.0, $rows[0][3]);
        $this->assertGreaterThan(0, $rows[0][4]);
        $this->assertEquals(132.0, $rows[0][5]);
        $this->assertGreaterThan(0, $rows[0][6]);
    }

    public function test_xlsx_contains_room_sheet_and_headers(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProject();

        $response = $this->get(route('admin.utilities.room-area.projects.export.xlsx', $project));
        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('content-type')
        );

        $tmp = tempnam(sys_get_temp_dir(), 'ra-xlsx-test-');
        file_put_contents($tmp, $response->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertNotFalse($sheet);
        $this->assertStringContainsString('Phase', $sheet);
        $this->assertStringContainsString('Area (sq ft)', $sheet);
        $this->assertStringContainsString('Net wall (sq ft)', $sheet);
        $this->assertStringContainsString('Kitchen', $sheet);
        $this->assertStringContainsString('Rough-in', $sheet);
        $this->assertStringContainsString('Project total', $sheet);
    }

    public function test_assigning_a_phase_leaves_room_geometry_unchanged(): void
    {
        $this->actingAsAdmin();
        $project = $this->makeProject();
        $bath = $project->rooms->firstWhere('name', 'Bath');
        $phase = $project->phases->first();
        $vertices = $bath->vertices;
        $area = $bath->area_sqft;

        $this->patchJson(route('admin.utilities.room-area.rooms.phase', $bath), [
            'phase_id' => $phase->id,
        ])->assertOk()->assertJsonPath('ok', true);

        $bath->refresh();
        $this->assertSame($phase->id, (int) $bath->phase_id);
        $this->assertSame($vertices, $bath->vertices);
        $this->assertEquals($area, (float) $bath->area_sqft);
    }

    private function makeProject(): RoomAreaProject
    {
        $client = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $project = RoomAreaProject::create([
            'name' => 'Maple kitchen',
            'client_project_id' => $client->id,
            'scale_pixels_per_foot' => 12,
        ]);
        $phase = RoomAreaPhase::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rough-in',
            'sort_order' => 1,
        ]);
        $this->makeRoom($project, 'Kitchen', 144, 120, $phase->id, [
            'id' => 'obj_1',
            'type' => 'door',
            'subtype' => 'single',
            'wall_id' => 'wall_p1_0_1',
            'width' => 36,
            'offset' => 12,
        ]);
        $this->makeRoom($project, 'Bath', 96, 96);

        return $project->fresh(['phases', 'rooms', 'clientProject']);
    }

    private function makeRoom(
        RoomAreaProject $project,
        string $name,
        float $widthPx,
        float $heightPx,
        ?int $phaseId = null,
        ?array $door = null
    ): RoomAreaRoom {
        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'phase_id' => $phaseId,
            'name' => $name,
            'scale_pixels_per_foot' => 12,
            'area_sqft' => ($widthPx / 12) * ($heightPx / 12),
            'wall_height_ft' => 8,
            'flooring_waste_percent' => 10,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => $widthPx, 'y' => 0],
                            ['x' => $widthPx, 'y' => $heightPx],
                            ['x' => 0, 'y' => $heightPx],
                        ],
                    ]],
                ]],
                'objects' => $door ? [$door] : [],
            ],
        ]);
    }
}
