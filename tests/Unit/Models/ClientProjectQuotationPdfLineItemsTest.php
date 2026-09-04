<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Services\DebrisRemovalExpenseService;
use Tests\TestCase;

class ClientProjectQuotationPdfLineItemsTest extends TestCase
{
    public function test_pdf_lists_debris_as_its_own_pre_tax_line_not_folded_into_labour(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => [
                [
                    'description' => 'Cut part of the counter top',
                    'amount' => 0,
                    'apply_hst' => false,
                    'is_heading' => true,
                ],
                [
                    'description' => 'Material',
                    'amount' => 44.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Labour',
                    'amount' => 437.50,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Debris removal',
                    'amount' => 300.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');
        $byDesc = collect($lines)->keyBy('description');

        $this->assertContains(DebrisRemovalExpenseService::LINE_DESCRIPTION, $descriptions);
        $this->assertEqualsWithDelta(44.00, $byDesc['Material']['amount'], 0.01);
        $this->assertEqualsWithDelta(437.50, $byDesc['Labour']['amount'], 0.01);

        $debris = $byDesc[DebrisRemovalExpenseService::LINE_DESCRIPTION];
        $this->assertEqualsWithDelta(300.00, $debris['amount'], 0.01);
        $this->assertFalse($debris['apply_hst']);
        $this->assertTrue($debris['hst_included']);
        $this->assertTrue($debris['is_standalone']);
        $this->assertSame(
            DebrisRemovalExpenseService::LINE_DESCRIPTION,
            end($descriptions)
        );

        $taxableHst = ClientProjectQuotation::lineHstAmount($byDesc['Material'])
            + ClientProjectQuotation::lineHstAmount($byDesc['Labour']);
        $this->assertEqualsWithDelta(44.00 + 437.50 + 300.00, $quote->pdfSubtotal(), 0.01);
        $this->assertEqualsWithDelta($taxableHst, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(
            $quote->pdfSubtotal() + $quote->pdfHst(),
            $quote->pdfGrandTotal(),
            0.01
        );
    }

    public function test_pdf_still_folds_commute_into_labour(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => [
                [
                    'description' => 'Labour',
                    'amount' => 100.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Labour'], $descriptions);
        $this->assertEqualsWithDelta(200.00, $lines[0]['amount'], 0.01);
    }

    public function test_pdf_splits_commute_evenly_across_job_labour_lines(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => 'Job 1',
                    'amount' => 0,
                    'apply_hst' => false,
                    'is_heading' => true,
                ],
                [
                    'description' => 'Material',
                    'amount' => 10.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Labour',
                    'amount' => 100.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Job 2',
                    'amount' => 0,
                    'apply_hst' => false,
                    'is_heading' => true,
                ],
                [
                    'description' => 'Labour',
                    'amount' => 200.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Job 3',
                    'amount' => 0,
                    'apply_hst' => false,
                    'is_heading' => true,
                ],
                [
                    'description' => 'Labour',
                    'amount' => 300.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => 99.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertSame([33.0, 33.0, 33.0], ClientProjectQuotation::splitAmountEvenly(99.00, 3));

        $lines = $quote->pdfLineItems();
        $labourLines = array_values(array_filter(
            $lines,
            fn ($row) => ($row['description'] ?? '') === 'Labour'
        ));

        $this->assertCount(3, $labourLines);
        $this->assertFalse((bool) preg_grep('/^Commute expense/', array_column($lines, 'description')));

        $preTaxCommute = ClientProjectQuotation::preTaxFromHstIncluded(99.00);
        $shares = ClientProjectQuotation::splitAmountEvenly($preTaxCommute, 3);

        $this->assertEqualsWithDelta(100.00 + $shares[0], $labourLines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(200.00 + $shares[1], $labourLines[1]['amount'], 0.01);
        $this->assertEqualsWithDelta(300.00 + $shares[2], $labourLines[2]['amount'], 0.01);
        $this->assertEqualsWithDelta($preTaxCommute, $shares[0] + $shares[1] + $shares[2], 0.01);
    }
}
