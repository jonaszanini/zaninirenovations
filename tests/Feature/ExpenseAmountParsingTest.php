<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\User;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ExpenseAmountParsingTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_global_expense_store_parses_grouped_amount(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);

        $response = $this->post(route('admin.financials.expenses.store'), [
            'client_project_id' => $project->id,
            'occurred_on' => now()->toDateString(),
            'description' => 'Test expense with grouped amount',
            'amount' => '1,234.56',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_project_expenses', [
            'client_project_id' => $project->id,
            'description' => 'Test expense with grouped amount',
            'amount' => 1234.56,
        ]);
    }

    public function test_project_expense_store_parses_grouped_amount(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);

        $response = $this->post(route('admin.client-projects.expenses.store', $project), [
            'occurred_on' => now()->toDateString(),
            'description' => 'Test project expense with grouped amount',
            'amount' => '1,234.56',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_project_expenses', [
            'client_project_id' => $project->id,
            'description' => 'Test project expense with grouped amount',
            'amount' => 1234.56,
        ]);
    }

    public function test_expense_store_accepts_plain_decimal(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);

        $response = $this->post(route('admin.financials.expenses.store'), [
            'client_project_id' => $project->id,
            'occurred_on' => now()->toDateString(),
            'description' => 'Test expense plain decimal',
            'amount' => '567.89',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('client_project_expenses', [
            'client_project_id' => $project->id,
            'description' => 'Test expense plain decimal',
            'amount' => 567.89,
        ]);
    }
}
