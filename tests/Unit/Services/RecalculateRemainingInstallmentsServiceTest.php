<?php

namespace Tests\Unit\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Services\RecalculateRemainingInstallmentsService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\TestCase;

class RecalculateRemainingInstallmentsServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_analyze_detects_discrepancy_between_quotation_and_plan(): void
    {
        $paid = $this->installment(1838.28 / 1.13, 30, true, 1838.28);
        $unpaidA = $this->installment(1838.28 / 1.13, 30, false, 1838.28);
        $unpaidB = $this->installment(2451.04 / 1.13, 40, false, 2451.04);

        $project = $this->mockProject([
            'quot_sub' => 6000.00,
            'quot_grand' => 6780.00,
            'total_amount' => 5422.65,
            'payment_plan' => 3,
            'installments' => [$paid, $unpaidA, $unpaidB],
            'unpaid_invoice_count' => 2,
        ]);

        $analysis = (new RecalculateRemainingInstallmentsService)->analyze($project);

        $this->assertTrue($analysis['applicable']);
        $this->assertTrue($analysis['has_discrepancy']);
        $this->assertFalse($analysis['blocked']);
        $this->assertSame(1, $analysis['paid_count']);
        $this->assertSame(2, $analysis['unpaid_count']);
        $this->assertSame(3, $analysis['max_remaining']);
        $this->assertEqualsWithDelta(6000.00, $analysis['quotation_pre_tax'], 0.01);
        $this->assertEqualsWithDelta(6780.00, $analysis['quotation_grand'], 0.01);
    }

    public function test_analyze_ignores_discrepancy_before_invoices_or_payments(): void
    {
        $project = $this->mockProject([
            'quot_sub' => 250.00,
            'quot_grand' => 282.50,
            'total_amount' => 0,
            'payment_plan' => 1,
            'installments' => [],
            'unpaid_invoice_count' => 0,
            'installment_invoice_count' => 0,
        ]);

        $analysis = (new RecalculateRemainingInstallmentsService)->analyze($project);

        $this->assertTrue($analysis['applicable']);
        $this->assertFalse($analysis['has_discrepancy']);
        $this->assertFalse($analysis['finance_committed']);
        $this->assertFalse($analysis['blocked']);
    }

    public function test_analyze_detects_discrepancy_when_installment_invoices_exist(): void
    {
        $unpaid = $this->installment(250.00, 100, false, 282.50);

        $project = $this->mockProject([
            'quot_sub' => 400.00,
            'quot_grand' => 452.00,
            'total_amount' => 250.00,
            'payment_plan' => 1,
            'installments' => [$unpaid],
            'unpaid_invoice_count' => 1,
            'installment_invoice_count' => 1,
        ]);

        $analysis = (new RecalculateRemainingInstallmentsService)->analyze($project);

        $this->assertTrue($analysis['finance_committed']);
        $this->assertTrue($analysis['has_discrepancy']);
    }

    public function test_analyze_blocks_when_new_total_is_below_paid(): void
    {
        $paid = $this->installment(3000.00, 50, true, 3390.00);
        $unpaid = $this->installment(3000.00, 50, false, 3390.00);

        $project = $this->mockProject([
            'quot_sub' => 2000.00,
            'quot_grand' => 2260.00,
            'total_amount' => 6000.00,
            'payment_plan' => 2,
            'installments' => [$paid, $unpaid],
            'unpaid_invoice_count' => 1,
        ]);

        $analysis = (new RecalculateRemainingInstallmentsService)->analyze($project);

        $this->assertTrue($analysis['blocked']);
        $this->assertNotNull($analysis['block_reason']);
        $this->assertStringContainsString('less than already paid', $analysis['block_reason']);
    }

    public function test_preview_keeps_paid_amount_and_splits_remaining(): void
    {
        $paid = $this->installment(1500.00, 30, true, 1695.00);
        $unpaidA = $this->installment(1500.00, 30, false, 1695.00);
        $unpaidB = $this->installment(2000.00, 40, false, 2260.00);

        $project = $this->mockProject([
            'quot_sub' => 6000.00,
            'quot_grand' => 6780.00,
            'total_amount' => 5000.00,
            'payment_plan' => 3,
            'installments' => [$paid, $unpaidA, $unpaidB],
            'unpaid_invoice_count' => 2,
        ]);

        $preview = (new RecalculateRemainingInstallmentsService)->preview($project, 2, 'defaults');

        $this->assertSame(3, $preview['new_plan']);
        $this->assertCount(3, $preview['rows']);
        $this->assertTrue($preview['rows'][0]['is_paid']);
        $this->assertSame(1500.00, $preview['rows'][0]['amount']);
        $this->assertFalse($preview['rows'][1]['is_paid']);
        $this->assertFalse($preview['rows'][2]['is_paid']);

        $unpaidSum = round($preview['rows'][1]['amount'] + $preview['rows'][2]['amount'], 2);
        $this->assertEqualsWithDelta(4500.00, $unpaidSum, 0.01);

        $totalPct = round(array_sum(array_column($preview['rows'], 'percentage')), 2);
        $this->assertEqualsWithDelta(100.0, $totalPct, 0.01);

        $totalAmt = round(array_sum(array_column($preview['rows'], 'amount')), 2);
        $this->assertEqualsWithDelta(6000.00, $totalAmt, 0.01);
    }

    public function test_preview_lump_sum_remaining_uses_one_installment(): void
    {
        $paid = $this->installment(1000.00, 25, true, 1130.00);
        $unpaidA = $this->installment(1000.00, 25, false, 1130.00);
        $unpaidB = $this->installment(2000.00, 50, false, 2260.00);

        $project = $this->mockProject([
            'quot_sub' => 5000.00,
            'quot_grand' => 5650.00,
            'total_amount' => 4000.00,
            'payment_plan' => 3,
            'installments' => [$paid, $unpaidA, $unpaidB],
            'unpaid_invoice_count' => 2,
        ]);

        $preview = (new RecalculateRemainingInstallmentsService)->preview($project, 1, 'defaults');

        $this->assertSame(2, $preview['new_plan']);
        $this->assertCount(2, $preview['rows']);
        $this->assertSame(1000.00, $preview['rows'][0]['amount']);
        $this->assertEqualsWithDelta(4000.00, $preview['rows'][1]['amount'], 0.01);
        $this->assertSame('On completion', $preview['rows'][1]['label']);
    }

    public function test_preview_rejects_too_many_remaining_installments(): void
    {
        $paid = $this->installment(1000.00, 50, true, 1130.00);
        $unpaid = $this->installment(1000.00, 50, false, 1130.00);

        $project = $this->mockProject([
            'quot_sub' => 3000.00,
            'quot_grand' => 3390.00,
            'total_amount' => 2000.00,
            'payment_plan' => 2,
            'installments' => [$paid, $unpaid],
            'unpaid_invoice_count' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        (new RecalculateRemainingInstallmentsService)->preview($project, 4, 'defaults');
    }

    /**
     * @param  list<ClientProjectInstallment>  $installments
     * @param  array<string, mixed>  $opts
     */
    private function mockProject(array $opts): ClientProject
    {
        $installments = new Collection($opts['installments']);

        /** @var ClientProject&\Mockery\MockInterface $project */
        $project = Mockery::mock(ClientProject::class)->makePartial();
        $project->shouldReceive('isHourly')->andReturn(false);
        $project->shouldReceive('isAccepted')->andReturn(true);
        $project->shouldReceive('quotationSubtotalForFinancials')->andReturn((float) $opts['quot_sub']);
        $project->shouldReceive('quotationGrandTotalForFinancials')->andReturn((float) $opts['quot_grand']);
        $project->payment_plan = (int) $opts['payment_plan'];
        $project->total_amount = (float) $opts['total_amount'];
        $project->setRelation('installments', $installments);

        $invoiceRelation = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $invoiceRelation->shouldReceive('whereNotNull')->with('installment_number')->andReturnSelf();
        $invoiceRelation->shouldReceive('whereNotIn')->with('status', [
            ClientProjectInvoice::STATUS_PAID,
            ClientProjectInvoice::STATUS_REFUNDED,
        ])->andReturnSelf();
        $unpaidInvoices = (int) ($opts['unpaid_invoice_count'] ?? 0);
        $installmentInvoices = (int) ($opts['installment_invoice_count'] ?? $unpaidInvoices);
        $invoiceRelation->shouldReceive('count')->andReturn($installmentInvoices, $unpaidInvoices);
        $project->shouldReceive('invoices')->andReturn($invoiceRelation);

        return $project;
    }

    private function installment(float $amount, float $pct, bool $paid, float $effectiveTotal): ClientProjectInstallment
    {
        /** @var ClientProjectInstallment&\Mockery\MockInterface $inst */
        $inst = Mockery::mock(ClientProjectInstallment::class)->makePartial();
        $inst->amount = round($amount, 2);
        $inst->percentage = $pct;
        $inst->is_paid = $paid;
        $inst->label = $paid ? 'On signing (holds the schedule)' : 'On completion';
        $inst->sort_order = 0;
        $inst->shouldReceive('effectiveTotal')->andReturn(round($effectiveTotal, 2));

        return $inst;
    }
}
