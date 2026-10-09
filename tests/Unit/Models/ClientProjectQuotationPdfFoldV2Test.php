<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Services\DebrisRemovalExpenseService;
use Tests\TestCase;

class ClientProjectQuotationPdfFoldV2Test extends TestCase
{
    public function test_v1_created_labour_stays_hst_included(): void
    {
        $quote = new ClientProjectQuotation([
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
        $quote->exists = true;

        $lines = $quote->pdfLineItems();
        $this->assertSame(['Labour'], array_column($lines, 'description'));
        $this->assertFalse($lines[0]['apply_hst']);
        $this->assertTrue($lines[0]['hst_included']);
        $this->assertSame('13% incl.', ClientProjectQuotation::lineHstColumnLabel($lines[0]));
        $this->assertEquals(50.00, $quote->pdfGrandTotal());
    }

    public function test_v1_cash_quote_keeps_main_fold_into_material(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 1,
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $quote->exists = true;

        $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'));
        $this->assertEquals(226.00, $quote->pdfGrandTotal());
    }

    public function test_v2_never_folds_into_permit_electrical_demo_or_custom(): void
    {
        foreach (['Building permit', 'Electrical work', 'Demolition', 'Custom millwork'] as $desc) {
            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'pdf_fold_version' => 2,
                'items' => [
                    ['description' => $desc, 'amount' => 200.00, 'apply_hst' => true],
                    [
                        'description' => 'Commute expense',
                        'amount' => 113.00,
                        'apply_hst' => false,
                        'hst_included' => true,
                    ],
                ],
            ]);

            $descriptions = array_column($quote->pdfLineItems(), 'description');
            $this->assertContains($desc, $descriptions, $desc);
            $this->assertContains('Labour', $descriptions, $desc);
            $byDesc = collect($quote->pdfLineItems())->keyBy('description');
            $this->assertEqualsWithDelta(200.00, $byDesc[$desc]['amount'], 0.001, $desc);
        }
    }

    public function test_v2_folds_into_fence_combined_summary_not_a_new_labour_line(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'pdf_fold_version' => 2,
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
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('standard board fence', $lines[0]['description']);
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
    }

    public function test_v2_paving_does_not_back_out_commute_tax_twice(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 2,
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

        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertFalse((bool) preg_grep('/Commute|^Labour$/i', array_column($quote->pdfLineItems(), 'description')));
    }

    public function test_v2_pdf_total_equals_editor_and_footer_equals_line_sum(): void
    {
        $materials = [40.00, 100.00, 500.00, 566.00];
        $commutes = [0.01, 0.50, 1.00, 1.13, 13.00, 113.00];

        foreach ($materials as $material) {
            foreach ($commutes as $commute) {
                $quote = new ClientProjectQuotation([
                    'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                    'pdf_fold_version' => 2,
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
                $this->assertFooterEqualsLineSum($quote);
            }
        }
    }

    public function test_two_labour_lines_split_a_tiny_commute_and_match_editor(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Labour', 'amount' => 100.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertFooterEqualsLineSum($quote);
        $this->assertCount(2, $quote->pdfLineItems());
    }

    public function test_hst_override_honours_hst_amount(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'subtotal_override' => 50.00,
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

        $pdfHst = $quote->pdfHst();
        $this->assertGreaterThan(13.00, $pdfHst);
        $ratio = 50.00 / $quote->lineItemsSubtotal();
        $this->assertEquals(round($pdfHst * $ratio, 2), $quote->hst());
        $this->assertEquals($quote->grandTotal(), $quote->editorGrandTotal());
    }

    public function test_pdf_hst_matches_real_editor_js_per_row_rounding(): void
    {
        $jsPath = public_path('js/admin-standard-quotation.js');
        $this->assertFileExists($jsPath);
        $source = (string) file_get_contents($jsPath);
        $this->assertStringContainsString('lineHst = Math.round(lineSub * HST * 100) / 100', $source);
        $this->assertStringContainsString('lineHst = Math.round((lineGross - lineSub) * 100) / 100', $source);

        $script = <<<'JS'
var HST = 0.13;
function lineHst(lineGross, inclusive, chargePreTax) {
  var lineSub, lineHst;
  if (inclusive) {
    lineSub = Math.round(lineGross / (1 + HST) * 100) / 100;
    lineHst = Math.round((lineGross - lineSub) * 100) / 100;
  } else if (chargePreTax) {
    lineSub = lineGross;
    lineHst = Math.round(lineSub * HST * 100) / 100;
  } else {
    lineHst = 0;
  }
  return lineHst;
}
var rows = JSON.parse(process.argv[2]);
var sum = 0;
rows.forEach(function (r) {
  sum += lineHst(r.gross, !!r.inclusive, !!r.chargePreTax);
});
process.stdout.write(String(Math.round(sum * 100) / 100));
JS;

        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                ['description' => 'Material', 'amount' => 33.33, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 1.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $payload = json_encode([
            ['gross' => 100.00, 'inclusive' => false, 'chargePreTax' => true],
            ['gross' => 33.33, 'inclusive' => false, 'chargePreTax' => true],
            ['gross' => 1.00, 'inclusive' => true, 'chargePreTax' => false],
        ]);

        $tmp = tempnam(sys_get_temp_dir(), 'pdf-hst-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' '.escapeshellarg($payload).' 2>&1', $output, $code);
        @unlink($tmp);

        $this->assertSame(0, $code, implode("\n", $output));
        $jsHst = (float) implode('', $output);
        $this->assertEquals($jsHst, $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
    }

    public function test_migration_fails_loudly_on_a_missing_table(): void
    {
        $src = (string) file_get_contents(
            database_path('migrations/2026_10_09_050000_add_pdf_fold_version_to_quotations.php')
        );

        $this->assertStringContainsString('RuntimeException', $src);
        $this->assertStringContainsString('required table', $src);
        $this->assertStringContainsString('is missing', $src);
    }

    private function assertFooterEqualsLineSum(ClientProjectQuotation $quote): void
    {
        $sum = 0.0;
        foreach ($quote->pdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += (float) ($row['amount'] ?? 0);
            $sum += ClientProjectQuotation::lineHstAmount($row);
        }

        $this->assertEquals(round($sum, 2), $quote->pdfGrandTotal());
    }
}
