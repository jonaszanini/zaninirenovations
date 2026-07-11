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

    public function test_multi_stone_entries_sum_area_for_base_materials(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate([
            'stone_entries' => [
                [
                    'stone_type_id' => 1,
                    'stone_name' => 'Type A',
                    'input_mode' => 'sqft',
                    'area_sq_ft' => 100,
                    'sq_ft_per_stone' => 0.5,
                    'price_per_stone' => 2,
                    'height_in' => 2,
                    'length_in' => 12,
                    'width_in' => 12,
                ],
                [
                    'stone_type_id' => 2,
                    'stone_name' => 'Type B',
                    'input_mode' => 'dimensions',
                    'length_ft' => 10,
                    'width_ft' => 8,
                    'area_sq_ft' => 80,
                    'sq_ft_per_stone' => 0.5,
                    'price_per_stone' => 3,
                    'height_in' => 3,
                    'length_in' => 6,
                    'width_in' => 9,
                ],
            ],
            'excavation_depth_in' => 12,
            'bedding_sand_depth_in' => 1,
            'add_polymeric_sand' => false,
            'include_geotextile' => false,
            'waste_factor' => 0,
            'hst_rate' => 0.13,
        ]);

        $this->assertSame(180.0, (float) ($result['areas']['total_sq_ft'] ?? 0));

        $rows = collect($result['materials']['rows'])->keyBy('label');
        $stoneA = $rows->get('Type A paving stones');
        $stoneB = $rows->get('Type B paving stones');
        $this->assertNotNull($stoneA);
        $this->assertNotNull($stoneB);

        $crushed = $rows->get('Crushed stone base (3/4 in.)');
        $this->assertNotNull($crushed);
        $this->assertStringContainsString('3 in. paver', $crushed['note']);
    }

    public function test_qty_mode_resolves_area_from_stone_count(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate([
            'stone_entries' => [
                [
                    'stone_type_id' => 1,
                    'stone_name' => 'Pavers',
                    'input_mode' => 'qty',
                    'qty' => 200,
                    'area_sq_ft' => 100,
                    'sq_ft_per_stone' => 0.5,
                    'price_per_stone' => 1.5,
                    'height_in' => 2,
                    'length_in' => 6,
                    'width_in' => 9,
                ],
            ],
            'excavation_depth_in' => 11,
            'bedding_sand_depth_in' => 1,
            'add_polymeric_sand' => false,
            'include_geotextile' => false,
            'waste_factor' => 0,
            'hst_rate' => 0.13,
        ]);

        $this->assertSame(100.0, (float) ($result['areas']['total_sq_ft'] ?? 0));
        $rows = collect($result['materials']['rows'])->keyBy('label');
        $stone = $rows->get('Pavers paving stones');
        $this->assertNotNull($stone);
        $this->assertSame(200, (int) $stone['qty']);
    }

    /**
     * @param  array<string, mixed>  $marbleOverrides
     * @return array<string, mixed>
     */
    private function marbleInputs(array $marbleOverrides = []): array
    {
        return array_merge([
            'marble_enabled' => true,
            'marble_area_sq_ft' => 50,
            'marble_depth_in' => 2,
            'marble_include_geotextile' => true,
            'marble_name' => 'Test marble',
            'marble_reference_depth_in' => 2,
            'marble_sq_ft_per_bag_at_reference_depth' => 9,
            'marble_price_per_bag' => 9.98,
            'marble_bulk_min_bags' => 70,
            'marble_bulk_discount_pct' => 10,
        ], $marbleOverrides);
    }

    public function test_marble_disabled_adds_no_marble_section(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate($this->minimalInputs(['marble_enabled' => false]));

        $this->assertNull($result['marble'] ?? null);
    }

    public function test_marble_enabled_does_not_increase_main_stone_area(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $base = $this->minimalInputs();
        $withMarble = $calc->calculate(array_merge($base, $this->marbleInputs()));
        $withoutMarble = $calc->calculate($base);

        $this->assertSame(
            (float) ($withoutMarble['areas']['total_sq_ft'] ?? 0),
            (float) ($withMarble['areas']['total_sq_ft'] ?? 0)
        );
        $this->assertNotNull($withMarble['marble']);
        $this->assertSame(50.0, (float) ($withMarble['areas']['marble_area_sq_ft'] ?? 0));
    }

    public function test_marble_geotextile_off_skips_geo_material_rows_qty(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate(array_merge(
            $this->minimalInputs(),
            $this->marbleInputs(['marble_include_geotextile' => false])
        ));

        $geoRow = collect($result['materials']['rows'])->firstWhere('label', 'Marble bed geotextile fabric');
        $this->assertNotNull($geoRow);
        $this->assertSame(0, (int) $geoRow['qty']);
    }

    public function test_marble_bulk_discount_applied_in_calculation(): void
    {
        $calc = new PavingQuotationCalculator(collect([]), collect([]));
        $result = $calc->calculate(array_merge(
            $this->minimalInputs(['waste_factor' => 0]),
            $this->marbleInputs([
                'marble_area_sq_ft' => 700,
                'marble_sq_ft_per_bag_at_reference_depth' => 10,
                'marble_price_per_bag' => 10,
                'marble_bulk_min_bags' => 70,
                'marble_bulk_discount_pct' => 10,
            ])
        ));

        $bagRow = collect($result['materials']['rows'])->first(fn ($row) => str_contains($row['label'] ?? '', '(bags)'));
        $this->assertNotNull($bagRow);
        $this->assertGreaterThanOrEqual(70, (int) $bagRow['qty']);
        $this->assertSame(9.0, (float) $bagRow['unit_price']);
    }
}
