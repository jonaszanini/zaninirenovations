<?php

namespace Tests\Unit\Services;

use App\Services\StandardQuotationCalculator;
use Tests\TestCase;

class StandardQuotationCalculatorTest extends TestCase
{
    public function test_delivery_is_added_once_not_multiplied_by_quantity(): void
    {
        $row = StandardQuotationCalculator::breakdownMaterialRow([
            'short_name' => 'Debris bin',
            'unit_price' => 400,
            'quantity' => 2,
            'delivery_cad' => 80,
            'apply_hst' => true,
        ]);

        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(880.0, $row['line_subtotal'], 0.01);
        $this->assertEqualsWithDelta(114.4, $row['line_hst'], 0.01);
        $this->assertEqualsWithDelta(994.4, $row['line_total_with_hst'], 0.01);
    }

    public function test_compute_rolls_delivery_into_materials_subtotal(): void
    {
        $computed = (new StandardQuotationCalculator)->compute(
            [
                [
                    'short_name' => 'Debris bin',
                    'description' => 'Dumpster',
                    'unit_price' => 400,
                    'quantity' => 1,
                    'delivery_cad' => 80,
                    'apply_hst' => true,
                ],
            ],
            0,
            'hours',
            0,
            false,
            0
        );

        $this->assertEqualsWithDelta(480.0, $computed['materials_subtotal_before_hst'], 0.01);
        $this->assertEqualsWithDelta(80.0, $computed['materials'][0]['delivery_cad'], 0.01);
    }

    public function test_compute_preserves_electrical_line_kind(): void
    {
        $computed = (new StandardQuotationCalculator)->compute(
            [
                [
                    'entry_mode' => 'electrical',
                    'description' => 'Electrical work (Including material)',
                    'unit_price' => 650,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            0,
            'hours',
            0,
            false,
            0
        );

        $this->assertSame('electrical', $computed['materials'][0]['line_kind']);
        $this->assertSame('electrical', $computed['materials'][0]['entry_mode']);
        $this->assertEqualsWithDelta(650.0, $computed['materials_subtotal_before_hst'], 0.01);
    }

    public function test_compute_preserves_manual_image_url(): void
    {
        $computed = (new StandardQuotationCalculator)->compute(
            [
                [
                    'entry_mode' => 'manual',
                    'short_name' => 'Paint',
                    'description' => 'Interior paint',
                    'url' => 'https://supplier.com/product',
                    'image_url' => 'https://supplier.com/image.jpg',
                    'unit_price' => 20,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            0,
            'hours',
            0,
            false,
            0
        );

        $this->assertSame('https://supplier.com/image.jpg', $computed['materials'][0]['image_url']);
        $this->assertSame('https://supplier.com/product', $computed['materials'][0]['url']);
    }

    public function test_compute_works_sums_jobs_and_applies_quote_level_contingency(): void
    {
        $calc = new StandardQuotationCalculator();
        $job1Rows = [[
            'short_name' => 'Vanity',
            'description' => '',
            'unit_price' => 200,
            'quantity' => 1,
            'apply_hst' => true,
        ]];
        $job2Rows = [[
            'short_name' => 'Towel bar',
            'description' => '',
            'unit_price' => 40,
            'quantity' => 1,
            'apply_hst' => true,
        ]];

        $job1 = $calc->compute($job1Rows, 65, 'hours', 2, false, 0);
        $job2 = $calc->compute($job2Rows, 0, 'hours', 0, false, 0, true, 80, false);

        $computed = $calc->computeWorks([
            [
                'title' => 'Replace vanity',
                'materials' => $job1Rows,
                'labour_hourly_rate' => 65,
                'labour_mode' => 'hours',
                'labour_quantity' => 2,
                'labour_use_fixed' => false,
            ],
            [
                'title' => 'Install towel bar',
                'materials' => $job2Rows,
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 80,
                'labour_fixed_hst_included' => false,
            ],
        ], true, 10);

        $this->assertCount(2, $computed['works']);
        $this->assertSame('Replace vanity', $computed['works'][0]['title']);
        $this->assertEqualsWithDelta(240.0, $computed['materials_subtotal_before_hst'], 0.01);
        $this->assertEqualsWithDelta(24.0, $computed['contingency_amount'], 0.01);
        $this->assertEqualsWithDelta(
            round($job1['labour_total'] + $job2['labour_total'], 2),
            $computed['labour_total'],
            0.01
        );
        $this->assertEqualsWithDelta(
            round($job1['labour_hst'] + $job2['labour_hst'], 2),
            $computed['labour_hst'],
            0.01
        );
        $this->assertEqualsWithDelta(
            round($computed['materials_with_hst'] + $computed['labour_total'] + $computed['labour_hst'], 2),
            $computed['grand_total'],
            0.01
        );
    }

    public function test_compute_works_can_omit_labour_hst(): void
    {
        $works = [[
            'title' => 'Install',
            'materials' => [[
                'short_name' => 'Board',
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
        ]];

        $calc = new StandardQuotationCalculator();
        $withHst = $calc->computeWorks($works, false, 0, true);
        $cash = $calc->computeWorks($works, false, 0, false);

        $this->assertEqualsWithDelta(26.0, $withHst['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(0.0, $cash['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(200.0, $cash['labour_total'], 0.01);
        $this->assertEqualsWithDelta(
            $withHst['grand_total'] - 26.0,
            $cash['grand_total'],
            0.01
        );
        $this->assertEqualsWithDelta(200.0, $cash['pre_hst_exempt'], 0.01);
        $this->assertEqualsWithDelta(100.0, $cash['pre_hst_taxable'], 0.01);
    }

    public function test_blank_default_material_row_is_ignored_even_with_qty(): void
    {
        $this->assertNull(StandardQuotationCalculator::breakdownMaterialRow([
            'short_name' => '',
            'description' => '',
            'unit_price' => 0,
            'quantity' => 1,
            'delivery_cad' => 0,
        ]));

        $computed = (new StandardQuotationCalculator)->compute(
            [[
                'short_name' => '',
                'description' => '',
                'unit_price' => 0,
                'quantity' => 1,
                'apply_hst' => true,
            ]],
            0,
            'hours',
            0,
            false,
            0
        );

        $this->assertSame([], $computed['materials']);
        $this->assertEqualsWithDelta(0.0, $computed['materials_subtotal_before_hst'], 0.01);
    }

    public function test_cash_labour_ignores_flat_fee_hst_included(): void
    {
        $works = [[
            'title' => 'Install',
            'materials' => [],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 1130,
            'labour_fixed_hst_included' => true,
            'labour_hourly_rate' => 0,
            'labour_mode' => 'hours',
            'labour_quantity' => 0,
        ]];

        $calc = new StandardQuotationCalculator();
        $withHst = $calc->computeWorks($works, false, 0, true);
        $cash = $calc->computeWorks($works, false, 0, false);

        $this->assertEqualsWithDelta(1000.0, $withHst['labour_total'], 0.01);
        $this->assertEqualsWithDelta(130.0, $withHst['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(1130.0, $cash['labour_total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $cash['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(1130.0, $cash['grand_total'], 0.01);
    }

    public function test_contingency_excludes_electrical_lump_sums(): void
    {
        $computed = (new StandardQuotationCalculator)->compute(
            [
                [
                    'short_name' => 'Lumber',
                    'unit_price' => 85.62,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
                [
                    'line_kind' => 'electrical',
                    'entry_mode' => 'electrical',
                    'description' => 'Electrical work (Including material)',
                    'unit_price' => 700,
                    'quantity' => 1,
                    'apply_hst' => true,
                ],
            ],
            0,
            'hours',
            0,
            true,
            20
        );

        $this->assertEqualsWithDelta(17.12, $computed['contingency_amount'], 0.01);
        $this->assertEqualsWithDelta(785.62, $computed['materials_subtotal_before_hst'], 0.01);
    }

    public function test_compute_hourly_respects_include_labour_hst_without_a_later_zero(): void
    {
        $calc = new StandardQuotationCalculator();
        $withHst = $calc->compute([], 50, 'hours', 2, false, 0, false, 0.0, false, true);
        $cash = $calc->compute([], 50, 'hours', 2, false, 0, false, 0.0, false, false);

        $this->assertEqualsWithDelta(100.0, $withHst['labour_total'], 0.01);
        $this->assertEqualsWithDelta(13.0, $withHst['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(113.0, $withHst['grand_total'], 0.01);
        $this->assertEqualsWithDelta(100.0, $withHst['pre_hst_taxable'], 0.01);

        $this->assertEqualsWithDelta(100.0, $cash['labour_total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $cash['labour_hst'], 0.01);
        $this->assertEqualsWithDelta(100.0, $cash['grand_total'], 0.01);
        $this->assertEqualsWithDelta(0.0, $cash['pre_hst_taxable'], 0.01);
        $this->assertEqualsWithDelta(100.0, $cash['pre_hst_exempt'], 0.01);
    }

    public function test_compute_works_hourly_cash_totals_match_compute(): void
    {
        $works = [[
            'title' => 'Install',
            'materials' => [],
            'labour_use_fixed' => false,
            'labour_hourly_rate' => 50,
            'labour_mode' => 'hours',
            'labour_quantity' => 2,
        ]];

        $calc = new StandardQuotationCalculator();
        $direct = $calc->compute([], 50, 'hours', 2, false, 0, false, 0.0, false, false);
        $worksCash = $calc->computeWorks($works, false, 0, false);

        $this->assertEqualsWithDelta($direct['labour_hst'], $worksCash['labour_hst'], 0.01);
        $this->assertEqualsWithDelta($direct['grand_total'], $worksCash['grand_total'], 0.01);
        $this->assertEqualsWithDelta($direct['pre_hst_exempt'], $worksCash['pre_hst_exempt'], 0.01);
    }

    public function test_compute_keeps_saved_catalog_delivery_instead_of_live_overlay(): void
    {
        $computed = (new StandardQuotationCalculator)->compute(
            [[
                'short_name' => 'Bin',
                'catalog_material_id' => 999999,
                'unit_price' => 400,
                'quantity' => 1,
                'delivery_cad' => 25,
                'apply_hst' => true,
            ]],
            0,
            'hours',
            0,
            false,
            0
        );

        $this->assertEqualsWithDelta(25.0, $computed['materials'][0]['delivery_cad'], 0.01);
        $this->assertEqualsWithDelta(425.0, $computed['materials_subtotal_before_hst'], 0.01);
    }

    public function test_surcharge_remainder_stays_on_last_job_unless_gated(): void
    {
        $works = [
            [
                'title' => 'Job one',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
                'labour_fixed_hst_included' => false,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
                'labour_fixed_hst_included' => false,
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

        $calc = new StandardQuotationCalculator();
        $legacy = $calc->computeWorks($works, false, 0, true, true, 30, false);
        $current = $calc->computeWorks($works, false, 0, true, true, 30, true);

        $this->assertEqualsWithDelta(-0.01, $legacy['works'][2]['labour_total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $current['works'][2]['labour_total'], 0.001);
        $this->assertEqualsWithDelta(0.0, $current['works'][2]['building_labor_surcharge'], 0.001);
        $this->assertGreaterThan(0, $current['works'][0]['labour_total']);
        $this->assertGreaterThan(0, $current['works'][1]['labour_total']);
        $this->assertEqualsWithDelta(
            $current['labour_total'],
            round($current['works'][0]['labour_total'] + $current['works'][1]['labour_total'], 2),
            0.001
        );
    }

    public function test_v3_standard_quote_gates_surcharge_remainder_onto_labour(): void
    {
        $sq = new \App\Models\StandardQuotation([
            'pdf_fold_version' => 3,
            'include_building' => true,
            'building_labor_percent' => 30,
            'include_labour_hst' => true,
            'contingency_enabled' => false,
            'works' => [
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
            ],
        ]);

        $computed = $sq->computedTotals();
        $this->assertEqualsWithDelta(0.0, $computed['works'][2]['labour_total'], 0.001);
        $lines = $sq->detailedPdfLineItems();
        $labourAmounts = [];
        foreach ($lines as $row) {
            if (($row['description'] ?? '') === 'Labour') {
                $labourAmounts[] = (float) $row['amount'];
            }
        }
        $this->assertNotContains(0.01, $labourAmounts);
        foreach ($labourAmounts as $amount) {
            $this->assertGreaterThan(0.01, $amount);
        }
    }
}
