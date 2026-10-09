<?php

namespace Tests\Unit\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Models\Material;
use App\Services\FenceQuotationCalculator;
use App\Support\FenceGateSpec;
use App\Support\FenceWorkDescriptionGenerator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FenceQuotationOct8FixesTest extends TestCase
{
    public function test_gate_only_run_counts_hinge_and_latch(): void
    {
        $result = $this->calculator()->calculate([
            'sides' => [['length_ft' => 4, 'gate_goes_here' => true]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(2, $result['geometry']['sides_breakdown'][0]['posts']);
        $this->assertSame(2, $result['geometry']['structural_posts']);
        $this->assertSame(2, $result['geometry']['posts_before_waste']);
    }

    public function test_placed_and_unplaced_gates_get_the_same_board_count(): void
    {
        $placed = $this->calculator()->calculate([
            'sides' => [['length_ft' => 16, 'gate_goes_here' => true, 'gate_preset' => '4_single']],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);
        $unplaced = $this->calculator()->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
            'extra_gate_preset' => '4_single',
        ]);

        $placedBoards = $this->gateBoardsFromNotes($placed);
        $unplacedBoards = $this->gateBoardsFromNotes($unplaced);
        $expected = (int) ceil((4.0 * 12.0) / 5.5);

        $this->assertSame($expected, $placedBoards);
        $this->assertSame($expected, $unplacedBoards);
        $this->assertSame($placedBoards, $unplacedBoards);
    }

    public function test_one_shared_existing_corner_tick_still_dedupes(): void
    {
        $rightOnly = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 20, 'already_have_post_right' => true],
                ['length_ft' => 20],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);
        $leftOnly = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 20],
                ['length_ft' => 20, 'already_have_post_left' => true],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(3, $rightOnly['geometry']['sides_breakdown'][0]['posts']);
        $this->assertSame(4, $rightOnly['geometry']['sides_breakdown'][1]['posts']);
        $this->assertSame(6, $rightOnly['geometry']['structural_posts']);
        $this->assertSame(6, $leftOnly['geometry']['structural_posts']);
    }

    public function test_posted_string_zero_flags_are_off(): void
    {
        $result = $this->calculator()->calculate([
            'sides' => [[
                'length_ft' => 20,
                'already_have_post_left' => '0',
                'already_have_post_right' => '0',
                'gate_goes_here' => '0',
                'connected_to_previous' => '0',
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];
        $this->assertFalse($side['already_have_post_left']);
        $this->assertFalse($side['already_have_post_right']);
        $this->assertFalse($side['gate_goes_here']);
        $this->assertSame(4, $side['posts']);
    }

    public function test_gate_wider_than_run_clamps_opening(): void
    {
        $result = $this->calculator()->calculate([
            'sides' => [[
                'length_ft' => 8,
                'gate_goes_here' => true,
                'gate_preset' => '10_double',
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(8.0, $result['geometry']['sides_breakdown'][0]['gate_opening_ft']);
        $this->assertSame(8.0, $result['geometry']['gates'][0]['width_ft']);
    }

    public function test_disconnected_runs_do_not_share_a_corner_post(): void
    {
        $connected = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 16],
                ['length_ft' => 16, 'connected_to_previous' => true],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);
        $separate = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 16],
                ['length_ft' => 16, 'connected_to_previous' => false],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(
            $connected['geometry']['structural_posts'] + 1,
            $separate['geometry']['structural_posts']
        );
    }

    public function test_zero_gates_is_the_default_and_does_not_add_hardware(): void
    {
        $result = $this->calculator()->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $this->assertSame(0, $result['inputs']['gate_count']);
        $this->assertSame(0, $result['geometry']['gates_on_sides']);
        $this->assertNull(collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_GATE_HARDWARE));
    }

    public function test_double_drive_gate_uses_opening_width_two_hinges_and_two_hardware_sets(): void
    {
        $result = $this->calculator()->calculate([
            'sides' => [[
                'length_ft' => 10,
                'gate_goes_here' => true,
                'gate_preset' => '10_double',
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];
        $this->assertSame(10.0, $side['gate_opening_ft']);
        $this->assertSame('double', $side['gate_type']);
        $this->assertSame(0.0, $side['fence_length_ft']);
        $this->assertSame(2, $side['posts']);

        $hardware = collect($result['materials'])->firstWhere('key', FenceMaterial::KEY_GATE_HARDWARE);
        $this->assertNotNull($hardware);
        $this->assertSame(2, $hardware['quantity']);

        $expectedBoards = (int) ceil((10.0 * 12.0) / 5.5);
        $this->assertSame($expectedBoards, $this->gateBoardsFromNotes($result));
    }

    public function test_three_and_five_foot_singles_change_opening_and_boards(): void
    {
        $three = $this->calculator()->calculate([
            'sides' => [['length_ft' => 12, 'gate_goes_here' => true, 'gate_preset' => '3_single']],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);
        $five = $this->calculator()->calculate([
            'sides' => [['length_ft' => 12, 'gate_goes_here' => true, 'gate_preset' => '5_single']],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(3.0, $three['geometry']['sides_breakdown'][0]['gate_opening_ft']);
        $this->assertSame(5.0, $five['geometry']['sides_breakdown'][0]['gate_opening_ft']);
        $this->assertSame((int) ceil((3 * 12) / 5.5), $this->gateBoardsFromNotes($three));
        $this->assertSame((int) ceil((5 * 12) / 5.5), $this->gateBoardsFromNotes($five));
    }

    public function test_six_by_six_uses_own_cap_and_concrete_rate(): void
    {
        $settings = $this->settings();
        $settings->concrete_per_post_6ft = 1.78;
        $settings->concrete_per_post_6x6_6ft = 2.5;
        $four = new FenceQuotationCalculator($settings, $this->catalogue());
        $six = new FenceQuotationCalculator($settings, $this->catalogue());

        $fourResult = $four->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'post_size' => '4x4',
        ]);
        $sixResult = $six->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'post_size' => '6x6',
        ]);

        $this->assertNotNull(collect($sixResult['materials'])->firstWhere('key', FenceMaterial::KEY_POST_CAP_6X6));
        $this->assertNull(collect($sixResult['materials'])->firstWhere('key', FenceMaterial::KEY_POST_CAP));

        $fourConcrete = collect($fourResult['materials'])->firstWhere('key', FenceMaterial::KEY_CONCRETE);
        $sixConcrete = collect($sixResult['materials'])->firstWhere('key', FenceMaterial::KEY_CONCRETE);
        $this->assertGreaterThan((float) $fourConcrete['quantity'], (float) $sixConcrete['quantity']);
    }

    public function test_delivery_is_charged_once_per_vendor(): void
    {
        $catalogue = $this->catalogue();
        $vendorMaterial = function (float $delivery) {
            $mat = new Material([
                'name' => 'Home Depot item',
                'price_cad' => 10,
                'delivery_cad' => $delivery,
                'vendor_id' => 7,
            ]);
            $mat->setRelation('vendor', new \App\Models\MaterialVendor(['name' => 'Home Depot']));

            return $mat;
        };
        $catalogue->get(FenceMaterial::KEY_CONCRETE)->setRelation('material', $vendorMaterial(40));
        $catalogue->get(FenceMaterial::KEY_RAIL)->setRelation('material', $vendorMaterial(55));

        $result = $this->calculatorWithCatalogue($catalogue)->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $deliveries = collect($result['materials'])->filter(
            fn (array $row): bool => str_ends_with((string) ($row['key'] ?? ''), '_delivery')
        );
        $this->assertCount(1, $deliveries);
        $this->assertEqualsWithDelta(55.0, $deliveries->first()['subtotal'], 0.01);
    }

    public function test_frozen_prices_keep_saved_unit_prices(): void
    {
        $live = $this->calculator()->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);
        $frozen = FenceQuotationCalculator::frozenUnitPricesFromCalculation($live);
        $frozen[FenceMaterial::KEY_BOARD_6] = 99.0;

        $repriced = $this->calculator()->calculate([
            'sides' => [['length_ft' => 16]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ], $frozen);

        $board = collect($repriced['materials'])->firstWhere('key', FenceMaterial::KEY_BOARD_6);
        $this->assertEqualsWithDelta(99.0, (float) $board['unit_price'], 0.01);
        $this->assertSame('frozen', $repriced['price_source']);
    }

    public function test_template_fill_uses_run_and_gate_counts(): void
    {
        $calc = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 20, 'gate_goes_here' => true, 'gate_preset' => '4_single'],
                ['length_ft' => 20],
                ['length_ft' => 18],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $text = FenceWorkDescriptionGenerator::generateFromTemplate(
            $calc,
            FenceWorkDescriptionGenerator::defaultTemplate()
        );

        $this->assertStringContainsString('58 linear feet', $text);
        $this->assertStringContainsString('3 runs', $text);
        $this->assertStringContainsString('4 ft single', $text);
        $this->assertStringNotContainsString('{{', $text);
    }

    public function test_gate_spec_presets_cover_common_sizes(): void
    {
        $this->assertSame(3.0, FenceGateSpec::fromKey('3_single')->widthFt);
        $this->assertSame(12.0, FenceGateSpec::fromKey('12_double')->widthFt);
        $this->assertTrue(FenceGateSpec::fromKey('8_double')->isDouble());
        $this->assertSame(2, FenceGateSpec::fromKey('10_double')->hardwareSets());
    }

    private function gateBoardsFromNotes(array $result): int
    {
        $picket = collect($result['materials'])->first(
            fn (array $row): bool => in_array($row['key'] ?? '', [FenceMaterial::KEY_BOARD_6, FenceMaterial::KEY_BOARD_8], true)
                && str_contains((string) ($row['notes'] ?? ''), 'gate boards')
        );
        $this->assertNotNull($picket);
        $this->assertMatchesRegularExpression('/gate boards:\s*(\d+)/', (string) $picket['notes']);
        preg_match('/gate boards:\s*(\d+)/', (string) $picket['notes'], $m);

        return (int) $m[1];
    }

    private function calculator(): FenceQuotationCalculator
    {
        return $this->calculatorWithCatalogue($this->catalogue());
    }

    private function calculatorWithCatalogue(Collection $catalogue): FenceQuotationCalculator
    {
        return new FenceQuotationCalculator($this->settings(), $catalogue);
    }

    private function settings(): FenceFormulaSetting
    {
        return new FenceFormulaSetting([
            'gate_rails' => 5,
            'concrete_per_post_6ft' => 1.78,
            'concrete_per_post_8ft' => 2.5,
            'concrete_per_post_6x6_6ft' => 2.5,
            'concrete_per_post_6x6_8ft' => 3.5,
            'labour_rate_per_ft' => 55.00,
            'labour_rate_8ft_per_ft' => 60.00,
            'labour_per_additional_gate' => 150.00,
            'rails_per_section_6ft' => 2,
            'rails_per_section_8ft' => 3,
            'nails_per_picket' => 6,
            'board_width_in' => 5.5,
            'max_post_spacing_ft' => 8.0,
            'waste_extra_posts' => 1,
            'waste_pickets_per_section' => 1,
            'waste_extra_rails' => 3,
            'waste_extra_brackets' => 6,
            'waste_extra_caps' => 1,
            'waste_concrete_posts_per_extra_bag' => 3,
        ]);
    }

    private function catalogue(): Collection
    {
        $make = function (string $key, float $price, ?int $qtyPerBox = null): FenceMaterial {
            return new FenceMaterial([
                'key' => $key,
                'name' => $key,
                'price_cad' => $price,
                'quantity_per_box' => $qtyPerBox,
            ]);
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
}
