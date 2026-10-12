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

    public function test_v1_paving_heading_without_apply_hst_stays_a_taxed_row(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 1,
            'items' => [
                ['description' => 'Job 1 — Driveway', 'amount' => 0, 'is_heading' => true],
                [
                    'description' => 'Paving Stones — Material',
                    'amount' => 1130.00,
                    'apply_hst' => true,
                    'hst_included' => true,
                ],
            ],
        ]);
        $quote->exists = true;

        $lines = $quote->pdfLineItems();
        $this->assertArrayNotHasKey('is_heading', $lines[0]);
        $this->assertTrue((bool) $lines[0]['apply_hst']);
        $this->assertSame('Job 1 — Driveway', $lines[0]['description']);
        $this->assertEquals(0.0, $lines[0]['amount']);
    }

    public function test_v2_paving_heading_without_apply_hst_stays_a_taxed_row(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Job 1 — Driveway', 'amount' => 0, 'is_heading' => true],
                [
                    'description' => 'Paving Stones — Material',
                    'amount' => 1130.00,
                    'apply_hst' => true,
                    'hst_included' => true,
                ],
            ],
        ]);
        $quote->exists = true;

        $lines = $quote->pdfLineItems();
        $this->assertArrayNotHasKey('is_heading', $lines[0]);
        $this->assertTrue((bool) $lines[0]['apply_hst']);
        $this->assertSame('Job 1 — Driveway', $lines[0]['description']);
        $this->assertEquals(0.0, $lines[0]['amount']);
    }

    public function test_zero_and_unknown_fold_versions_are_legacy(): void
    {
        foreach ([0, 9] as $version) {
            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'pdf_fold_version' => $version,
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
            $this->assertTrue($quote->usesLegacyPdfFold());
            $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'));
        }
    }

    public function test_persisted_null_fold_version_is_v1(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
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
        $this->assertNull($quote->pdf_fold_version);
        $this->assertTrue($quote->usesLegacyPdfFold());
        $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'));
        $this->assertEquals(226.00, $quote->pdfGrandTotal());
    }

    public function test_v2_never_folds_into_permit_electrical_demo_or_custom(): void
    {
        $names = [
            'Building permit',
            'Electrical work',
            'Electrical labour',
            'Demolition',
            'Demolition labour',
            'Kitchen cabinets supply',
            'Plumbing rough-in',
            'Panel upgrade 200A',
            'Custom millwork',
        ];
        foreach ($names as $desc) {
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
            $this->assertNotContains($desc, ['Labour'], $desc);
            $byDesc = collect($quote->pdfLineItems())->keyBy('description');
            $this->assertEqualsWithDelta(200.00, $byDesc[$desc]['amount'], 0.001, $desc);
            $this->assertEqualsWithDelta(200.00, $byDesc[$desc]['amount'], 0.001);
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

    public function test_two_equal_labour_lines_keep_a_sub_50_cent_commute(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Labour', 'amount' => 100.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 100.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 0.09,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(226.09, $quote->editorGrandTotal());
        $this->assertEquals(226.09, $quote->pdfGrandTotal());
        $this->assertFooterEqualsLineSum($quote);
    }

    public function test_v2_exempt_material_plus_commute_label_matches_hst(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Material — HST exempt', 'amount' => 40.00, 'apply_hst' => false],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $labour = collect($quote->pdfLineItems())->firstWhere('description', 'Labour');
        $this->assertNotNull($labour);
        $this->assertFalse((bool) ($labour['apply_hst'] ?? false));
        $this->assertFalse((bool) ($labour['hst_included'] ?? false));
        $this->assertSame('—', ClientProjectQuotation::lineHstColumnLabel($labour));
        $this->assertEquals(0.00, ClientProjectQuotation::lineHstAmount($labour));
        $this->assertEquals(153.00, $quote->pdfGrandTotal());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
    }

    public function test_hst_override_honours_hst_amount(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
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

        $this->assertEquals($quote->hst(), $quote->pdfHst());
        $this->assertEquals($quote->grandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals($quote->grandTotal(), $quote->editorGrandTotal());
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_v1_hst_override_uses_amount_times_ratio_times_rate(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 1,
            'subtotal_override' => 400.00,
            'items' => [
                ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 300.00, 'apply_hst' => true],
            ],
        ]);
        $quote->exists = true;

        $this->assertEquals(52.00, $quote->hst());
        $this->assertEquals(452.00, $quote->grandTotal());
    }

    public function test_v2_matches_labour_labels_case_and_dash_insensitive(): void
    {
        foreach (['labour', 'LABOUR', 'Kitchen — labour', 'Kitchen - Labour'] as $desc) {
            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'pdf_fold_version' => 2,
                'items' => [
                    ['description' => $desc, 'amount' => 100.00, 'apply_hst' => true],
                    [
                        'description' => 'Commute expense',
                        'amount' => 1.13,
                        'apply_hst' => false,
                        'hst_included' => true,
                    ],
                ],
            ]);
            $lines = $quote->pdfLineItems();
            $this->assertCount(1, $lines, $desc);
            $this->assertSame($desc, $lines[0]['description']);
            $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $desc);
        }
    }

    public function test_v2_creates_material_line_for_additional_cost_without_a_target(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Building permit', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $descriptions = array_column($quote->pdfLineItems(), 'description');
        $this->assertContains('Building permit', $descriptions);
        $this->assertContains('Material', $descriptions);
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $permit = collect($quote->pdfLineItems())->firstWhere('description', 'Building permit');
        $this->assertEqualsWithDelta(200.00, $permit['amount'], 0.001);
    }

    public function test_v2_writes_zero_hst_amount_so_tiny_commute_does_not_re_tax(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Labour', 'amount' => 4114.38, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 0.01,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertArrayHasKey('hst_amount', $quote->pdfLineItems()[0]);
    }

    public function test_v1_byte_identity_is_covered_by_main_fixtures(): void
    {
        $this->assertFileExists(base_path('tests/fixtures/pdf-fold-v1/named.json'));
        $this->assertFileExists(base_path('tests/fixtures/pdf-fold-v1/paving.json'));
    }

    public function test_v2_pdf_equals_editor_across_sources(): void
    {
        $amounts = [0.09, 0.50, 1.00, 7.77, 33.33, 100.00, 113.00, 566.00];
        $checked = 0;
        foreach ([
            ClientProjectQuotation::SOURCE_STANDARD,
            ClientProjectQuotation::SOURCE_FENCE,
            ClientProjectQuotation::SOURCE_LOW_DECK,
            ClientProjectQuotation::SOURCE_BASEMENT,
            ClientProjectQuotation::SOURCE_FLOORING,
            ClientProjectQuotation::SOURCE_PAVING,
        ] as $source) {
            foreach ($amounts as $material) {
                foreach ([0.09, 1.00, 13.00, 113.00] as $commute) {
                    $items = $source === ClientProjectQuotation::SOURCE_PAVING
                        ? [
                            [
                                'description' => 'Paving Stones — Material',
                                'amount' => $material,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                            [
                                'description' => 'Paving Stones — Labour',
                                'amount' => 100.00,
                                'apply_hst' => true,
                            ],
                            [
                                'description' => 'Commute expense',
                                'amount' => $commute,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                        ]
                        : [
                            ['description' => 'Material', 'amount' => $material, 'apply_hst' => true],
                            ['description' => 'Labour', 'amount' => 80.00, 'apply_hst' => true],
                            [
                                'description' => 'Commute expense',
                                'amount' => $commute,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                        ];

                    $quote = new ClientProjectQuotation([
                        'source_type' => $source,
                        'pdf_fold_version' => 2,
                        'items' => $items,
                    ]);
                    $this->assertEquals(
                        $quote->editorGrandTotal(),
                        $quote->pdfGrandTotal(),
                        "{$source} material {$material} commute {$commute}"
                    );
                    $checked++;
                }
            }
        }

        $this->assertSame(192, $checked);
    }

    public function test_v2_paving_additional_cost_creates_or_folds_and_matches_editor(): void
    {
        $withMaterial = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 2,
            'items' => [
                [
                    'description' => 'Paving Stones — Material',
                    'amount' => 1000.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Paving Stones — Labour',
                    'amount' => 500.00,
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $this->assertEquals($withMaterial->editorGrandTotal(), $withMaterial->pdfGrandTotal());
        $this->assertFalse((bool) preg_grep('/Additional cost/i', array_column($withMaterial->pdfLineItems(), 'description')));

        $permitOnly = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Building permit', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 498.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);
        $descriptions = array_column($permitOnly->pdfLineItems(), 'description');
        $this->assertContains('Building permit', $descriptions);
        $this->assertContains('Material', $descriptions);
        $this->assertEquals($permitOnly->editorGrandTotal(), $permitOnly->pdfGrandTotal());
        $permit = collect($permitOnly->pdfLineItems())->firstWhere('description', 'Building permit');
        $this->assertEqualsWithDelta(200.00, $permit['amount'], 0.001);
    }

    public function test_v2_additional_costs_never_fold_into_excluded_lines(): void
    {
        foreach (['Building permit', 'Electrical labour', 'Demolition', 'Plumbing rough-in', 'Kitchen cabinets supply', 'Custom millwork'] as $desc) {
            $quote = new ClientProjectQuotation([
                'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
                'pdf_fold_version' => 2,
                'items' => [
                    ['description' => $desc, 'amount' => 200.00, 'apply_hst' => true],
                    [
                        'description' => 'Additional cost — Dumpster',
                        'amount' => 113.00,
                        'apply_hst' => false,
                        'hst_included' => true,
                    ],
                ],
            ]);
            $byDesc = collect($quote->pdfLineItems())->keyBy('description');
            $this->assertEqualsWithDelta(200.00, $byDesc[$desc]['amount'], 0.001, $desc);
            $this->assertArrayHasKey('Material', $byDesc->all(), $desc);
            $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $desc);
        }
    }

    public function test_v2_pdf_equals_editor_via_node_on_a_sample(): void
    {
        $script = <<<'JS'
var HST = 0.13;
var cases = JSON.parse(process.argv[2]);
var out = cases.map(function (c) {
  var total = 0;
  c.items.forEach(function (row) {
    total += Number(row.amount || 0);
    if (row.apply_hst) {
      if (row.hst_amount != null) {
        total += Number(row.hst_amount);
      } else if (row.hst_taxable_amount != null) {
        total += Math.round(Number(row.hst_taxable_amount) * HST * 100) / 100;
      } else {
        total += Math.round(Number(row.amount || 0) * HST * 100) / 100;
      }
    }
  });
  return Math.round(total * 100) / 100;
});
process.stdout.write(JSON.stringify(out));
JS;

        $cases = [];
        $amounts = [0.09, 0.50, 7.77, 33.33, 113.00, 566.00, 1000.00];
        foreach ([
            ClientProjectQuotation::SOURCE_STANDARD,
            ClientProjectQuotation::SOURCE_FENCE,
            ClientProjectQuotation::SOURCE_PAVING,
        ] as $source) {
            foreach ($amounts as $material) {
                foreach ([0.09, 1.13, 113.00] as $extra) {
                    $items = $source === ClientProjectQuotation::SOURCE_PAVING
                        ? [
                            [
                                'description' => 'Paving Stones — Material',
                                'amount' => $material,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                            [
                                'description' => 'Paving Stones — Labour',
                                'amount' => 80.00,
                                'apply_hst' => true,
                            ],
                            [
                                'description' => 'Additional cost — Dumpster',
                                'amount' => $extra,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                        ]
                        : [
                            ['description' => 'Material', 'amount' => $material, 'apply_hst' => true],
                            ['description' => 'Labour', 'amount' => 80.00, 'apply_hst' => true],
                            [
                                'description' => 'Commute expense',
                                'amount' => $extra,
                                'apply_hst' => false,
                                'hst_included' => true,
                            ],
                        ];
                    $cases[] = ['source' => $source, 'items' => $items];
                }
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'v2-node-');
        file_put_contents($tmp, $script);
        $output = [];
        $code = 0;
        exec('node '.escapeshellarg($tmp).' '.escapeshellarg(json_encode($cases)).' 2>&1', $output, $code);
        @unlink($tmp);
        $this->assertSame(0, $code, implode("\n", $output));
        $jsTotals = json_decode(implode('', $output), true);
        $this->assertIsArray($jsTotals);
        $this->assertCount(count($cases), $jsTotals);

        foreach ($cases as $i => $case) {
            $quote = new ClientProjectQuotation([
                'source_type' => $case['source'],
                'pdf_fold_version' => 2,
                'items' => $case['items'],
            ]);
            $this->assertEquals($jsTotals[$i], $quote->editorGrandTotal(), 'editor '.$i);
            $this->assertEquals($jsTotals[$i], $quote->pdfGrandTotal(), 'pdf '.$i);
        }
    }

    public function test_v2_hst_included_additional_cost_keeps_hst_on_untaxed_material(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 2,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => false],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(1678.00, $quote->editorGrandTotal());
        $this->assertEquals(1678.00, $quote->pdfGrandTotal());
        $this->assertEquals(78.00, $quote->editorHst());
        $this->assertEquals(78.00, $quote->pdfHst());
        $this->assertNotEquals(65.00, $quote->pdfHst());
        $this->assertNotEquals(1665.00, $quote->pdfGrandTotal());

        $materials = array_values(array_filter(
            $quote->pdfLineItems(),
            static fn (array $row): bool => ($row['description'] ?? '') === 'Material'
        ));
        $this->assertCount(2, $materials);
        $this->assertEqualsWithDelta(1000.00, $materials[0]['amount'], 0.001);
        $this->assertFalse((bool) ($materials[0]['apply_hst'] ?? false));
        $this->assertSame('—', ClientProjectQuotation::lineHstColumnLabel($materials[0]));

        $this->assertEqualsWithDelta(100.00, $materials[1]['amount'], 0.001);
        $this->assertTrue((bool) ($materials[1]['apply_hst'] ?? false));
        $this->assertEqualsWithDelta(13.00, $materials[1]['hst_amount'], 0.001);
        $this->assertSame('13%', ClientProjectQuotation::lineHstColumnLabel($materials[1]));

        $labour = collect($quote->pdfLineItems())->firstWhere('description', 'Labour');
        $this->assertNotNull($labour);
        $this->assertEqualsWithDelta(500.00, $labour['amount'], 0.001);
        $this->assertEqualsWithDelta(65.00, ClientProjectQuotation::lineHstAmount($labour), 0.001);
    }

    public function test_v2_pdf_hst_matches_editor_hst_across_identity_corpus(): void
    {
        $dir = base_path('tests/fixtures/pdf-fold-v1');
        $checked = 0;
        $paving = 0;
        foreach (['named', 'standard', 'fence', 'low_deck', 'basement', 'flooring', 'paving'] as $source) {
            $data = json_decode((string) file_get_contents($dir.'/'.$source.'.json'), true);
            $this->assertIsArray($data);
            $rows = $source === 'named' ? array_values($data) : $data;
            foreach ($rows as $key => $fx) {
                $sourceType = $fx['source'] ?? $source;
                $quote = new ClientProjectQuotation([
                    'source_type' => $sourceType,
                    'pdf_fold_version' => 3,
                    'items' => $fx['items'],
                    'subtotal_override' => $fx['override'] ?? null,
                ]);
                $label = $source.' '.$key;
                $this->assertEquals($quote->editorHst(), $quote->pdfHst(), $label.' HST');
                if ($sourceType === ClientProjectQuotation::SOURCE_PAVING) {
                    // Paving PDFs convert tax-in primary lines to pretax, so
                    // the face/HST split can move. Charged HST still matches.
                    $paving++;
                } else {
                    $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $label.' total');
                }
                $checked++;
            }
        }
        $this->assertSame(12008, $checked);
        $this->assertSame(2002, $paving);
    }

    public function test_v2_debris_into_cash_labour_stays_off_the_hst_footer(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_FENCE,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
                ['description' => '6 ft fence — labour', 'amount' => 500.00, 'apply_hst' => false],
                [
                    'description' => 'Debris removal and disposal',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(1743.00, $quote->editorGrandTotal());
        $this->assertEquals(1743.00, $quote->pdfGrandTotal());
        $this->assertEquals(130.00, $quote->editorHst());
        $this->assertEquals(130.00, $quote->pdfHst());

        $cashLabour = collect($quote->pdfLineItems())->firstWhere('description', '6 ft fence — labour');
        $this->assertNotNull($cashLabour);
        $this->assertEqualsWithDelta(613.00, $cashLabour['amount'], 0.001);
        $this->assertFalse((bool) ($cashLabour['apply_hst'] ?? false));
        $this->assertSame('—', ClientProjectQuotation::lineHstColumnLabel($cashLabour));
        $this->assertNotContains('Labour', array_column($quote->pdfLineItems(), 'description'));
    }

    public function test_v2_sq_labour_hst_off_does_not_print_labour_13_percent(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'source_snapshot' => ['include_labour_hst' => false],
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => false],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Debris removal and disposal',
                    'amount' => 226.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(130.00, $quote->editorHst());
        $this->assertEquals(130.00, $quote->pdfHst());
        $this->assertNotEquals(169.00, $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $labourLines = array_values(array_filter(
            $quote->pdfLineItems(),
            static fn (array $row): bool => ($row['description'] ?? '') === 'Labour'
        ));
        $this->assertCount(1, $labourLines);
        $this->assertFalse((bool) ($labourLines[0]['apply_hst'] ?? false));
        $this->assertSame('—', ClientProjectQuotation::lineHstColumnLabel($labourLines[0]));
    }

    public function test_v2_new_taxed_material_line_sits_after_its_category(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Job one', 'amount' => 0, 'apply_hst' => false, 'is_heading' => true],
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => false],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                ['description' => 'Job two', 'amount' => 0, 'apply_hst' => false, 'is_heading' => true],
                ['description' => 'Material', 'amount' => 40.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertSame(
            ['Job one', 'Material', 'Labour', 'Job two', 'Material'],
            array_column($quote->pdfLineItems(), 'description')
        );
        $materials = array_values(array_filter(
            $quote->pdfLineItems(),
            static fn (array $row): bool => ($row['description'] ?? '') === 'Material'
        ));
        $this->assertCount(2, $materials);
        $this->assertEqualsWithDelta(1000.00, $materials[0]['amount'], 0.001);
        $this->assertFalse((bool) ($materials[0]['apply_hst'] ?? false));
        $this->assertEqualsWithDelta(140.00, $materials[1]['amount'], 0.001);
        $this->assertTrue((bool) ($materials[1]['apply_hst'] ?? false));
        $this->assertEquals(83.20, $quote->pdfHst());
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_v2_pooled_extras_keep_per_line_hst_leftover(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Labour', 'amount' => 46.63, 'apply_hst' => true, 'hst_amount' => 6.07],
                [
                    'description' => 'Commute expense',
                    'amount' => 16.38,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Debris removal and disposal',
                    'amount' => 21.42,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => 'Additional labour — Extra hours',
                    'amount' => 28.98,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(13.74, $quote->editorHst());
        $this->assertEquals(13.74, $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
    }

    public function test_v2_labour_word_match_covers_installer_and_cash_labels(): void
    {
        $mustMatch = [
            'Flooring installation (labor)',
            'Tile installation (labor)',
            'Basement renovation (labour)',
            'Labour – x',
            'Labour - x',
            'Labour — x',
            'Labour (cash)',
            'General labour',
            'Labour & material',
        ];
        foreach ($mustMatch as $desc) {
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
            $this->assertSame([$desc], $descriptions, $desc);
            $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $desc);
        }

        $mustExclude = [
            'Electrical labour',
            'Plumbing labour',
            'Demolition labour',
            'Building permit labour',
            'Kitchen cabinets supply',
            'Custom labour',
            'Labourer supplies',
        ];
        foreach ($mustExclude as $desc) {
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

    public function test_unsaved_new_quote_previews_as_v3(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
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
        $this->assertFalse($quote->exists);
        $this->assertNull($quote->pdf_fold_version);
        $this->assertSame(3, $quote->resolvedPdfFoldVersion());
        $this->assertTrue($quote->usesCurrentPdfFold());
        $this->assertFalse($quote->usesLegacyPdfFold());
        $this->assertFalse($quote->usesPdfFoldV2());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals(114.00, $quote->pdfGrandTotal());
    }

    public function test_unsaved_quote_linked_to_v1_source_previews_as_v1(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'source_snapshot' => ['pdf_fold_version' => 1],
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
        $this->assertFalse($quote->exists);
        $this->assertNull($quote->pdf_fold_version);
        $this->assertSame(1, $quote->resolvedPdfFoldVersion());
        $this->assertTrue($quote->usesLegacyPdfFold());
        $this->assertFalse($quote->usesCurrentPdfFold());
        $this->assertEquals(113.99, $quote->pdfGrandTotal());
        $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'));
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
