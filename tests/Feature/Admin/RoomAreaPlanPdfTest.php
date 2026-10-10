<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\RoomAreaPhase;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Models\StandardQuotation;
use App\Services\RoomAreaPlanDocument;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaPlanPdfTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_plan_pdf_includes_brand_rooms_totals_phases_and_signature(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makeFixture();

        $document = app(RoomAreaPlanDocument::class)->build($fixture['project']);
        $this->assertSame('Zanini Renovations', $document['brand']);
        $this->assertNotEmpty($document['logo_data_uri']);
        $this->assertSame('Maple kitchen', $document['project_name']);
        $this->assertSame('Pat Owner', $document['client_name']);
        $this->assertSame('Kitchen reno', $document['client_project_name']);
        $this->assertEquals(184.0, $document['totals']['area_sqft']);
        $this->assertEquals(76.0, $document['totals']['perimeter_lf']);
        $this->assertGreaterThan(0, $document['totals']['net_wall_area_sqft']);
        $this->assertEquals(202.4, $document['totals']['flooring_with_waste_sqft']);
        $this->assertSame('Rough-in', $document['sections'][0]['name']);
        $this->assertEquals(120.0, $document['sections'][0]['totals']['area_sqft']);
        $this->assertSame('Kitchen', $document['sections'][0]['rooms'][0]['name']);
        $this->assertContains('12\'-0"', $document['sections'][0]['rooms'][0]['dimensions']);
        $this->assertStringContainsString('<svg', $document['sections'][0]['rooms'][0]['svg']);
        $this->assertSame('Alex Client', $document['signature']['name']);
        $this->assertNotEmpty($document['signature']['image']);

        $html = view('admin.utilities.room_area_plan_pdf', $document)->render();
        $this->assertStringContainsString('Zanini Renovations', $html);
        $this->assertStringContainsString('Maple kitchen', $html);
        $this->assertStringContainsString('Pat Owner', $html);
        $this->assertStringContainsString('Kitchen reno', $html);
        $this->assertStringContainsString('Rough-in', $html);
        $this->assertStringContainsString('Kitchen', $html);
        $this->assertStringContainsString('Bath', $html);
        $this->assertStringContainsString('184.00 sq ft', $html);
        $this->assertStringContainsString('76.00 lf', $html);
        $this->assertStringContainsString('Client signature', $html);
        $this->assertStringContainsString('Alex Client', $html);
        $this->assertStringNotContainsString('admin-dark', $html);
        $this->assertStringNotContainsString('var(--admin-', $html);

        $response = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $text = $this->pdfText($pdf);
        $this->assertStringContainsString('Zanini', $text);
        $this->assertStringContainsString('Kitchen', $text);
        $this->assertStringContainsString('Rough-in', $text);
        $this->assertStringContainsString('Alex Client', $text);
    }

    public function test_signature_save_does_not_email_or_change_saved_rooms_or_quotes(): void
    {
        $this->actingAsAdmin();
        Mail::fake();
        Storage::fake('local');
        $fixture = $this->makeFixture();
        $vertices = $fixture['kitchen']->vertices;
        $quote = StandardQuotation::create([
            'client_project_id' => $fixture['client']->id,
            'quote_number' => 'Q-RA-B4-1',
            'client_name' => 'Pat Owner',
            'display_title' => 'Kitchen — Room Area',
            'grand_total' => 515.28,
            'materials_subtotal_before_hst' => 456.00,
            'materials' => [['description' => 'LVP', 'quantity' => 88, 'unit_price' => 4.5]],
            'works' => [['name' => 'LVP', 'qty' => 88, 'unit_price' => 4.5]],
        ]);
        $quoteSnap = $quote->fresh()->toArray();
        $roomSnap = $fixture['kitchen']->fresh()->only(['id', 'name', 'vertices', 'area_sqft']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner',
            'signed_at' => '2026-10-10',
            'signature' => self::TINY_PNG,
        ])->assertOk()->assertJsonPath('ok', true);

        Mail::assertNothingSent();
        $this->assertSame($quoteSnap, $quote->fresh()->toArray());
        $this->assertSame($roomSnap, $fixture['kitchen']->fresh()->only(['id', 'name', 'vertices', 'area_sqft']));
        $this->assertSame($vertices, $fixture['kitchen']->fresh()->vertices);
    }

    /**
     * @return array{client: ClientProject, project: RoomAreaProject, kitchen: RoomAreaRoom, bath: RoomAreaRoom}
     */
    private function makeFixture(): array
    {
        $client = ClientProject::factory()->create([
            'client_name' => 'Pat Owner',
            'name' => 'Kitchen reno',
        ]);
        $project = RoomAreaProject::create([
            'name' => 'Maple kitchen',
            'client_project_id' => $client->id,
            'scale_pixels_per_foot' => 12,
            'signer_name' => 'Alex Client',
            'signed_at' => '2026-10-09',
        ]);
        $phase = RoomAreaPhase::create([
            'room_area_project_id' => $project->id,
            'name' => 'Rough-in',
            'sort_order' => 1,
        ]);
        $kitchen = $this->makeRoom($project, 'Kitchen', 144, 120, $phase->id, [
            'id' => 'obj_1',
            'type' => 'door',
            'subtype' => 'single',
            'wall_id' => 'wall_p1_0_1',
            'width' => 36,
            'offset' => 12,
        ]);
        $bath = $this->makeRoom($project, 'Bath', 96, 96);
        Storage::disk('local')->put(
            'room-plans/signatures/project-'.$project->id.'.png',
            base64_decode(substr(self::TINY_PNG, strlen('data:image/png;base64,')))
        );
        $project->signature_path = 'room-plans/signatures/project-'.$project->id.'.png';
        $project->save();
        $project->refresh();

        return [
            'client' => $client,
            'project' => $project->fresh(['phases', 'rooms', 'clientProject']),
            'kitchen' => $kitchen,
            'bath' => $bath,
        ];
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

    private function pdfText(string $pdf): string
    {
        $chunks = [$pdf];
        if (preg_match_all('/stream\r?\n(.*)\r?\nendstream/s', $pdf, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if (is_string($decoded) && $decoded !== '') {
                    $chunks[] = $decoded;
                }
            }
        }
        $blob = implode("\n", $chunks);
        preg_match_all('/\\((?:\\\\.|[^\\\\)])*\\)/', $blob, $strings);

        return $blob.' '.implode(' ', $strings[0] ?? []);
    }
}
