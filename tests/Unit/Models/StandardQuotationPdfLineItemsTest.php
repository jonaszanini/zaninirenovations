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
                'pdf_fold_version' => 3,
                'items' => $items,
            ]);

            $computed = $quote->computedTotals();
            $editorGrand = round((float) $computed['grand_total'] + 113.0 + 50.0, 2);
            $descriptions = array_column($cpq->pdfLineItems(), 'description');

            $this->assertEqualsWithDelta($editorGrand, $cpq->pdfGrandTotal(), 0.01, $mode);
            $this->assertEqualsWithDelta(1678.00 + 50.00, $editorGrand, 0.01, $mode);
            $this->assertEquals(65.00, $cpq->pdfHst(), $mode);
            $this->assertEquals($cpq->editorHst(), $cpq->pdfHst(), $mode);
            $this->assertFalse((bool) preg_grep('/Commute|Debris/i', $descriptions), $mode);
            foreach ($cpq->pdfLineItems() as $row) {
                if (($row['description'] ?? '') === 'Labour') {
                    $this->assertFalse((bool) ($row['apply_hst'] ?? false), $mode);
                }
            }
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
     * D5: the aggregated Material PDF line must use per-row HST (same as
     * admin-standard-quotation.js), not one round on the summed subtotal.
     * Executes the real editor arithmetic extracted from that file.
     */
    public function test_aggregated_material_hst_matches_real_editor_js(): void
    {
        $jsPath = public_path('js/admin-standard-quotation.js');
        $this->assertFileExists($jsPath);
        $source = (string) file_get_contents($jsPath);
        $this->assertTrue(
            (bool) preg_match(
                '/var lineGross = Math\.round\(\(unit \* qty \+ delivery\) \* 100\) \/ 100;.*?lineTot = lineSub;\n      \}/s',
                $source,
                $m
            ),
            'Must extract the real material-row math from admin-standard-quotation.js'
        );
        $extracted = $m[0];
        $this->assertStringContainsString('Math.round((unit * qty + delivery) * 100) / 100', $extracted);
        $this->assertStringContainsString('Math.round(lineSub * HST * 100) / 100', $extracted);

        $cases = [
            [['unit' => 33.33, 'qty' => 1], ['unit' => 33.33, 'qty' => 1]],
            [['unit' => 10.01, 'qty' => 3], ['unit' => 7.77, 'qty' => 2], ['unit' => 1.13, 'qty' => 4]],
        ];
        for ($i = 1; $i <= 80; $i++) {
            $rows = [];
            $n = ($i % 4) + 2;
            for ($r = 0; $r < $n; $r++) {
                $rows[] = [
                    'unit' => round((($i * 17 + $r * 31) % 9000) / 100, 2),
                    'qty' => (($i + $r) % 5) + 1,
                ];
            }
            $cases[] = $rows;
        }

        $flat = [];
        foreach ($cases as $caseIdx => $rows) {
            foreach ($rows as $row) {
                $flat[] = [
                    'case' => $caseIdx,
                    'unit' => $row['unit'],
                    'qty' => $row['qty'],
                    'delivery' => 0,
                    'inclusive' => false,
                    'chargePreTax' => true,
                ];
            }
        }

        $script = <<<JS
var HST = 0.13;
function lineMath(unit, qty, delivery, inclusive, chargePreTax) {
  {$extracted}
  return {lineSub: lineSub, lineHst: lineHst, lineTot: lineTot};
}
var rows = JSON.parse(process.argv[2]);
var out = rows.map(function (r) {
  return Object.assign({case: r.case}, lineMath(r.unit, r.qty, r.delivery, !!r.inclusive, !!r.chargePreTax));
});
process.stdout.write(JSON.stringify(out));
JS;

        $tmp = tempnam(sys_get_temp_dir(), 'sq-d5-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' '.escapeshellarg(json_encode($flat)).' 2>&1', $output, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $output));
        $jsRows = json_decode(implode('', $output), true);
        $this->assertIsArray($jsRows);
        $this->assertNotSame([], $jsRows);

        $byCase = [];
        foreach ($jsRows as $row) {
            $byCase[$row['case']][] = $row;
        }

        $mismatches = 0;
        foreach ($cases as $caseIdx => $rows) {
            $materials = [];
            foreach ($rows as $i => $row) {
                $materials[] = [
                    'short_name' => 'Row '.$i,
                    'unit_price' => $row['unit'],
                    'quantity' => $row['qty'],
                    'apply_hst' => true,
                ];
            }
            $sq = new StandardQuotation([
                'pdf_fold_version' => 3,
                'materials' => $materials,
                'labour_hourly_rate' => 0,
                'labour_mode' => 'hours',
                'labour_quantity' => 0,
                'labour_use_fixed' => false,
                'contingency_enabled' => false,
                'include_commute' => false,
                'include_debris_removal' => false,
            ]);
            $sqLines = $sq->summaryPdfLineItems();
            $sqHst = 0.0;
            foreach ($sqLines as $line) {
                $sqHst += \App\Models\ClientProjectQuotation::lineHstAmount($line);
            }
            $cpq = new \App\Models\ClientProjectQuotation([
                'source_type' => \App\Models\ClientProjectQuotation::SOURCE_FENCE,
                'pdf_fold_version' => 3,
                'items' => $sqLines,
            ]);

            $jsHst = 0.0;
            $jsGrand = 0.0;
            foreach ($byCase[$caseIdx] ?? [] as $jsRow) {
                $jsHst += (float) $jsRow['lineHst'];
                $jsGrand += (float) $jsRow['lineTot'];
            }
            $jsHst = round($jsHst, 2);
            $jsGrand = round($jsGrand, 2);

            if (abs($jsHst - $cpq->pdfHst()) > 0.001 || abs($jsGrand - $cpq->pdfGrandTotal()) > 0.001) {
                $mismatches++;
            }
            $this->assertEquals($jsHst, round($sqHst, 2), 'SQ aggregate HST case '.$caseIdx);
            $this->assertEquals($jsHst, $cpq->pdfHst(), 'HST case '.$caseIdx);
            $this->assertEquals($jsGrand, $cpq->pdfGrandTotal(), 'total case '.$caseIdx);
        }

        $this->assertSame(0, $mismatches);
        $this->assertGreaterThan(80, count($cases));
    }

    public function test_v2_labour_building_and_contingency_match_real_editor_js(): void
    {
        $js = (string) file_get_contents(public_path('js/admin-standard-quotation.js'));
        $this->assertTrue((bool) preg_match(
            '/var lineGross = Math\.round\(\(unit \* qty \+ delivery\) \* 100\) \/ 100;.*?lineTot = lineSub;\n      \}/s',
            $js,
            $matRow
        ), 'Must extract the real material-row math');
        $this->assertTrue((bool) preg_match(
            '/lab = Math\.round\(fa \/ \(1 \+ HST\) \* 100\) \/ 100;.*?labHst = Math\.round\(\(fa - lab\) \* 100\) \/ 100;/s',
            $js,
            $labInc
        ), 'Must extract HST-included labour math');
        $this->assertTrue((bool) preg_match(
            '/lab = Math\.round\(fa \* 100\) \/ 100;.*?labWithHst = Math\.round\(\(lab \+ labHst\) \* 100\) \/ 100;/s',
            $js,
            $labEx
        ), 'Must extract pre-tax labour math');
        $this->assertTrue((bool) preg_match(
            '/buildingSurcharge = Math\.round\(lab \* \(buildingPct \/ 100\) \* 100\) \/ 100;/',
            $js,
            $buildingAmt
        ), 'Must extract building surcharge amount math');
        $this->assertTrue((bool) preg_match(
            '/buildingHst = Math\.round\(buildingSurcharge \* HST \* 100\) \/ 100;/',
            $js,
            $buildingHst
        ), 'Must extract building HST math');
        $this->assertTrue((bool) preg_match(
            '/contAmt = Math\.round\(contBase \* \(contPct \/ 100\) \* 100\) \/ 100;.*?contHst = Math\.round\(contAmt \* effRate \* 100\) \/ 100;/s',
            $js,
            $cont
        ), 'Must extract contingency math');

        $script = <<<JS
var HST = 0.13;
function labourHstEnabled() { return true; }
function lineMath(unit, qty, delivery, inclusive, chargePreTax) {
  {$matRow[0]}
  return {lineSub: lineSub, lineHst: lineHst, lineTot: lineTot};
}
function includedLabour(fa) {
  var lab, labHst;
  {$labInc[0]}
  return {lab: lab, labHst: labHst};
}
function pretaxLabour(fa) {
  var lab, labHst, labWithHst;
  {$labEx[0]}
  return {lab: lab, labHst: labHst};
}
function building(lab, buildingPct) {
  var buildingSurcharge = 0, buildingHst = 0;
  if (buildingPct > 0) {
    {$buildingAmt[0]}
    {$buildingHst[0]}
  }
  return {buildingSurcharge: buildingSurcharge, buildingHst: buildingHst};
}
function contingency(contBase, contBaseHst, contPct) {
  var contAmt, contHst, effRate;
  effRate = contBase > 0 ? (contBaseHst / contBase) : 0;
  {$cont[0]}
  return {contAmt: contAmt, contHst: contHst};
}
var cases = JSON.parse(process.argv[2]);
var out = cases.map(function (c) {
  var jobs = c.jobs || [{mat: c.mat, fa: c.fa, inclusiveLab: c.inclusiveLab !== false, inclusiveMat: !!c.inclusiveMat}];
  var contBase = 0, contBaseHst = 0, lab = 0, labHst = 0, matSub = 0, matHst = 0;
  jobs.forEach(function (job) {
    var m = lineMath(job.mat, 1, 0, !!job.inclusiveMat, true);
    matSub += m.lineSub;
    matHst += m.lineHst;
    contBase += m.lineSub;
    contBaseHst += m.lineHst;
    var l = job.inclusiveLab === false ? pretaxLabour(job.fa) : includedLabour(job.fa);
    lab += l.lab;
    labHst += l.labHst;
  });
  matSub = Math.round(matSub * 100) / 100;
  matHst = Math.round(matHst * 100) / 100;
  lab = Math.round(lab * 100) / 100;
  labHst = Math.round(labHst * 100) / 100;
  var b = c.buildingPct ? building(lab, c.buildingPct) : {buildingSurcharge: 0, buildingHst: 0};
  var labourAmt = Math.round((lab + b.buildingSurcharge) * 100) / 100;
  var labourHst = Math.round((labHst + b.buildingHst) * 100) / 100;
  var cont = c.contPct ? contingency(contBase, contBaseHst, c.contPct) : {contAmt: 0, contHst: 0};
  var extra = 0;
  (c.additional || []).forEach(function (a) {
    extra += a;
  });
  extra = Math.round(extra * 100) / 100;
  return {
    labourAmt: labourAmt,
    labourHst: labourHst,
    contHst: cont.contHst,
    matHst: matHst,
    grand: Math.round((matSub + matHst + cont.contAmt + cont.contHst + labourAmt + labourHst + extra) * 100) / 100
  };
});
process.stdout.write(JSON.stringify(out));
JS;

        $cases = [
            ['fa' => 113.00, 'mat' => 100.00, 'buildingPct' => 30, 'contPct' => 10, 'inclusiveLab' => true],
            ['fa' => 566.00, 'mat' => 33.33, 'buildingPct' => 30, 'contPct' => 20, 'inclusiveLab' => true],
            ['fa' => 99.99, 'mat' => 250.00, 'buildingPct' => 15, 'contPct' => 10, 'inclusiveLab' => true],
            ['fa' => 100.00, 'mat' => 40.00, 'buildingPct' => 0, 'contPct' => 0, 'inclusiveLab' => true],
            ['fa' => 200.00, 'mat' => 80.00, 'buildingPct' => 0, 'contPct' => 0, 'inclusiveLab' => false],
            ['fa' => 200.00, 'mat' => 33.33, 'buildingPct' => 0, 'contPct' => 0, 'inclusiveLab' => false, 'inclusiveMat' => true],
            [
                'jobs' => [
                    ['mat' => 33.33, 'fa' => 113.00, 'inclusiveLab' => true],
                    ['mat' => 66.67, 'fa' => 226.00, 'inclusiveLab' => true],
                ],
                'buildingPct' => 30,
                'contPct' => 10,
            ],
            [
                'fa' => 300.00,
                'mat' => 150.00,
                'buildingPct' => 0,
                'contPct' => 0,
                'inclusiveLab' => false,
                'additional' => [113.00],
            ],
        ];
        $tmp = tempnam(sys_get_temp_dir(), 'sq-lab-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' '.escapeshellarg(json_encode($cases)).' 2>&1', $output, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $output));
        $jsOut = json_decode(implode('', $output), true);
        $this->assertIsArray($jsOut);
        $this->assertCount(count($cases), $jsOut);

        $mutantLineHstFired = false;
        $mutantContFired = false;
        $mutantLabourFired = false;

        foreach ($cases as $i => $case) {
            $jobs = $case['jobs'] ?? null;
            $attrs = [
                'pdf_fold_version' => 3,
                'include_labour_hst' => true,
                'include_building' => ((float) ($case['buildingPct'] ?? 0)) > 0,
                'building_labor_percent' => $case['buildingPct'] ?? 0,
                'contingency_enabled' => ((float) ($case['contPct'] ?? 0)) > 0,
                'contingency_percent' => $case['contPct'] ?? 0,
                'include_commute' => false,
                'include_debris_removal' => false,
            ];
            if ($jobs !== null) {
                $attrs['works'] = array_map(static function (array $job, int $idx): array {
                    return [
                        'title' => 'Job '.($idx + 1),
                        'materials' => [[
                            'short_name' => 'Row '.$idx,
                            'unit_price' => $job['mat'],
                            'quantity' => 1,
                            'apply_hst' => true,
                            'hst_includes_unit' => ! empty($job['inclusiveMat']),
                        ]],
                        'labour_use_fixed' => true,
                        'labour_fixed_amount' => $job['fa'],
                        'labour_fixed_hst_included' => $job['inclusiveLab'] !== false,
                    ];
                }, $jobs, array_keys($jobs));
            } else {
                $attrs['materials'] = [[
                    'short_name' => 'Plywood',
                    'unit_price' => $case['mat'],
                    'quantity' => 1,
                    'apply_hst' => true,
                    'hst_includes_unit' => ! empty($case['inclusiveMat']),
                ]];
                $attrs['labour_use_fixed'] = true;
                $attrs['labour_fixed_amount'] = $case['fa'];
                $attrs['labour_fixed_hst_included'] = ($case['inclusiveLab'] ?? true) !== false;
            }

            $sq = new StandardQuotation($attrs);
            $items = $sq->summaryPdfLineItems();
            foreach ($case['additional'] ?? [] as $gross) {
                $items[] = [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => $gross,
                    'apply_hst' => false,
                    'hst_included' => true,
                ];
            }
            $cpq = new \App\Models\ClientProjectQuotation([
                'source_type' => \App\Models\ClientProjectQuotation::SOURCE_FENCE,
                'pdf_fold_version' => 3,
                'items' => $items,
            ]);
            $this->assertEquals($jsOut[$i]['grand'], $cpq->pdfGrandTotal(), 'grand case '.$i);
            $this->assertEquals($jsOut[$i]['grand'], $cpq->editorGrandTotal(), 'editor case '.$i);

            $labour = collect($sq->summaryPdfLineItems())->firstWhere('description', 'Labour');
            if ($labour && ($jsOut[$i]['labourHst'] ?? 0) > 0) {
                $this->assertEquals(
                    $jsOut[$i]['labourHst'],
                    \App\Models\ClientProjectQuotation::lineHstAmount($labour),
                    'labour HST '.$i
                );
            }

            if (! empty($case['inclusiveMat']) || (($case['jobs'][0]['inclusiveMat'] ?? false))) {
                $mutantIgnoreLineHst = $sq->summaryPdfLineItems();
                foreach ($mutantIgnoreLineHst as &$row) {
                    if (($row['description'] ?? '') === 'Material') {
                        unset($row['hst_amount']);
                    }
                }
                unset($row);
                $mutantMatHst = 0.0;
                foreach ($mutantIgnoreLineHst as $row) {
                    $mutantMatHst += \App\Models\ClientProjectQuotation::lineHstAmount($row);
                }
                if (abs(round($mutantMatHst, 2) - $cpq->pdfHst()) > 0.001) {
                    $mutantLineHstFired = true;
                }
                $this->assertNotEquals(
                    round($mutantMatHst, 2),
                    $cpq->pdfHst(),
                    'mutant that ignores line_hst on tax-included rows must fail case '.$i
                );
            }

            if (((float) ($case['contPct'] ?? 0)) > 0 && (float) $jsOut[$i]['contHst'] > 0) {
                $mutantDropCont = $sq->summaryPdfLineItems();
                foreach ($mutantDropCont as &$row) {
                    if (($row['description'] ?? '') === 'Material' && isset($row['hst_amount'])) {
                        $row['hst_amount'] = round((float) $row['hst_amount'] - (float) $jsOut[$i]['contHst'], 2);
                    }
                }
                unset($row);
                $mutantContHst = 0.0;
                foreach ($mutantDropCont as $row) {
                    $mutantContHst += \App\Models\ClientProjectQuotation::lineHstAmount($row);
                }
                $this->assertNotEquals(
                    round($mutantContHst, 2),
                    $cpq->pdfHst(),
                    'mutant that drops contingency HST must fail case '.$i
                );
                $mutantContFired = true;
            }

            if (($case['inclusiveLab'] ?? true) !== false && ($jsOut[$i]['labourHst'] ?? 0) > 0) {
                $mutantIgnoreIncluded = $sq->summaryPdfLineItems();
                foreach ($mutantIgnoreIncluded as &$row) {
                    if (($row['description'] ?? '') === 'Labour') {
                        unset($row['hst_amount']);
                    }
                }
                unset($row);
                $mutantHst = 0.0;
                foreach ($mutantIgnoreIncluded as $row) {
                    $mutantHst += \App\Models\ClientProjectQuotation::lineHstAmount($row);
                }
                if (abs(round($mutantHst, 2) - $cpq->pdfHst()) > 0.001) {
                    $mutantLabourFired = true;
                    $this->assertNotEquals(
                        round($mutantHst, 2),
                        $cpq->pdfHst(),
                        'mutant that drops labour hst_amount must fail case '.$i
                    );
                }
            }
        }

        $this->assertTrue($mutantLineHstFired, 'Need a tax-included material where ignoring line_hst drifts');
        $this->assertTrue($mutantContFired, 'Need a contingency mutant that drops HST');
        $this->assertTrue($mutantLabourFired, 'Need an HST-included labour mutant that drifts');
    }

    public function test_v3_multi_job_assigns_hst_remainder_when_last_job_has_no_labour(): void
    {
        $sq = new StandardQuotation([
            'pdf_fold_version' => 3,
            'works' => [
                [
                    'title' => 'Job one',
                    'materials' => [[
                        'short_name' => 'A',
                        'unit_price' => 33.33,
                        'quantity' => 1,
                        'apply_hst' => true,
                    ]],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 33.33,
                    'labour_fixed_hst_included' => false,
                ],
                [
                    'title' => 'Job two',
                    'materials' => [[
                        'short_name' => 'B',
                        'unit_price' => 33.34,
                        'quantity' => 1,
                        'apply_hst' => true,
                    ]],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 33.34,
                    'labour_fixed_hst_included' => false,
                ],
                [
                    'title' => 'Job three no labour',
                    'materials' => [[
                        'short_name' => 'C',
                        'unit_price' => 10.00,
                        'quantity' => 1,
                        'apply_hst' => true,
                    ]],
                    'labour_use_fixed' => true,
                    'labour_fixed_amount' => 0,
                    'labour_fixed_hst_included' => false,
                ],
            ],
            'include_labour_hst' => true,
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'include_commute' => false,
            'include_debris_removal' => false,
        ]);

        $computed = $sq->computedTotals();
        $lines = $sq->detailedPdfLineItems();
        $labourHst = 0.0;
        $materialHst = 0.0;
        foreach ($lines as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $hst = \App\Models\ClientProjectQuotation::lineHstAmount($row);
            if (($row['description'] ?? '') === 'Labour') {
                $labourHst += $hst;
            }
            if (($row['description'] ?? '') === 'Material') {
                $materialHst += $hst;
            }
        }

        $this->assertEquals(
            round((float) $computed['labour_hst'], 2),
            round($labourHst, 2),
            'labour HST remainder must land on an earlier Labour line'
        );
        $expectedMatHst = round((float) $computed['materials_hst'], 2);
        $this->assertEquals(
            $expectedMatHst,
            round($materialHst, 2),
            'contingency HST remainder must land on a Material line'
        );

        $cpq = new \App\Models\ClientProjectQuotation([
            'source_type' => \App\Models\ClientProjectQuotation::SOURCE_FENCE,
            'pdf_fold_version' => 3,
            'items' => $lines,
        ]);
        $this->assertEquals($cpq->editorGrandTotal(), $cpq->pdfGrandTotal());
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
