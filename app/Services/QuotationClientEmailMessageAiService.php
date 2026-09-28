<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Support\QuotationClientEmailMessage;
use App\Support\QuotationWorkDescriptionHtml;
use App\Support\SimpleMarkdownHtml;
use RuntimeException;

/**
 * Refines the admin-written client email note: professional, plain, no fancy wording.
 */
class QuotationClientEmailMessageAiService
{
    public function __construct(protected GeminiService $gemini) {}

    public function refine(string $message): string
    {
        $plain = trim(html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($plain === '') {
            throw new RuntimeException('Write a message first, then click Improve with AI.');
        }

        $instruction = AiInstruction::forKey(AiInstruction::KEY_QUOTATION_CLIENT_EMAIL_MESSAGE);
        if (! $instruction) {
            throw new RuntimeException('Client email message AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $system = str_contains($instruction->instruction, '{{ message }}')
            ? str_replace('{{ message }}', $message, $instruction->instruction)
            : $instruction->instruction."\n\nContractor's text:\n".$message;

        $text = trim($this->gemini->generate($system, 'Refine the contractor\'s text for the client email.'));
        $text = trim(preg_replace('/^```(?:\w+)?\s*|\s*```$/u', '', $text) ?? $text);
        if ($text === '') {
            throw new RuntimeException('Gemini did not return any text.');
        }

        if (! QuotationWorkDescriptionHtml::isHtml($text)) {
            $text = SimpleMarkdownHtml::convert($text);
        }

        $html = QuotationClientEmailMessage::normalize($text);
        if ($html === null) {
            throw new RuntimeException('Gemini did not return any text.');
        }

        return $html;
    }
}
