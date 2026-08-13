<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SeedPaymentPlanFromQuotationTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_accepting_a_quotation_seeds_financials_installments_and_percentages(): void
    {
        $project = $this->makeProject();
        $quotation = $this->makeAcceptedQuotation($project, 2, [40, 60], 200.00, ClientProjectQuotation::STATUS_SENT);

        $this->actingAsAdmin();
        $this->post(route('admin.client-projects.quotation.status', [$project, $quotation]), [
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
        ])->assertRedirect();

        $project->refresh();
        $this->assertSame(2, (int) $project->payment_plan);
        $this->assertEqualsWithDelta(200.00, (float) $project->total_amount, 0.01);

        $rows = $project->installments()->orderBy('sort_order')->get();
        $this->assertCount(2, $rows);
        $this->assertEqualsWithDelta(40.0, (float) $rows[0]->percentage, 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $rows[1]->percentage, 0.01);
        $this->assertEqualsWithDelta(80.00, (float) $rows[0]->amount, 0.01);
        $this->assertEqualsWithDelta(120.00, (float) $rows[1]->amount, 0.01);
        $this->assertSame(
            ClientProjectFinanceController::installmentLabel(2, 0),
            $rows[0]->label
        );
        $this->assertSame(
            ClientProjectFinanceController::installmentLabel(2, 1),
            $rows[1]->label
        );
    }

    public function test_project_show_seeds_financials_when_installments_were_never_saved(): void
    {
        $project = $this->makeProject([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
        ]);
        $this->makeAcceptedQuotation($project, 2, [40, 60], 200.00);

        $this->assertSame(0, $project->installments()->count());
        $this->assertSame(1, (int) $project->payment_plan);

        $this->actingAsAdmin();
        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('2 installments', false)
            ->assertSee('40.00', false)
            ->assertSee('60.00', false);

        $project->refresh();
        $this->assertSame(2, (int) $project->payment_plan);
        $this->assertCount(2, $project->installments);
    }

    public function test_does_not_overwrite_a_saved_multi_installment_plan(): void
    {
        $project = $this->makeProject([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
            'payment_plan' => 3,
            'total_amount' => 200,
        ]);
        $this->makeAcceptedQuotation($project, 2, [40, 60], 200.00);

        $project->installments()->create([
            'sort_order' => 0,
            'label' => ClientProjectFinanceController::installmentLabel(3, 0),
            'percentage' => 30,
            'amount' => 60,
            'is_paid' => false,
        ]);
        $project->installments()->create([
            'sort_order' => 1,
            'label' => ClientProjectFinanceController::installmentLabel(3, 1),
            'percentage' => 30,
            'amount' => 60,
            'is_paid' => false,
        ]);
        $project->installments()->create([
            'sort_order' => 2,
            'label' => ClientProjectFinanceController::installmentLabel(3, 2),
            'percentage' => 40,
            'amount' => 80,
            'is_paid' => false,
        ]);

        $project->syncFixedContractTotalsFromQuotation();

        $project->refresh();
        $this->assertSame(3, (int) $project->payment_plan);
        $this->assertCount(3, $project->installments);
        $this->assertEqualsWithDelta(30.0, (float) $project->installments->sortBy('sort_order')->first()->percentage, 0.01);
    }

    public function test_does_not_seed_when_an_installment_is_already_paid(): void
    {
        $project = $this->makeProject([
            'status' => ClientProject::STATUS_PROPOSAL_ACCEPTED,
            'payment_plan' => 1,
            'total_amount' => 200,
        ]);
        $this->makeAcceptedQuotation($project, 2, [40, 60], 200.00);

        $project->installments()->create([
            'sort_order' => 0,
            'label' => ClientProjectFinanceController::installmentLabel(1, 0),
            'percentage' => 100,
            'amount' => 200,
            'is_paid' => true,
        ]);

        $project->syncFixedContractTotalsFromQuotation();

        $project->refresh();
        $this->assertSame(1, (int) $project->payment_plan);
        $this->assertCount(1, $project->installments);
        $this->assertTrue((bool) $project->installments->first()->is_paid);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProject(array $overrides = []): ClientProject
    {
        return ClientProject::factory()->create(array_merge([
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
            'payment_plan' => 1,
        ], $overrides));
    }

    /**
     * @param  list<float>  $percentages
     */
    private function makeAcceptedQuotation(
        ClientProject $project,
        int $installments,
        array $percentages,
        float $subtotal,
        string $status = ClientProjectQuotation::STATUS_ACCEPTED
    ): ClientProjectQuotation {
        return ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Standard quotation',
            'status' => $status,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-'.str_pad((string) random_int(8000, 9999), 4, '0', STR_PAD_LEFT),
            'items' => [
                [
                    'description' => 'Labour',
                    'amount' => $subtotal,
                    'apply_hst' => true,
                ],
            ],
            'payment_installments' => $installments,
            'payment_percentages' => $percentages,
        ]);
    }
}
