<?php

namespace Tests\Unit\Services;

use App\Models\StandardQuotation;
use App\Services\StandardQuotationCalculator;
use App\Services\StandardQuotationKitRewriteAuditor;
use Tests\TestCase;

class StandardQuotationKitRewriteAuditorTest extends TestCase
{
    public function test_flags_empty_string_urls_electrical_kind_and_total_gap(): void
    {
        $works = [[
            'title' => 'Kitchen',
            'materials' => [
                [
                    'short_name' => 'LVP',
                    'description' => 'Flooring',
                    'url' => '',
                    'image_url' => '',
                    'unit_price' => 100,
                    'quantity' => 1,
                    'apply_hst' => true,
                    'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
                    'entry_mode' => 'manual',
                ],
                [
                    'short_name' => '',
                    'description' => StandardQuotation::ELECTRICAL_LINE_DESCRIPTION,
                    'url' => '',
                    'image_url' => '',
                    'unit_price' => 250,
                    'quantity' => 1,
                    'apply_hst' => true,
                    'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
                    'entry_mode' => 'manual',
                ],
            ],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 0,
        ]];

        $computed = (new StandardQuotationCalculator)->computeWorks($works, false, 0, true, false, 0);
        $quote = new StandardQuotation([
            'quote_number' => 'SQ-AUDIT-1',
            'works' => $works,
            'materials' => $works[0]['materials'],
            'include_labour_hst' => true,
            'contingency_enabled' => false,
            'include_commute' => true,
            'commute_amount' => 100.00,
            'include_debris_removal' => true,
            'debris_removal_amount' => 110.00,
            'grand_total' => $computed['grand_total'],
        ]);

        $issues = (new StandardQuotationKitRewriteAuditor)->issuesFor($quote);

        $this->assertContains('empty_string_url', $issues);
        $this->assertContains('empty_string_image_url', $issues);
        $this->assertContains('electrical_text_as_material', $issues);
        $this->assertContains('saved_total_mismatch', $issues);
        $this->assertSame(
            StandardQuotationCalculator::grandTotalWithCommuteAndDebris($computed, 100.00, 110.00),
            $quote->editorGrandTotal()
        );
    }

    public function test_clean_quote_has_no_findings(): void
    {
        $works = [[
            'title' => 'Kitchen',
            'materials' => [[
                'short_name' => 'LVP',
                'description' => 'Flooring',
                'url' => null,
                'image_url' => null,
                'unit_price' => 100,
                'quantity' => 1,
                'apply_hst' => true,
                'line_kind' => StandardQuotation::LINE_KIND_MATERIAL,
                'entry_mode' => 'manual',
            ]],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => 0,
        ]];
        $computed = (new StandardQuotationCalculator)->computeWorks($works, false, 0, true, false, 0);
        $quote = new StandardQuotation([
            'quote_number' => 'SQ-AUDIT-2',
            'works' => $works,
            'materials' => $works[0]['materials'],
            'include_labour_hst' => true,
            'contingency_enabled' => false,
            'include_commute' => false,
            'include_debris_removal' => false,
            'grand_total' => $computed['grand_total'],
        ]);

        $this->assertSame([], (new StandardQuotationKitRewriteAuditor)->issuesFor($quote));
    }
}
