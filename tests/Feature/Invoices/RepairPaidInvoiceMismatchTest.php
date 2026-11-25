<?php

namespace Tests\Feature\Invoices;

use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Services\Invoice\RepairPaidInvoiceMismatchService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;

class RepairPaidInvoiceMismatchTest extends IntegrationTestCase
{
    public function test_repair_fixes_mismatched_invoice_without_email_and_is_idempotent(): void
    {
        Mail::fake();
        Log::spy();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'client_email' => 'client@example.com',
        ]);
        $installment = ClientProjectInstallment::query()->create([
            'client_project_id' => $project->id,
            'sort_order' => 0,
            'label' => 'On signing',
            'percentage' => 100,
            'amount' => 205.00,
            'is_paid' => true,
            'paid_on' => '2026-11-23',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'invoice_number' => 'INV-MISMATCH-0001',
            'installment_number' => 1,
            'subtotal' => 205.00,
            'hst' => 26.65,
            'total' => 231.65,
            'paid_at' => null,
            'paid_amount' => null,
            'reminders_paused_at' => null,
        ]);

        $this->artisan('invoices:list-paid-mismatch')
            ->expectsOutput('INV-MISMATCH-0001')
            ->assertSuccessful();

        $invoice->refresh();
        $this->assertSame(ClientProjectInvoice::STATUS_SENT, $invoice->status);
        $this->assertNull($invoice->reminders_paused_at);

        $this->artisan('invoices:repair-paid-mismatch')->assertSuccessful();

        $invoice->refresh();
        $installment->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertSame('2026-11-23', $invoice->paid_at->toDateString());
        $this->assertSame(231.65, (float) $invoice->paid_amount);
        $this->assertNotNull($invoice->reminders_paused_at);
        $this->assertTrue((bool) $installment->is_paid);
        Mail::assertNothingSent();

        Log::shouldHaveReceived('info')
            ->once()
            ->with('Repaired paid invoice mismatch', ['invoice_number' => 'INV-MISMATCH-0001']);

        $paidAt = $invoice->paid_at->toDateTimeString();
        $pausedAt = $invoice->reminders_paused_at->toDateTimeString();
        $amount = (string) $invoice->paid_amount;

        $this->artisan('invoices:repair-paid-mismatch')
            ->expectsOutput('No paid mismatches to repair.')
            ->assertSuccessful();

        $invoice->refresh();
        $this->assertSame($paidAt, $invoice->paid_at->toDateTimeString());
        $this->assertSame($pausedAt, $invoice->reminders_paused_at->toDateTimeString());
        $this->assertSame($amount, (string) $invoice->paid_amount);
        $this->assertTrue($invoice->isPaid());
        Mail::assertNothingSent();

        $this->artisan('invoices:list-paid-mismatch')
            ->expectsOutput('No paid mismatches.')
            ->assertSuccessful();
    }

    public function test_repair_leaves_an_already_paid_invoice_untouched(): void
    {
        Mail::fake();
        Log::spy();

        $paidAt = Carbon::parse('2026-11-23 15:04:00');
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'client_email' => 'client@example.com',
        ]);
        ClientProjectInstallment::query()->create([
            'client_project_id' => $project->id,
            'sort_order' => 0,
            'label' => 'On signing',
            'percentage' => 100,
            'amount' => 205.00,
            'is_paid' => true,
            'paid_on' => '2026-11-23',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->paid()->create([
            'invoice_number' => 'INV-ALREADY-PAID-0001',
            'installment_number' => 1,
            'subtotal' => 205.00,
            'hst' => 26.65,
            'total' => 231.65,
            'paid_at' => $paidAt,
            'paid_amount' => 231.65,
            'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            'reminders_paused_at' => null,
        ]);

        $before = [
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at->toDateTimeString(),
            'paid_amount' => (string) $invoice->paid_amount,
            'payment_method' => $invoice->payment_method,
            'reminders_paused_at' => $invoice->reminders_paused_at,
        ];

        $changed = app(RepairPaidInvoiceMismatchService::class)->repair();

        $invoice->refresh();
        $this->assertSame([], $changed);
        $this->assertSame($before, [
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at->toDateTimeString(),
            'paid_amount' => (string) $invoice->paid_amount,
            'payment_method' => $invoice->payment_method,
            'reminders_paused_at' => $invoice->reminders_paused_at,
        ]);
        $this->assertTrue($invoice->isPaid());
        $this->assertNull($invoice->reminders_paused_at);
        Mail::assertNothingSent();
        Log::shouldNotHaveReceived('info');
    }
}
