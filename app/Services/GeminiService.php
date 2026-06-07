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

    /**
     * Generate a single image from a text prompt (Gemini image models).
     *
     * @return array{bytes: string, mime: string}
     *
     * @throws RuntimeException
     */
    public function generateImage(string $prompt): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Image prompt is empty.');
        }

        $apiKey = trim((string) config('gemini.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $model = trim((string) config('gemini.image_model', 'gemini-3.1-flash-image-preview'));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = (int) config('gemini.image_timeout_seconds', 180);

        $url = $baseUrl.'/models/'.$model.':generateContent?key='.urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => '16:9',
                    ],
                ],
            ]);

        if (!$response->successful()) {
            $message = $response->json('error.message')
                ?? $response->json('message')
                ?? ('Gemini image API error (HTTP '.$response->status().').');

            throw new RuntimeException($message);
        }

        $inline = $this->extractInlineImageData($response->json());
        if ($inline === null) {
            $reason = $response->json('candidates.0.finishReason');
            $block = $response->json('promptFeedback.blockReason');
            $detail = $reason ?: $block ?: 'no image data';

            throw new RuntimeException('Gemini returned no image ('.$detail.').');
        }

        $bytes = base64_decode($inline['data'], true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Gemini returned invalid image data.');
        }

        return [
            'bytes' => $bytes,
            'mime' => $inline['mime'],
        ];
    }

    /**
     * @param  mixed  $payload
     * @return array{data: string, mime: string}|null
     */
    private function extractInlineImageData($payload): ?array
    {
        if (!is_array($payload)) {
            return null;
        }

        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return null;
        }

        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (!is_array($inline)) {
                continue;
            }

            $data = $inline['data'] ?? null;
            if (!is_string($data) || $data === '') {
                continue;
            }

            $mime = $inline['mimeType'] ?? $inline['mime_type'] ?? 'image/jpeg';

            return [
                'data' => $data,
                'mime' => is_string($mime) ? $mime : 'image/jpeg',
            ];
        }

        return null;
    }
}
