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
}
