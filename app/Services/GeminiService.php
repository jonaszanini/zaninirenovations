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
    public function generateImage(string $prompt, string $aspectRatio = '16:9', string $imageSize = '1K'): array
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
                        'aspectRatio' => $aspectRatio,
                        'imageSize' => $imageSize,
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
     * Edit an existing image from a text prompt (Gemini image models).
     *
     * @return array{bytes: string, mime: string}
     *
     * @throws RuntimeException
     */
    public function modifyImage(string $prompt, string $imageBytes, string $mime): array
    {
        $prompt = trim($prompt);
        if ($prompt === '') {
            throw new RuntimeException('Edit prompt is empty.');
        }

        if ($imageBytes === '') {
            throw new RuntimeException('Source image is empty.');
        }

        $mime = strtolower(trim($mime));
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $apiKey = trim((string) config('gemini.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured. Add GEMINI_API_KEY to your .env file.');
        }

        $model = trim((string) config('gemini.image_model', 'gemini-3.1-flash-image-preview'));
        $baseUrl = rtrim((string) config('gemini.base_url'), '/');
        $timeout = (int) config('gemini.image_timeout_seconds', 180);
        $inlineMax = max(1, (int) config('gemini.inline_image_max_bytes', 7 * 1024 * 1024));

        $imagePart = strlen($imageBytes) <= $inlineMax
            ? [
                'inlineData' => [
                    'mimeType' => $mime,
                    'data' => base64_encode($imageBytes),
                ],
            ]
            : [
                'fileData' => $this->uploadImageForGemini($imageBytes, $mime, $apiKey, $baseUrl, $timeout),
            ];

        $url = $baseUrl.'/models/'.$model.':generateContent?key='.urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($url, [
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $prompt],
                            $imagePart,
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['IMAGE'],
                ],
            ]);

        if (! $response->successful()) {
            $message = $response->json('error.message')
                ?? $response->json('message')
                ?? ('Gemini image edit API error (HTTP '.$response->status().').');

            throw new RuntimeException($message);
        }

        $inline = $this->extractInlineImageData($response->json());
        if ($inline === null) {
            $reason = $response->json('candidates.0.finishReason');
            $block = $response->json('promptFeedback.blockReason');
            $detail = $reason ?: $block ?: 'no image data';

            throw new RuntimeException('Gemini returned no edited image ('.$detail.').');
        }

        $bytes = base64_decode($inline['data'], true);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Gemini returned invalid edited image data.');
        }

        return [
            'bytes' => $bytes,
            'mime' => $inline['mime'],
        ];
    }

    /**
     * @return array{mimeType: string, fileUri: string}
     */
    private function uploadImageForGemini(
        string $imageBytes,
        string $mime,
        string $apiKey,
        string $baseUrl,
        int $timeout,
    ): array {
        $displayName = 'source-'.bin2hex(random_bytes(6)).'.'.$this->extensionForMime($mime);
        $uploadUrl = $baseUrl.'/upload/v1beta/files?key='.urlencode($apiKey);

        $response = Http::timeout($timeout)
            ->asMultipart()
            ->attach(
                'metadata',
                json_encode(['file' => ['displayName' => $displayName]], JSON_THROW_ON_ERROR),
                'metadata.json',
                ['Content-Type' => 'application/json'],
            )
            ->attach('file', $imageBytes, $displayName, ['Content-Type' => $mime])
            ->post($uploadUrl);

        if (! $response->successful()) {
            $message = $response->json('error.message')
                ?? $response->json('message')
                ?? ('Gemini file upload error (HTTP '.$response->status().').');

            throw new RuntimeException($message);
        }

        $fileUri = $response->json('file.uri');
        $fileName = $response->json('file.name');
        $fileMime = $response->json('file.mimeType') ?? $mime;

        if (! is_string($fileUri) || $fileUri === '') {
            throw new RuntimeException('Gemini file upload did not return a file URI.');
        }

        if (is_string($fileName) && $fileName !== '') {
            $this->waitForGeminiFileActive($fileName, $apiKey, $baseUrl, $timeout);
        }

        return [
            'mimeType' => is_string($fileMime) && $fileMime !== '' ? $fileMime : $mime,
            'fileUri' => $fileUri,
        ];
    }

    private function waitForGeminiFileActive(string $fileName, string $apiKey, string $baseUrl, int $timeout): void
    {
        $statusUrl = $baseUrl.'/'.ltrim($fileName, '/').'?key='.urlencode($apiKey);
        $deadline = time() + min($timeout, 120);

        while (time() < $deadline) {
            $response = Http::timeout(30)->acceptJson()->get($statusUrl);
            if (! $response->successful()) {
                break;
            }

            $state = $response->json('state') ?? $response->json('file.state');
            if ($state === 'ACTIVE') {
                return;
            }

            if ($state === 'FAILED') {
                throw new RuntimeException('Gemini could not process the uploaded source image.');
            }

            usleep(500_000);
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
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
