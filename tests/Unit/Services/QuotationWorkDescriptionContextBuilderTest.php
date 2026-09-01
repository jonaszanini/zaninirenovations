<?php

namespace Tests\Unit\Services;

use App\Services\QuotationWorkDescriptionContextBuilder;
use Tests\TestCase;

class QuotationWorkDescriptionContextBuilderTest extends TestCase
{
    public function test_shed_context_uses_sq_ft_labels_and_skips_empty_size(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('shed', [
            'client_name' => 'Jason Costantini',
            'shed_type' => 'gable',
            'length_ft' => 13,
            'width_ft' => 3,
            'wall_height_ft' => 6.75,
            'roof_type' => 'weather_coat',
            'siding' => 'none',
            'foundation' => 'blocks',
            'area_label' => '39 sq ft',
        ]);

        $this->assertStringContainsString('Custom shed', $text);
        $this->assertStringContainsString('13 ft × 3 ft', $text);
        $this->assertStringContainsString('39 sq ft', $text);
        $this->assertStringContainsString('Weather coat', $text);
        $this->assertStringContainsString('Not now', $text);
        $this->assertStringContainsString('Deck blocks', $text);
        $this->assertStringNotContainsString('m²', $text);
    }

    public function test_from_quote_instruction_file_has_context_placeholder(): void
    {
        $instruction = require database_path('data/ai_instruction_quotation_work_description_from_quote.php');

        $this->assertStringContainsString('{{ quotation_context }}', $instruction);
        $this->assertStringContainsString('Do not mention prices', $instruction);
        $this->assertStringNotContainsString('{{ contractor_notes }}', $instruction);
    }
}
