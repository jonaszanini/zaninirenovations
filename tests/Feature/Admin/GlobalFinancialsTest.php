<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class GlobalFinancialsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_invoices_page_loads_and_shows_invoices(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);
        $invoice = ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
            'status' => ClientProjectInvoice::STATUS_SENT,
            'invoice_number' => 'INV-2026-0001',
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
        ]);

        $this->get(route('admin.financials.invoices'))
            ->assertOk()
            ->assertSee('All Invoices')
            ->assertSee('INV-2026-0001');
    }

    public function test_invoices_page_filters_by_status(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);
        $sentInvoice = ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
            'status' => ClientProjectInvoice::STATUS_SENT,
            'invoice_number' => 'INV-SENT-001',
        ]);
        $paidInvoice = ClientProjectInvoice::factory()->create([
            'client_project_id' => $project->id,
            'status' => ClientProjectInvoice::STATUS_PAID,
            'invoice_number' => 'INV-PAID-001',
        ]);

        $this->get(route('admin.financials.invoices', ['status' => 'sent']))
            ->assertOk()
            ->assertSee('INV-SENT-001')
            ->assertDontSee('INV-PAID-001');
    }

    public function test_expenses_page_loads_and_shows_expenses(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);
        $expense = ClientProjectExpense::factory()->create([
            'client_project_id' => $project->id,
            'description' => 'Lumber and screws',
            'amount' => 150.00,
        ]);

        $this->get(route('admin.financials.expenses'))
            ->assertOk()
            ->assertSee('All Expenses')
            ->assertSee('Lumber and screws');
    }

    public function test_quick_expense_can_be_created(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);

        $this->from(route('admin.financials.expenses'))
            ->post(route('admin.financials.expenses.store'), [
                'client_project_id' => $project->id,
                'occurred_on' => now()->toDateString(),
                'description' => 'Test expense',
                'amount' => 99.50,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('client_project_expenses', [
            'client_project_id' => $project->id,
            'description' => 'Test expense',
            'amount' => 99.50,
        ]);
    }

    public function test_project_search_returns_active_projects(): void
    {
        $this->actingAsAdmin();

        $activeProject = ClientProject::factory()->create([
            'name' => 'Active Project Test',
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);
        $completedProject = ClientProject::factory()->create([
            'name' => 'Completed Project Test',
            'status' => ClientProject::STATUS_COMPLETED,
        ]);

        $this->get(route('admin.financials.projects.search', ['q' => 'Project Test']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'Active Project Test'])
            ->assertJsonMissing(['name' => 'Completed Project Test']);
    }
}
