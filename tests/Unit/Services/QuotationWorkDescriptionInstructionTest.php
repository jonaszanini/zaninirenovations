<?php

namespace Tests\Unit\Services;

use App\Support\QuotationWorkDescriptionStyle;
use Tests\TestCase;

class QuotationWorkDescriptionInstructionTest extends TestCase
{
    public function test_from_quote_and_improve_instructions_share_style_rules_and_banned_list(): void
    {
        $rules = QuotationWorkDescriptionStyle::styleRulesForPrompt();
        $fromQuote = require database_path('data/ai_instruction_quotation_work_description_from_quote.php');
        $improve = require database_path('data/ai_instruction_quotation_work_description.php');

        $this->assertStringContainsString($rules, $fromQuote);
        $this->assertStringContainsString($rules, $improve);
        $this->assertStringContainsString('{{ quotation_context }}', $fromQuote);
        $this->assertStringNotContainsString('{{ contractor_notes }}', $fromQuote);
        $this->assertStringContainsString('{{ contractor_notes }}', $improve);

        $this->assertStringContainsString('contractor explaining the job to a homeowner', $rules);
        $this->assertStringContainsString('Short sentences', $rules);
        $this->assertStringContainsString('24 linear feet', $rules);
        $this->assertStringContainsString('The fence has 2 sections', $rules);
        $this->assertStringContainsString('Do not invent extra sections', $rules);
        $this->assertStringContainsString('fully insured', $rules);
        $this->assertStringContainsString('No sales pitch', $rules);
        $this->assertStringNotContainsString('58 linear feet', $rules);
        $this->assertStringNotContainsString('The fence has 3 sections', $rules);
        $this->assertStringNotContainsString('4 ft gate', $rules);

        foreach (QuotationWorkDescriptionStyle::BANNED_PHRASES as $phrase) {
            $this->assertStringContainsString(
                $phrase,
                $rules,
                'Style rules are missing banned phrase: '.$phrase
            );
            $this->assertStringContainsString($phrase, $fromQuote);
            $this->assertStringContainsString($phrase, $improve);
        }
    }
}
