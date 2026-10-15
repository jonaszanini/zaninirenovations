<?php

namespace Tests\Feature\Invoices;

use App\Mail\ClientProjectInvoiceRefundClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RefundInvoiceTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_paid_and_cancelled_invoice_tabs_offer_refund(): void
    {
        $this->actingAsAdmin();

        [$openProject, $openInvoice] = $this->paidFirstInstallment();
        $this->get(route('admin.client-projects.show', $openProject))
            ->assertOk()
            ->assertSee('data-cp-refund-url="'.route('admin.client-projects.invoices.refund', [$openProject, $openInvoice]).'"', false)
            ->assertSee('id="cp-refund-modal"', false)
            ->assertSee('Refund invoice', false);

        [$cancelledProject, $cancelledInvoice] = $this->paidFirstInstallment();
        $cancelledProject->status = ClientProject::STATUS_CANCELLED;
        $cancelledProject->save();

        $this->get(route('admin.client-projects.show', $cancelledProject))
            ->assertOk()
            ->assertSee('data-cp-refund-url="'.route('admin.client-projects.invoices.refund', [$cancelledProject, $cancelledInvoice]).'"', false)
            ->assertSee('refund a paid invoice', false);
    }

    public function test_guest_cannot_refund_an_invoice(): void
    {
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->paid()->create();

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), [
            'refunded_on' => now()->toDateString(),
            'refund_amount' => $invoice->total,
            'refund_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
        ])->assertRedirect(route('login'));
    }

    public function test_full_refund_of_first_installment_drops_revenue_and_keeps_the_invoice(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $invoice] = $this->paidFirstInstallment();
        $project->status = ClientProject::STATUS_IN_PROGRESS ?? 'in_progress';
        $project->save();

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload((float) $invoice->paid_amount))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $project->refresh()->load(['installments', 'invoices']);

        $this->assertTrue($invoice->isRefunded());
        $this->assertSame(1, (int) $invoice->installment_number);
        $this->assertNotNull($invoice->refund_pdf_path);
        Storage::disk('local')->assertExists($invoice->refund_pdf_path);
        $this->assertFalse((bool) $project->installments->first()->is_paid);
        $this->assertSame(0.0, $project->totalRevenueCollected());
        $this->assertSame(ClientProject::STATUS_IN_PROGRESS ?? 'in_progress', $project->status);
        $this->assertNotNull(ClientProjectInvoice::query()->find($invoice->id));
    }

    public function test_full_refund_is_allowed_on_a_cancelled_project(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        [$project, $invoice] = $this->paidFirstInstallment();
        $project->status = ClientProject::STATUS_CANCELLED;
        $project->save();

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload((float) $invoice->paid_amount))
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $invoice->refresh();
        $project->refresh()->load('installments');

        $this->assertTrue($invoice->isRefunded());
        $this->assertSame(ClientProject::STATUS_CANCELLED, $project->status);
        $this->assertFalse((bool) $project->installments->first()->is_paid);
        $this->assertSame(0.0, $project->totalRevenueCollected());
    }

    public function test_partial_refund_reduces_revenue_by_the_pre_tax_share_and_stays_paid(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        [$project, $invoice] = $this->paidFirstInstallment();

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload(113))
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice->refresh();
        $project->refresh()->load(['installments', 'invoices']);

        $this->assertTrue($invoice->isPaid());
        $this->assertFalse($invoice->isRefunded());
        $this->assertSame(113.0, (float) $invoice->refund_amount);
        $this->assertSame(100.0, $invoice->preTaxShareOfRefund());
        $this->assertTrue((bool) $project->installments->first()->is_paid);
        $this->assertSame(900.0, $project->totalRevenueCollected());
    }

    public function test_cannot_refund_more_than_was_paid(): void
    {
        $this->actingAsAdmin();
        [$project, $invoice] = $this->paidFirstInstallment();

        $this->from('/admin/client-projects/'.$project->id)
            ->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload(2000))
            ->assertRedirect()
            ->assertSessionHas('error');

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertSame(0.0, (float) $invoice->refund_amount);
    }

    public function test_cannot_refund_an_unpaid_invoice(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create();

        $this->from('/admin/client-projects/'.$project->id)
            ->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload((float) $invoice->total))
            ->assertRedirect()
            ->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame(ClientProjectInvoice::STATUS_DRAFT, $invoice->status);
    }

    public function test_refund_can_email_a_receipt(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $invoice] = $this->paidFirstInstallment();

        $payload = $this->payload((float) $invoice->paid_amount);
        $payload['also_email_refund'] = '1';

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(ClientProjectInvoiceRefundClient::class);
    }

    public function test_hourly_refund_reduces_profit_by_the_pre_tax_share(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'billing_type' => ClientProject::BILLING_HOURLY,
            'hourly_rate' => 50,
        ]);
        $project->timeEntries()->create([
            'occurred_on' => now()->toDateString(),
            'hours' => 10,
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->paid()->create([
            'subtotal' => 100,
            'hst' => 13,
            'total' => 113,
            'paid_amount' => 113,
        ]);

        $this->post(route('admin.client-projects.invoices.refund', [$project, $invoice]), $this->payload(113))
            ->assertSessionHas('success');

        $project->refresh()->load(['timeEntries', 'invoices']);

        $this->assertSame(400.0, $project->totalRevenueCollected());
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInvoice}
     */
    private function paidFirstInstallment(): array
    {
        $project = ClientProject::factory()->create([
            'billing_type' => ClientProject::BILLING_FIXED,
            'status' => 'in_progress',
        ]);
        $project->installments()->create([
            'sort_order' => 1,
            'label' => 'On signing',
            'percentage' => 50,
            'amount' => 1000,
            'is_paid' => true,
            'paid_on' => now()->toDateString(),
        ]);
        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->paid()->create([
            'installment_number' => 1,
            'installment_description' => 'On signing',
            'subtotal' => 1000,
            'hst' => 130,
            'total' => 1130,
            'paid_amount' => 1130,
        ]);

        return [$project, $invoice];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(float $amount): array
    {
        return [
            'refunded_on' => now()->toDateString(),
            'refund_amount' => $amount,
            'refund_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            'also_email_refund' => '0',
        ];
    }
}
