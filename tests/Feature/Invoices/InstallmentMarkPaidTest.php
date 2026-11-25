<?php

namespace Tests\Feature\Invoices;

use App\Mail\ClientProjectInvoiceReceiptClient;
use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Services\Invoice\PrepareBillingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class InstallmentMarkPaidTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00:00', 'America/Toronto'));
        config(['client_projects.past_due_invoice_reminders_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_marking_installment_invoice_paid_updates_status_totals_receipt_and_stops_reminders(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $installment, $invoice] = $this->sentInstallmentInvoice();

        $this->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
            'paid_on' => '2026-11-23',
            'paid_amount' => '231.65',
            'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            'payment_reference' => 'ET1123',
            'also_email_receipt' => '1',
            'also_sms_receipt' => '0',
            'return_fragment' => 'financials',
        ])->assertRedirect(route('admin.client-projects.show', $project).'#financials');

        $invoice->refresh();
        $installment->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame('2026-11-23', $invoice->paid_at->toDateString());
        $this->assertSame(231.65, (float) $invoice->paid_amount);
        $this->assertSame(ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER, $invoice->payment_method);
        $this->assertSame('ET1123', $invoice->payment_reference);
        $this->assertNotNull($invoice->reminders_paused_at);
        $this->assertTrue((bool) $installment->is_paid);
        $this->assertSame('2026-11-23', $installment->paid_on->toDateString());

        Mail::assertSent(ClientProjectInvoiceReceiptClient::class, function ($mail) use ($invoice) {
            return $mail->hasTo('client@example.com') && $mail->invoice->is($invoice);
        });

        $this->artisan('client-projects:send-past-due-invoice-reminders')->assertSuccessful();
        Mail::assertNotSent(ClientProjectInvoiceReminderClient::class);

        $project->unsetRelation('invoices');
        $project->unsetRelation('installments');
        $totals = app(PrepareBillingService::class)->totals($project->fresh());
        $this->assertSame(231.65, $totals['paid']);
        $this->assertSame(0.0, $totals['outstanding']);

        $html = $this->get(route('admin.client-projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('E-transfer', $html);
        $this->assertStringContainsString('ET1123', $html);
        $this->assertStringNotContainsString(
            route('admin.client-projects.invoices.reminder', [$project, $invoice]),
            $html
        );
        $this->assertStringNotContainsString(
            route('admin.client-projects.invoices.mark-paid', [$project, $invoice]),
            $html
        );
        $this->assertStringNotContainsString(
            route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]),
            $html
        );
    }

    public function test_regular_invoice_mark_paid_still_saves_method_reference_and_shows_them(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'client_email' => 'client@example.com',
            'client_phone' => '4165550100',
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'due_on' => '2026-11-20',
        ]);

        $this->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
            'paid_on' => '2026-11-25',
            'paid_amount' => $invoice->total,
            'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_CHECK,
            'payment_reference' => 'CHQ88',
            'also_email_receipt' => '1',
            'also_sms_receipt' => '0',
        ])->assertRedirect();

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertSame(ClientProjectInvoice::PAYMENT_METHOD_CHECK, $invoice->payment_method);
        $this->assertSame('CHQ88', $invoice->payment_reference);
        $this->assertNotNull($invoice->reminders_paused_at);

        Mail::assertSent(ClientProjectInvoiceReceiptClient::class);

        $html = $this->get(route('admin.client-projects.show', $project))->assertOk()->getContent();
        $this->assertStringContainsString('Cheque', $html);
        $this->assertStringContainsString('CHQ88', $html);
    }

    public function test_financials_mark_paid_button_uses_the_invoice_dialog(): void
    {
        $this->actingAsAdmin();
        [$project, $installment, $invoice] = $this->sentInstallmentInvoice();

        $html = $this->get(route('admin.client-projects.show', $project))->assertOk()->getContent();

        $this->assertStringContainsString(
            route('admin.client-projects.invoices.mark-paid', [$project, $invoice]),
            $html
        );
        $this->assertStringContainsString('id="cp-mark-paid-method"', $html);
        $this->assertStringContainsString('name="payment_reference"', $html);
        $this->assertStringContainsString('Cheque', $html);
        $this->assertStringContainsString('Credit card', $html);
        $this->assertStringContainsString('data-cp-markpaid-fragment="financials"', $html);
        $this->assertSame($installment->client_project_id, $project->id);
    }

    public function test_stop_reminders_succeeds_when_installment_is_paid_and_invoice_is_still_sent(): void
    {
        $this->actingAsAdmin();
        [$project, $installment, $invoice] = $this->sentInstallmentInvoice();

        $installment->forceFill([
            'is_paid' => true,
            'paid_on' => '2026-11-23',
        ])->save();

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $invoice->refresh();
        $this->assertSame(ClientProjectInvoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->reminders_paused_at);
        $this->assertTrue($invoice->dailyRemindersPaused());
    }

    public function test_stop_reminders_accepts_an_emailed_invoice_whose_status_is_not_exactly_sent(): void
    {
        $this->actingAsAdmin();
        [$project, $installment, $invoice] = $this->sentInstallmentInvoice();

        $invoice->forceFill([
            'status' => ClientProjectInvoice::STATUS_DRAFT,
            'sent_at' => now(),
        ])->save();
        $installment->forceFill([
            'is_paid' => true,
            'paid_on' => '2026-11-23',
        ])->save();

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.invoices.reminders.toggle', [$project, $invoice]))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $this->assertNotNull($invoice->fresh()->reminders_paused_at);
    }

    public function test_reminder_command_skips_invoice_whose_installment_is_marked_paid(): void
    {
        Mail::fake();

        [$project, $installment, $invoice] = $this->sentInstallmentInvoice();
        $installment->forceFill([
            'is_paid' => true,
            'paid_on' => '2026-11-23',
        ])->save();

        $this->assertSame(ClientProjectInvoice::STATUS_SENT, $invoice->status);

        $this->artisan('client-projects:send-past-due-invoice-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInstallment, 2: ClientProjectInvoice}
     */
    private function sentInstallmentInvoice(): array
    {
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'client_email' => 'client@example.com',
            'client_phone' => '4165550100',
            'billing_type' => ClientProject::BILLING_FIXED,
            'payment_plan' => 2,
        ]);

        $installment = ClientProjectInstallment::query()->create([
            'client_project_id' => $project->id,
            'sort_order' => 0,
            'label' => 'On signing',
            'percentage' => 50,
            'amount' => 205.00,
            'is_paid' => false,
        ]);

        ClientProjectInstallment::query()->create([
            'client_project_id' => $project->id,
            'sort_order' => 1,
            'label' => 'On completion',
            'percentage' => 50,
            'amount' => 205.00,
            'is_paid' => false,
        ]);

        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'installment_number' => 1,
            'installment_description' => 'On signing',
            'subtotal' => 205.00,
            'hst' => 26.65,
            'total' => 231.65,
            'due_on' => '2026-11-20',
            'items' => [
                ['description' => 'On signing', 'amount' => 205.00, 'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR],
            ],
        ]);

        return [$project, $installment, $invoice];
    }
}
