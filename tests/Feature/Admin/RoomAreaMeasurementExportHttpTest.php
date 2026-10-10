<?php

namespace Tests\Feature\Admin;

use App\Services\RoomAreaMeasurementExport;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\MakesRoomAreaPlanFixture;
use ZipArchive;

class RoomAreaMeasurementExportHttpTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesRoomAreaPlanFixture;

    public function test_csv_lists_rooms_columns_phase_totals_bom_and_two_decimals(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $project = $fixture['project'];

        $response = $this->get(route('admin.utilities.room-area.projects.export.csv', $project));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $csv = $response->getContent();
        $this->assertStringStartsWith(RoomAreaMeasurementExport::UTF8_BOM, $csv);
        $withoutBom = substr($csv, strlen(RoomAreaMeasurementExport::UTF8_BOM));
        $header = str_getcsv(strtok($withoutBom, "\n"));
        $this->assertSame(RoomAreaMeasurementExport::COLUMNS, $header);
        $this->assertStringContainsString('Kitchen', $csv);
        $this->assertStringContainsString('Bath', $csv);
        $this->assertStringContainsString('Rough-in', $csv);
        $this->assertStringContainsString('Unassigned', $csv);
        $this->assertStringContainsString('Project total', $csv);
        $this->assertStringContainsString('Maple kitchen', $csv);
        $this->assertStringContainsString('Pat Owner', $csv);
        $this->assertMatchesRegularExpression('/Rough-in,Kitchen,120\\.00,44\\.00,332\\.00,132\\.00,32\\.67/', $withoutBom);
        $this->assertMatchesRegularExpression('/Unassigned,Bath,64\\.00,32\\.00,256\\.00,70\\.40,0\\.00/', $withoutBom);
        $this->assertMatchesRegularExpression('/"?Project total"?,,184\\.00,76\\.00,588\\.00,202\\.40,32\\.67/', $withoutBom);

        $rows = app(RoomAreaMeasurementExport::class)->rows($project);
        $this->assertSame('Rough-in', $rows[0][0]);
        $this->assertSame('Kitchen', $rows[0][1]);
        $this->assertEquals(120.0, $rows[0][2]);
        $this->assertEquals(44.0, $rows[0][3]);
        $this->assertEquals(332.0, $rows[0][4]);
        $this->assertEquals(132.0, $rows[0][5]);
        $this->assertEquals(32.67, $rows[0][6]);
    }

    public function test_csv_prefixes_formula_injection_and_keeps_non_ascii_and_long_names(): void
    {
        $this->actingAsAdmin();
        $long = 'José Ñoño '.str_repeat('測', 80);
        $fixture = $this->makePlanFixture([
            'name' => '=HYPERLINK("http://evil.example")',
        ]);
        $fixture['client']->update(['client_name' => '+2+3+cmd']);
        $fixture['kitchen']->update(['name' => $long]);
        $fixture['bath']->update(['name' => "@SUM(A1)\t"]);
        $fixture['phase']->update(['name' => "-1+1"]);

        $csv = $this->get(route('admin.utilities.room-area.projects.export.csv', $fixture['project']->fresh(['rooms', 'phases', 'clientProject'])))
            ->assertOk()
            ->getContent();
        $this->assertStringStartsWith(RoomAreaMeasurementExport::UTF8_BOM, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+2+3+cmd", $csv);
        $this->assertStringContainsString("'-1+1", $csv);
        $this->assertStringContainsString("'@SUM(A1)", $csv);
        $this->assertStringContainsString($long, $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    public function test_xlsx_contains_room_sheet_and_headers(): void
    {
        $this->actingAsAdmin();
        $project = $this->makePlanFixture()['project'];

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
        $this->assertStringContainsString('332.00', $sheet);
        $this->assertStringContainsString('32.67', $sheet);
        $this->assertStringContainsString('t="n"><v>120.00</v>', $sheet);
        $this->assertStringContainsString('t="n"><v>332.00</v>', $sheet);
        $this->assertStringContainsString('t="inlineStr"', $sheet);
    }

    public function test_xlsx_keeps_formula_text_as_strings_without_apostrophe(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture(['name' => '=HYPERLINK("http://evil.example")']);
        $fixture['kitchen']->update(['name' => '+2+3']);

        $tmp = tempnam(sys_get_temp_dir(), 'ra-xlsx-inj-');
        file_put_contents($tmp, $this->get(route('admin.utilities.room-area.projects.export.xlsx', $fixture['project']->fresh(['rooms', 'phases', 'clientProject'])))->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($tmp);

        $this->assertStringContainsString('t="inlineStr"><is><t>=HYPERLINK', $sheet);
        $this->assertStringContainsString('t="inlineStr"><is><t>+2+3</t>', $sheet);
        $this->assertStringNotContainsString("'=HYPERLINK", $sheet);
        $this->assertStringNotContainsString("'+2+3", $sheet);
        $this->assertStringContainsString('t="n"><v>', $sheet);
    }

    public function test_assigning_a_phase_leaves_room_geometry_unchanged(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $bath = $fixture['bath'];
        $phase = $fixture['phase'];
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
}
