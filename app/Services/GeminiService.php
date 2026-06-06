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
        $apiKey = trim((string) config('gemini.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $model = trim((string) config('gemini.model', 'gemini-2.5-flash'));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = (int) config('gemini.timeout_seconds', 120);

        $url = $baseUrl.'/models/'.$model.':generateContent?key='.urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($url, [
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
            ]);

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

            throw new RuntimeException('Gemini returned no contract text ('.$detail.').');
        }

        return trim($text);
    }
}
