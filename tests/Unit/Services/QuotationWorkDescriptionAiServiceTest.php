<?php

namespace Tests\Unit\Services;

use App\Models\AiInstruction;
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
}
