<?php

namespace App\Services;

use App\Models\AiInstruction;
use RuntimeException;

class QuotationWorkDescriptionAiService
{
    public function __construct(
        protected GeminiService $gemini,
    ) {}

    public function suggest(string $contractorNotes): string
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION);
        if (! $instruction) {
            throw new RuntimeException('Quotation work description AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $contractorNotes = trim($contractorNotes);
        if ($contractorNotes === '') {
            throw new RuntimeException('Enter a brief description first.');
        }

        $systemInstruction = str_replace(
            '{{ contractor_notes }}',
            $contractorNotes,
            $instruction->instruction,
        );

        $text = trim($this->gemini->generate(
            $systemInstruction,
            'Rewrite the contractor notes into the description of the work.',
        ));

        if ($text === '') {
            throw new RuntimeException('Gemini did not return a description.');
        }

        return $this->normalizeParagraph($text);
    }

    private function normalizeParagraph(string $text): string
    {
        $text = preg_replace('/^```(?:\w+)?\s*|\s*```$/u', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B\"'");

        $lines = preg_split('/\R+/u', $text) ?: [];
        $parts = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        return trim(implode(' ', $parts));
    }
}
