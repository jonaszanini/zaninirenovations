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
        $this->assertFalse($labour['apply_hst']);
        $this->assertEqualsWithDelta(113.00, $labour['amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(153.00, $quote->pdfGrandTotal(), 0.01);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $quote->pdfGrandTotal(), 0.001);
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
        $this->assertEqualsWithDelta(100.00, $byDesc['Labour']['amount'], 0.01);
        $this->assertTrue($byDesc['Labour']['apply_hst']);
        $this->assertEqualsWithDelta(26.00, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(226.00, $quote->pdfGrandTotal(), 0.01);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $quote->pdfGrandTotal(), 0.001);
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
        $this->assertFalse($lines[0]['apply_hst']);
        $this->assertEqualsWithDelta(50.00, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(50.00, $quote->pdfGrandTotal(), 0.01);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $quote->pdfGrandTotal(), 0.001);
    }

    public function test_saved_quote_without_labour_keeps_legacy_fold_and_total(): void
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
        $quote->exists = true;
        $quote->pdf_fold_version = 1;

        $lines = $quote->pdfLineItems();
        $descriptions = array_column($lines, 'description');
        $byDesc = collect($lines)->keyBy('description');

        $this->assertFalse((bool) preg_grep('/Commute/i', $descriptions));
        $this->assertSame(['Material'], $descriptions);
        $this->assertEqualsWithDelta(200.00, $byDesc['Material']['amount'], 0.01);
        $this->assertEqualsWithDelta(26.00, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(226.00, $quote->pdfGrandTotal(), 0.01);
    }

    /**
     * @dataProvider newQuoteTaxedLabourCases
     */
    public function test_new_quote_without_labour_gets_taxed_labour_to_the_cent(
        float $material,
        float $commuteGross,
        float $expectedLabour,
        float $expectedHst,
        float $expectedTotal
    ): void {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                [
                    'description' => 'Material',
                    'amount' => $material,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => $commuteGross,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertFalse((bool) preg_grep('/Commute/i', array_column($lines, 'description')));
        $this->assertEqualsWithDelta($material, $byDesc['Material']['amount'], 0.001);
        $this->assertEqualsWithDelta($expectedLabour, $byDesc['Labour']['amount'], 0.001);
        $this->assertTrue($byDesc['Labour']['apply_hst']);
        $this->assertEqualsWithDelta($expectedHst, $quote->pdfHst(), 0.001);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $quote->pdfGrandTotal(), 0.001);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $expectedTotal, 0.001);
        $this->assertEqualsWithDelta(
            $quote->pdfSubtotal() + $quote->pdfHst(),
            $quote->pdfGrandTotal(),
            0.001
        );
    }

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float, 4: float}>
     */
    public function newQuoteTaxedLabourCases(): array
    {
        return [
            '113 commute on 100 material' => [100.00, 113.00, 100.00, 26.00, 226.00],
            '1.00 commute on 100 material' => [100.00, 1.00, 0.88, 13.12, 114.00],
            '50 commute on 40 material' => [40.00, 50.00, 44.25, 10.95, 95.20],
        ];
    }

    public function test_pdf_grand_total_equals_editor_across_many_amounts(): void
    {
        $materials = [40.00, 100.00, 500.00, 566.00];
        $commutes = [0.01, 0.50, 1.00, 1.13, 2.26, 5.00, 13.00, 50.00, 113.00, 200.00];

        foreach ($materials as $material) {
            foreach ($commutes as $commute) {
                $quote = new ClientProjectQuotation([
                    'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                    'items' => [
                        ['description' => 'Material', 'amount' => $material, 'apply_hst' => true],
                        [
                            'description' => 'Commute expense',
                            'amount' => $commute,
                            'apply_hst' => false,
                            'hst_included' => true,
                        ],
                    ],
                ]);

                $this->assertEquals(
                    $quote->editorGrandTotal(),
                    $quote->pdfGrandTotal(),
                    "Material {$material} + commute {$commute}"
                );
            }
        }

        $labourQuote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'items' => [
                ['description' => 'Labour', 'amount' => 566.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $this->assertSame(640.58, $labourQuote->editorGrandTotal());
        $this->assertEquals($labourQuote->editorGrandTotal(), $labourQuote->pdfGrandTotal());
    }

    public function test_saved_cash_quote_with_no_taxed_line_stays_untaxed(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 1,
            'items' => [
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => false],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $quote->exists = true;

        $lines = $quote->pdfLineItems();
        $labour = collect($lines)->firstWhere('description', 'Labour');

        $this->assertSame(['Labour'], array_column($lines, 'description'));
        $this->assertFalse($labour['apply_hst']);
        $this->assertEqualsWithDelta(613.00, $labour['amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, $quote->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(613.00, $quote->pdfGrandTotal(), 0.01);

        $debrisOnly = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 1,
            'items' => [
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 50.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $debrisOnly->exists = true;

        $debrisLines = $debrisOnly->pdfLineItems();
        $this->assertSame(['Labour'], array_column($debrisLines, 'description'));
        $this->assertFalse($debrisLines[0]['apply_hst']);
        $this->assertEqualsWithDelta(50.00, $debrisLines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(0.00, $debrisOnly->pdfHst(), 0.01);
        $this->assertEqualsWithDelta(50.00, $debrisOnly->pdfGrandTotal(), 0.01);
    }

    public function test_new_fence_combined_summary_is_the_fold_target(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'items' => [
                [
                    'description' => '6 ft standard board fence, 4×4 posts in concrete, 24 linear ft (1 run)',
                    'amount' => 1200.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
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
        $descriptions = array_column($lines, 'description');

        $this->assertCount(1, $lines);
        $this->assertFalse((bool) preg_grep('/Commute|Debris|^Labour$/i', $descriptions));
        $this->assertStringContainsString('standard board fence', $descriptions[0]);
        $this->assertEqualsWithDelta($quote->editorGrandTotal(), $quote->pdfGrandTotal(), 0.001);
    }

    public function test_new_paving_combined_summary_is_the_fold_target(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'items' => [
                [
                    'description' => 'Paving Stones — 200 sq ft Unilock',
                    'amount' => 2400.00,
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

        $this->assertCount(1, $lines);
        $this->assertFalse((bool) preg_grep('/Commute|^Labour$/i', $descriptions));
        $this->assertStringContainsString('Paving Stones', $descriptions[0]);
    }

    public function test_saved_legacy_fold_keeps_one_cent_gap(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 1,
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $quote->exists = true;

        $this->assertEqualsWithDelta(113.99, $quote->pdfGrandTotal(), 0.001);
        $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'));
    }
}
