<?php

namespace Tests\Unit\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use App\Services\FenceQuotationCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FenceQuotationCalculatorTest extends TestCase
{
    private function calculatorWithCatalogue(Collection $catalogue): FenceQuotationCalculator
    {
        $settings = new FenceFormulaSetting([
            'gate_rails' => 5,
            'concrete_per_post_6ft' => 1.78,
            'concrete_per_post_8ft' => 2.5,
            'labour_rate_per_ft' => 55.00,
            'labour_rate_8ft_per_ft' => 60.00,
            'labour_per_additional_gate' => 150.00,
            'rails_per_section_6ft' => 2,
            'rails_per_section_8ft' => 3,
            'nails_per_picket' => 6,
            'board_width_in' => 5.5,
            'standard_gap_in' => 0.125,
            'max_post_spacing_ft' => 8.0,
        ]);

        return new FenceQuotationCalculator($settings, $catalogue);
    }

    private function basicCatalogue(): Collection
    {
        $make = function (string $key, float $price, ?int $qtyPerBox = null): FenceMaterial {
            $row = new FenceMaterial([
                'key' => $key,
                'name' => $key,
                'price_cad' => $price,
                'quantity_per_box' => $qtyPerBox,
            ]);

            return $row;
        };

        return collect([
            $make(FenceMaterial::KEY_POST_10, 40),
            $make(FenceMaterial::KEY_POST_12, 45),
            $make(FenceMaterial::KEY_BOARD_6, 8),
            $make(FenceMaterial::KEY_BOARD_8, 10),
            $make(FenceMaterial::KEY_RAIL, 7),
            $make(FenceMaterial::KEY_CONCRETE, 12),
            $make(FenceMaterial::KEY_NAILS, 100, 1000),
            $make(FenceMaterial::KEY_BRACKET, 3),
            $make(FenceMaterial::KEY_POST_CAP, 5),
            $make(FenceMaterial::KEY_GATE_HARDWARE, 25),
        ])->keyBy('key');
    }

    public function test_calculate_returns_expected_shape(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
        ]);

        $this->assertArrayHasKey('inputs', $result);
        $this->assertArrayHasKey('geometry', $result);
        $this->assertArrayHasKey('materials', $result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertGreaterThan(0, $result['geometry']['total_linear_ft']);
        $this->assertSame(FenceQuotation::HST_RATE, $result['totals']['hst_rate']);
    }

    public function test_nails_row_uses_box_quantity_from_catalogue(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $nails = collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_NAILS);

        $this->assertNotNull($nails);
        $this->assertSame('box', $nails['unit']);
        $this->assertSame(1000, $nails['quantity_per_box']);
        $this->assertGreaterThan(0, $nails['quantity']);
    }

    public function test_invalid_fence_height_defaults_to_six_feet(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 10]],
            'fence_height_ft' => 7,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(6.0, $result['inputs']['fence_height_ft']);
    }
}
