<?php

namespace Tests\Unit\Services;

use App\Services\QuotationWorkDescriptionContextBuilder;
use Tests\TestCase;

class QuotationWorkDescriptionFenceContextTest extends TestCase
{
    public function test_three_entered_runs_are_not_described_as_eight_calculator_sections(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('fence', [
            'client_name' => 'Jonas',
            'fence_height_ft' => 6,
            'post_size' => '4x4',
            'board_style' => 'standard',
            'gate_count' => 1,
            'sides' => [
                ['length_ft' => 20, 'gate_goes_here' => true],
                ['length_ft' => 20],
                ['length_ft' => 18],
            ],
            // Live geometry panel text: calculator post-to-post spans, not user runs.
            'geometry_summary' => '58 linear ft · 8 sections · 1 gate',
            'sections_total' => 8,
            'totals_summary' => 'Materials: CA$1,200; Labour: CA$3,190; Grand total: CA$4,390',
            'materials_summary' => "Fence board pickets — CA$400\nPressure treated fence rails — CA$120\nPost caps — CA$40\nConcrete — CA$80\nAnti-sag kit",
        ]);

        $this->assertStringContainsString('Fence sections (runs entered on this quote): 3', $text);
        $this->assertStringContainsString('Total length: 58 linear feet', $text);
        $this->assertStringContainsString('Section 1: 20 linear feet', $text);
        $this->assertStringContainsString('Section 2: 20 linear feet', $text);
        $this->assertStringContainsString('Section 3: 18 linear feet', $text);
        $this->assertStringContainsString('6 ft', $text);
        $this->assertStringContainsString('4x4', $text);
        $this->assertStringContainsString('standard board', $text);
        $this->assertStringContainsString('Gates: 1 (each 4 ft wide)', $text);
        $this->assertStringContainsString('Post footings: concrete', $text);
        $this->assertStringContainsString('Fence board pickets', $text);
        $this->assertStringContainsString('Anti-sag kit', $text);

        $this->assertDoesNotMatchRegularExpression('/\b8\s+sections?\b/i', $text);
        $this->assertStringNotContainsString('Layout / geometry', $text);
        $this->assertStringNotContainsString('CA$', $text);
        $this->assertStringNotContainsString('Grand total', $text);
    }
}
