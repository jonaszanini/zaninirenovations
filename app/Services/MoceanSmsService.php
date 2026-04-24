<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for Mocean REST v2 outbound SMS (form POST + Bearer token).
 */
class MoceanSmsService
{
    public function isConfigured(): bool
    {
        return filled(config('mocean.api_token'));
    }

    /**
     * @param  string  $toDigits  E.164 digits only (e.g. 16478347570), no "+" or spaces.
     * @return array{ok: bool, detail: string}
     */
    public function send(string $toDigits, string $text): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'detail' => 'Mocean is not configured (missing MOCEAN_API_TOKEN).'];
        }

        $to = preg_replace('/\D/', '', $toDigits);
        if ($to === '') {
            return ['ok' => false, 'detail' => 'Recipient phone has no digits.'];
        }

        $token = (string) config('mocean.api_token');
        $from = (string) config('mocean.from', 'MOCEAN');
        $url = (string) config('mocean.send_url');

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                ])
                ->asForm()
                ->post($url, [
                    'mocean-from' => $from,
                    'mocean-to' => $to,
                    'mocean-text' => $text,
                    'mocean-resp-format' => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::error('Mocean SMS request failed', ['message' => $e->getMessage()]);

            return ['ok' => false, 'detail' => $e->getMessage()];
        }

        if (!$response->successful()) {
            Log::warning('Mocean SMS HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ok' => false, 'detail' => 'HTTP '.$response->status().': '.$response->body()];
        }

        $data = $response->json();
        if (!is_array($data)) {
            Log::warning('Mocean SMS unexpected body', ['body' => $response->body()]);

            return ['ok' => false, 'detail' => 'Invalid Mocean response body.'];
        }

        $status = $data['messages'][0]['status'] ?? null;
        if ((int) $status !== 0) {
            $err = $data['messages'][0]['err_msg'] ?? json_encode($data);

            Log::warning('Mocean SMS rejected', ['response' => $data]);

            return ['ok' => false, 'detail' => $err];
        }

        return ['ok' => true, 'detail' => 'Mocean status 0'];
    }
}
