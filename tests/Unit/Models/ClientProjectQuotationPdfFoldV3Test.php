<?php

namespace Tests\Unit\Models;

use App\Models\ClientProjectQuotation;
use App\Services\DebrisRemovalExpenseService;
use Tests\TestCase;

class ClientProjectQuotationPdfFoldV3Test extends TestCase
{
    public function test_override_footer_follows_override_and_reconciles(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'subtotal_override' => 1400.00,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(1400.00, $quote->pdfSubtotal());
        $this->assertEquals(1400.00, $quote->subtotal());
        $this->assertEquals($quote->hst(), $quote->pdfHst());
        $this->assertEquals($quote->grandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals(
            round($quote->pdfSubtotal() + $quote->pdfHst(), 2),
            $quote->pdfGrandTotal()
        );
        $this->assertEquals(182.00, $quote->pdfHst());
        $this->assertEquals(1582.00, $quote->pdfGrandTotal());
    }

    public function test_custom_millwork_labour_is_the_fold_target(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Custom millwork (labour)', 'amount' => 8000.00, 'apply_hst' => true],
                ['description' => 'Custom millwork materials', 'amount' => 400.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 226.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $descriptions = array_column($quote->pdfLineItems(), 'description');
        $this->assertSame(['Custom millwork (labour)', 'Custom millwork materials'], $descriptions);
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals(1131.00, $quote->pdfHst());
    }

    public function test_custom_cabinet_supply_stays_excluded(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'source_snapshot' => ['include_labour_hst' => true],
            'items' => [
                ['description' => 'Custom cabinet supply', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $descriptions = array_column($quote->pdfLineItems(), 'description');
        $this->assertContains('Custom cabinet supply', $descriptions);
        $this->assertContains('Labour', $descriptions);
        $byDesc = collect($quote->pdfLineItems())->keyBy('description');
        $this->assertEqualsWithDelta(200.00, $byDesc['Custom cabinet supply']['amount'], 0.001);
        $this->assertTrue((bool) ($byDesc['Labour']['apply_hst'] ?? false));
    }

    public function test_millwork_materials_plus_commute_taxes_the_new_labour_row(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Custom millwork materials', 'amount' => 1000.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $byDesc = collect($quote->pdfLineItems())->keyBy('description');
        $this->assertArrayHasKey('Labour', $byDesc->all());
        $this->assertTrue((bool) ($byDesc['Labour']['apply_hst'] ?? false));
        $this->assertEquals(143.00, $quote->pdfHst());
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
        $this->assertEquals(1243.00, $quote->pdfGrandTotal());
    }

    /**
     * @dataProvider calculatorSourceTypes
     */
    public function test_pdf_hst_matches_editor_per_source_type(string $source, string $labourName): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => $source,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
                ['description' => $labourName, 'amount' => 500.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
                [
                    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                    'amount' => 56.50,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals($quote->editorHst(), $quote->pdfHst(), $source.' HST');
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $source.' total');
        $descriptions = array_column($quote->pdfLineItems(), 'description');
        if ($labourName !== 'Labour') {
            $this->assertNotContains('Labour', $descriptions, $source);
        } else {
            $this->assertSame(['Material', 'Labour'], $descriptions, $source);
        }
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function calculatorSourceTypes(): array
    {
        return [
            'standard' => [ClientProjectQuotation::SOURCE_STANDARD, 'Labour'],
            'millwork' => [ClientProjectQuotation::SOURCE_MILLWORK, 'Custom millwork (labour)'],
            'fence' => [ClientProjectQuotation::SOURCE_FENCE, '6 ft fence — labour'],
            'basement' => [ClientProjectQuotation::SOURCE_BASEMENT, 'Basement renovation (labour)'],
            'flooring' => [ClientProjectQuotation::SOURCE_FLOORING, 'Flooring installation (labor)'],
            'tile' => [ClientProjectQuotation::SOURCE_TILE, 'Tile installation (labor)'],
            'paving' => [ClientProjectQuotation::SOURCE_PAVING, 'Paving Stones — Labour'],
            'stairs' => [ClientProjectQuotation::SOURCE_STAIRS, 'Stair installation (labour)'],
            'painting' => [ClientProjectQuotation::SOURCE_PAINTING, 'Painting (labour)'],
            'shed' => [ClientProjectQuotation::SOURCE_SHED, 'Shed build (labour)'],
            'trimming' => [ClientProjectQuotation::SOURCE_TRIMMING, 'Trim (labour)'],
            'low_deck' => [ClientProjectQuotation::SOURCE_LOW_DECK, 'Deck labour'],
        ];
    }

    public function test_unknown_fold_versions_use_legacy_v1(): void
    {
        foreach ([0, 4, 99] as $version) {
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
            $this->assertTrue($quote->usesLegacyPdfFold(), (string) $version);
            $this->assertFalse($quote->usesCurrentPdfFold(), (string) $version);
            $this->assertFalse($quote->usesPdfFoldV2(), (string) $version);
            $this->assertSame(['Material'], array_column($quote->pdfLineItems(), 'description'), (string) $version);
            $this->assertEquals(113.99, $quote->pdfGrandTotal(), (string) $version);
        }
    }

    public function test_sq_without_saved_labour_hst_setting_defaults_on(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(143.00, $quote->editorHst());
        $this->assertEquals(143.00, $quote->pdfHst());
        $labour = collect($quote->pdfLineItems())->firstWhere('description', 'Labour');
        $this->assertNotNull($labour);
        $this->assertTrue((bool) ($labour['apply_hst'] ?? false));
    }

    public function test_v3_paving_heading_without_apply_hst_stays_a_section_heading(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 3,
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

        $lines = $quote->pdfLineItems();
        $this->assertTrue((bool) ($lines[0]['is_heading'] ?? false));
        $this->assertFalse((bool) $lines[0]['apply_hst']);
        $this->assertSame('Job 1 — Driveway', $lines[0]['description']);
    }

    public function test_heading_without_apply_hst_key_stays_untaxed_and_material_follows_jobs(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Job one', 'amount' => 0, 'is_heading' => true],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                ['description' => 'Job two', 'amount' => 0, 'is_heading' => true],
                ['description' => 'Labour', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $clean = $quote->cleanItems();
        $this->assertTrue((bool) $clean[0]['is_heading']);
        $this->assertFalse((bool) $clean[0]['apply_hst']);
        $this->assertSame(
            ['Job one', 'Labour', 'Job two', 'Material', 'Labour'],
            array_column($quote->pdfLineItems(), 'description')
        );
        $material = collect($quote->pdfLineItems())->firstWhere('description', 'Material');
        $this->assertFalse((bool) ($material['is_standalone'] ?? false));
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_v1_cpq_linked_to_v3_sq_one_cent_is_from_live_v3_remainder(): void
    {
        // A v1 client PDF reads live SQ lines. v3 computeWorks puts the
        // building-surcharge remainder on a job that has labour; v1/v2 leave
        // it on the last job (can be −$0.01). LegacyPdfFoldV1 is frozen and
        // cannot ask the SQ to recompute as v1, so a v1 CPQ re-linked to a
        // v3 SQ can move 1¢. Stored v1 items are unchanged until that re-link.
        $works = [
            [
                'title' => 'Job one',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 10,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ];
        $calc = new \App\Services\StandardQuotationCalculator();
        $v1 = $calc->computeWorks($works, false, 0, true, true, 30, false);
        $v3 = $calc->computeWorks($works, false, 0, true, true, 30, true);
        $this->assertEqualsWithDelta(-0.01, $v1['works'][2]['labour_total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $v3['works'][2]['labour_total'], 0.001);
        $this->assertNotEquals(
            round((float) $v1['works'][2]['labour_total'], 2),
            round((float) $v3['works'][2]['labour_total'], 2)
        );
    }

    public function test_sq_labour_hst_off_is_read_from_the_calculator_flag(): void
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
            ],
        ]);

        $this->assertEquals(130.00, $quote->editorHst());
        $this->assertEquals(130.00, $quote->pdfHst());
        $labour = collect($quote->pdfLineItems())->firstWhere('description', 'Labour');
        $this->assertFalse((bool) ($labour['apply_hst'] ?? false));
        $this->assertEqualsWithDelta(613.00, $labour['amount'], 0.001);
    }

    public function test_paving_commute_without_taxed_labour_matches_independent_editor_hst(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 3,
            'items' => [
                [
                    'description' => 'Paving Stones — Material',
                    'amount' => 2400.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                    'hst_amount' => 0.01,
                ],
                [
                    'description' => 'Commute expense',
                    'amount' => 452.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $expected = round(
            ClientProjectQuotation::pavingEditorLineHst([
                'description' => 'Paving Stones — Material',
                'amount' => 2400.00,
                'apply_hst' => false,
                'hst_included' => true,
            ]) + (452.00 - ClientProjectQuotation::preTaxFromHstIncluded(452.00)),
            2
        );

        $this->assertEquals($expected, $quote->editorHst());
        $this->assertEquals($expected, $quote->pdfHst());
        $this->assertNotEquals(0.01, $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $labour = collect($quote->pdfLineItems())->firstWhere('description', 'Labour');
        $this->assertNotNull($labour);
        $this->assertTrue((bool) ($labour['apply_hst'] ?? false));
    }

    public function test_paving_restores_main_tax_in_conversion(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_PAVING,
            'pdf_fold_version' => 3,
            'items' => [
                [
                    'description' => 'Paving Stones — Material',
                    'amount' => 1130.00,
                    'apply_hst' => true,
                    'hst_included' => true,
                    'hst_amount' => 99.99,
                ],
            ],
        ]);

        $lines = $quote->pdfLineItems();
        $this->assertCount(1, $lines);
        $this->assertEqualsWithDelta(1000.00, $lines[0]['amount'], 0.001);
        $this->assertTrue((bool) $lines[0]['apply_hst']);
        $this->assertEquals(130.00, $quote->pdfHst());
        $this->assertEquals(
            ClientProjectQuotation::pavingEditorLineHst($quote->cleanItems()[0]),
            $quote->pdfHst()
        );
        $this->assertNotEquals(99.99, $quote->pdfHst());
    }

    public function test_new_material_line_uses_category_order_not_the_top(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Building permit', 'amount' => 200.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertSame(
            ['Building permit', 'Material', 'Labour'],
            array_column($quote->pdfLineItems(), 'description')
        );
        foreach ($quote->pdfLineItems() as $row) {
            $this->assertFalse((bool) ($row['is_standalone'] ?? false), $row['description']);
        }
    }

    public function test_single_job_permit_plus_extra_does_not_draw_standalone_rule(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Building permit', 'amount' => 300.00, 'apply_hst' => false],
                ['description' => 'Labour', 'amount' => 1000.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertSame(
            ['Building permit', 'Material', 'Labour'],
            array_column($quote->pdfLineItems(), 'description')
        );
        foreach ($quote->pdfLineItems() as $row) {
            $this->assertFalse((bool) ($row['is_standalone'] ?? false), $row['description']);
        }
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_no_headings_and_second_labour_row_are_not_standalone(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_MILLWORK,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Custom millwork materials', 'amount' => 1000.00, 'apply_hst' => true],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => false],
                [
                    'description' => 'Commute expense',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $labours = array_values(array_filter(
            $quote->pdfLineItems(),
            static fn (array $row): bool => ($row['description'] ?? '') === 'Labour'
        ));
        $this->assertGreaterThanOrEqual(2, count($labours));
        foreach ($quote->pdfLineItems() as $row) {
            $this->assertFalse((bool) ($row['is_standalone'] ?? false), $row['description']);
        }
    }

    public function test_d1_merges_into_existing_taxed_material(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => false],
                ['description' => 'Material', 'amount' => 40.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $materials = array_values(array_filter(
            $quote->pdfLineItems(),
            static fn (array $row): bool => ($row['description'] ?? '') === 'Material'
        ));
        $this->assertCount(2, $materials);
        $this->assertEqualsWithDelta(1000.00, $materials[0]['amount'], 0.001);
        $this->assertEqualsWithDelta(140.00, $materials[1]['amount'], 0.001);
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_quote_level_extra_sits_after_the_jobs(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Job one', 'amount' => 0, 'apply_hst' => false, 'is_heading' => true],
                ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => false],
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                ['description' => 'Job two', 'amount' => 0, 'apply_hst' => false, 'is_heading' => true],
                ['description' => 'Labour', 'amount' => 200.00, 'apply_hst' => true],
                [
                    'description' => 'Additional cost — Dumpster',
                    'amount' => 113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertSame(
            ['Job one', 'Material', 'Labour', 'Job two', 'Material', 'Labour'],
            array_column($quote->pdfLineItems(), 'description')
        );
        $lines = $quote->pdfLineItems();
        $this->assertFalse((bool) ($lines[4]['is_standalone'] ?? false));
        foreach ($lines as $row) {
            $this->assertFalse((bool) ($row['is_standalone'] ?? false), $row['description']);
        }
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
    }

    public function test_negative_additional_cost_folds_so_pdf_matches_editor(): void
    {
        $quote = new ClientProjectQuotation([
            'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
            'pdf_fold_version' => 3,
            'items' => [
                ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
                [
                    'description' => 'Additional labour — Discount',
                    'amount' => -113.00,
                    'apply_hst' => false,
                    'hst_included' => true,
                ],
            ],
        ]);

        $this->assertEquals(-13.00, $quote->editorHst() - 65.00);
        $this->assertEquals($quote->editorHst(), $quote->pdfHst());
        $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal());
        $this->assertEquals(52.00, $quote->pdfHst());
        $this->assertEquals(452.00, $quote->pdfGrandTotal());
    }

    public function test_v3_corpus_pdf_matches_editor_including_millwork(): void
    {
        $dir = base_path('tests/fixtures/pdf-fold-v1');
        $checked = 0;
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
                $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $label.' total');
                if (! $quote->hasSubtotalOverrideActive()) {
                    $this->assertEquals(
                        round($quote->pdfSubtotal() + $quote->pdfHst(), 2),
                        $quote->pdfGrandTotal(),
                        $label.' footer'
                    );
                }
                $checked++;
            }
        }

        foreach ([
            ClientProjectQuotation::SOURCE_MILLWORK,
            ClientProjectQuotation::SOURCE_TILE,
            ClientProjectQuotation::SOURCE_STAIRS,
            ClientProjectQuotation::SOURCE_PAINTING,
            ClientProjectQuotation::SOURCE_SHED,
            ClientProjectQuotation::SOURCE_TRIMMING,
        ] as $source) {
            foreach ([100.00, 1000.00, 4388.00] as $labour) {
                foreach ([0.00, 113.00, 339.00] as $commute) {
                    $items = [
                        ['description' => 'Custom millwork (labour)', 'amount' => $labour, 'apply_hst' => true],
                        ['description' => 'Custom millwork materials', 'amount' => 400.00, 'apply_hst' => true],
                    ];
                    if ($source !== ClientProjectQuotation::SOURCE_MILLWORK) {
                        $items[0]['description'] = 'Installation (labour)';
                        $items[1]['description'] = 'Materials';
                    }
                    if ($commute > 0) {
                        $items[] = [
                            'description' => 'Commute expense',
                            'amount' => $commute,
                            'apply_hst' => false,
                            'hst_included' => true,
                        ];
                    }
                    $quote = new ClientProjectQuotation([
                        'source_type' => $source,
                        'pdf_fold_version' => 3,
                        'items' => $items,
                    ]);
                    $this->assertEquals($quote->editorHst(), $quote->pdfHst(), $source.' '.$labour.' '.$commute);
                    $this->assertEquals($quote->editorGrandTotal(), $quote->pdfGrandTotal(), $source.' total');
                    $checked++;
                }
            }
        }

        $this->assertGreaterThan(12000, $checked);
    }
}
