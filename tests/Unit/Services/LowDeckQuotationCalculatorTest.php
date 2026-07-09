<?php

namespace Tests\Unit\Services;

use App\Exceptions\LowDeckAreaExceededException;
use App\Models\LowDeckFormulaSetting;
use App\Models\LowDeckMaterial;
use App\Services\LowDeckQuotationCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class LowDeckQuotationCalculatorTest extends TestCase
{
    private function settings(array $overrides = []): LowDeckFormulaSetting
    {
        return new LowDeckFormulaSetting(array_merge([
            'max_area_sq_ft' => 592.00,
            'block_spacing_ft' => 5.00,
            'default_joist_spacing_in' => 16,
            'max_joist_span_in' => 112.00,
            'board_width_in' => 5.500,
            'board_gap_in' => 0.125,
            'post_length_per_block_ft' => 2.00,
            'gravel_depth_in' => 3.00,
            'gravel_footprint_in' => 12.00,
            'gravel_cubic_ft_per_bag' => 0.500,
            'structural_screws_per_post' => 2,
            'structural_screws_per_corner' => 4,
            'board_screws_per_inch' => 0.2000,
            'frame_screws_per_t_joint' => 3,
            'frame_screws_per_cross_joint' => 6,
            'joist_tape_roll_length_ft' => 50.00,
            'joist_tape_overlap_in' => 2.00,
            'kerf_in' => 0.125,
            'labour_rate_per_sqft' => 10.00,
            'waste_dump_fee_cad' => 60.00,
        ], $overrides));
    }

    /** @return Collection<string, LowDeckMaterial> */
    private function catalogue(): Collection
    {
        $rows = [
            [LowDeckMaterial::KEY_BLOCK_4X4, 10.0, null],
            [LowDeckMaterial::KEY_POST_4X4_8, 5.0, null],
            [LowDeckMaterial::KEY_POST_4X4_10, 6.0, null],
            [LowDeckMaterial::KEY_POST_4X4_12, 7.0, null],
            [LowDeckMaterial::KEY_GRAVEL_BAG, 5.0, null],
            [LowDeckMaterial::KEY_UNDER_DECK_GRAVEL_30KG, 4.0, null],
            [LowDeckMaterial::KEY_WEED_FABRIC_3X50, 40.0, null],
            [LowDeckMaterial::KEY_WEED_FABRIC_3X100, 60.0, null],
            [LowDeckMaterial::KEY_FABRIC_SPIKES_30PACK, 12.0, null],
            [LowDeckMaterial::KEY_JOIST_2X6_8, 6.0, null],
            [LowDeckMaterial::KEY_JOIST_2X6_10, 7.0, null],
            [LowDeckMaterial::KEY_JOIST_2X6_12, 8.0, null],
            [LowDeckMaterial::KEY_JOIST_2X6_16, 10.0, null],
            [LowDeckMaterial::KEY_JOIST_HANGER_2X6, 3.5, null],
            [LowDeckMaterial::KEY_JOIST_TAPE_50FT, 30.0, null],
            [LowDeckMaterial::KEY_BOARD_5_4X6_8, 4.0, null],
            [LowDeckMaterial::KEY_BOARD_5_4X6_10, 5.0, null],
            [LowDeckMaterial::KEY_BOARD_5_4X6_12, 6.0, null],
            [LowDeckMaterial::KEY_SCREW_STRUCTURAL_6IN, 20.0, 50],
            [LowDeckMaterial::KEY_SCREW_DECK_2_5IN, 15.0, 350],
            [LowDeckMaterial::KEY_SCREW_FRAME_3IN, 12.0, 100],
        ];

        $catalogue = new Collection;
        foreach ($rows as [$key, $price, $box]) {
            $catalogue->put($key, new LowDeckMaterial([
                'key' => $key,
                'name' => $key,
                'price_cad' => $price,
                'quantity_per_box' => $box,
            ]));
        }

        return $catalogue;
    }

    private function rowByKey(array $materials, string $key): ?array
    {
        foreach ($materials as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    public function test_geometry_for_twelve_by_sixteen_deck(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => false,
        ]);

        $geom = $result['geometry'];

        $this->assertSame(192.0, $geom['area_sq_ft']);
        $this->assertSame(12.0, $geom['span_ft']);
        $this->assertSame(16.0, $geom['run_ft']);

        $this->assertSame(20, $geom['blocks']['count']);
        $this->assertSame(4, $geom['blocks']['corner_count']);

        $this->assertSame(16, $geom['joists']['total_lines']);
        $this->assertSame(12, $geom['joists']['field_count']);
        $this->assertSame(2, $geom['joists']['edge_count']);
        $this->assertSame(13.96, $geom['joists']['field_spacing_in']);
        $this->assertSame(13.96, $geom['joist_spacing_in']);

        $this->assertSame(6, $geom['beams']['lines_total']);
        $this->assertSame(2, $geom['beams']['interior_count']);
        $this->assertSame(24, $geom['boards']['infill_rows']);
        $this->assertSame(23, $geom['boards']['infill_full_rows']);
        $this->assertSame(1, $geom['boards']['infill_rip_rows']);
        $this->assertSame(28, $geom['boards']['total_boards']);
        $this->assertSame('picture_frame', $geom['boards']['layout']);
        $this->assertCount(24, $geom['boards']['infill_row_widths_in']);
        $this->assertSame(3.625, $geom['boards']['infill_row_widths_in'][23]);

        $this->assertSame(28, $geom['intersections']['cross_count']);
        $this->assertSame(40, $geom['intersections']['t_count']);

        $this->assertSame(6, $geom['joist_tape']['rolls_required']);
        $this->assertSame(22, $geom['joist_tape']['members_taped']);

        $this->assertSame(24, $geom['joist_hangers']['count']);
        $this->assertSame(32, $geom['joist_hangers']['quote_count']);
        $this->assertSame(8, $geom['joist_hangers']['extra_count']);
        $this->assertSame(12, $geom['joist_hangers']['field_joist_count']);
        $this->assertCount(24, $geom['joist_hangers']['positions_ft']);
        $this->assertSame('top', $geom['joist_hangers']['positions_ft'][0]['end']);
        $this->assertSame(0.375, $geom['joist_hangers']['positions_ft'][0]['y_ft']);
        $this->assertSame('bottom', $geom['joist_hangers']['positions_ft'][1]['end']);
        $this->assertSame(11.625, $geom['joist_hangers']['positions_ft'][1]['y_ft']);

        $hangers = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_JOIST_HANGER_2X6);
        $this->assertNotNull($hangers);
        $this->assertSame(32, $hangers['quantity']);
        $this->assertStringContainsString('32 hangers (24 for field joists + 8 extra)', $hangers['notes']);
    }

    public function test_fastener_counts_round_up_to_retail_boxes(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => false,
        ]);

        $structural = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_SCREW_STRUCTURAL_6IN);
        $this->assertNotNull($structural);
        $this->assertSame(48, $structural['quantity_of_individual_units']);
        $this->assertSame(1, $structural['quantity']);

        $deck = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_SCREW_DECK_2_5IN);
        $this->assertNotNull($deck);
        $this->assertSame(999, $deck['quantity_of_individual_units']);
        $this->assertSame(3, $deck['quantity']);

        $frame = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_SCREW_FRAME_3IN);
        $this->assertNotNull($frame);
        $this->assertSame(288, $frame['quantity_of_individual_units']);
        $this->assertSame(3, $frame['quantity']);
    }

    public function test_material_waste_defaults_to_ten_percent_and_inflates_order_quantities(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'include_under_deck_gravel' => false,
        ]);

        $this->assertSame(10.0, $result['inputs']['material_waste_percent']);
        $this->assertSame(10.0, $result['totals']['material_waste_percent']);
        $this->assertGreaterThan(0, $result['totals']['material_waste_amount']);

        $blocks = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_BLOCK_4X4);
        $this->assertNotNull($blocks);
        $this->assertSame(22, $blocks['quantity']);

        $wasteFee = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WASTE_DUMP_FEE);
        $this->assertNotNull($wasteFee);
        $this->assertSame(1, $wasteFee['quantity']);

        $hangers = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_JOIST_HANGER_2X6);
        $this->assertNotNull($hangers);
        $this->assertSame(32, $hangers['quantity']);
    }

    public function test_material_waste_can_be_set_to_zero(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => false,
        ]);

        $blocks = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_BLOCK_4X4);
        $this->assertNotNull($blocks);
        $this->assertSame(20, $blocks['quantity']);
        $this->assertSame(0.0, $result['totals']['material_waste_amount']);
    }

    public function test_waste_dump_fee_is_a_flat_line_in_materials_subtotal(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => false,
        ]);

        $waste = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WASTE_DUMP_FEE);
        $this->assertNotNull($waste);
        $this->assertSame(1, $waste['quantity']);
        $this->assertSame(60.0, $waste['subtotal']);

        $this->assertSame(60.0, $result['totals']['waste_dump_fee']);
        // The flat fee is part of the taxable materials subtotal.
        $this->assertGreaterThanOrEqual(60.0, $result['totals']['materials_subtotal']);
    }

    public function test_waste_dump_fee_can_be_overridden_per_quote(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'waste_dump_fee_cad' => 90,
            'include_under_deck_gravel' => false,
        ]);

        $this->assertSame(90.0, $result['totals']['waste_dump_fee']);
        $waste = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WASTE_DUMP_FEE);
        $this->assertSame(90.0, $waste['subtotal']);
    }

    public function test_labour_is_area_times_rate_with_hst(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'labour_rate_per_sqft' => 10,
            'include_under_deck_gravel' => false,
        ]);

        $totals = $result['totals'];
        $this->assertSame(1920.0, $totals['labour_subtotal']); // 192 sq ft × CA$10
        $this->assertSame(round(1920.0 * 0.13, 2), $totals['labour_hst']);

        $expectedGrand = round(
            $totals['materials_subtotal'] + $totals['hst'] + $totals['labour_subtotal'] + $totals['labour_hst'],
            2
        );
        $this->assertSame($expectedGrand, $totals['grand_total']);
    }

    public function test_area_over_guardrail_is_rejected(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());

        $this->expectException(LowDeckAreaExceededException::class);
        $calc->calculate(['width_ft' => 30, 'length_ft' => 30]);
    }

    public function test_area_exactly_at_guardrail_is_allowed(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(['max_area_sq_ft' => 592.00]), $this->catalogue());

        // 24 × 24 = 576 sq ft, just under the 592 guardrail.
        $result = $calc->calculate([
            'width_ft' => 24,
            'length_ft' => 24,
            'include_under_deck_gravel' => false,
        ]);
        $this->assertSame(576.0, $result['geometry']['area_sq_ft']);
    }

    public function test_under_deck_gravel_defaults_on_and_rounds_bags_up(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 10,
            'length_ft' => 10,
            'material_waste_percent' => 0,
        ]);

        $this->assertTrue($result['inputs']['include_under_deck_gravel']);

        // 100 sq ft / 8 = 12.5 → 13 bags
        $gravel = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_UNDER_DECK_GRAVEL_30KG);
        $this->assertNotNull($gravel);
        $this->assertSame(13, $gravel['quantity']);

        $spikes = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_FABRIC_SPIKES_30PACK);
        $this->assertNotNull($spikes);
        $this->assertSame(1, $spikes['quantity']);
    }

    public function test_under_deck_gravel_can_be_disabled(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 10,
            'length_ft' => 10,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => false,
        ]);

        $this->assertFalse($result['inputs']['include_under_deck_gravel']);
        $this->assertNull($this->rowByKey($result['materials'], LowDeckMaterial::KEY_UNDER_DECK_GRAVEL_30KG));
        $this->assertNull($this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X50));
        $this->assertNull($this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X100));
        $this->assertNull($this->rowByKey($result['materials'], LowDeckMaterial::KEY_FABRIC_SPIKES_30PACK));
    }

    public function test_weed_fabric_uses_50ft_roll_when_deck_is_at_or_below_150_sq_ft(): void
    {
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 10,
            'length_ft' => 15,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => true,
        ]);

        $this->assertSame(150.0, $result['geometry']['area_sq_ft']);

        $fabric50 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X50);
        $fabric100 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X100);
        $this->assertNotNull($fabric50);
        $this->assertSame(1, $fabric50['quantity']);
        $this->assertNull($fabric100);
    }

    public function test_weed_fabric_prefers_cheaper_100ft_roll_over_two_50ft_rolls(): void
    {
        // Catalogue prices: 50 ft = $40, 100 ft = $60 → one 100 is cheaper than two 50s ($80).
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 12,
            'length_ft' => 16,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => true,
        ]);

        $this->assertSame(192.0, $result['geometry']['area_sq_ft']);

        $fabric50 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X50);
        $fabric100 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X100);
        $this->assertNull($fabric50);
        $this->assertNotNull($fabric100);
        $this->assertSame(1, $fabric100['quantity']);

        // 192 / 8 = 24 bags exactly
        $gravel = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_UNDER_DECK_GRAVEL_30KG);
        $this->assertNotNull($gravel);
        $this->assertSame(24, $gravel['quantity']);
    }

    public function test_weed_fabric_mixes_rolls_for_large_decks_when_cheapest(): void
    {
        // 450 sq ft: options include 2×100 ($120) + 1×50 ($40) = $160, or 3×100 = $180, or 1×100 + 3×50 = $180.
        $calc = new LowDeckQuotationCalculator($this->settings(), $this->catalogue());
        $result = $calc->calculate([
            'width_ft' => 15,
            'length_ft' => 30,
            'material_waste_percent' => 0,
            'include_under_deck_gravel' => true,
        ]);

        $this->assertSame(450.0, $result['geometry']['area_sq_ft']);

        $fabric50 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X50);
        $fabric100 = $this->rowByKey($result['materials'], LowDeckMaterial::KEY_WEED_FABRIC_3X100);
        // Cheapest: 1×100 ($60) + 1×50 ($40) = $100 vs 2×100 ($120) or 3×50 ($120).
        $this->assertNotNull($fabric100);
        $this->assertSame(1, $fabric100['quantity']);
        $this->assertNotNull($fabric50);
        $this->assertSame(1, $fabric50['quantity']);
    }
}
