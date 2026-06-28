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

    public function test_thirty_three_point_five_feet_yields_five_sections_and_six_posts(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 33.5]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(5, $result['geometry']['sections_total']);
        $this->assertSame(6, $result['geometry']['structural_posts']);
        $this->assertSame(6, $result['geometry']['posts_before_waste']);
    }

    public function test_existing_boundary_posts_reduce_post_count(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 17.83,
                'already_have_post_left' => true,
                'already_have_post_right' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(3, $result['geometry']['sections_total']);
        $this->assertSame(2, $result['geometry']['structural_posts']);

        $side = $result['geometry']['sides_breakdown'][0];
        $this->assertSame(3, $side['sections']);
        $this->assertSame(2, $side['posts']);
    }

    public function test_gate_on_side_reduces_structural_posts_and_sections(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $withoutGate = $calculator->calculate([
            'sides' => [['length_ft' => 33.5]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $withGate = $calculator->calculate([
            'sides' => [[
                'length_ft' => 33.5,
                'gate_goes_here' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(5, $withoutGate['geometry']['sections_total']);
        $this->assertSame(6, $withoutGate['geometry']['structural_posts']);

        $this->assertSame(4, $withGate['geometry']['sections_total']);
        $this->assertSame(6, $withGate['geometry']['structural_posts']);
        $this->assertSame(0, $withGate['geometry']['gate_posts']);
        $this->assertSame(6, $withGate['geometry']['posts_before_waste']);
        $this->assertSame(1, $withGate['geometry']['gates_on_sides']);
        $this->assertTrue($withGate['geometry']['sides_breakdown'][0]['gate_goes_here']);
        $this->assertSame(29.5, $withGate['geometry']['sides_breakdown'][0]['fence_length_ft']);
        $this->assertSame(29.5, $withGate['geometry']['picket_linear_ft']);
    }

    public function test_gate_side_uses_remaining_length_for_sections(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_left' => true,
                'gate_goes_here' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];
        $this->assertSame(4.0, $side['fence_length_ft']);
        $this->assertSame(1, $side['sections']);
        $this->assertSame(2, $side['posts']);
        $this->assertCount(1, $side['section_lengths_ft']);
        $this->assertEqualsWithDelta(3.708, $side['section_lengths_ft'][0], 0.01);
    }

    public function test_gate_side_assignment_respects_gate_count_limit(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [
                ['length_ft' => 16, 'gate_goes_here' => true],
                ['length_ft' => 16, 'gate_goes_here' => true],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(1, $result['geometry']['gates_on_sides']);
        $this->assertTrue($result['geometry']['sides_breakdown'][0]['gate_goes_here']);
        $this->assertFalse($result['geometry']['sides_breakdown'][1]['gate_goes_here']);
    }
}
