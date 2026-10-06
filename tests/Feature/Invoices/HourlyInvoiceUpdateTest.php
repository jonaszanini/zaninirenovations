<?php

namespace Tests\Feature\Invoices;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class HourlyInvoiceUpdateTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_save_and_close_hourly_invoice_with_labour_materials_expense_and_credit(): void
    {
        $this->actingAsAdmin();

        [$project, $invoice, $expense] = $this->makeHourlyInvoiceDraft();

        $response = $this->put(
            route('admin.client-projects.invoices.update', [$project, $invoice]),
            $this->payload($expense, 'close')
        );

        $response->assertRedirect(route('admin.client-projects.show', $project).'#invoice');
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(150.0, (float) $invoice->subtotal);
        $this->assertSame(19.5, (float) $invoice->hst);
        $this->assertSame(204.5, (float) $invoice->total);
        $this->assertSame($invoice->id, $expense->fresh()->client_project_invoice_id);
        $kinds = array_column($invoice->cleanItems(), 'line_kind');
        $this->assertContains(ClientProjectInvoice::LINE_KIND_LABOUR, $kinds);
        $this->assertContains(ClientProjectInvoice::LINE_KIND_MATERIALS, $kinds);
        $this->assertContains(ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT, $kinds);
    }

    public function test_save_draft_hourly_invoice_with_labour_materials_expense_and_credit(): void
    {
        $this->actingAsAdmin();

        [$project, $invoice, $expense] = $this->makeHourlyInvoiceDraft();

        $response = $this->put(
            route('admin.client-projects.invoices.update', [$project, $invoice]),
            $this->payload($expense, 'draft')
        );

        $response->assertRedirect(route('admin.client-projects.invoices.edit', [$project, $invoice]));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->get(route('admin.client-projects.invoices.edit', [$project, $invoice]))
            ->assertOk();

        $invoice->refresh();
        $this->assertSame(150.0, (float) $invoice->subtotal);
        $this->assertSame(19.5, (float) $invoice->hst);
        $this->assertSame(204.5, (float) $invoice->total);
        $this->assertSame($invoice->id, $expense->fresh()->client_project_invoice_id);
        $kinds = array_column($invoice->cleanItems(), 'line_kind');
        $this->assertContains(ClientProjectInvoice::LINE_KIND_LABOUR, $kinds);
        $this->assertContains(ClientProjectInvoice::LINE_KIND_MATERIALS, $kinds);
        $this->assertContains(ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT, $kinds);
    }

    public function test_project_show_renders_invoice_row_actions_for_sent_hourly_invoice(): void
    {
        $this->actingAsAdmin();

        [$project, $invoice] = $this->makeHourlyInvoiceDraft();
        $invoice->update([
            'status' => ClientProjectInvoice::STATUS_SENT,
            'sent_at' => now(),
            'due_on' => now()->addDays(10)->toDateString(),
        ]);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee($invoice->invoice_number, false);
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInvoice, 2: ClientProjectExpense}
     */
    private function makeHourlyInvoiceDraft(): array
    {
        $project = ClientProject::factory()->create([
            'name' => 'Hourly wood job',
            'client_name' => 'Luccas',
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_HOURLY,
            'hourly_rate' => 50,
        ]);

        $expense = ClientProjectExpense::factory()->create([
            'client_project_id' => $project->id,
            'occurred_on' => '2026-10-06',
            'description' => '2 x 6 - Wood',
            'amount' => 35.00,
            'expense_ref' => 1,
        ]);

        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => null,
            'installment_description' => 'Hourly work',
            'items' => [
                [
                    'description' => 'Oct 6, 2026 · 3.50 h @ CA$50.00/h',
                    'amount' => 175.00,
                    'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR,
                ],
                [
                    'description' => 'Materials · 2 x 6 - Wood (Oct 6, 2026)',
                    'amount' => 35.00,
                    'line_kind' => ClientProjectInvoice::LINE_KIND_MATERIALS,
                    'expense_id' => $expense->id,
                ],
            ],
            'subtotal' => 175.00,
            'hst' => 22.75,
            'total' => 232.75,
            'hst_exempt' => false,
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);

        return [$project, $invoice, $expense];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ClientProjectExpense $expense, string $intent): array
    {
        return [
            'intent' => $intent,
            'hst_exempt' => '0',
            'manual_description' => 'Hourly work',
            'due_on' => '2026-10-16',
            'items' => [
                [
                    'description' => 'Oct 6, 2026 · 3.50 h @ CA$50.00/h',
                    'amount' => '175.00',
                    'line_kind' => 'labour',
                ],
                [
                    'description' => 'Materials · 2 x 6 - Wood (Oct 6, 2026)',
                    'amount' => '35.00',
                    'line_kind' => 'materials',
                    'expense_id' => (string) $expense->id,
                ],
                [
                    'description' => 'Prepaid toward estimate',
                    'amount' => '25.00',
                    'line_kind' => 'labour_credit',
                ],
            ],
        ];
    }
}
