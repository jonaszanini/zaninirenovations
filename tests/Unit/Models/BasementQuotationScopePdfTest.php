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
}
