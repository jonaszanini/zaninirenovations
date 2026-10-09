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
            'existing_post_labour_credit' => 30.00,
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
            $make(FenceMaterial::KEY_POST_6X6_10, 55),
            $make(FenceMaterial::KEY_POST_6X6_12, 60),
            $make(FenceMaterial::KEY_BOARD_6, 8),
            $make(FenceMaterial::KEY_BOARD_8, 10),
            $make(FenceMaterial::KEY_RAIL, 7),
            $make(FenceMaterial::KEY_CONCRETE, 12),
            $make(FenceMaterial::KEY_NAILS, 100, 1000),
            $make(FenceMaterial::KEY_BRACKET, 3),
            $make(FenceMaterial::KEY_POST_CAP, 5),
            $make(FenceMaterial::KEY_POST_CAP_6X6, 8),
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
        $this->assertTrue($result['totals']['include_labour_hst']);
        $this->assertGreaterThan(0, $result['totals']['labour_hst']);
    }

    public function test_labour_hst_can_be_excluded(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $withHst = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'include_labour_hst' => true,
        ]);
        $withoutHst = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'include_labour_hst' => false,
        ]);

        $this->assertFalse($withoutHst['totals']['include_labour_hst']);
        $this->assertSame(0.0, $withoutHst['totals']['labour_hst']);
        $this->assertSame(
            round($withHst['totals']['grand_total'] - $withHst['totals']['labour_hst'], 2),
            $withoutHst['totals']['grand_total']
        );
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

    public function test_linked_catalog_delivery_is_a_flat_line(): void
    {
        $catalogue = $this->basicCatalogue();
        $concrete = $catalogue->get(FenceMaterial::KEY_CONCRETE);
        $concrete->setRelation('material', new \App\Models\Material([
            'name' => 'Quikrete 30kg',
            'price_cad' => 7.00,
            'delivery_cad' => 40.00,
        ]));

        $calculator = $this->calculatorWithCatalogue($catalogue);
        $result = $calculator->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $delivery = collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_CONCRETE.'_delivery');
        $this->assertNotNull($delivery);
        $this->assertSame(1, $delivery['quantity']);
        $this->assertSame('flat', $delivery['unit']);
        $this->assertEqualsWithDelta(40.0, $delivery['subtotal'], 0.01);
        $this->assertEqualsWithDelta(40.0, $delivery['unit_price'], 0.01);
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

    public function test_sections_on_a_side_are_equal_length(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 33.5,
                'already_have_post_left' => true,
                'already_have_post_right' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $lengths = $result['geometry']['sides_breakdown'][0]['section_lengths_ft'];
        $this->assertCount(5, $lengths);

        foreach ($lengths as $length) {
            $this->assertEqualsWithDelta(6.467, $length, 0.01);
        }
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

    public function test_custom_labour_rate_affects_grand_total(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $base = $calculator->calculate([
            'sides' => [['length_ft' => 41.5]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 25,
            'labour_rate_8ft_per_ft' => 27,
        ]);

        $higher = $calculator->calculate([
            'sides' => [['length_ft' => 41.5]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 26,
            'labour_rate_8ft_per_ft' => 27,
        ]);

        $this->assertSame(25.0, $base['inputs']['labour_rate_per_ft']);
        $this->assertSame(26.0, $higher['inputs']['labour_rate_per_ft']);
        $this->assertGreaterThan($base['totals']['grand_total'], $higher['totals']['grand_total']);
        $this->assertGreaterThan(
            $base['totals']['labour_subtotal'],
            $higher['totals']['labour_subtotal']
        );
    }

    public function test_six_by_six_posts_use_six_by_six_material_keys(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'post_size' => FenceQuotation::POST_SIZE_6X6,
        ]);

        $postRow = collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_POST_6X6_10);
        $this->assertNotNull($postRow);
        $this->assertSame(FenceMaterial::KEY_POST_6X6_10, $postRow['key']);
        $capRow = collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_POST_CAP_6X6);
        $this->assertNotNull($capRow);
        $this->assertNull(collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_POST_CAP));

        $eightFt = $calculator->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 8,
            'board_style' => 'standard',
            'gate_count' => 0,
            'post_size' => FenceQuotation::POST_SIZE_6X6,
        ]);

        $post12 = collect($eightFt['materials'])->firstWhere('key', FenceMaterial::KEY_POST_6X6_12);
        $this->assertNotNull($post12);
    }

    public function test_no_existing_post_ticks_keeps_labour_as_length_times_rate(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $length = 24.0;
        $rate = 55.0;

        $result = $calculator->calculate([
            'sides' => [['length_ft' => $length]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => $rate,
        ]);

        $this->assertSame(round($length * $rate, 2), $result['totals']['labour_subtotal']);
        $this->assertSame(0, $result['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $result['totals']['existing_post_credit']['amount']);
        $this->assertSame(30.0, $result['inputs']['existing_post_labour_credit']);
    }

    public function test_one_left_existing_post_tick_credits_materials_and_labour(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $baseInputs = [
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'include_labour_hst' => true,
        ];

        $base = $calculator->calculate($baseInputs);
        $left = $calculator->calculate(array_merge($baseInputs, [
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
        ]));

        $this->assertSame(1, $left['totals']['existing_post_credit']['count']);
        $this->assertSame(30.0, $left['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $left['totals']['labour_subtotal'],
            0.001
        );

        $materialDelta = $base['totals']['materials_subtotal'] - $left['totals']['materials_subtotal'];
        $hstDelta = $base['totals']['hst'] - $left['totals']['hst'];
        $labourHstDelta = $base['totals']['labour_hst'] - $left['totals']['labour_hst'];
        $this->assertGreaterThan(0, $materialDelta);
        $this->assertEqualsWithDelta($materialDelta * FenceQuotation::HST_RATE, $hstDelta, 0.02);
        $this->assertEqualsWithDelta(30 * FenceQuotation::HST_RATE, $labourHstDelta, 0.02);
        $this->assertEqualsWithDelta(
            $base['totals']['grand_total'] - $materialDelta - $hstDelta - 30 - $labourHstDelta,
            $left['totals']['grand_total'],
            0.02
        );
    }

    public function test_both_existing_post_ticks_credit_sixty_labour(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $base = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
        ]);
        $both = $calculator->calculate([
            'sides' => [[
                'length_ft' => 24,
                'already_have_post_left' => true,
                'already_have_post_right' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
        ]);

        $this->assertSame(2, $both['totals']['existing_post_credit']['count']);
        $this->assertSame(60.0, $both['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 60,
            $both['totals']['labour_subtotal'],
            0.001
        );
    }

    public function test_connected_run_shared_corner_is_not_double_credited(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $base = $calculator->calculate([
            'sides' => [
                ['length_ft' => 16],
                ['length_ft' => 16, 'connected_to_previous' => true],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
        ]);
        $sharedTicks = $calculator->calculate([
            'sides' => [
                ['length_ft' => 16, 'already_have_post_right' => true],
                ['length_ft' => 16, 'already_have_post_left' => true, 'connected_to_previous' => true],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
        ]);

        $this->assertSame(
            $base['geometry']['structural_posts'] - 1,
            $sharedTicks['geometry']['structural_posts']
        );
        $this->assertSame(1, $sharedTicks['totals']['existing_post_credit']['count']);
        $this->assertSame(30.0, $sharedTicks['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $sharedTicks['totals']['labour_subtotal'],
            0.001
        );
    }

    public function test_backfilled_zero_credit_leaves_labour_unchanged_when_ticks_remove_posts(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $base = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => 0,
        ]);
        $ticked = $calculator->calculate([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => 0,
        ]);

        $this->assertSame($base['totals']['labour_subtotal'], $ticked['totals']['labour_subtotal']);
        $this->assertSame(1, $ticked['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $ticked['totals']['existing_post_credit']['amount']);
        $this->assertSame(24.0 * 55.0, $ticked['totals']['labour_subtotal']);
    }

    public function test_existing_post_labour_credit_cannot_go_negative(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_left' => true,
                'already_have_post_right' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 10,
            'existing_post_labour_credit' => 999,
        ]);

        $this->assertSame(2, $result['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $result['totals']['labour_subtotal']);
        $this->assertSame(80.0, $result['totals']['labour_before_credit']);
        $this->assertSame(80.0, $result['totals']['existing_post_credit']['amount']);
        $this->assertNotEquals(1998.0, $result['totals']['existing_post_credit']['amount']);
    }

    public function test_gate_edge_ticks_credit_left_and_right_posts(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $baseInputs = [
            'sides' => [['length_ft' => 24, 'gate_goes_here' => true, 'gate_preset' => '4_single']],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => 30,
        ];

        $base = $calculator->calculate($baseInputs);
        $left = $calculator->calculate(array_merge($baseInputs, [
            'sides' => [[
                'length_ft' => 24,
                'gate_goes_here' => true,
                'gate_preset' => '4_single',
                'already_have_post_left' => true,
            ]],
        ]));
        $right = $calculator->calculate(array_merge($baseInputs, [
            'sides' => [[
                'length_ft' => 24,
                'gate_goes_here' => true,
                'gate_preset' => '4_single',
                'already_have_post_right' => true,
            ]],
        ]));
        $both = $calculator->calculate(array_merge($baseInputs, [
            'sides' => [[
                'length_ft' => 24,
                'gate_goes_here' => true,
                'gate_preset' => '4_single',
                'already_have_post_left' => true,
                'already_have_post_right' => true,
            ]],
        ]));

        $this->assertSame(1, $left['totals']['existing_post_credit']['count']);
        $this->assertSame(30.0, $left['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $left['totals']['labour_subtotal'],
            0.001
        );

        $this->assertSame(1, $right['totals']['existing_post_credit']['count']);
        $this->assertSame(30.0, $right['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 30,
            $right['totals']['labour_subtotal'],
            0.001
        );

        $this->assertSame(2, $both['totals']['existing_post_credit']['count']);
        $this->assertSame(60.0, $both['totals']['existing_post_credit']['amount']);
        $this->assertEqualsWithDelta(
            $base['totals']['labour_subtotal'] - 60,
            $both['totals']['labour_subtotal'],
            0.001
        );
    }

    public function test_eight_point_three_three_foot_existing_tick_is_a_no_op(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $baseInputs = [
            'sides' => [['length_ft' => 8.33]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => 30,
        ];

        $base = $calculator->calculate($baseInputs);
        $ticked = $calculator->calculate(array_merge($baseInputs, [
            'sides' => [['length_ft' => 8.33, 'already_have_post_left' => true]],
        ]));

        $this->assertSame(
            $base['geometry']['structural_posts'],
            $ticked['geometry']['structural_posts']
        );
        $this->assertSame(0, $ticked['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $ticked['totals']['existing_post_credit']['amount']);
        $this->assertSame($base['totals']['labour_subtotal'], $ticked['totals']['labour_subtotal']);
    }

    public function test_string_zero_tick_flags_do_not_apply_credit(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $ticked = $calculator->calculate([
            'sides' => [[
                'length_ft' => 24,
                'already_have_post_left' => '0',
                'already_have_post_right' => '0',
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => 30,
        ]);

        $this->assertFalse($ticked['inputs']['sides'][0]['already_have_post_left']);
        $this->assertFalse($ticked['inputs']['sides'][0]['already_have_post_right']);
        $this->assertSame(0, $ticked['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $ticked['totals']['existing_post_credit']['amount']);
        $this->assertSame(24.0 * 55.0, $ticked['totals']['labour_subtotal']);
    }

    public function test_zero_string_credit_is_a_no_op_tick(): void
    {
        $calculator = $this->calculatorWithCatalogue($this->basicCatalogue());
        $ticked = $calculator->calculate([
            'sides' => [['length_ft' => 24, 'already_have_post_left' => true]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'existing_post_labour_credit' => '0',
        ]);

        $this->assertSame(1, $ticked['totals']['existing_post_credit']['count']);
        $this->assertSame(0.0, $ticked['totals']['existing_post_credit']['amount']);
        $this->assertSame(0.0, $ticked['inputs']['existing_post_labour_credit']);
        $this->assertSame(24.0 * 55.0, $ticked['totals']['labour_subtotal']);
    }

    public function test_gate_side_assignment_honours_all_marked_sides(): void
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

        $this->assertSame(2, $result['inputs']['gate_count']);
        $this->assertSame(2, $result['geometry']['gates_on_sides']);
        $this->assertTrue($result['geometry']['sides_breakdown'][0]['gate_goes_here']);
        $this->assertTrue($result['geometry']['sides_breakdown'][1]['gate_goes_here']);
    }
}
