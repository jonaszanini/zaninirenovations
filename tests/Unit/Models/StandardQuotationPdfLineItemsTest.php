<?php

namespace Tests\Unit\Models;

use App\Models\StandardQuotation;
use Tests\TestCase;

class StandardQuotationPdfLineItemsTest extends TestCase
{
    public function test_detailed_pdf_collapses_materials_into_one_line(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'description' => '1/2-inch x 4-feet x 8 feet Sanded Birch Plywood',
                    'unit_price' => 48.98,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => '2x2',
                    'description' => '',
                    'unit_price' => 3.88,
                    'quantity' => 3,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Stainer',
                    'description' => '',
                    'unit_price' => 25,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Electrical work',
                    'description' => '',
                    'unit_price' => 650,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_hourly_rate' => 60,
            'labour_mode' => 'days',
            'labour_quantity' => 1,
            'labour_use_fixed' => false,
            'contingency_enabled' => true,
            'contingency_percent' => 20,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Material'], array_values(array_filter(
            $descriptions,
            fn ($d) => str_starts_with((string) $d, 'Material')
        )));
        $this->assertContains('Material', $descriptions);
        $this->assertNotContains('Plywood', $descriptions);
        $this->assertFalse((bool) preg_grep('/Plywood|2x2|Stainer|Electrical work/', $descriptions));

        $materialsPreHst = 48.98 + 11.64 + 25.0 + 650.0;
        $contingency = round($materialsPreHst * 0.20, 2);

        $material = collect($lines)->firstWhere('description', 'Material');
        $this->assertNotNull($material);
        $this->assertEqualsWithDelta($materialsPreHst + $contingency, $material['amount'], 0.01);
        $this->assertTrue($material['apply_hst']);

        $this->assertFalse((bool) preg_grep('/Contingency/', $descriptions));

        $labour = collect($lines)->first(fn ($row) => ($row['description'] ?? '') === 'Labour');
        $this->assertNotNull($labour);
        $this->assertEqualsWithDelta(480.0, $labour['amount'], 0.01);
        $this->assertFalse((bool) preg_grep('/hours|days|\/hr|flat fee/i', $descriptions));
    }

    public function test_detailed_pdf_folds_contingency_into_material_not_electrical(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Lumber',
                    'unit_price' => 85.62,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                    'entry_mode' => 'electrical',
                    'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                    'unit_price' => 700,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => true,
            'contingency_percent' => 20,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $descriptions = array_column($lines, 'description');
        $byDesc = collect($lines)->keyBy('description');

        $this->assertFalse((bool) preg_grep('/Contingency/', $descriptions));
        $this->assertEqualsWithDelta(85.62 + 17.12, $byDesc['Material']['amount'], 0.01);
        $this->assertEqualsWithDelta(700.0, $byDesc[StandardQuotation::ELECTRICAL_LINE_DESCRIPTION]['amount'], 0.01);
        $this->assertSame(
            ['Material', StandardQuotation::ELECTRICAL_LINE_DESCRIPTION],
            $descriptions
        );
    }

    public function test_detailed_pdf_lists_electrical_work_as_its_own_line(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'description' => '1/2-inch birch',
                    'unit_price' => 48.98,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'line_kind' => StandardQuotation::LINE_KIND_ELECTRICAL,
                    'entry_mode' => 'electrical',
                    'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                    'unit_price' => 650,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'labour_use_fixed' => false,
            'contingency_enabled' => false,
            'contingency_percent' => 0,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertEqualsWithDelta(48.98, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(650.0, $byDesc[StandardQuotation::ELECTRICAL_LINE_DESCRIPTION]['amount'], 0.01);
        $this->assertTrue($byDesc[StandardQuotation::ELECTRICAL_LINE_DESCRIPTION]['apply_hst']);
        $this->assertSame(
            ['Material', StandardQuotation::ELECTRICAL_LINE_DESCRIPTION],
            array_column($lines, 'description')
        );
    }

    public function test_detailed_pdf_splits_hst_exempt_materials(): void
    {
        $quote = new StandardQuotation([
            'materials' => [
                [
                    'short_name' => 'Taxed lumber',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'short_name' => 'Exempt item',
                    'unit_price' => 40,
                    'quantity' => 1,
                    'apply_hst' => false,
                ],
            ],
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'contingency_percent' => 0,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertEqualsWithDelta(100.0, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(40.0, $byDesc['Material — HST exempt']['amount'], 0.01);
        $this->assertFalse($byDesc['Material — HST exempt']['apply_hst']);
    }

    public function test_multi_job_pdf_lists_each_job_material_and_labour(): void
    {
        $quote = new StandardQuotation([
            'works' => [
                [
                    'title' => 'Replace vanity',
                    'materials' => [
                        [
                            'short_name' => 'Vanity',
                            'unit_price' => 200,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 150,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
                [
                    'title' => 'Install towel bar',
                    'materials' => [
                        [
                            'short_name' => 'Bar',
                            'unit_price' => 40,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 80,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
            ],
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $this->assertTrue($quote->hasMultipleWorks());
        $lines = $quote->detailedPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame('Replace vanity', $descriptions[0]);
        $this->assertTrue(! empty($lines[0]['is_heading']));
        $this->assertContains('Install towel bar', $descriptions);
        $this->assertNotContains('Vanity', $descriptions);
        $this->assertNotContains('Bar', $descriptions);

        $materialLines = array_values(array_filter($lines, fn ($row) => ($row['description'] ?? '') === 'Material'));
        $this->assertCount(2, $materialLines);
        $this->assertEqualsWithDelta(220.0, $materialLines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(44.0, $materialLines[1]['amount'], 0.01);

        $labourLines = array_values(array_filter(
            $lines,
            fn ($row) => ($row['description'] ?? '') === 'Labour'
        ));
        $this->assertCount(2, $labourLines);
        $this->assertEqualsWithDelta(150.0, $labourLines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(80.0, $labourLines[1]['amount'], 0.01);
        $this->assertFalse((bool) preg_grep('/hours|days|\/hr|flat fee/i', $descriptions));
    }

    public function test_combined_pdf_merges_material_and_labour_into_one_line(): void
    {
        $quote = new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => true,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 200,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->combinedPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Labour and material'], $descriptions);
        $this->assertEqualsWithDelta(300.0, $lines[0]['amount'], 0.01);
        $this->assertTrue($lines[0]['apply_hst']);
        $this->assertNotContains('Material', $descriptions);
        $this->assertNotContains('Labour', $descriptions);
    }

    public function test_combined_multi_job_pdf_one_line_per_job(): void
    {
        $quote = new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => true,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
            'works' => [
                [
                    'title' => 'Kitchen',
                    'materials' => [
                        [
                            'short_name' => 'Counter',
                            'unit_price' => 100,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 50,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
                [
                    'title' => 'Bathroom',
                    'materials' => [
                        [
                            'short_name' => 'Vanity',
                            'unit_price' => 80,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 40,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
            ],
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->combinedPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame([
            'Kitchen',
            'Labour and material',
            'Bathroom',
            'Labour and material',
        ], $descriptions);
        $this->assertTrue(! empty($lines[0]['is_heading']));
        $this->assertEqualsWithDelta(150.0, $lines[1]['amount'], 0.01);
        $this->assertEqualsWithDelta(120.0, $lines[3]['amount'], 0.01);
        $this->assertNotContains('Material', $descriptions);
        $this->assertNotContains('Labour', $descriptions);
    }

    public function test_summary_pdf_shows_material_and_labour_lines(): void
    {
        $quote = new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => true,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'works' => [
                [
                    'title' => 'Kitchen',
                    'materials' => [
                        [
                            'short_name' => 'Counter',
                            'unit_price' => 100,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 50,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
                [
                    'title' => 'Bathroom',
                    'materials' => [
                        [
                            'short_name' => 'Vanity',
                            'unit_price' => 80,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 40,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
            ],
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->summaryPdfLineItems();
        $descriptions = array_column($lines, 'description');

        $this->assertSame(['Material', 'Labour'], $descriptions);
        $this->assertEqualsWithDelta(180.0, $lines[0]['amount'], 0.01);
        $this->assertEqualsWithDelta(90.0, $lines[1]['amount'], 0.01);
        $this->assertNotContains('Kitchen', $descriptions);
        $this->assertNotContains('Bathroom', $descriptions);
        $this->assertNotContains('Labour and material', $descriptions);
    }

    public function test_multi_job_pdf_lists_debris_after_jobs_not_inside_job_one(): void
    {
        $quote = new StandardQuotation([
            'works' => [
                [
                    'title' => 'Cut part of the counter top',
                    'materials' => [
                        [
                            'short_name' => 'Plywood',
                            'unit_price' => 44,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 437.50,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
                [
                    'title' => 'Install towel bar',
                    'materials' => [
                        [
                            'short_name' => 'Bar',
                            'unit_price' => 40,
                            'quantity' => 1,
                            'apply_hst' => true,
                        ],
                    ],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 80,
                    'labour_fixed_hst_included' => false,
                    'labour_hourly_rate' => 0,
                    'labour_mode' => 'hours',
                    'labour_quantity' => 0,
                ],
            ],
            'contingency_enabled' => false,
            'contingency_percent' => 0,
            'include_commute' => false,
            'include_debris_removal' => true,
            'debris_removal_amount' => 300,
        ]);

        $lines = $quote->detailedPdfLineItems();
        $descriptions = array_column($lines, 'description');
        $labourLines = array_values(array_filter(
            $lines,
            fn ($row) => ($row['description'] ?? '') === 'Labour'
        ));

        $this->assertEqualsWithDelta(437.50, $labourLines[0]['amount'], 0.01);
        $this->assertSame(
            \App\Services\DebrisRemovalExpenseService::LINE_DESCRIPTION,
            end($descriptions)
        );
        $debris = collect($lines)->last();
        $this->assertEqualsWithDelta(300.0, $debris['amount'], 0.01);
        $this->assertTrue($debris['hst_included']);
        $this->assertFalse($debris['apply_hst']);
    }

    public function test_cash_labour_is_exempt_and_stays_off_the_taxable_combined_line(): void
    {
        $quote = new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => true,
            'include_labour_hst' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
            'materials' => [
                [
                    'short_name' => 'Plywood',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 200,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $lines = $quote->combinedPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');

        $this->assertEqualsWithDelta(100.0, $byDesc['Material']['amount'], 0.01);
        $this->assertTrue($byDesc['Material']['apply_hst']);
        $this->assertEqualsWithDelta(200.0, $byDesc['Labour']['amount'], 0.01);
        $this->assertFalse($byDesc['Labour']['apply_hst']);
        $this->assertEqualsWithDelta(0.0, $quote->computedTotals()['labour_hst'], 0.01);
    }

    public function test_client_pdf_cash_labour_plus_commute_and_debris_matches_editor_in_all_modes(): void
    {
        foreach ([
            StandardQuotation::PDF_DETAIL_COMBINED,
            StandardQuotation::PDF_DETAIL_SUMMARY,
            StandardQuotation::PDF_DETAIL_DETAILED,
        ] as $mode) {
            $quote = $this->cashLabourQuoteWithHiddenExtras($mode);
            $items = match ($mode) {
                StandardQuotation::PDF_DETAIL_COMBINED => $quote->combinedPdfLineItems(),
                StandardQuotation::PDF_DETAIL_SUMMARY => $quote->summaryPdfLineItems(),
                default => $quote->detailedPdfLineItems(),
            };

            $cpq = new \App\Models\ClientProjectQuotation([
                'source_type' => \App\Models\ClientProjectQuotation::SOURCE_FENCE,
                'items' => $items,
            ]);

            $computed = $quote->computedTotals();
            $editorGrand = round((float) $computed['grand_total'] + 113.0 + 50.0, 2);
            $descriptions = array_column($cpq->pdfLineItems(), 'description');

            $this->assertEqualsWithDelta($editorGrand, $cpq->pdfGrandTotal(), 0.01, $mode);
            $this->assertEqualsWithDelta(1678.00 + 50.00, $editorGrand, 0.01, $mode);
            $this->assertFalse((bool) preg_grep('/Commute|Debris/i', $descriptions), $mode);
        }
    }

    public function test_includes_labour_off_still_shows_priced_labour_and_honest_wording(): void
    {
        $quote = new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => false,
            'include_labour_hst' => false,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'materials' => [[
                'short_name' => 'Plywood',
                'unit_price' => 100,
                'quantity' => 1,
                'apply_hst' => true,
            ]],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 200,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $this->assertSame('Labour and material', $quote->pdfSummaryLineDescription());
        $this->assertEqualsWithDelta(200.0, $quote->labourPreTaxAmountForProjectQuotation(), 0.01);

        $lines = $quote->summaryPdfLineItems();
        $byDesc = collect($lines)->keyBy('description');
        $this->assertEqualsWithDelta(100.0, $byDesc['Material']['amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, $byDesc['Labour']['amount'], 0.01);
    }

    /**
     * Materials $500 + HST $65, cash labour $1,000, commute $113, debris $50.
     */
    private function cashLabourQuoteWithHiddenExtras(string $mode): StandardQuotation
    {
        return new StandardQuotation([
            'includes_material' => true,
            'includes_labour' => true,
            'include_labour_hst' => false,
            'pdf_detail_mode' => $mode,
            'materials' => [[
                'short_name' => 'Plywood',
                'unit_price' => 500,
                'quantity' => 1,
                'apply_hst' => true,
            ]],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 1000,
            'labour_fixed_hst_included' => false,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
            'contingency_enabled' => false,
            'include_commute' => true,
            'commute_amount' => 113,
            'commute_estimated_trips' => 1,
            'include_debris_removal' => true,
            'debris_removal_amount' => 50,
        ]);
    }
}
