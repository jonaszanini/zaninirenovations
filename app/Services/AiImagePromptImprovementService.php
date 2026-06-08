<?php

namespace App\Services;

use App\Models\AiInstruction;
use RuntimeException;

class AiImagePromptImprovementService
{
    public function __construct(
        protected GeminiService $gemini,
    ) {}

    /**
     * @return array{critique: string, optimized_prompt: string, why_better: string}
     */
    public function improve(string $prompt): array
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_IMAGE_MODIFY_PROMPT);
        if (! $instruction) {
            throw new RuntimeException('Image modify prompt AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Enter edit instructions before improving the prompt.');
        }

        $userText = "Please analyze the following prompt:\n\n\"{$prompt}\"";

        $raw = $this->gemini->generateStructuredJson(
            $instruction->instruction,
            $userText,
            $this->responseSchema(),
        );

        $optimized = $this->stringField($raw, 'optimized_prompt');
        if ($optimized === '') {
            throw new RuntimeException('Gemini did not return an optimized prompt.');
        }

        if (strlen($optimized) > 8000) {
            $optimized = mb_substr($optimized, 0, 8000);
        }

        return [
            'critique' => $this->stringField($raw, 'critique'),
            'optimized_prompt' => $optimized,
            'why_better' => $this->stringField($raw, 'why_better'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'critique' => [
                    'type' => 'STRING',
                    'description' => 'Section 1: Critique and gaps in the original prompt.',
                ],
                'optimized_prompt' => [
                    'type' => 'STRING',
                    'description' => 'Section 2: The fully rewritten, ready-to-use optimized prompt.',
                ],
                'why_better' => [
                    'type' => 'STRING',
                    'description' => 'Section 3: Why the optimized prompt is better.',
                ],
            ],
            'required' => ['critique', 'optimized_prompt', 'why_better'],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function stringField(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }
}
