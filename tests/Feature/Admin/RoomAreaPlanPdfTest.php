<?php

namespace Tests\Feature\Admin;

use App\Models\RoomAreaSignature;
use App\Services\RoomAreaPlanDocument;
use App\Services\RoomAreaSignableQuote;
use App\Services\RoomAreaSignatureRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\MakesRoomAreaPlanFixture;

class RoomAreaPlanPdfTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesRoomAreaPlanFixture;

    private const TINY_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_plan_pdf_uses_letter_paper_without_remote_fetching(): void
    {
        $this->assertSame('letter', RoomAreaPlanDocument::PAPER);
        $this->assertFalse(RoomAreaPlanDocument::REMOTE_ENABLED);

        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();

        $response = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']));
        $response->assertOk();
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(4000, strlen($pdf));
        $this->assertTrue(
            (bool) preg_match('/MediaBox \\[0(?:\\.0+)? 0(?:\\.0+)? 612(?:\\.[0-9]+)? 792(?:\\.[0-9]+)?\\]/', $pdf),
            'Plan PDF should use Letter (612×792) instead of A4.'
        );
    }

    public function test_plan_pdf_includes_brand_rooms_and_exact_phase_totals(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();

        $document = app(RoomAreaPlanDocument::class)->build($fixture['project']);
        $this->assertSame('Zanini Renovations', $document['brand']);
        $this->assertNotEmpty($document['logo_data_uri']);
        $this->assertSame('Maple kitchen', $document['project_name']);
        $this->assertSame('Pat Owner', $document['client_name']);
        $this->assertSame('Kitchen reno', $document['client_project_name']);
        $this->assertEquals(184.0, $document['totals']['area_sqft']);
        $this->assertEquals(76.0, $document['totals']['perimeter_lf']);
        $this->assertEquals(588.0, $document['totals']['net_wall_area_sqft']);
        $this->assertEquals(202.4, $document['totals']['flooring_with_waste_sqft']);
        $this->assertEquals(32.67, $document['totals']['casing_lf']);
        $this->assertSame('Rough-in', $document['sections'][0]['name']);
        $this->assertEquals(120.0, $document['sections'][0]['totals']['area_sqft']);
        $this->assertEquals(332.0, $document['sections'][0]['totals']['net_wall_area_sqft']);
        $this->assertEquals(32.67, $document['sections'][0]['totals']['casing_lf']);
        $this->assertSame('Kitchen', $document['sections'][0]['rooms'][0]['name']);
        $this->assertContains('12\'-0"', $document['sections'][0]['rooms'][0]['dimensions']);
        $this->assertStringContainsString('<svg', $document['sections'][0]['rooms'][0]['svg']);
        $this->assertEquals(256.0, $document['sections'][1]['totals']['net_wall_area_sqft']);
        $this->assertEquals(0.0, $document['sections'][1]['totals']['casing_lf']);
        $this->assertFalse($document['signature']['signed']);

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
        $this->assertStringContainsString('588.00 sq ft', $html);
        $this->assertStringContainsString('Client signature', $html);
        $this->assertStringNotContainsString('admin-dark', $html);
        $this->assertStringNotContainsString('var(--admin-', $html);

        $response = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $pdf = $response->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $text = $this->pdfText($pdf);
        foreach (['Zanini', 'Kitchen', 'Rough-in', '184'] as $needle) {
            $this->assertTrue(
                str_contains($text, $needle) || str_contains($html, $needle),
                "Plan PDF content is missing {$needle}"
            );
        }
    }

    public function test_signature_uses_server_time_and_ignores_client_date(): void
    {
        $admin = $this->actingAsAdmin();
        Mail::fake();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-10 15:04:05');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client'], ['version' => 3]);
        $kitchen = $fixture['kitchen']->fresh();
        $vertices = $kitchen->vertices;
        $quoteSnap = $quote->fresh()->toArray();
        $roomSnap = $fixture['kitchen']->fresh()->only(['id', 'name', 'vertices', 'area_sqft']);

        $this->withHeaders(['User-Agent' => 'RoomAreaTester/1.0'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
                'signer_name' => 'Pat Owner',
                'signed_at' => '1999-01-01',
                'standard_quotation_id' => $quote->id,
                'signature' => self::TINY_PNG,
            ])->assertOk()->assertJsonPath('ok', true);

        Mail::assertNothingSent();
        $this->assertSame($quoteSnap, $quote->fresh()->toArray());
        $this->assertSame($roomSnap, $fixture['kitchen']->fresh()->only(['id', 'name', 'vertices', 'area_sqft']));
        $this->assertEquals($vertices, $fixture['kitchen']->fresh()->vertices);

        $signature = RoomAreaSignature::query()->where('room_area_project_id', $fixture['project']->id)->first();
        $this->assertNotNull($signature);
        $this->assertSame('Pat Owner', $signature->signer_name);
        $this->assertSame('2026-10-10 15:04:05', $signature->signed_at->format('Y-m-d H:i:s'));
        $this->assertSame($admin->id, (int) $signature->admin_user_id);
        $this->assertSame('203.0.113.10', $signature->ip);
        $this->assertSame('RoomAreaTester/1.0', $signature->user_agent);
        $this->assertSame($quote->id, (int) $signature->standard_quotation_id);
        $this->assertSame(3, (int) $signature->quote_version);
        $this->assertNull($signature->voided_at);
        $frozen = Storage::disk('local')->get($signature->pdf_path);
        $this->assertSame($signature->snapshot_hash, hash('sha256', $frozen));
        Carbon::setTestNow();
    }

    public function test_draft_quote_cannot_be_signed(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makeDraftQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::DRAFT_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->count());
    }

    public function test_revising_quote_cannot_be_signed(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makeRevisingQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::REVISING_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->count());
    }

    public function test_signature_is_write_once_until_voided(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertOk();

        $first = RoomAreaSignature::query()->first();
        $hash = $first->snapshot_hash;
        $path = $first->pdf_path;

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Someone Else',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertStatus(409)->assertJsonPath('message', RoomAreaSignatureRecorder::ACTIVE_EXISTS_MESSAGE);

        $this->assertSame(1, RoomAreaSignature::query()->count());
        $this->assertSame($hash, $first->fresh()->snapshot_hash);
        $this->assertSame($path, $first->fresh()->pdf_path);
        $this->assertSame('Pat Owner', $first->fresh()->signer_name);

        $this->deleteJson(route('admin.utilities.room-area.projects.signature', $fixture['project']))
            ->assertStatus(405);

        $this->postJson(route('admin.utilities.room-area.projects.signature.void', $fixture['project']), [
            'reason' => 'Client initialed the wrong sheet',
        ])->assertOk()->assertJsonPath('ok', true);

        $first->refresh();
        $this->assertNotNull($first->voided_at);
        $this->assertSame('Client initialed the wrong sheet', $first->void_reason);
        $this->assertSame($hash, $first->snapshot_hash);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner Jr',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertOk();

        $this->assertSame(2, RoomAreaSignature::query()->count());
        $this->assertNotNull(RoomAreaSignature::query()->whereNull('voided_at')->first());
        $this->assertSame('Pat Owner', $first->fresh()->signer_name);
    }

    public function test_frozen_pdf_does_not_change_after_rooms_are_edited(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Alex Client',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertOk();

        $signature = RoomAreaSignature::query()->first();
        $before = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']))->assertOk()->getContent();
        $this->assertSame($signature->snapshot_hash, hash('sha256', $before));
        $this->assertSame($signature->snapshot_hash, hash('sha256', Storage::disk('local')->get($signature->pdf_path)));
        $this->assertStringContainsString('Alex Client', $this->pdfText($before).view(
            'admin.utilities.room_area_plan_pdf',
            app(RoomAreaPlanDocument::class)->build($fixture['project'])
        )->render());

        $fixture['kitchen']->update([
            'name' => 'Kitchen expanded',
            'area_sqft' => 999,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 240, 'y' => 0],
                            ['x' => 240, 'y' => 240],
                            ['x' => 0, 'y' => 240],
                        ],
                    ]],
                ]],
                'objects' => [],
            ],
        ]);

        $after = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']))->assertOk()->getContent();
        $this->assertSame($before, $after);
        $this->assertSame($signature->snapshot_hash, hash('sha256', $after));

        $live = app(RoomAreaPlanDocument::class)->build($fixture['project']->fresh(['rooms', 'phases', 'clientProject', 'signatures']));
        $this->assertEquals(400.0 + 64.0, $live['totals']['area_sqft']);
        $this->assertSame('Alex Client', $live['signature']['name']);
    }

    public function test_signature_accepts_non_ascii_and_very_long_names(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $name = 'José García-Ñoño '.str_repeat('Å', 180);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => $name,
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertOk();

        $signature = RoomAreaSignature::query()->first();
        $this->assertSame($name, $signature->signer_name);
    }

    public function test_quote_from_another_project_returns_404(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $other = $this->makePlanFixture(['name' => 'Other layout']);
        $quote = $this->makePublishedQuote($other['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), [
            'signer_name' => 'Pat Owner',
            'standard_quotation_id' => $quote->id,
            'signature' => self::TINY_PNG,
        ])->assertNotFound();
    }

    private function pdfText(string $pdf): string
    {
        $chunks = [];
        if (preg_match_all('/stream\r?\n(.*)\r?\nendstream/s', $pdf, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                if ($decoded === false && strlen($stream) > 2) {
                    $decoded = @gzinflate(substr($stream, 2));
                }
                if (is_string($decoded) && $decoded !== '') {
                    $chunks[] = $decoded;
                }
            }
        }
        $blob = implode("\n", $chunks);
        preg_match_all('/\\((?:\\\\.|[^\\\\)])*\\)/', $blob, $strings);
        $literals = implode(' ', $strings[0] ?? []);

        return $blob.' '.$literals;
    }
}
