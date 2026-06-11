<?php

namespace Tests\Feature\Invoices;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ActsAsAdmin;
use Tests\IntegrationTestCase;

class MarkPaidInvoiceTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_guest_cannot_mark_invoice_paid(): void
    {
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->create();

        $this->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]))
            ->assertRedirect(route('login'));
    }

    public function test_cannot_mark_already_paid_invoice(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->paid()->create();

        $this->from('/admin/client-projects/'.$project->id)
            ->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
                'paid_on' => now()->toDateString(),
                'paid_amount' => $invoice->total,
                'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_CASH,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_cannot_mark_empty_invoice_paid(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->create([
            'items' => [],
            'subtotal' => 0,
            'hst' => 0,
            'total' => 0,
        ]);

        $this->from('/admin/client-projects/'.$project->id)
            ->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
                'paid_on' => now()->toDateString(),
                'paid_amount' => 0,
                'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_CASH,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_mark_paid_persists_payment_and_generates_receipt_path(): void
    {
        Storage::fake('local');
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->sent()->create();

        $this->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
            'paid_on' => '2026-06-01',
            'paid_amount' => $invoice->total,
            'payment_method' => ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            'also_email_receipt' => false,
            'also_sms_receipt' => false,
        ])->assertRedirect();

        $invoice->refresh();

        $this->assertTrue($invoice->isPaid());
        $this->assertSame(ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER, $invoice->payment_method);
        $this->assertSame((float) $invoice->total, (float) $invoice->paid_amount);
        $this->assertNotNull($invoice->receipt_pdf_path);
        Storage::disk('local')->assertExists($invoice->receipt_pdf_path);
    }

    public function test_mark_paid_validation_rejects_invalid_payment_method(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->create();

        $this->post(route('admin.client-projects.invoices.mark-paid', [$project, $invoice]), [
            'paid_on' => now()->toDateString(),
            'paid_amount' => $invoice->total,
            'payment_method' => 'bitcoin',
        ])->assertSessionHasErrors('payment_method');
    }
}
