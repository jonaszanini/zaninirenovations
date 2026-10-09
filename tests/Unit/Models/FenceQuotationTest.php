<?php

namespace Tests\Unit\Models;

use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use Tests\TestCase;

class FenceQuotationTest extends TestCase
{
    public function test_summary_description_includes_post_size(): void
    {
        $quote = new FenceQuotation([
            'fence_height_ft' => 6,
            'post_size' => FenceQuotation::POST_SIZE_6X6,
            'gate_count' => 1,
            'sides' => [
                ['length_ft' => 33.5],
                ['length_ft' => 8],
            ],
        ]);

        $this->assertSame(
            '6 ft standard board fence, 6×6 posts in concrete, 41.5 linear ft (2 runs), one 4 ft single gate',
            $quote->summaryDescription()
        );
    }

    public function test_post_size_falls_back_to_calculation_material_name(): void
    {
        $quote = new FenceQuotation([
            'calculation' => [
                'materials' => [[
                    'key' => FenceMaterial::KEY_POST_10,
                    'name' => '4x4x10 Post',
                ]],
            ],
            'fence_height_ft' => 6,
            'gate_count' => 0,
            'sides' => [['length_ft' => 10]],
        ]);

        $this->assertSame(FenceQuotation::POST_SIZE_4X4, $quote->resolvedPostSize());
        $this->assertStringContainsString('4×4 posts', $quote->summaryDescription());
    }

    public function test_existing_post_labour_credit_is_fillable_and_cast(): void
    {
        $quote = new FenceQuotation([
            'existing_post_labour_credit' => '30.00',
        ]);

        $this->assertContains('existing_post_labour_credit', $quote->getFillable());
        $this->assertSame('30.00', $quote->existing_post_labour_credit);
    }
}
