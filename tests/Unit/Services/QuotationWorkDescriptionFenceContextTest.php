<?php

namespace Tests\Unit\Services;

use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Services\FenceQuotationCalculator;
use App\Services\QuotationWorkDescriptionContextBuilder;
use Illuminate\Support\Collection;
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
            'geometry_summary' => '58 linear ft · 8 sections · 1 gate',
            'sections_total' => 8,
            'totals_summary' => 'Materials: CA$1,200; Labour: CA$3,190; Grand total: CA$4,390',
            'materials_summary' => "Fence board pickets — CA$400\nPressure treated fence rails — CA$120\nPost caps — CA$40\nConcrete — CA$80\nAnti-sag kit",
        ]);

        $this->assertStringContainsString('Fence runs entered on this quote: 3', $text);
        $this->assertStringContainsString('Total length: 58 linear feet', $text);
        $this->assertStringContainsString('Run 1: 20 linear feet', $text);
        $this->assertStringContainsString('Run 2: 20 linear feet', $text);
        $this->assertStringContainsString('Run 3: 18 linear feet', $text);
        $this->assertStringContainsString('6 ft', $text);
        $this->assertStringContainsString('4x4', $text);
        $this->assertStringContainsString('standard board', $text);
        $this->assertStringContainsString('Gates: 1 (4 ft single)', $text);
        $this->assertStringContainsString('Post footings: concrete', $text);
        $this->assertStringContainsString('Fence board pickets', $text);
        $this->assertStringContainsString('Anti-sag kit', $text);

        $this->assertNoCalculatorSpanLeak($text, 8);
        $this->assertStringNotContainsString('Layout / geometry', $text);
        $this->assertStringNotContainsString('CA$', $text);
        $this->assertStringNotContainsString('Grand total', $text);
    }

    public function test_calculator_notes_from_20_20_18_gate_job_do_not_reach_context(): void
    {
        $calc = $this->calculator()->calculate([
            'sides' => [
                ['length_ft' => 20, 'gate_goes_here' => true],
                ['length_ft' => 20],
                ['length_ft' => 18],
            ],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 1,
            'post_size' => '4x4',
        ]);

        $spanCount = (int) $calc['geometry']['sections_total'];
        $this->assertSame(8, $spanCount);
        $this->assertSame(58.0, (float) $calc['geometry']['total_linear_ft']);

        $notes = collect($calc['materials'])->pluck('notes')->implode("\n");
        $this->assertMatchesRegularExpression('/per panel\s*\(\s*8\s*\)/i', $notes);
        $this->assertMatchesRegularExpression('/\(\s*2\s*\/\s*panel\s*\)/i', $notes);

        $leakyInnerText = collect($calc['materials'])->map(function (array $row): string {
            return trim(($row['name'] ?? '').' '.($row['notes'] ?? ''));
        })->implode("\n");

        $text = (new QuotationWorkDescriptionContextBuilder)->build('fence', [
            'fence_height_ft' => 6,
            'post_size' => '4x4',
            'board_style' => 'standard',
            'gate_count' => 1,
            'sides' => [
                ['length_ft' => 20, 'gate_goes_here' => true],
                ['length_ft' => 20],
                ['length_ft' => 18],
            ],
            'geometry_summary' => $calc['geometry']['total_linear_ft'].' linear ft · '.$spanCount.' sections · 1 gate',
            'sections_total' => $spanCount,
            'materials_summary' => $leakyInnerText,
        ]);

        $this->assertStringContainsString('Fence runs entered on this quote: 3', $text);
        $this->assertStringContainsString('board_6', $text);
        $this->assertStringContainsString('rail', $text);
        $this->assertStringContainsString('concrete', $text);
        $this->assertNoCalculatorSpanLeak($text, $spanCount);
    }

    private function assertNoCalculatorSpanLeak(string $text, int $spanCount): void
    {
        $this->assertDoesNotMatchRegularExpression('/\b'.$spanCount.'\s+sections?\b/i', $text);
        $this->assertDoesNotMatchRegularExpression('/per section\s*\(\s*'.$spanCount.'\s*\)/i', $text);
        $this->assertDoesNotMatchRegularExpression('/\(\s*\d+\s*\/\s*section\s*\)/i', $text);
        $this->assertDoesNotMatchRegularExpression('/\+\s*\d+\s+per section/i', $text);
        $this->assertDoesNotMatchRegularExpression('/per panel\s*\(\s*'.$spanCount.'\s*\)/i', $text);
        $this->assertDoesNotMatchRegularExpression('/\(\s*\d+\s*\/\s*panel\s*\)/i', $text);
        $this->assertDoesNotMatchRegularExpression('/\+\s*\d+\s+per panel/i', $text);
        $this->assertStringNotContainsStringIgnoringCase('pre-waste', $text);
        $this->assertStringNotContainsStringIgnoringCase('+1 waste', $text);
        $this->assertDoesNotMatchRegularExpression('/\beight\b/i', $text);
    }

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

        return new FenceQuotationCalculator($settings, $this->catalogue());
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
