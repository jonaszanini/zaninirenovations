<?php

namespace Tests\Unit\Services;

use App\Models\AiInstruction;
use App\Models\FenceFormulaSetting;
use App\Models\FenceMaterial;
use App\Services\FenceQuotationCalculator;
use App\Services\GeminiService;
use App\Services\QuotationWorkDescriptionAiService;
use App\Services\QuotationWorkDescriptionContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationWorkDescriptionAiServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_from_quotation_does_not_send_eight_sections_for_three_runs(): void
    {
        $this->assertNotNull(AiInstruction::forKey(AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION_FROM_QUOTE));

        $captured = '';
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generate')->willReturnCallback(function (string $system) use (&$captured) {
            $captured = $system;

            return 'This quote is for installing about 58 linear feet of 6 ft high board-style fence. The fence has 3 sections.';
        });

        $service = new QuotationWorkDescriptionAiService(
            $gemini,
            new QuotationWorkDescriptionContextBuilder(),
        );

        $text = $service->composeFromQuotation('fence', $this->threeRunFencePayload());

        $this->assertStringContainsString('Fence sections (runs entered on this quote): 3', $captured);
        $this->assertDoesNotMatchRegularExpression('/\b8\s+sections?\b/i', $captured);
        $this->assertDoesNotMatchRegularExpression('/per section\s*\(\s*8\s*\)/i', $captured);
        $this->assertDoesNotMatchRegularExpression('/\(\s*\d+\s*\/\s*section\s*\)/i', $captured);
        $this->assertStringContainsString('3 sections', $text);
        $this->assertStringNotContainsStringIgnoringCase('precision installation', $text);
    }

    public function test_compose_from_quotation_runs_style_post_processor(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generate')->willReturn(
            'This project encompasses the precision installation of 58 linear feet of fence.'
        );

        $service = new QuotationWorkDescriptionAiService(
            $gemini,
            new QuotationWorkDescriptionContextBuilder(),
        );

        $text = $service->composeFromQuotation('fence', $this->threeRunFencePayload());

        $this->assertStringContainsString('includes the installation', $text);
        $this->assertStringNotContainsStringIgnoringCase('encompasses', $text);
        $this->assertStringNotContainsStringIgnoringCase('precision installation', $text);
    }

    public function test_gemini_prompt_does_not_include_calculator_span_notes_for_20_20_18_gate_job(): void
    {
        $calc = $this->fenceCalculator()->calculate([
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

        $leakyInnerText = collect($calc['materials'])->map(function (array $row): string {
            return trim(($row['name'] ?? '').' '.($row['notes'] ?? ''));
        })->implode("\n");
        $this->assertMatchesRegularExpression('/per section\s*\(\s*8\s*\)/i', $leakyInnerText);

        $captured = '';
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generate')->willReturnCallback(function (string $system) use (&$captured) {
            $captured = $system;

            return 'This quote is for installing about 58 linear feet of 6 ft high board-style fence. The fence has 3 sections.';
        });

        $service = new QuotationWorkDescriptionAiService(
            $gemini,
            new QuotationWorkDescriptionContextBuilder(),
        );

        $service->composeFromQuotation('fence', [
            'fence_height_ft' => 6,
            'post_size' => '4x4',
            'board_style' => 'standard',
            'gate_count' => 1,
            'sides' => [
                ['length_ft' => 20, 'gate_goes_here' => true],
                ['length_ft' => 20],
                ['length_ft' => 18],
            ],
            'geometry_summary' => $calc['geometry']['total_linear_ft'].' linear ft · '.$spanCount.' sections',
            'materials_summary' => $leakyInnerText,
        ]);

        $this->assertStringContainsString('Fence sections (runs entered on this quote): 3', $captured);
        $this->assertDoesNotMatchRegularExpression('/\b8\s+sections?\b/i', $captured);
        $this->assertDoesNotMatchRegularExpression('/per section\s*\(\s*8\s*\)/i', $captured);
        $this->assertDoesNotMatchRegularExpression('/\(\s*\d+\s*\/\s*section\s*\)/i', $captured);
        $this->assertDoesNotMatchRegularExpression('/\+\s*\d+\s+per section/i', $captured);
    }

    /**
     * @return array<string, mixed>
     */
    private function threeRunFencePayload(): array
    {
        return [
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
            'materials_summary' => "Fence board pickets\nPressure treated fence rails\nPost caps\nConcrete\nAnti-sag kit",
        ];
    }

    private function fenceCalculator(): FenceQuotationCalculator
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

        $make = function (string $key, float $price, ?int $qtyPerBox = null): FenceMaterial {
            return new FenceMaterial([
                'key' => $key,
                'name' => $key,
                'price_cad' => $price,
                'quantity_per_box' => $qtyPerBox,
            ]);
        };

        $catalogue = collect([
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
            $make(FenceMaterial::KEY_GATE_HARDWARE, 25),
        ])->keyBy('key');

        return new FenceQuotationCalculator($settings, $catalogue);
    }
}
