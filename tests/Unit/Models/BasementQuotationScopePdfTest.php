<?php

namespace Tests\Unit\Models;

use App\Models\BasementQuotation;
use App\Services\BasementQuotationCalculator;
use Tests\TestCase;

class BasementQuotationScopePdfTest extends TestCase
{
    public function test_scope_groups_flow_in_work_sequence_across_two_columns(): void
    {
        $quote = new BasementQuotation([
            'inputs' => [
                'area_sqft' => 800,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'include_building_permit' => true,
            ],
            'calculation' => [
                'scope' => ['area_sqft' => 800, 'ceiling_height_ft' => 8],
                'labor' => [
                    ['section' => 'painting', 'label' => 'Paint walls', 'hours' => 4],
                    ['section' => 'demolition', 'label' => 'Tear out flooring', 'hours' => 2],
                    ['section' => 'doors_trim', 'label' => 'Install doors', 'hours' => 3],
                    ['section' => 'framing', 'label' => 'Frame walls', 'hours' => 5],
                    ['section' => 'electrical', 'label' => 'Rough-in outlets', 'hours' => 3],
                    ['section' => 'finishing', 'label' => 'Final cleaning', 'hours' => 1],
                ],
            ],
            'work_description' => null,
        ]);

        $html = $quote->scopeDescriptionForPdf();

        $this->assertStringContainsString('class="br-pdf-scope-cols"', $html);
        $this->assertStringContainsString('page-break-inside:avoid', $html);

        $expected = [
            'Demolition & Preparation',
            'Framing',
            'Electrical',
            'Doors, Trim & Baseboards',
            'Painting',
            'Final Details & Cleanup',
        ];
        $positions = [];
        foreach ($expected as $heading) {
            $pos = strpos($html, '>'.e($heading).'<');
            $this->assertNotFalse($pos, $heading.' should appear in PDF scope');
            $positions[] = $pos;
        }
        $sorted = $positions;
        sort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $positions);

        // First row is consecutive work-sequence pair (Demo | Framing), not left-half/right-half.
        $demo = strpos($html, '>'.e('Demolition & Preparation').'<');
        $framing = strpos($html, '>'.e('Framing').'<');
        $doors = strpos($html, '>'.e('Doors, Trim & Baseboards').'<');
        $this->assertLessThan($framing, $demo);
        $this->assertLessThan($doors, $framing);

        foreach (array_values(BasementQuotationCalculator::SECTIONS) as $heading) {
            if (str_contains($html, '>'.e($heading).'<')) {
                $this->assertStringContainsString('br-pdf-scope-group', $html);
                break;
            }
        }
    }

    public function test_disabled_electrical_plumbing_and_bathroom_do_not_leak_stale_scope(): void
    {
        $quote = new BasementQuotation([
            'inputs' => [
                'area_sqft' => 454,
                'ceiling_height_ft' => 8,
                'room_count' => 2,
                'scope_preset' => 'custom',
                'enabled_sections' => ['overview', 'demolition', 'drywall'],
                'include_bathroom' => true,
                'bath_type' => 'full',
                'electrical_lines' => [
                    ['description' => 'STALE PANEL UPGRADE', 'amount' => 1800, 'hst_included' => true],
                ],
                'plumbing_lines' => [
                    ['description' => 'STALE ROUGH-IN', 'amount' => 900, 'hst_included' => true],
                ],
            ],
            'calculation' => [
                'scope' => ['area_sqft' => 454, 'ceiling_height_ft' => 8],
                'labor' => [
                    ['section' => 'demolition', 'label' => 'Tear out flooring', 'hours' => 2],
                    ['section' => 'electrical', 'label' => 'STALE ROUGH-IN OUTLETS', 'hours' => 3],
                    ['section' => 'plumbing', 'label' => 'STALE STACK RELOCATE', 'hours' => 2],
                    ['section' => 'bathroom', 'label' => 'STALE VANITY INSTALL', 'hours' => 4],
                    ['section' => 'drywall', 'label' => 'Hang drywall', 'hours' => 5],
                ],
            ],
            'work_description' => '<p>CLIENT ASKED FOR RECESSED NICHE</p>',
        ]);

        $html = $quote->scopeDescriptionForPdf();

        $this->assertStringContainsString('Not included: ', $html);
        $this->assertStringContainsString('Electrical', $html);
        $this->assertStringContainsString('Plumbing', $html);
        $this->assertStringContainsString('Bathroom', $html);
        $this->assertStringNotContainsString('Bathroom: Full bath', $html);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $html);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $html);
        $this->assertStringNotContainsString('STALE ROUGH-IN OUTLETS', $html);
        $this->assertStringNotContainsString('STALE STACK RELOCATE', $html);
        $this->assertStringNotContainsString('STALE VANITY INSTALL', $html);
        $this->assertStringContainsString('Tear out flooring', $html);
        $this->assertStringContainsString('Hang drywall', $html);
        $this->assertStringContainsString('CLIENT ASKED FOR RECESSED NICHE', $html);
        $this->assertSame([], $quote->enabledTradeProjectLineItems());
    }

    public function test_enabled_electrical_and_plumbing_keep_priced_line_details(): void
    {
        $quote = new BasementQuotation([
            'inputs' => [
                'area_sqft' => 400,
                'ceiling_height_ft' => 8,
                'scope_preset' => 'custom',
                'enabled_sections' => ['overview', 'electrical', 'plumbing'],
                'electrical_lines' => [
                    ['description' => 'Panel upgrade', 'amount' => 1800, 'hst_included' => true],
                ],
                'plumbing_lines' => [
                    ['description' => 'Rough-in stack', 'amount' => 900, 'hst_included' => true],
                ],
            ],
            'calculation' => [
                'scope' => ['area_sqft' => 400, 'ceiling_height_ft' => 8],
                'labor' => [],
            ],
            'work_description' => null,
        ]);

        $html = $quote->scopeDescriptionForPdf();
        $this->assertStringContainsString('Panel upgrade', $html);
        $this->assertStringContainsString('Rough-in stack', $html);
        $this->assertStringContainsString('>'.e('Electrical').'<', $html);
        $this->assertStringContainsString('>'.e('Plumbing').'<', $html);
        preg_match('/Not included: ([^<]+)/', $html, $notIncluded);
        $this->assertStringNotContainsString('Electrical', $notIncluded[1] ?? '');
        $this->assertStringNotContainsString('Plumbing', $notIncluded[1] ?? '');

        $lines = collect($quote->enabledTradeProjectLineItems())->pluck('description')->all();
        $this->assertNotEmpty(preg_grep('/Panel upgrade/', $lines));
        $this->assertNotEmpty(preg_grep('/Rough-in stack/', $lines));
    }
}
