<?php

namespace Tests\Unit\Models;

use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectQuotation;
use App\Models\ClientProjectTimeEntry;
use Tests\TestCase;

class ClientProjectHstToSaveTest extends TestCase
{
    public function test_hourly_sets_aside_hst_on_full_labour_revenue(): void
    {
        $project = new ClientProject([
            'billing_type' => ClientProject::BILLING_HOURLY,
            'hourly_rate' => 50,
        ]);
        $project->setRelation('timeEntries', collect([
            new ClientProjectTimeEntry(['hours' => 10]),
        ]));

        $this->assertSame(500.0, $project->revenueForProfit());
        $this->assertSame(65.0, $project->hstToSave());
    }

    public function test_fixed_price_sets_aside_hst_on_labour_share_only(): void
    {
        $project = $this->fixedProjectWithQuote(
            labour: 794.27,
            materials: 1104.60,
            paid: 1898.87,
        );

        $this->assertSame(1898.87, $project->revenueForProfit());
        $this->assertSame(103.26, $project->hstToSave());
    }

    public function test_fixed_price_scales_labour_hst_to_partial_paid_revenue(): void
    {
        $project = $this->fixedProjectWithQuote(
            labour: 794.27,
            materials: 1104.60,
            paid: 949.44,
        );

        $share = 794.27 / 1898.87;
        $expected = round(949.44 * $share * ClientProject::HST_RATE, 2);

        $this->assertSame($expected, $project->hstToSave());
    }

    public function test_materials_only_quote_sets_aside_no_hst(): void
    {
        $quote = new ClientProjectQuotation([
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'labour' => false,
            'material' => true,
            'items' => [[
                'description' => 'Materials',
                'amount' => 1104.60,
                'apply_hst' => true,
            ]],
            'source_snapshot' => [
                'labour_total' => 794.27,
                'materials_subtotal' => 1104.60,
            ],
        ]);

        $project = $this->fixedProject($quote, 1104.60);

        $this->assertSame(0.0, $project->hstToSave());
    }

    public function test_no_quote_split_treats_paid_revenue_as_labour(): void
    {
        $project = new ClientProject(['billing_type' => ClientProject::BILLING_FIXED]);
        $project->setRelation('quotations', collect());
        $project->setRelation('installments', collect([
            new ClientProjectInstallment([
                'amount' => 1898.87,
                'is_paid' => true,
            ]),
        ]));

        $this->assertSame(246.85, $project->hstToSave());
    }

    public function test_subtotal_override_scales_labour_the_same_as_hst(): void
    {
        $quote = new ClientProjectQuotation([
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'labour' => true,
            'material' => true,
            'subtotal_override' => 1000.00,
            'items' => [[
                'description' => 'Fence',
                'amount' => 2000.00,
                'apply_hst' => true,
            ]],
            'source_snapshot' => [
                'labour_total' => 800.00,
                'materials_subtotal' => 1200.00,
            ],
        ]);

        $project = $this->fixedProject($quote, 1000.00);

        $this->assertSame(400.0, $quote->labourPreTaxForHstSetAside());
        $this->assertSame(52.0, $project->hstToSave());
    }

    public function test_paving_labour_excludes_commute_and_debris(): void
    {
        $quote = new ClientProjectQuotation([
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'labour' => true,
            'material' => true,
            'items' => [
                [
                    'description' => 'Paving — Material',
                    'amount' => 1000.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Paving — Labour',
                    'amount' => 550.00,
                    'apply_hst' => true,
                    'hst_taxable_amount' => 500.00,
                ],
            ],
            'source_snapshot' => [
                'calculation' => [
                    'summary' => ['labour_total' => 500.00],
                ],
            ],
        ]);

        $this->assertSame(500.0, $quote->labourPreTaxForHstSetAside());

        $project = $this->fixedProject($quote, $quote->subtotal());
        $expected = round(500.0 * ClientProject::HST_RATE, 2);
        $this->assertSame($expected, $project->hstToSave());
    }

    private function fixedProjectWithQuote(float $labour, float $materials, float $paid): ClientProject
    {
        $quote = new ClientProjectQuotation([
            'status' => ClientProjectQuotation::STATUS_ACCEPTED,
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'labour' => true,
            'material' => true,
            'items' => [[
                'description' => 'Fence',
                'amount' => round($labour + $materials, 2),
                'apply_hst' => true,
            ]],
            'source_snapshot' => [
                'labour_total' => $labour,
                'materials_subtotal' => $materials,
            ],
        ]);

        return $this->fixedProject($quote, $paid);
    }

    private function fixedProject(ClientProjectQuotation $quote, float $paid): ClientProject
    {
        $project = new ClientProject(['billing_type' => ClientProject::BILLING_FIXED]);
        $project->setRelation('quotations', collect([$quote]));
        $project->setRelation('installments', collect([
            new ClientProjectInstallment([
                'amount' => $paid,
                'is_paid' => true,
            ]),
        ]));

        return $project;
    }
}
