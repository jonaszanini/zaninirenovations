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

            // Must say "fully insured" without dollar amount
            $this->assertStringContainsString('fully insured', strtolower($html));
            $this->assertDoesNotMatchRegularExpression('/fully insured [for up to ]?\$[0-9]/i', $html);

            // Must mention permits as optional extra
            $this->assertStringContainsString('building permits', strtolower($html));
            $this->assertStringContainsString('optional extra', strtolower($html));

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

        // Second duplicate submit with same idempotency key
        $res2 = $this->postJson(route('admin.client-projects.quotation.send', [$project, $quotation]), [
            'to' => 'client@example.test',
            'subject' => 'Quote',
            'message' => 'Hello. We are fully insured.',
            'idempotency_key' => $key,
            'confirm_draft' => 1,
        ]);
        $res2->assertOk()->assertJson(['success' => true, 'duplicate' => true]);

        // Sent only once
        Mail::assertSent(ClientProjectQuotationClient::class, 1);
        $this->assertEquals(1, QuotationSendAudit::query()->where('client_project_quotation_id', $quotation->id)->count());
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
