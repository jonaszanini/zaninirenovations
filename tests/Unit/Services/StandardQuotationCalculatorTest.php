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
}
