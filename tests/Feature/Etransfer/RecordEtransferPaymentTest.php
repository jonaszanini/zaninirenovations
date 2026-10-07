<?php

namespace Tests\Feature\Etransfer;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
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
            ->assertSee('INV-2026-0001');
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
}
