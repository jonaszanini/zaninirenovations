<?php

namespace Tests\Unit\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Services\FenceQuotationCalculator;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Exhaustive post/section checks for fence sides with all combinations of
 * existing left/right posts and gate placement.
 */
class FenceQuotationScenarioTest extends TestCase
{
    private function calculator(): FenceQuotationCalculator
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

        $make = fn (string $key, float $price): FenceMaterial => new FenceMaterial([
            'key' => $key,
            'name' => $key,
            'price_cad' => $price,
            'quantity_per_box' => $key === FenceMaterial::KEY_NAILS ? 1000 : null,
        ]);

        $catalogue = collect([
            $make(FenceMaterial::KEY_POST_10, 40),
            $make(FenceMaterial::KEY_POST_12, 45),
            $make(FenceMaterial::KEY_BOARD_6, 8),
            $make(FenceMaterial::KEY_BOARD_8, 10),
            $make(FenceMaterial::KEY_RAIL, 7),
            $make(FenceMaterial::KEY_CONCRETE, 12),
            $make(FenceMaterial::KEY_NAILS, 100),
            $make(FenceMaterial::KEY_BRACKET, 3),
            $make(FenceMaterial::KEY_POST_CAP, 5),
            $make(FenceMaterial::KEY_GATE_HARDWARE, 25),
        ])->keyBy('key');

        return new FenceQuotationCalculator($settings, $catalogue);
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool, 3: int, 4: int}>
     */
    public function singleSideEightFootScenarios(): array
    {
        return [
            'no gate, no existing' => [false, false, false, 1, 2],
            'no gate, left existing' => [true, false, false, 1, 1],
            'no gate, right existing' => [false, true, false, 1, 1],
            'no gate, both existing' => [true, true, false, 1, 0],
            'gate, no existing' => [false, false, true, 1, 3],
            'gate, left existing' => [true, false, true, 1, 2],
            'gate, right existing' => [false, true, true, 1, 2],
            'gate, both existing' => [true, true, true, 1, 1],
        ];
    }

    /** @dataProvider singleSideEightFootScenarios */
    public function test_eight_foot_single_side_post_counts(
        bool $leftExisting,
        bool $rightExisting,
        bool $gateHere,
        int $expectedSections,
        int $expectedPosts,
    ): void {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_left' => $leftExisting,
                'already_have_post_right' => $rightExisting,
                'gate_goes_here' => $gateHere,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => $gateHere ? 1 : 0,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];
        $geom = $result['geometry'];

        $this->assertSame($expectedSections, $side['sections'], 'sections mismatch');
        $this->assertSame($expectedPosts, $side['posts'], 'side posts mismatch');
        $this->assertSame($expectedPosts, $geom['structural_posts'], 'structural posts mismatch');
        $this->assertSame(0, $geom['gate_posts'], 'gate posts should be on side when placed');
        $this->assertSame($expectedPosts, $geom['posts_before_waste'], 'pre-waste posts mismatch');

        if ($gateHere) {
            $this->assertSame(4.0, $side['fence_length_ft']);
            $this->assertTrue($side['gate_goes_here']);
        }
    }

    public function test_two_side_corner_with_gate_on_side_two(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [
                [
                    'length_ft' => 33.5,
                    'already_have_post_left' => true,
                    'already_have_post_right' => true,
                ],
                [
                    'length_ft' => 8,
                    'already_have_post_left' => true,
                    'already_have_post_right' => false,
                    'gate_goes_here' => true,
                ],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $geom = $result['geometry'];
        $side1 = $geom['sides_breakdown'][0];
        $side2 = $geom['sides_breakdown'][1];

        $this->assertSame(5, $side1['sections']);
        $this->assertSame(4, $side1['posts']);
        $this->assertSame(1, $side2['sections']);
        $this->assertSame(2, $side2['posts']);
        // Shared corner: both sides mark existing at join — no double-count deduction needed.
        $this->assertSame(6, $geom['structural_posts']);
        $this->assertSame(6, $geom['posts_before_waste']);
    }

    public function test_two_side_shared_corner_dedup_when_neither_marks_existing(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [
                ['length_ft' => 16],
                ['length_ft' => 16],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
        ]);

        $side1Posts = $result['geometry']['sides_breakdown'][0]['posts'];
        $side2Posts = $result['geometry']['sides_breakdown'][1]['posts'];

        $this->assertSame($side1Posts + $side2Posts - 1, $result['geometry']['structural_posts']);
    }

    public function test_gate_count_without_side_placement_adds_extra_gate_posts(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $this->assertSame(0, $result['geometry']['gates_on_sides']);
        $this->assertSame(1, $result['geometry']['gate_posts']);
        $this->assertGreaterThan(
            $result['geometry']['structural_posts'],
            $result['geometry']['posts_before_waste']
        );
    }

    public function test_gate_only_side_four_feet_long(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 4,
                'gate_goes_here' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];

        $this->assertSame(0, $side['sections']);
        $this->assertSame(0.0, $side['fence_length_ft']);
        $this->assertSame(1, $side['posts']);
        $this->assertSame(1, $result['geometry']['structural_posts']);
    }

    public function test_gate_side_with_existing_right_counts_hinge_and_skips_latch(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_right' => true,
                'gate_goes_here' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];

        // Fence run needs left-end + hinge posts; far-right latch already exists.
        $this->assertSame(1, $side['sections']);
        $this->assertSame(2, $side['posts']);
    }

    public function test_gate_side_with_both_existing_needs_only_hinge_post(): void
    {
        $calculator = $this->calculator();

        $result = $calculator->calculate([
            'sides' => [[
                'length_ft' => 8,
                'already_have_post_left' => true,
                'already_have_post_right' => true,
                'gate_goes_here' => true,
            ]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
        ]);

        $side = $result['geometry']['sides_breakdown'][0];

        $this->assertSame(1, $side['sections']);
        $this->assertSame(1, $side['posts']);
    }

    public function test_section_span_excludes_post_width_for_gate_side(): void
    {
        $calculator = $this->calculator();

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

        $this->assertCount(1, $side['section_lengths_ft']);
        // 4 ft fence run minus one 3.5 in post face.
        $this->assertEqualsWithDelta(3.708, $side['section_lengths_ft'][0], 0.01);
    }
}
