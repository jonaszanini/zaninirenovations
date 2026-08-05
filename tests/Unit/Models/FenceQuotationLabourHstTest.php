<?php

namespace Tests\Unit\Models;

use App\Models\FenceQuotation;
use Tests\TestCase;

class FenceQuotationLabourHstTest extends TestCase
{
    public function test_project_lines_keep_single_taxable_row_when_labour_hst_included(): void
    {
        $quote = new FenceQuotation([
            'materials_subtotal' => 100,
            'labour_total' => 200,
            'include_labour_hst' => true,
            'fence_height_ft' => 6,
            'post_size' => FenceQuotation::POST_SIZE_4X4,
            'gate_count' => 0,
            'sides' => [['length_ft' => 20]],
        ]);

        $items = $quote->projectQuotationLineItems();

        $this->assertCount(1, $items);
        $this->assertSame(300.0, $items[0]['amount']);
        $this->assertTrue($items[0]['apply_hst']);
    }

    public function test_project_lines_split_when_labour_hst_excluded(): void
    {
        $quote = new FenceQuotation([
            'materials_subtotal' => 100,
            'labour_total' => 200,
            'include_labour_hst' => false,
            'fence_height_ft' => 6,
            'post_size' => FenceQuotation::POST_SIZE_4X4,
            'gate_count' => 0,
            'sides' => [['length_ft' => 20]],
        ]);

        $items = $quote->projectQuotationLineItems();

        $this->assertCount(2, $items);
        $this->assertSame(100.0, $items[0]['amount']);
        $this->assertTrue($items[0]['apply_hst']);
        $this->assertSame(200.0, $items[1]['amount']);
        $this->assertFalse($items[1]['apply_hst']);
    }
}
