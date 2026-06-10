<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Local Ollama REST API — used as fallback when CSS/JSON-LD parsing fails.
 */
class OllamaService
{
    /**
     * @return array<string, mixed>
     */
    public function extractProductFromHtmlSnippet(string $htmlSnippet, string $pageUrl): array
    {
        if (! config('materials.ollama.enabled', true)) {
            throw new RuntimeException('Ollama is disabled (OLLAMA_ENABLED=false).');
        }

        $model = (string) config('materials.ollama.model', 'qwen3:14b');
        $baseUrl = (string) config('materials.ollama.base_url', 'http://127.0.0.1:11434');
        $timeout = max(30, (int) config('materials.ollama.timeout_seconds', 120));

        $prompt = <<<PROMPT
Extract product data from this Canadian retail HTML snippet. Page URL: {$pageUrl}

Return ONLY valid JSON with these keys (use null when missing):
- name (string)
- sku (string)
- price_cad (number, CAD dollars without currency symbol)
- image_url (string absolute URL or null)
- model (string or null)

HTML snippet:
{$htmlSnippet}
PROMPT;

        $response = Http::timeout($timeout)
            ->post($baseUrl.'/api/generate', [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Ollama HTTP '.$response->status().': '.$response->body());
        }

        $text = (string) ($response->json('response') ?? '');
        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Ollama returned invalid JSON.');
        }

        return $decoded;
    }

    public function isReachable(): bool
    {
        if (! config('materials.ollama.enabled', true)) {
            return false;
        }

        $baseUrl = (string) config('materials.ollama.base_url', 'http://127.0.0.1:11434');

        try {
            return Http::timeout(3)->get($baseUrl.'/api/tags')->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
