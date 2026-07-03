<?php

namespace Tests\Unit\Services;

use App\Services\PavingQuotationCalculator;
use Tests\TestCase;

class PavingQuotationCalculatorTest extends TestCase
{
    public function test_crushed_base_depth_is_excavation_minus_paver_height_minus_sand(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate([
            'total_area_sq_ft' => 100,
            'stone_sq_ft_per_stone' => 0.5,
            'stone_price_per_stone' => 1,
            'excavation_depth_in' => 8,
            'stone_height_in' => 2,
            'bedding_sand_depth_in' => 1,
            'stone_length_in' => 12,
            'stone_width_in' => 12,
            'add_polymeric_sand' => false,
            'include_geotextile' => false,
            'waste_factor' => 0,
            'hst_rate' => 0.13,
        ]);

        $this->assertSame(5.0, (float) ($result['inputs']['crushed_base_depth_in'] ?? 0));
    }

    public function test_crushed_base_and_bedding_quantities_include_volume_and_weight(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate([
            'total_area_sq_ft' => 588,
            'stone_sq_ft_per_stone' => 0.5,
            'stone_price_per_stone' => 1,
            'excavation_depth_in' => 8,
            'stone_height_in' => 2,
            'bedding_sand_depth_in' => 1,
            'stone_length_in' => 12,
            'stone_width_in' => 12,
            'add_polymeric_sand' => false,
            'include_geotextile' => false,
            'waste_factor' => 0,
            'hst_rate' => 0.13,
        ]);

        $rows = collect($result['materials']['rows'])->keyBy('label');

        $stone = $rows->get('Crushed stone base (3/4 in.)');
        $this->assertNotNull($stone);
        $this->assertSame(11.0, (float) $stone['qty']);
        $this->assertSame(15.5, (float) $stone['qty_tons']);
        $this->assertStringContainsString('8 in. excavation − 2 in. paver − 1 in. sand', $stone['note']);

        $sand = $rows->get('Bedding sand');
        $this->assertNotNull($sand);
        $this->assertSame(2.0, (float) $sand['qty']);
        $this->assertSame(3.0, (float) $sand['qty_tons']);
    }

    public function test_polymeric_sand_uses_paver_face_coverage_tiers(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));

        $small = $calc->calculate($this->minimalInputs([
            'total_area_sq_ft' => 140,
            'stone_length_in' => 4,
            'stone_width_in' => 8,
            'add_polymeric_sand' => true,
        ]));

        $medium = $calc->calculate($this->minimalInputs([
            'total_area_sq_ft' => 170,
            'stone_length_in' => 6,
            'stone_width_in' => 9,
            'add_polymeric_sand' => true,
        ]));

        $large = $calc->calculate($this->minimalInputs([
            'total_area_sq_ft' => 220,
            'stone_length_in' => 12,
            'stone_width_in' => 12,
            'add_polymeric_sand' => true,
        ]));

        $this->assertSame(2, $this->qtyForLabel($small, 'Polymeric sand'));
        $this->assertSame(2, $this->qtyForLabel($medium, 'Polymeric sand'));
        $this->assertSame(2, $this->qtyForLabel($large, 'Polymeric sand'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalInputs(array $overrides = []): array
    {
        return array_merge([
            'total_area_sq_ft' => 100,
            'stone_sq_ft_per_stone' => 0.5,
            'stone_price_per_stone' => 1,
            'excavation_depth_in' => 11,
            'stone_height_in' => 2,
            'bedding_sand_depth_in' => 1,
            'stone_length_in' => 6,
            'stone_width_in' => 9,
            'add_polymeric_sand' => false,
            'include_geotextile' => false,
            'waste_factor' => 0,
            'hst_rate' => 0.13,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $calc
     */
    private function qtyForLabel(array $calc, string $label): int
    {
        foreach ($calc['materials']['rows'] as $row) {
            if (($row['label'] ?? '') === $label) {
                return (int) ($row['qty'] ?? 0);
            }
        }

        $this->fail("Missing material row [{$label}]");

        return 0;
    }
}
