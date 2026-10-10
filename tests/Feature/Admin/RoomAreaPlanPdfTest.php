<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\RoomAreaController;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaSignature;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use App\Services\RoomAreaPlanDocument;
use App\Services\RoomAreaSignableQuote;
use App\Services\RoomAreaSignatureRecorder;
use App\Services\RoomAreaSignatureRetention;
use App\Services\RoomAreaSignatureStorage;
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

    public function test_projects_table_has_no_unused_signature_columns(): void
    {
        $this->assertFalse(Schema::hasColumn('room_area_projects', 'signer_name'));
        $this->assertFalse(Schema::hasColumn('room_area_projects', 'signed_at'));
        $this->assertFalse(Schema::hasColumn('room_area_projects', 'signature_path'));
        $this->assertTrue(Schema::hasColumn('room_area_projects', 'deleted_at'));
    }

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
            ->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
                $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG),
                ['signed_at' => '1999-01-01']
            ))->assertOk()->assertJsonPath('ok', true);

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

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::DRAFT_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->count());
    }

    public function test_revising_quote_cannot_be_signed(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makeRevisingQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::REVISING_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->count());
    }

    public function test_signature_is_write_once_until_voided(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        $first = RoomAreaSignature::query()->first();
        $hash = $first->snapshot_hash;
        $path = $first->pdf_path;

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Someone Else', self::TINY_PNG))
            ->assertStatus(409)->assertJsonPath('message', RoomAreaSignatureRecorder::ACTIVE_EXISTS_MESSAGE);

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

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner Jr', self::TINY_PNG))
            ->assertOk();

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

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Alex Client', self::TINY_PNG))
            ->assertOk();

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

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, $name, self::TINY_PNG))
            ->assertOk();

        $signature = RoomAreaSignature::query()->first();
        $this->assertSame($name, $signature->signer_name);
    }

    public function test_quote_from_another_project_returns_404(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $other = $this->makePlanFixture(['name' => 'Other layout']);
        $quote = $this->makePublishedQuote($other['client']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertNotFound();
    }

    public function test_missing_frozen_pdf_returns_error_instead_of_live_rebuild(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Alex Client', self::TINY_PNG))
            ->assertOk();

        $signature = RoomAreaSignature::query()->first();
        Storage::disk('local')->delete($signature->pdf_path);
        $fixture['kitchen']->update(['name' => 'Should not appear live']);

        $response = $this->get(route('admin.utilities.room-area.projects.plan', $fixture['project']));
        $response->assertStatus(409);
        $body = $response->getContent();
        $this->assertStringContainsString(RoomAreaSignatureRecorder::MISSING_FROZEN_PDF_MESSAGE, $body);
        $this->assertStringNotContainsString('%PDF', $body);
        $this->assertStringNotContainsString('Should not appear live', $body);
    }

    public function test_superseded_quote_version_cannot_be_signed(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client'], ['version' => 1]);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
            $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG),
            ['quote_version' => 1]
        ))->assertOk();
        $this->postJson(route('admin.utilities.room-area.projects.signature.void', $fixture['project']), [
            'reason' => 'Quote was revised',
        ])->assertOk();

        $quote->update(['version' => 2]);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG),
            ['quote_version' => 1]
        ))->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::SUPERSEDED_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->whereNull('voided_at')->count());
    }

    public function test_stale_quote_version_cannot_be_signed_after_publish_bump(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client'], ['version' => 1]);

        $options = app(RoomAreaSignableQuote::class)->options($fixture['project']->fresh());
        $option = collect($options)->firstWhere('id', $quote->id);
        $this->assertNotNull($option);
        $this->assertFalse($option['is_superseded']);
        $this->assertTrue($option['can_sign']);

        $quote->update(['version' => 2]);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG),
            ['quote_version' => 1]
        ))->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::SUPERSEDED_MESSAGE);

        $this->assertSame(0, RoomAreaSignature::query()->count());

        $this->postJson(
            route('admin.utilities.room-area.projects.signature', $fixture['project']),
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG)
        )->assertOk();
        $this->assertSame(1, RoomAreaSignature::query()->whereNull('voided_at')->count());
        $this->assertSame(2, (int) RoomAreaSignature::query()->first()->quote_version);
    }

    public function test_deleting_a_signed_project_archives_it_and_keeps_signature_files(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();
        $this->postJson(route('admin.utilities.room-area.projects.signature.void', $fixture['project']), [
            'reason' => 'Redo',
        ])->assertOk();
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        $rows = RoomAreaSignature::query()->where('room_area_project_id', $fixture['project']->id)->get();
        $this->assertCount(2, $rows);
        $paths = $rows->flatMap(fn (RoomAreaSignature $row) => [$row->pdf_path, $row->signature_path])->all();
        foreach ($paths as $path) {
            $this->assertTrue(Storage::disk('local')->exists($path));
        }

        $this->deleteJson(route('admin.utilities.room-area.projects.destroy', $fixture['project']))
            ->assertOk()
            ->assertJsonPath('archived', true)
            ->assertJsonPath('message', RoomAreaController::ARCHIVED_FOR_SIGNATURES_MESSAGE);

        $this->assertNotNull(RoomAreaProject::withTrashed()->find($fixture['project']->id));
        $this->assertNull(RoomAreaProject::query()->find($fixture['project']->id));
        $this->assertSame(2, RoomAreaSignature::query()->where('room_area_project_id', $fixture['project']->id)->count());
        foreach ($paths as $path) {
            $this->assertTrue(Storage::disk('local')->exists($path));
        }
    }

    public function test_unsigned_project_still_deletes(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();

        $this->deleteJson(route('admin.utilities.room-area.projects.destroy', $fixture['project']))
            ->assertOk();

        $this->assertNull(RoomAreaProject::withTrashed()->find($fixture['project']->id));
        $this->assertSame(0, RoomAreaSignature::query()->count());
    }

    public function test_unique_active_column_blocks_a_second_active_row(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        $first = RoomAreaSignature::query()->first();
        $this->assertSame(1, (int) $first->active);
        try {
            RoomAreaSignature::query()->create([
                'room_area_project_id' => $first->room_area_project_id,
                'standard_quotation_id' => $first->standard_quotation_id,
                'quote_version' => ((int) $first->quote_version) + 1,
                'active' => 1,
                'signer_name' => 'Double Tap',
                'signed_at' => now(),
                'admin_user_id' => $first->admin_user_id,
                'snapshot_hash' => str_repeat('a', 64),
                'pdf_path' => 'room-plans/signatures/dup.pdf',
                'signature_path' => 'room-plans/signatures/dup.png',
            ]);
            $this->fail('Expected a unique constraint on the active signature per project.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertSame(1062, (int) ($e->errorInfo[1] ?? 0));
        }
        $this->assertSame(1, RoomAreaSignature::query()->whereNull('voided_at')->count());
    }

    public function test_deleting_a_signed_quote_returns_a_friendly_error(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        $this->delete(route('admin.quotations.standard.destroy', $quote))
            ->assertRedirect(route('admin.quotations.standard.index'))
            ->assertSessionHas('error', RoomAreaSignatureRetention::QUOTE_DELETE_MESSAGE);

        $this->assertNotNull($quote->fresh());
        $this->assertSame(1, RoomAreaSignature::query()->count());
    }

    public function test_deleting_a_quote_with_only_voided_signatures_is_blocked(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();
        $this->postJson(route('admin.utilities.room-area.projects.signature.void', $fixture['project']), [
            'reason' => 'Redo',
        ])->assertOk();

        $this->delete(route('admin.quotations.standard.destroy', $quote))
            ->assertRedirect(route('admin.quotations.standard.index'))
            ->assertSessionHas('error', RoomAreaSignatureRetention::QUOTE_DELETE_MESSAGE)
            ->assertSessionMissing('success');

        $this->assertNotNull($quote->fresh());
        $this->assertSame(1, RoomAreaSignature::query()->count());
    }

    public function test_unsigned_quote_still_deletes(): void
    {
        $this->actingAsAdmin();
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);

        $this->delete(route('admin.quotations.standard.destroy', $quote))
            ->assertRedirect(route('admin.quotations.standard.index'))
            ->assertSessionHas('success');

        $this->assertNull($quote->fresh());
    }

    public function test_deleting_an_admin_who_signed_returns_a_friendly_error(): void
    {
        $admin = $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        try {
            $admin->delete();
            $this->fail('Expected signer delete to be blocked.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame(RoomAreaSignatureRetention::USER_DELETE_MESSAGE, $e->getMessage());
        }
        $this->assertNotNull(User::query()->find($admin->id));
        $this->assertSame(1, RoomAreaSignature::query()->count());
    }

    public function test_bumped_quote_version_flags_stale_signature_and_blocks_old_version(): void
    {
        $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client'], ['version' => 1]);
        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), $this->signaturePayload($quote, 'Pat Owner', self::TINY_PNG))
            ->assertOk();

        $quote->update(['version' => 2]);
        $payload = app(RoomAreaController::class)->planProjectPayload(
            $fixture['project']->fresh(['signatures', 'phases', 'rooms', 'clientProject'])
        );
        $this->assertTrue($payload['signature_version_stale']);
        $this->assertSame(RoomAreaSignatureRetention::STALE_SIGNATURE_MESSAGE, $payload['signature_stale_message']);
        $this->assertSame(1, $payload['quote_version']);
        $this->assertSame(2, $payload['quote_current_version']);

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG),
            ['quote_version' => 2]
        ))->assertStatus(409)->assertJsonPath('message', RoomAreaSignatureRecorder::ACTIVE_EXISTS_MESSAGE);

        $this->postJson(route('admin.utilities.room-area.projects.signature.void', $fixture['project']), [
            'reason' => 'Quote was revised',
        ])->assertOk();

        $this->postJson(route('admin.utilities.room-area.projects.signature', $fixture['project']), array_merge(
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG),
            ['quote_version' => 1]
        ))->assertStatus(422)->assertJsonPath('message', RoomAreaSignableQuote::SUPERSEDED_MESSAGE);

        $this->postJson(
            route('admin.utilities.room-area.projects.signature', $fixture['project']),
            $this->signaturePayload($quote->fresh(), 'Pat Owner', self::TINY_PNG)
        )->assertOk();
        $this->assertSame(2, (int) RoomAreaSignature::query()->whereNull('voided_at')->first()->quote_version);
    }

    public function test_failed_signature_cleans_up_written_files(): void
    {
        $admin = $this->actingAsAdmin();
        Storage::fake('local');
        $fixture = $this->makePlanFixture();
        $quote = $this->makePublishedQuote($fixture['client']);
        $document = $this->createMock(RoomAreaPlanDocument::class);
        $document->method('pdfBinary')->willThrowException(new \RuntimeException('pdf failed'));
        $recorder = new RoomAreaSignatureRecorder(null, new RoomAreaSignatureStorage, $document);
        $request = \Illuminate\Http\Request::create('/admin', 'POST');

        try {
            $recorder->record($request, $fixture['project'], $admin, $quote, 'Pat Owner', self::TINY_PNG, 1);
            $this->fail('Expected PDF generation to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('pdf failed', $e->getMessage());
        }

        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, RoomAreaSignature::query()->count());
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
