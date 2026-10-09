<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Services\DebrisRemovalExpenseService;
use Tests\TestCase;

class ClientProjectQuotationPdfLineItemsTest extends TestCase
{
    public function test_pdf_folds_debris_into_labour_line(): void
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
        $preTaxDebris = ClientProjectQuotation::preTaxFromHstIncluded(300.00);

        $this->assertNotContains(DebrisRemovalExpenseService::LINE_DESCRIPTION, $descriptions);
        $this->assertFalse((bool) preg_grep('/Debris/i', $descriptions));
        $this->assertEqualsWithDelta(44.00, $byDesc['Material']['amount'], 0.01);
        $this->assertEqualsWithDelta(437.50 + $preTaxDebris, $byDesc['Labour']['amount'], 0.01);
        $this->assertEqualsWithDelta(44.00 + 437.50 + $preTaxDebris, $quote->pdfSubtotal(), 0.01);
        $this->assertEqualsWithDelta(
            $quote->pdfSubtotal() + $quote->pdfHst(),
            $quote->pdfGrandTotal(),
            0.01
        );
    }

    public function test_pdf_folds_debris_into_labour_and_material_line(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => 'Labour and material',
                    'amount' => 1932.93,
                    'apply_hst' => true,
                ],
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 50.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $preTaxDebris = ClientProjectQuotation::preTaxFromHstIncluded(50.00);

        $this->assertSame(['Labour and material'], array_column($lines, 'description'));
        $this->assertEqualsWithDelta(1932.93 + $preTaxDebris, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(2234.21, $quote->pdfGrandTotal(), 0.01);
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

    public function test_tile_pdf_folds_debris_into_labour_and_renames_materials(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_TILE,
            'items' => [
                [
                    'description' => 'Tile installation (labor)',
                    'amount' => 450.94,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Client-selected tile & materials',
                    'amount' => 78.70,
                    'apply_hst' => true,
                ],
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 45.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Tile installation (labor)', 'Materials'], $descriptions);
        $this->assertFalse((bool) preg_grep('/Debris/i', $descriptions));

        $preTaxDebris = ClientProjectQuotation::preTaxFromHstIncluded(45.00);
        $this->assertEqualsWithDelta(450.94 + $preTaxDebris, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(78.70, $lines[1]['amount'], 0.01);
        $this->assertSame('Materials', $lines[1]['description']);
    }

    public function test_pdf_folds_additional_costs_into_materials_or_labour_by_category(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FLOORING,
            'items' => [
                [
                    'description' => 'Flooring installation (labor)',
                    'amount' => 400.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Client-selected flooring material',
                    'amount' => 200.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Additional labour — Extra hours',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');
        $preTax = ClientProjectQuotation::preTaxFromHstIncluded(113.00);

        $this->assertFalse((bool) preg_grep('/^Additional labour/', $descriptions));
        $this->assertFalse((bool) preg_grep('/^Additional cost/', $descriptions));
        $this->assertEqualsWithDelta(400.00 + $preTax, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(200.00 + $preTax, $lines[1]['amount'], 0.01);
    }

    public function test_pdf_cash_labour_keeps_commute_and_debris_gross_so_total_matches_editor(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => [
                [
                    'description' => 'Material',
                    'amount' => 500.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Labour',
                    'amount' => 1000.00,
                    'apply_hst' => false,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');
        $labour = collect($lines)->firstWhere('description', 'Labour');

        $this->assertFalse((bool) preg_grep('/Commute|Debris/i', $descriptions));
        $this->assertEqualsWithDelta(1226.00, $labour['amount'], 0.01);
        $this->assertFalse($labour['apply_hst']);
        $this->assertEqualsWithDelta(1791.00, $quote->pdfGrandTotal(), 0.01);
    }

    public function test_pdf_creates_labour_line_when_commute_has_no_labour_or_taxable_target(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => 'Material — HST exempt',
                    'amount' => 40.00,
                    'apply_hst' => false,
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
        $labour = collect($lines)->firstWhere('description', 'Labour');

        $this->assertFalse((bool) preg_grep('/Commute/i', $descriptions));
        $this->assertNotNull($labour);
        $this->assertEqualsWithDelta(113.00, $labour['amount'], 0.01);
        $this->assertEqualsWithDelta(153.00, $quote->pdfGrandTotal(), 0.01);
    }

    public function test_pdf_creates_labour_line_when_materials_are_taxed_and_there_is_no_labour(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => 'Material',
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
        $byDesc = collect($lines)->keyBy('description');

        $this->assertFalse((bool) preg_grep('/Commute/i', $descriptions));
        $this->assertEqualsWithDelta(100.00, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(113.00, $byDesc['Labour']['amount'], 0.01);
        $this->assertFalse($byDesc['Labour']['apply_hst']);
        $this->assertEqualsWithDelta(226.00, $quote->pdfGrandTotal(), 0.01);
    }

    public function test_pdf_creates_labour_line_for_debris_when_there_is_no_labour(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 50.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertFalse((bool) preg_grep('/Debris/i', $descriptions));
        $this->assertSame(['Labour'], $descriptions);
        $this->assertEqualsWithDelta(50.00, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(50.00, $quote->pdfGrandTotal(), 0.01);
    }
}
