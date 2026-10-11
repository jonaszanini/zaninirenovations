<?php

namespace Tests\Feature\Etransfer;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use App\Services\Etransfer\EtransferInvoiceMatcher;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RecordEtransferPaymentTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_view_etransfers_page(): void
    {
        $this->get(route('admin.financials.etransfers'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_list_shows_pending_notice_and_sidebar_badge(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Jane Citizen',
            'client_email' => 'jane@example.test',
        ]);
        ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0001',
            'total' => 1130,
            'subtotal' => 1000,
            'hst' => 130,
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Jane Citizen',
            'sender_email' => 'jane@example.test',
            'amount' => 1130,
            'message' => 'INV-2026-0001',
            'reference_number' => 'CALIST0001',
        ]);

        $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('Incoming e-transfers')
            ->assertSee('Jane Citizen')
            ->assertSee('Record payment')
            ->assertSee('Paste email')
            ->assertSee('INV-2026-0001')
            ->assertSee('cp-money-input', false)
            ->assertSee('data-cp-money-entry="direct"', false)
            ->assertSee('>Cancel</button>', false)
            ->assertSee('et-paste-advanced', false)
            ->assertDontSee('et-paste-advanced" open', false)
            ->assertSee('<textarea id="et-message"', false)
            ->assertSee('et-card__btns', false);
    }

    public function test_guest_cannot_paste_record_ignore_or_search_invoices(): void
    {
        $notice = EtransferNotice::factory()->create();

        $this->post(route('admin.financials.etransfers.store'), ['raw_text' => 'Jane sent you $10 CAD'])
            ->assertRedirect(route('login'));
        $this->post(route('admin.financials.etransfers.record', $notice), ['invoice_id' => 1])
            ->assertRedirect(route('login'));
        $this->post(route('admin.financials.etransfers.ignore', $notice))
            ->assertRedirect(route('login'));
        $this->get(route('admin.financials.etransfers.invoices.search'))
            ->assertRedirect(route('login'));
    }

    public function test_recording_a_notice_marks_invoice_paid_via_etransfer(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Jane Citizen',
            'client_email' => 'jane@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-ET-0007',
            'subtotal' => 1000,
            'hst' => 130,
            'total' => 1130,
        ]);
        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Jane Citizen',
            'amount' => 1130,
            'message' => 'INV-ET-0007',
            'reference_number' => 'CARECORD0007',
            'received_at' => '2026-10-06 16:32:00',
        ]);

        $this->post(route('admin.financials.etransfers.record', $notice), [
            'invoice_id' => $invoice->id,
        ])->assertRedirect(route('admin.financials.etransfers'));

        $invoice->refresh();
        $notice->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame(ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER, $invoice->payment_method);
        $this->assertSame(1130.00, (float) $invoice->paid_amount);
        $this->assertNotNull($invoice->receipt_pdf_path);
        Storage::disk('local')->assertExists($invoice->receipt_pdf_path);

        $this->assertTrue($notice->isMatched());
        $this->assertSame($invoice->id, $notice->invoice_id);
        $this->assertSame($invoice->id, $notice->payment_id);
    }

    public function test_cannot_record_payment_twice_on_the_same_notice(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'total' => 200,
            'subtotal' => 176.99,
            'hst' => 23.01,
        ]);
        $notice = EtransferNotice::factory()->create([
            'amount' => 200,
            'reference_number' => 'CATWICE0001',
        ]);

        $this->post(route('admin.financials.etransfers.record', $notice), [
            'invoice_id' => $invoice->id,
        ])->assertRedirect();

        $this->from(route('admin.financials.etransfers'))
            ->post(route('admin.financials.etransfers.record', $notice), [
                'invoice_id' => $invoice->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_ignore_marks_notice_ignored_without_paying(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create();
        $notice = EtransferNotice::factory()->create([
            'amount' => 50,
            'reference_number' => 'CAIGNORE0001',
        ]);

        $this->post(route('admin.financials.etransfers.ignore', $notice))
            ->assertRedirect(route('admin.financials.etransfers'));

        $this->assertTrue($notice->fresh()->status === EtransferNotice::STATUS_IGNORED);
        $this->assertFalse($invoice->fresh()->isPaid());
    }

    public function test_paste_email_creates_manual_pending_notice(): void
    {
        $this->actingAsAdmin();

        $text = <<<'TXT'
Alex Martin sent you $250.00 CAD
Message: deck deposit
Reference Number: CAPASTE0001
TXT;

        $this->post(route('admin.financials.etransfers.store'), [
            'raw_text' => $text,
        ])->assertRedirect(route('admin.financials.etransfers'));

        $notice = EtransferNotice::query()->where('reference_number', 'CAPASTE0001')->first();
        $this->assertNotNull($notice);
        $this->assertSame(EtransferNotice::SOURCE_MANUAL, $notice->source);
        $this->assertSame('Alex Martin', $notice->sender_name);
        $this->assertSame(250.00, (float) $notice->amount);
        $this->assertTrue($notice->isPending());
    }

    public function test_dashboard_do_today_shows_pending_etransfers(): void
    {
        $this->actingAsAdmin();

        EtransferNotice::factory()->create([
            'amount' => 80,
            'reference_number' => 'CADASH0001',
        ]);

        $this->get(route('admin.dashboard', ['refresh' => 1]))
            ->assertOk()
            ->assertSee('incoming e-transfer')
            ->assertSee('Review e-transfers');
    }

    public function test_zohar_barak_paid_invoice_in_memo_is_shown_already_paid(): void
    {
        $this->actingAsAdmin();

        $zohar = ClientProject::factory()->create([
            'name' => 'Zohar kitchen',
            'client_name' => 'Zohar Barak',
            'client_email' => 'zohar@example.test',
        ]);
        ClientProjectInvoice::factory()->for($zohar, 'project')->paid()->create([
            'invoice_number' => 'INV-2026-0257',
            'subtotal' => 2000,
            'hst' => 260,
            'total' => 2260,
        ]);

        $other = ClientProject::factory()->create([
            'name' => 'Unrelated deck',
            'client_name' => 'Recent Client',
            'client_email' => 'recent@example.test',
        ]);
        ClientProjectInvoice::factory()->for($other, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0300',
            'subtotal' => 442.48,
            'hst' => 57.52,
            'total' => 500,
            'sent_at' => now()->subDays(2),
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Zohar Barak',
            'sender_email' => null,
            'amount' => 2260,
            'message' => 'invoice #INV-2026-0257',
            'reference_number' => 'CAZOHAR0257',
        ]);

        $paid = ClientProjectInvoice::query()->where('invoice_number', 'INV-2026-0257')->first();
        $this->assertNotNull($paid);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0257')
            ->assertSee('Already paid')
            ->assertSee('et-chip--paid', false)
            ->assertSee('Record disabled — invoice already paid', false)
            ->assertSee('aria-label="About Already paid"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button[^>]*\bdisabled\b[^>]*>\s*Record payment/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value=""/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$paid->id.'"/',
            $html
        );
        $this->assertStringNotContainsString('No confident match', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/et-card__match-title">\s*INV-2026-0300/s',
            $html
        );
    }

    public function test_open_cited_amount_match_is_preselected_over_paid_cited_invoice(): void
    {
        $this->actingAsAdmin();

        $zohar = ClientProject::factory()->create([
            'client_name' => 'Zohar Barak',
            'client_email' => 'zohar@example.test',
        ]);
        ClientProjectInvoice::factory()->for($zohar, 'project')->paid()->create([
            'invoice_number' => 'INV-2026-0257',
            'subtotal' => 2000,
            'hst' => 260,
            'total' => 2260,
        ]);

        $malcolm = ClientProject::factory()->create([
            'client_name' => 'Malcolm Travis',
            'client_email' => 'malcolm@example.test',
        ]);
        $open = ClientProjectInvoice::factory()->for($malcolm, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Zohar Barak',
            'amount' => 379.61,
            'message' => 'INV-2026-0257 and INV-2026-0258',
            'reference_number' => 'CAD1BOTH0257',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0258')
            ->assertSee('Best match')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$open->id.'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*\bdisabled\b[^>]*>\s*Record payment/s',
            $html
        );
        $this->assertStringNotContainsString('Record disabled — invoice already paid', $html);
    }

    public function test_short_memo_form_shows_paid_invoice_candidate(): void
    {
        $this->actingAsAdmin();

        $zohar = ClientProject::factory()->create([
            'client_name' => 'Zohar Barak',
            'client_email' => 'zohar@example.test',
        ]);
        ClientProjectInvoice::factory()->for($zohar, 'project')->paid()->create([
            'invoice_number' => 'INV-2026-0257',
            'subtotal' => 2000,
            'hst' => 260,
            'total' => 2260,
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Zohar Barak',
            'amount' => 2260,
            'message' => 'invoice #257',
            'reference_number' => 'CASHORT0257',
        ]);

        $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0257')
            ->assertSee('Already paid')
            ->assertSee('Record disabled — invoice already paid', false);
    }

    public function test_amount_tie_is_ambiguous_and_not_preselected(): void
    {
        $this->actingAsAdmin();

        $a = ClientProject::factory()->create([
            'client_name' => 'Alpha Client',
            'client_email' => 'alpha@example.test',
        ]);
        $invoiceA = ClientProjectInvoice::factory()->for($a, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0010',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);
        $b = ClientProject::factory()->create([
            'client_name' => 'Beta Client',
            'client_email' => 'beta@example.test',
        ]);
        $invoiceB = ClientProjectInvoice::factory()->for($b, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0011',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Unknown Sender',
            'amount' => 379.61,
            'message' => 'thanks',
            'reference_number' => 'CAAMBIG0001',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('Ambiguous match')
            ->assertSee('Several open invoices match this amount')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value=""/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoiceA->id.'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoiceB->id.'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<button[^>]*\bdisabled\b[^>]*>\s*Record payment/s',
            $html
        );
    }

    public function test_recency_only_shows_no_confident_match_with_info_tip(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'client_name' => 'Unrelated Person',
            'client_email' => 'unrelated@example.test',
        ]);
        ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0400',
            'subtotal' => 1592.92,
            'hst' => 207.08,
            'total' => 1800,
            'sent_at' => now()->subDays(1),
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Someone Else',
            'sender_email' => 'nobody@example.test',
            'amount' => 55.55,
            'message' => 'thanks',
            'reference_number' => 'CATHRESH0001',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('No confident match')
            ->assertSee('admin-info-tip', false)
            ->assertSee('aria-label="About No confident match"', false)
            ->assertDontSee('No strong match', false)
            ->assertSee('disabled', false)
            ->getContent();

        $this->assertStringNotContainsString('<p class="et-card__match-sub">No confident match', $html);
        $this->assertStringNotContainsString('<p class="admin-form-help"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/et-card__match-title">\s*INV-2026-0400/s',
            $html
        );
    }

    public function test_malcolm_travis_name_and_amount_preselects_invoice(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Malcolm bathroom',
            'client_name' => 'Malcolm Travis',
            'client_email' => 'malcolm@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Malcolm Travis',
            'sender_email' => null,
            'amount' => 379.61,
            'message' => null,
            'reference_number' => 'CAMALCOLM258',
        ]);

        $notice = EtransferNotice::query()->where('reference_number', 'CAMALCOLM258')->first();
        $this->assertNotNull($notice);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0258')
            ->assertSee('Malcolm Travis')
            ->assertSee('Best match')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoice->id.'"/',
            $html
        );
        $this->assertStringContainsString('INV-2026-0258 · Malcolm Travis', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<button[^>]*\bdisabled\b[^>]*>\s*Record payment/s',
            $html
        );
    }

    public function test_unit_hash_does_not_preselect_other_clients_invoice_over_malcolm(): void
    {
        $this->actingAsAdmin();

        $malcolm = ClientProject::factory()->create([
            'client_name' => 'Malcolm Travis',
            'client_email' => 'malcolm@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($malcolm, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);
        $other = ClientProject::factory()->create([
            'client_name' => 'Other Client',
            'client_email' => 'other@example.test',
        ]);
        ClientProjectInvoice::factory()->for($other, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0305',
            'subtotal' => 796.46,
            'hst' => 103.54,
            'total' => 900,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'Unit #305 bathroom',
            'reference_number' => 'CAUNIT305',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0258')
            ->assertSee('Best match')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoice->id.'"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/et-card__match-title">\s*INV-2026-0305/s',
            $html
        );
        $this->assertStringNotContainsString('Ambiguous match', $html);
    }

    public function test_phone_digits_do_not_preselect_invoice(): void
    {
        $this->actingAsAdmin();

        $malcolm = ClientProject::factory()->create([
            'client_name' => 'Malcolm Travis',
            'client_email' => 'malcolm@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($malcolm, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'Call 647-834-0258',
            'reference_number' => 'CAPHONE0258',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoice->id.'"/',
            $html
        );
    }

    public function test_old_paid_unit_hash_does_not_hide_malcolm_amount_match(): void
    {
        $this->actingAsAdmin();

        $other = ClientProject::factory()->create([
            'client_name' => 'Other Client',
            'client_email' => 'other@example.test',
        ]);
        ClientProjectInvoice::factory()->for($other, 'project')->paid()->create([
            'invoice_number' => 'INV-2025-0305',
            'subtotal' => 796.46,
            'hst' => 103.54,
            'total' => 900,
        ]);
        $malcolm = ClientProject::factory()->create([
            'client_name' => 'Malcolm Travis',
            'client_email' => 'malcolm@example.test',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($malcolm, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'unit #305',
            'reference_number' => 'CAOLD305',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('INV-2026-0258')
            ->assertDontSee('Already paid')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value="'.$invoice->id.'"/',
            $html
        );
        $this->assertStringNotContainsString('INV-2025-0305', $html);
    }

    public function test_two_open_invoices_cited_in_memo_show_cited_ambiguous_warning(): void
    {
        $this->actingAsAdmin();

        $a = ClientProject::factory()->create(['client_name' => 'Zohar Barak']);
        ClientProjectInvoice::factory()->for($a, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0257',
            'subtotal' => 442.48,
            'hst' => 57.52,
            'total' => 500,
        ]);
        $b = ClientProject::factory()->create(['client_name' => 'Malcolm Travis']);
        ClientProjectInvoice::factory()->for($b, 'project')->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Someone Else',
            'amount' => 379.61,
            'message' => 'INV-2026-0257 and INV-2026-0258',
            'reference_number' => 'CACITED2',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('Ambiguous match')
            ->assertSee('This payment may cover more than one')
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value=""/',
            $html
        );
    }

    public function test_bare_hash_does_not_surface_paid_invoice_from_any_year(): void
    {
        $this->actingAsAdmin();

        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Zohar Barak']),
            'project'
        )->paid()->create([
            'invoice_number' => 'INV-2025-0257',
            'subtotal' => 2000,
            'hst' => 260,
            'total' => 2260,
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Someone Else',
            'amount' => 50,
            'message' => '#257',
            'reference_number' => 'CABAREHASH257',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('INV-2025-0257', $html);
        $this->assertStringNotContainsString('Already paid', $html);
        $this->assertStringNotContainsString('Record disabled — invoice already paid', $html);
    }

    public function test_four_digit_memo_is_not_treated_as_invoice_citation(): void
    {
        $this->actingAsAdmin();

        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Other Client']),
            'project'
        )->paid()->create([
            'invoice_number' => 'INV-2026-0305',
            'subtotal' => 796.46,
            'hst' => 103.54,
            'total' => 900,
        ]);

        EtransferNotice::factory()->create([
            'sender_name' => 'Someone Else',
            'amount' => 50,
            'message' => '0305',
            'reference_number' => 'CAFOUR0305',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('INV-2026-0305', $html);
        $this->assertStringNotContainsString('Already paid', $html);
    }

    public function test_cited_other_client_and_sender_amount_match_are_ambiguous(): void
    {
        $this->actingAsAdmin();

        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Malcolm Travis']),
            'project'
        )->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);
        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Zohar Barak']),
            'project'
        )->sent()->create([
            'invoice_number' => 'INV-2026-0400',
            'subtotal' => 442.48,
            'hst' => 57.52,
            'total' => 500,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Zohar Barak',
            'amount' => 500,
            'message' => 'INV-2026-0258',
            'reference_number' => 'CACONFLICT1',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('Ambiguous match')
            ->assertSee('INV-2026-0258 (Malcolm Travis)', false)
            ->assertSee('INV-2026-0400 (Zohar Barak)', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value=""/',
            $html
        );
    }

    public function test_cited_zohar_invoice_and_malcolm_amount_match_are_ambiguous(): void
    {
        $this->actingAsAdmin();

        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Malcolm Travis']),
            'project'
        )->sent()->create([
            'invoice_number' => 'INV-2026-0258',
            'subtotal' => 335.94,
            'hst' => 43.67,
            'total' => 379.61,
        ]);
        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Zohar Barak']),
            'project'
        )->sent()->create([
            'invoice_number' => 'INV-2026-0400',
            'subtotal' => 442.48,
            'hst' => 57.52,
            'total' => 500,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'INV-2026-0400',
            'reference_number' => 'CACONFLICT2',
        ]);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->assertSee('Ambiguous match')
            ->assertSee('INV-2026-0400 (Zohar Barak)', false)
            ->assertSee('INV-2026-0258 (Malcolm Travis)', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/id="et-inv-'.$notice->id.'-value"[^>]*value=""/',
            $html
        );
    }

    public function test_phone_digits_do_not_lookup_paid_invoice_from_database(): void
    {
        $this->actingAsAdmin();

        ClientProjectInvoice::factory()->for(
            ClientProject::factory()->create(['client_name' => 'Other Client']),
            'project'
        )->paid()->create([
            'invoice_number' => 'INV-2026-0647',
            'subtotal' => 796.46,
            'hst' => 103.54,
            'total' => 900,
        ]);

        $notice = EtransferNotice::factory()->create([
            'sender_name' => 'Unrelated Person',
            'amount' => 50,
            'message' => 'please call inv 647-834-0258',
            'reference_number' => 'CALOOKUP647',
        ]);

        $tokens = (new EtransferInvoiceMatcher)->mentionLookupTokens(
            (string) $notice->message
        );
        $this->assertSame([], $tokens);

        $html = $this->get(route('admin.financials.etransfers'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('INV-2026-0647', $html);
        $this->assertStringNotContainsString('Already paid', $html);
    }
}
