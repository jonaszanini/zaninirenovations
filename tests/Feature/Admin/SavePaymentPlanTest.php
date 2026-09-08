<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SavePaymentPlanTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_saving_pay_in_full_persists_and_survives_project_show(): void
    {
        $project = $this->makeProjectWithTwoInstallments();

        $this->actingAsAdmin();
        $this->post(route('admin.client-projects.installments.save', $project), [
            'payment_plan' => 1,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(1, (int) $project->payment_plan);
        $this->assertCount(1, $project->installments);
        $this->assertEqualsWithDelta(100.0, (float) $project->installments->first()->percentage, 0.01);
        $this->assertSame(
            ClientProjectFinanceController::installmentLabel(1, 0),
            $project->installments->first()->label
        );

        $this->get(route('admin.client-projects.show', $project))->assertOk();

        $project->refresh();
        $this->assertSame(1, (int) $project->payment_plan);
        $this->assertCount(1, $project->installments);
    }

    public function test_saving_pay_in_full_updates_unpaid_invoices_and_deletes_orphans(): void
    {
        $project = $this->makeProjectWithTwoInstallments();

        $first = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => 1,
            'installment_description' => 'On signing (holds the schedule)',
            'subtotal' => 80.00,
            'hst' => 10.40,
            'total' => 90.40,
            'items' => [['description' => 'On signing (holds the schedule)', 'amount' => 80.00]],
            'status' => ClientProjectInvoice::STATUS_DRAFT,
        ]);
        $second = ClientProjectInvoice::factory()->for($project, 'project')->sent()->create([
            'installment_number' => 2,
            'installment_description' => 'On completion',
            'subtotal' => 120.00,
            'hst' => 15.60,
            'total' => 135.60,
            'items' => [['description' => 'On completion', 'amount' => 120.00]],
        ]);

        $this->actingAsAdmin();
        $this->post(route('admin.client-projects.installments.save', $project), [
            'payment_plan' => 1,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(1, (int) $project->payment_plan);
        $this->assertCount(1, $project->installments);

        $first->refresh();
        $this->assertEqualsWithDelta(200.00, (float) $first->subtotal, 0.01);
        $this->assertSame(
            ClientProjectFinanceController::installmentLabel(1, 0),
            $first->installment_description
        );

        $this->assertDatabaseMissing('client_project_invoices', ['id' => $second->id]);
    }

    public function test_saving_payment_plan_does_not_modify_paid_invoices(): void
    {
        $project = $this->makeProjectWithTwoInstallments();

        $paid = ClientProjectInvoice::factory()->for($project, 'project')->paid()->create([
            'installment_number' => 1,
            'installment_description' => 'On signing (holds the schedule)',
            'subtotal' => 80.00,
            'hst' => 10.40,
            'total' => 90.40,
            'paid_amount' => 90.40,
            'items' => [['description' => 'On signing (holds the schedule)', 'amount' => 80.00]],
        ]);
        $unpaid = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'installment_number' => 2,
            'installment_description' => 'On completion',
            'subtotal' => 120.00,
            'hst' => 15.60,
            'total' => 135.60,
            'items' => [['description' => 'On completion', 'amount' => 120.00]],
        ]);

        $this->actingAsAdmin();
        $this->post(route('admin.client-projects.installments.save', $project), [
            'payment_plan' => 2,
            'percentages' => [50, 50],
        ])->assertRedirect();

        $paid->refresh();
        $this->assertEqualsWithDelta(80.00, (float) $paid->subtotal, 0.01);
        $this->assertSame(ClientProjectInvoice::STATUS_PAID, $paid->status);

        $unpaid->refresh();
        $this->assertEqualsWithDelta(100.00, (float) $unpaid->subtotal, 0.01);
    }

    private function makeProjectWithTwoInstallments(): ClientProject
    {
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
            'billing_type' => ClientProject::BILLING_FIXED,
            'payment_plan' => 2,
            'total_amount' => 200,
        ]);

        ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Standard quotation',
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-'.str_pad((string) random_int(8000, 9999), 4, '0', STR_PAD_LEFT),
            'items' => [
                [
                    'description' => 'Labour',
                    'amount' => 200.00,
                    'apply_hst' => true,
                ],
            ],
            'payment_installments' => 2,
            'payment_percentages' => [40, 60],
        ]);

        $project->installments()->create([
            'sort_order' => 0,
            'label' => ClientProjectFinanceController::installmentLabel(2, 0),
            'percentage' => 40,
            'amount' => 80,
            'is_paid' => false,
        ]);
        $project->installments()->create([
            'sort_order' => 1,
            'label' => ClientProjectFinanceController::installmentLabel(2, 1),
            'percentage' => 60,
            'amount' => 120,
            'is_paid' => false,
        ]);

        return $project;
    }
}
