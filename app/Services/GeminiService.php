<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiService
{
    /**
     * @throws RuntimeException
     */
    public function generate(string $systemInstruction, string $userText): string
    {
        return $this->requestText($systemInstruction, $userText);
    }

    /**
     * @param  array<string, mixed>  $responseSchema
     * @return array<string, mixed>
     *
     * @throws RuntimeException
     */
    public function generateStructuredJson(string $systemInstruction, string $userText, array $responseSchema): array
    {
        $text = $this->requestText($systemInstruction, $userText, [
            'responseMimeType' => 'application/json',
            'responseSchema' => $responseSchema,
        ]);

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON for structured output.');
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     *
     * @throws RuntimeException
     */
    private function requestText(string $systemInstruction, string $userText, array $generationConfig = []): string
    {
        $apiKey = trim((string) config('gemini.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $model = trim((string) config('gemini.model', 'gemini-2.5-flash'));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = (int) config('gemini.timeout_seconds', 120);

        $url = $baseUrl.'/models/'.$model.':generateContent?key='.urlencode($apiKey);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userText],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $systemInstruction],
                ],
            ],
        ];

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($url, $payload);

        if (!$response->successful()) {
            $message = $response->json('error.message')
                ?? $response->json('message')
                ?? ('Gemini API error (HTTP '.$response->status().').');

            throw new RuntimeException($message);
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!is_string($text) || trim($text) === '') {
            $reason = $response->json('candidates.0.finishReason');
            $block = $response->json('promptFeedback.blockReason');
            $detail = $reason ?: $block ?: 'empty response';

            throw new RuntimeException('Gemini returned no text ('.$detail.').');
        }

        return trim($text);
    }
}
