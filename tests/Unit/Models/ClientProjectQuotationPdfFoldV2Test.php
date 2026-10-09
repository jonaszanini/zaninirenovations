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

    public function test_v1_byte_identity_across_sources_with_and_without_override(): void
    {
        $commute = [
            'description' => 'Commute expense',
            'amount' => 113.00,
            'apply_hst' => false,
            'hst_included' => true,
        ];
        $debris = [
            'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
            'amount' => 50.00,
            'apply_hst' => false,
            'hst_included' => true,
        ];

        $cases = [
            'standard' => [
                'source' => ClientProjectQuotation::SOURCE_STANDARD,
                'items' => [
                    ['description' => 'Material', 'amount' => 100.00, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 200.00, 'apply_hst' => true],
                    $commute,
                ],
            ],
            'fence' => [
                'source' => ClientProjectQuotation::SOURCE_FENCE,
                'items' => [
                    [
                        'description' => '6 ft standard board fence, 4×4 posts in concrete, 24 linear ft (1 run)',
                        'amount' => 1200.00,
                        'apply_hst' => true,
                    ],
                    $commute,
                    $debris,
                ],
            ],
            'deck' => [
                'source' => ClientProjectQuotation::SOURCE_LOW_DECK,
                'items' => [
                    [
                        'description' => 'Low deck, 12×16 ft pressure-treated',
                        'amount' => 800.00,
                        'apply_hst' => true,
                    ],
                    $commute,
                ],
            ],
            'basement' => [
                'source' => ClientProjectQuotation::SOURCE_BASEMENT,
                'items' => [
                    ['description' => 'Material', 'amount' => 250.00, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 400.00, 'apply_hst' => true],
                    $commute,
                    $debris,
                ],
            ],
            'flooring' => [
                'source' => ClientProjectQuotation::SOURCE_FLOORING,
                'items' => [
                    ['description' => 'Flooring installation (labor)', 'amount' => 400.00, 'apply_hst' => true],
                    ['description' => 'Client-selected flooring material', 'amount' => 200.00, 'apply_hst' => true],
                    $commute,
                ],
            ],
            'paving' => [
                'source' => ClientProjectQuotation::SOURCE_PAVING,
                'items' => [
                    [
                        'description' => 'Paving Stones — Material',
                        'amount' => 1000.00,
                        'apply_hst' => false,
                        'hst_included' => true,
                    ],
                    [
                        'description' => 'Paving Stones — Labour',
                        'amount' => 613.00,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 500.00,
                    ],
                    $commute,
                ],
            ],
        ];

        $snapshots = [];
        foreach ($cases as $name => $case) {
            foreach ([null, 350.00] as $override) {
                $quote = new ClientProjectQuotation([
                    'source_type' => $case['source'],
                    'pdf_fold_version' => 1,
                    'subtotal_override' => $override,
                    'items' => $case['items'],
                ]);
                $quote->exists = true;

                $lines = array_values(array_map(static function (array $row): array {
                    $out = [
                        'description' => (string) ($row['description'] ?? ''),
                        'amount' => round((float) ($row['amount'] ?? 0), 2),
                        'apply_hst' => (bool) ($row['apply_hst'] ?? false),
                    ];
                    if (array_key_exists('hst_amount', $row)) {
                        $out['hst_amount'] = round((float) $row['hst_amount'], 2);
                    }
                    if (array_key_exists('hst_taxable_amount', $row)) {
                        $out['hst_taxable_amount'] = round((float) $row['hst_taxable_amount'], 2);
                    }
                    if (! empty($row['hst_included'])) {
                        $out['hst_included'] = true;
                    }

                    return $out;
                }, $quote->pdfLineItems()));

                $key = $name.($override === null ? '' : '_override');
                $snapshots[$key] = [
                    'lines' => $lines,
                    'hst' => $quote->hst(),
                    'pdf_hst' => $quote->pdfHst(),
                    'total' => $quote->grandTotal(),
                    'pdf_total' => $quote->pdfGrandTotal(),
                ];

                $nullVersion = new ClientProjectQuotation([
                    'source_type' => $case['source'],
                    'subtotal_override' => $override,
                    'items' => $case['items'],
                ]);
                $nullVersion->exists = true;
                $this->assertSame(
                    $lines,
                    array_values(array_map(static function (array $row): array {
                        $out = [
                            'description' => (string) ($row['description'] ?? ''),
                            'amount' => round((float) ($row['amount'] ?? 0), 2),
                            'apply_hst' => (bool) ($row['apply_hst'] ?? false),
                        ];
                        if (array_key_exists('hst_amount', $row)) {
                            $out['hst_amount'] = round((float) $row['hst_amount'], 2);
                        }
                        if (array_key_exists('hst_taxable_amount', $row)) {
                            $out['hst_taxable_amount'] = round((float) $row['hst_taxable_amount'], 2);
                        }
                        if (! empty($row['hst_included'])) {
                            $out['hst_included'] = true;
                        }

                        return $out;
                    }, $nullVersion->pdfLineItems())),
                    $key.' null version must match v1 lines'
                );
                $this->assertEquals($quote->hst(), $nullVersion->hst(), $key.' null HST');
                $this->assertEquals($quote->grandTotal(), $nullVersion->grandTotal(), $key.' null total');
            }
        }

        $this->assertSame($this->v1ByteIdentitySnapshots(), $snapshots);
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

    /**
     * Frozen v1 (main) PDF lines / HST / totals. Re-saving a stored quote
     * must keep these exact figures, including paving and overrides.
     *
     * @return array<string, array{lines: list<array<string, mixed>>, hst: float, pdf_hst: float, total: float, pdf_total: float}>
     */
    private function v1ByteIdentitySnapshots(): array
    {
        return [
            'standard' => [
                'lines' => [
                    ['description' => 'Material', 'amount' => 100.0, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 300.0, 'apply_hst' => true, 'hst_taxable_amount' => 300.0],
                ],
                'hst' => 52.0,
                'pdf_hst' => 52.0,
                'total' => 452.0,
                'pdf_total' => 452.0,
            ],
            'standard_override' => [
                'lines' => [
                    ['description' => 'Material', 'amount' => 100.0, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 300.0, 'apply_hst' => true, 'hst_taxable_amount' => 300.0],
                ],
                'hst' => 45.5,
                'pdf_hst' => 52.0,
                'total' => 395.5,
                'pdf_total' => 452.0,
            ],
            'fence' => [
                'lines' => [
                    [
                        'description' => '6 ft standard board fence, 4×4 posts in concrete, 24 linear ft (1 run)',
                        'amount' => 1344.25,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 1344.25,
                    ],
                ],
                'hst' => 174.75,
                'pdf_hst' => 174.75,
                'total' => 1519.0,
                'pdf_total' => 1519.0,
            ],
            'fence_override' => [
                'lines' => [
                    [
                        'description' => '6 ft standard board fence, 4×4 posts in concrete, 24 linear ft (1 run)',
                        'amount' => 1344.25,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 1344.25,
                    ],
                ],
                'hst' => 45.5,
                'pdf_hst' => 174.75,
                'total' => 395.5,
                'pdf_total' => 1519.0,
            ],
            'deck' => [
                'lines' => [
                    [
                        'description' => 'Low deck, 12×16 ft pressure-treated',
                        'amount' => 900.0,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 900.0,
                    ],
                ],
                'hst' => 117.0,
                'pdf_hst' => 117.0,
                'total' => 1017.0,
                'pdf_total' => 1017.0,
            ],
            'deck_override' => [
                'lines' => [
                    [
                        'description' => 'Low deck, 12×16 ft pressure-treated',
                        'amount' => 900.0,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 900.0,
                    ],
                ],
                'hst' => 45.5,
                'pdf_hst' => 117.0,
                'total' => 395.5,
                'pdf_total' => 1017.0,
            ],
            'basement' => [
                'lines' => [
                    ['description' => 'Material', 'amount' => 250.0, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 544.25, 'apply_hst' => true, 'hst_taxable_amount' => 544.25],
                ],
                'hst' => 103.25,
                'pdf_hst' => 103.25,
                'total' => 897.5,
                'pdf_total' => 897.5,
            ],
            'basement_override' => [
                'lines' => [
                    ['description' => 'Material', 'amount' => 250.0, 'apply_hst' => true],
                    ['description' => 'Labour', 'amount' => 544.25, 'apply_hst' => true, 'hst_taxable_amount' => 544.25],
                ],
                'hst' => 45.5,
                'pdf_hst' => 103.25,
                'total' => 395.5,
                'pdf_total' => 897.5,
            ],
            'flooring' => [
                'lines' => [
                    [
                        'description' => 'Flooring installation (labor)',
                        'amount' => 500.0,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 500.0,
                    ],
                    ['description' => 'Client-selected flooring material', 'amount' => 200.0, 'apply_hst' => true],
                ],
                'hst' => 91.0,
                'pdf_hst' => 91.0,
                'total' => 791.0,
                'pdf_total' => 791.0,
            ],
            'flooring_override' => [
                'lines' => [
                    [
                        'description' => 'Flooring installation (labor)',
                        'amount' => 500.0,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 500.0,
                    ],
                    ['description' => 'Client-selected flooring material', 'amount' => 200.0, 'apply_hst' => true],
                ],
                'hst' => 45.5,
                'pdf_hst' => 91.0,
                'total' => 395.5,
                'pdf_total' => 791.0,
            ],
            'paving' => [
                'lines' => [
                    ['description' => 'Paving Stones — Material', 'amount' => 884.96, 'apply_hst' => true],
                    [
                        'description' => 'Paving Stones — Labour',
                        'amount' => 688.5,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 588.5,
                    ],
                ],
                'hst' => 191.55,
                'pdf_hst' => 191.55,
                'total' => 1765.01,
                'pdf_total' => 1765.01,
            ],
            'paving_override' => [
                'lines' => [
                    ['description' => 'Paving Stones — Material', 'amount' => 884.96, 'apply_hst' => true],
                    [
                        'description' => 'Paving Stones — Labour',
                        'amount' => 688.5,
                        'apply_hst' => true,
                        'hst_taxable_amount' => 588.5,
                    ],
                ],
                'hst' => 45.5,
                'pdf_hst' => 191.55,
                'total' => 395.5,
                'pdf_total' => 1765.01,
            ],
        ];
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
