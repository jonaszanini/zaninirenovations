<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaPdfExportTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_pdf_export_route_returns_a_pdf(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.utilities.room-area.pdf'), [
            'title' => 'Rec room — Room plan',
            'summary' => 'Wall metrics from the Room Area drawing.',
            'diagram' => self::TINY_PNG,
            'rows' => [
                ['label' => 'Floor area', 'value' => '80 sq ft'],
                ['label' => 'Perimeter', 'value' => '36 lf'],
                ['label' => 'Baseboard', 'value' => '30 lf'],
                ['label' => 'Wall area', 'value' => '256 sq ft'],
            ],
            'objects' => [
                ['type' => 'door', 'subtype' => 'single', 'width' => 36, 'offset' => 12],
                ['type' => 'window', 'subtype' => 'fixed', 'width' => 48, 'offset' => 12],
            ],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_export_can_attach_to_a_saved_room(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');

        $clientProject = ClientProject::factory()->create();
        $project = RoomAreaProject::create([
            'name' => 'Attach project',
            'client_project_id' => $clientProject->id,
            'scale_pixels_per_foot' => 12,
        ]);
        $room = RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Office',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 2,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ]],
            ],
        ]);

        $this->post(route('admin.utilities.room-area.pdf'), [
            'title' => 'Office — Room plan',
            'rows' => [
                ['label' => 'Floor area', 'value' => '80 sq ft'],
            ],
            'diagram' => self::TINY_PNG,
            'room_id' => $room->id,
            'attach' => '1',
        ])->assertOk();

        $room->refresh();
        $this->assertNotEmpty($room->pdf_path);
        Storage::disk('local')->assertExists($room->pdf_path);

        $this->get(route('admin.utilities.room-area.rooms.pdf', $room))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
