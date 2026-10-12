<?php

namespace Tests\Unit\Support;

use App\Support\FenceWorkDescriptionGenerator;
use Tests\TestCase;

class FenceWorkDescriptionGeneratorTest extends TestCase
{
    public function test_default_template_replaces_placeholders(): void
    {
        $text = FenceWorkDescriptionGenerator::generateFromTemplate([
            'inputs' => [
                'fence_height_ft' => 6,
                'board_style' => 'standard',
                'post_size' => '4x4',
                'gate_count' => 1,
                'extra_gate_preset' => '4_single',
            ],
            'geometry' => [
                'total_linear_ft' => 58,
                'posts_before_waste' => 10,
                'sides_breakdown' => [
                    ['length_ft' => 20, 'gate_goes_here' => true, 'gate_preset' => '4_single'],
                    ['length_ft' => 20],
                    ['length_ft' => 18],
                ],
            ],
        ], FenceWorkDescriptionGenerator::defaultTemplate());

        $this->assertStringContainsString('58 linear feet', $text);
        $this->assertStringContainsString('6 ft standard board fence', $text);
        $this->assertStringContainsString('3 runs', $text);
        $this->assertStringContainsString('4×4 posts', $text);
        $this->assertStringContainsString('One 4 ft single gate', $text);
        $this->assertStringNotContainsString('{{', $text);
    }

    public function test_no_gates_phrase(): void
    {
        $this->assertSame('No gates', FenceWorkDescriptionGenerator::gatesPhrase([
            'inputs' => ['gate_count' => 0],
            'geometry' => ['sides_breakdown' => [['length_ft' => 16]]],
        ]));
    }

    public function test_one_run_uses_singular(): void
    {
        $text = FenceWorkDescriptionGenerator::generateFromTemplate([
            'inputs' => [
                'fence_height_ft' => 6,
                'board_style' => 'standard',
                'post_size' => '4x4',
                'gate_count' => 0,
            ],
            'geometry' => [
                'total_linear_ft' => 16,
                'posts_before_waste' => 3,
                'sides_breakdown' => [['length_ft' => 16]],
            ],
        ], FenceWorkDescriptionGenerator::defaultTemplate());

        $this->assertStringContainsString('1 run', $text);
        $this->assertStringNotContainsString('1 runs', $text);
        $this->assertSame('1 run', FenceWorkDescriptionGenerator::runCountPhrase(1));
        $this->assertSame('3 runs', FenceWorkDescriptionGenerator::runCountPhrase(3));
    }
}
