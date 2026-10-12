<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Support\QuotationWorkDescriptionStyle;
use RuntimeException;

class QuotationWorkDescriptionAiService
{
    public function __construct(
        protected GeminiService $gemini,
        protected QuotationWorkDescriptionContextBuilder $contextBuilder,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $contextPayload
     */
    public function suggest(string $contractorNotes, ?string $quotationType = null, ?array $contextPayload = null): string
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION);
        if (! $instruction) {
            throw new RuntimeException('Quotation work description AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $contractorNotes = trim($contractorNotes);
        $quotationContext = $this->contextBuilder->build($quotationType, $contextPayload);

        if ($contractorNotes === '' && $quotationContext === '') {
            throw new RuntimeException('Enter a brief description or fill in quotation details first.');
        }

        if ($contractorNotes === '') {
            $contractorNotes = 'Draft a professional description of the work using the quotation parameters below.';
        }

        $systemInstruction = str_replace(
            '{{ contractor_notes }}',
            $contractorNotes,
            $instruction->instruction,
        );

        if (str_contains($systemInstruction, '{{ quotation_context }}')) {
            $systemInstruction = str_replace(
                '{{ quotation_context }}',
                $quotationContext !== '' ? $quotationContext : '(No additional quotation parameters provided.)',
                $systemInstruction,
            );
        } elseif ($quotationContext !== '') {
            $systemInstruction .= "\n\n### Quotation parameters (use to make the description complete; do not invent facts beyond this):\n"
                .$quotationContext;
        }

        $text = trim($this->gemini->generate(
            $systemInstruction,
            'Rewrite the contractor notes into the description of the work.',
        ));

        if ($text === '') {
            throw new RuntimeException('Gemini did not return a description.');
        }

        return $this->normalizeParagraph($text);
    }

    /**
     * Draft a work description from structured quotation fields only (no contractor notes).
     *
     * @param  array<string, mixed>|null  $contextPayload
     */
    public function composeFromQuotation(string $quotationType, ?array $contextPayload = null): string
    {
        $type = trim($quotationType);
        if ($type === '' || $type === 'standard') {
            throw new RuntimeException('Create automatically from quotation is not available for this quotation type.');
        }

        $instruction = AiInstruction::forKey(AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION_FROM_QUOTE);
        if (! $instruction) {
            throw new RuntimeException('Quotation-from-quote AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $quotationContext = $this->contextBuilder->build($type, $contextPayload);
        if ($quotationContext === '') {
            throw new RuntimeException('Fill in quotation details first, then click Create automatically from quotation.');
        }

        $systemInstruction = str_replace(
            '{{ quotation_context }}',
            $quotationContext,
            $instruction->instruction,
        );

        $text = trim($this->gemini->generate(
            $systemInstruction,
            'Write the description of the work from the quotation parameters.',
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

        return QuotationWorkDescriptionStyle::apply(trim(implode(' ', $parts)));
    }
}
