<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendQuotationToClientJob;
use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\QuotationSendAudit;
use App\Models\StandardQuotation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\QuoteAcceptance\MakesAcceptableQuotations;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationSendToClientTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;

    public function test_send_flow_queues_job_and_dispatches_email_with_correct_rules(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();

        [$project, $quotation, $standard] = $this->makeLinkedQuotation([
            'quote_number' => 'SQ-2026-0394',
            'status' => ClientProjectQuotation::STATUS_DRAFT,
        ]);
        $quotation->update(['version' => 7]);

        $customSubject = 'Your quotation SQ-2026-0394 from Zanini Renovations';
        $customMessage = "Hi Ada,\n\nPlease find attached your quotation SQ-2026-0394.\n\nWe are fully insured. Please note that building permits, if required, are an optional extra.";

        $response = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'ada@example.test',
            'cc' => 'office@example.test',
            'subject' => $customSubject,
            'message' => $customMessage,
            'confirm_draft' => 1,
            'idempotency_key' => 'idem_key_12345',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => ClientProjectQuotation::STATUS_SENT,
            ]);

        // 1. Audit record logged
        $audit = QuotationSendAudit::query()->where('client_project_quotation_id', $quotation->id)->first();
        $this->assertNotNull($audit);
        $this->assertEquals($admin->id, $audit->admin_id);
        $this->assertContains('ada@example.test', $audit->recipients);
        $this->assertContains('office@example.test', $audit->recipients);
        $this->assertEquals('v7', (string) $audit->quote_version);
        $this->assertNotEmpty($audit->pdf_hash);
        $this->assertEquals(64, strlen($audit->pdf_hash)); // sha256 length

        // 2. Status set to Sent and sent_at populated
        $quotation->refresh();
        $this->assertEquals(ClientProjectQuotation::STATUS_SENT, $quotation->status);
        $this->assertNotNull($quotation->sent_at);

        // 3. Mail sent with correct rules
        Mail::assertSent(ClientProjectQuotationClient::class, function (ClientProjectQuotationClient $mail) use ($customSubject, $customMessage) {
            $mail->build();
            $this->assertEquals($customSubject, $mail->subject);

            $html = view($mail->view, $mail->viewData)->render();

            // Must say "fully insured" without dollar amount, and only once
            $this->assertStringContainsString('fully insured', strtolower($html));
            $this->assertSame(1, substr_count(strtolower($html), 'fully insured'));
            $this->assertDoesNotMatchRegularExpression('/fully insured [for up to ]?\$[0-9]/i', $html);

            // Must mention permits as optional extra, and only once
            $this->assertStringContainsString('building permits', strtolower($html));
            $this->assertStringContainsString('optional extra', strtolower($html));
            $this->assertSame(1, substr_count(strtolower($html), 'building permits'));

            // Greeting must appear only once
            $this->assertSame(1, substr_count(strtolower($html), 'hi ada'));

            // NEVER say "licensed", "one-person", or mention "WSIB"
            $this->assertStringNotContainsStringIgnoringCase('licensed', $html);
            $this->assertStringNotContainsStringIgnoringCase('one-person', $html);
            $this->assertStringNotContainsStringIgnoringCase('wsib', $html);

            return true;
        });

        // 4. Communication log and status history
        $comm = ClientCommunicationLog::query()->where('client_project_id', $project->id)->first();
        $this->assertNotNull($comm);
        $this->assertEquals('email', $comm->channel);
        $this->assertStringContainsString('ada@example.test', $comm->recipient);
    }

    public function test_double_submit_protection_with_idempotency_key(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation();
        [$project2, $quotation2] = $this->makeLinkedQuotation([
            'quote_number' => 'SQ-2026-0395',
        ]);

        // Missing idempotency_key on JSON request is rejected
        $missingKeyRes = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello. We are fully insured.',
            'confirm_draft' => 1,
        ]);
        $missingKeyRes->assertStatus(422);

        $key = 'idemp_' . uniqid();

        // First submit
        $res1 = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello. We are fully insured.',
            'idempotency_key' => $key,
            'confirm_draft' => 1,
        ]);
        $res1->assertOk()->assertJson(['success' => true]);

        // Second duplicate submit with same idempotency key on same quote
        $res2 = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello. We are fully insured.',
            'idempotency_key' => $key,
            'confirm_draft' => 1,
        ]);
        $res2->assertOk()->assertJson(['success' => true, 'duplicate' => true]);

        // Sent only once for quotation 1
        Mail::assertSent(ClientProjectQuotationClient::class, 1);
        $this->assertEquals(1, QuotationSendAudit::query()->where('client_project_quotation_id', $quotation->id)->count());

        // Same key used on a DIFFERENT quotation is allowed (keys are quote-scoped)
        $res3 = $this->postJson(route('admin.client-projects.quotation.send', [$project2, $quotation2]), [
            'to' => 'client@example.test',
            'subject' => 'Quote 2',
            'message' => 'Hello. We are fully insured.',
            'idempotency_key' => $key,
            'confirm_draft' => 1,
        ]);
        $res3->assertOk()->assertJson(['success' => true]);
        $this->assertFalse((bool) $res3->json('duplicate'));
        Mail::assertSent(ClientProjectQuotationClient::class, 2);
    }

    public function test_rejected_send_does_not_publish_draft(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation, $standard] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_DRAFT,
        ]);
        $standard->update(['is_draft' => true]);

        // 1. Invalid email with confirm_draft=1 must NOT publish the draft
        $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'not-an-email',
            'subject' => 'Quote',
            'message' => 'Hello',
            'confirm_draft' => 1,
            'idempotency_key' => 'idem_bad_email',
        ]);
        $res->assertStatus(422);
        $this->assertTrue((bool) $standard->fresh()->is_draft);
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $quotation->fresh()->status);

        // 2. Overlong subject (> 255 chars) with confirm_draft=1 must NOT publish or send
        $res2 = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => str_repeat('S', 300),
            'message' => 'Hello',
            'confirm_draft' => 1,
            'idempotency_key' => 'idem_long_subject',
        ]);
        $res2->assertStatus(422);
        $this->assertTrue((bool) $standard->fresh()->is_draft);
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $quotation->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_retry_after_failure_marks_quote_sent_and_audit_is_immutable(): void
    {
        $admin = $this->actingAsAdmin();
        [$project, $quotation, $standard] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_DRAFT,
        ]);

        // Simulate a failed first attempt using the same idempotency key
        $failedAudit = QuotationSendAudit::query()->create([
            'client_project_id' => $project->id,
            'client_project_quotation_id' => $quotation->id,
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'server_time' => now(),
            'recipient_email' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Body',
            'quote_version' => 'v1',
            'pdf_hash' => str_repeat('a', 64),
            'status' => QuotationSendAudit::STATUS_FAILED,
            'error' => 'SMTP timeout',
            'idempotency_key' => 'idem_retry_1',
        ]);

        // Audit record must be immutable (cannot be edited or deleted)
        try {
            $failedAudit->update(['subject' => 'Tampered']);
            $this->fail('Expected LogicException when updating QuotationSendAudit');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        try {
            $failedAudit->delete();
            $this->fail('Expected LogicException when deleting QuotationSendAudit');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('immutable', $e->getMessage());
        }

        // Retry with the same idempotency key succeeds and marks quote Sent
        Mail::fake();
        $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello',
            'confirm_draft' => 1,
            'idempotency_key' => 'idem_retry_1',
        ]);
        $res->assertOk()->assertJson([
            'success' => true,
            'status' => ClientProjectQuotation::STATUS_SENT,
        ]);

        $quotation->refresh();
        $this->assertEquals(ClientProjectQuotation::STATUS_SENT, $quotation->status);
        $this->assertNotNull($quotation->sent_at);
        $this->assertEquals(2, QuotationSendAudit::query()->where('client_project_quotation_id', $quotation->id)->count());
    }

    public function test_send_works_on_database_queue_driver(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        [$project, $quotation] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_DRAFT,
        ]);

        $originalQueue = config('queue.default');
        config(['queue.default' => 'database']);

        try {
            $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
                'to' => 'client@example.test',
                'subject' => 'Queued Quote',
                'message' => 'Hello',
                'confirm_draft' => 1,
                'idempotency_key' => 'idem_db_queue_1',
            ]);

            $res->assertOk()->assertJson([
                'success' => true,
                'queued' => true,
            ]);

            // Duplicate click while job is still queued is blocked via cache lock
            $dup = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
                'to' => 'client@example.test',
                'subject' => 'Queued Quote',
                'message' => 'Hello',
                'confirm_draft' => 1,
                'idempotency_key' => 'idem_db_queue_1',
            ]);
            $dup->assertOk()->assertJson([
                'success' => true,
                'duplicate' => true,
            ]);

            // Process the queued job from the database queue
            $this->artisan('queue:work', [
                'connection' => 'database',
                '--once' => true,
            ])->assertExitCode(0);

            $quotation->refresh();
            $this->assertEquals(ClientProjectQuotation::STATUS_SENT, $quotation->status);
            Mail::assertSent(ClientProjectQuotationClient::class, 1);
        } finally {
            config(['queue.default' => $originalQueue]);
        }
    }

    public function test_draft_quote_blocks_send_without_confirmation(): void
    {
        $this->actingAsAdmin();

        [$project, $quotation, $standard] = $this->makeLinkedQuotation([
            'status' => ClientProjectQuotation::STATUS_DRAFT,
        ]);

        $standard->update(['is_draft' => true]);

        // Send without confirm_draft
        $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello. We are fully insured.',
            'confirm_draft' => 0,
        ]);

        $res->assertStatus(422)
            ->assertJson([
                'is_draft' => true,
            ]);

        // Status must NOT change
        $quotation->refresh();
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $quotation->status);
        $this->assertNull($quotation->sent_at);
    }

    public function test_missing_or_invalid_email_blocks_send(): void
    {
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation();

        // Invalid email
        $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'not-an-email',
            'subject' => 'Quote',
            'message' => 'Hello',
            'confirm_draft' => 1,
        ]);

        $res->assertStatus(422);

        // Empty email
        $res2 = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => '',
            'subject' => 'Quote',
            'message' => 'Hello',
            'confirm_draft' => 1,
        ]);

        $res2->assertStatus(422);
    }

    public function test_unauthorized_user_cannot_send_quote(): void
    {
        [$project, $quotation] = $this->makeLinkedQuotation();

        // Guest user
        $res = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello',
        ]);

        $res->assertStatus(401);
    }
}
