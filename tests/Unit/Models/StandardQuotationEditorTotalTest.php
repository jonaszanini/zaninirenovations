<?php

namespace Tests\Unit\Models;

use App\Models\StandardQuotation;
use App\Services\StandardQuotationCalculator;
use Tests\TestCase;

class StandardQuotationEditorTotalTest extends TestCase
{
    public function test_editor_grand_total_adds_commute_and_debris(): void
    {
        $quote = new StandardQuotation([
            'works' => [[
                'title' => 'Kitchen',
                'materials' => [[
                    'short_name' => 'LVP',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ]],
            'include_labour_hst' => true,
            'contingency_enabled' => false,
            'include_commute' => true,
            'commute_amount' => 100.00,
            'include_debris_removal' => true,
            'debris_removal_amount' => 110.00,
        ]);

        $this->assertSame(113.00, (float) $quote->computedTotals()['grand_total']);
        $this->assertSame(323.00, $quote->editorGrandTotal());
    }

    public function test_project_quotation_line_items_split_exempt_and_keep_extras_out_of_hst(): void
    {
        $quote = new StandardQuotation([
            'id' => 44,
            'includes_material' => true,
            'includes_labour' => true,
            'works' => [[
                'title' => 'Kitchen',
                'materials' => [
                    [
                        'short_name' => 'Taxed',
                        'unit_price' => 100,
                        'quantity' => 1,
                        'apply_hst' => true,
                    ],
                    [
                        'short_name' => 'Exempt',
                        'unit_price' => 50,
                        'quantity' => 1,
                        'apply_hst' => false,
                    ],
                ],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 200,
            ]],
            'include_labour_hst' => true,
            'contingency_enabled' => true,
            'contingency_percent' => 10,
            'include_commute' => true,
            'commute_amount' => 100.00,
            'commute_estimated_trips' => 2,
            'include_debris_removal' => true,
            'debris_removal_amount' => 110.00,
        ]);

        $items = $quote->projectQuotationLineItems('Kitchen reno');
        $this->assertCount(4, $items);
        $this->assertSame('Kitchen reno — HST applicable', $items[0]['description']);
        $this->assertTrue($items[0]['apply_hst']);
        $this->assertSame(310.00, $items[0]['amount']);
        $this->assertSame('Kitchen reno — HST exempt', $items[1]['description']);
        $this->assertFalse($items[1]['apply_hst']);
        $this->assertSame(55.00, $items[1]['amount']);
        $this->assertSame('Commute expense — 2 trips', $items[2]['description']);
        $this->assertFalse($items[2]['apply_hst']);
        $this->assertTrue($items[2]['hst_included']);
        $this->assertSame(100.00, $items[2]['amount']);
        $this->assertSame('Debris removal and disposal', $items[3]['description']);
        $this->assertFalse($items[3]['apply_hst']);
        $this->assertTrue($items[3]['hst_included']);
        $this->assertSame(110.00, $items[3]['amount']);

        $computed = (new StandardQuotationCalculator)->computeWorks(
            $quote->resolvedWorks(),
            true,
            10,
            true,
            false,
            0
        );
        $this->assertSame($computed['pre_hst_taxable'], $items[0]['amount']);
        $this->assertSame($computed['pre_hst_exempt'], $items[1]['amount']);
    }
}
