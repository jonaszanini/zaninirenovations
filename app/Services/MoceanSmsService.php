<?php

namespace App\Services;

use App\Support\Logging\AppLogger;
use Illuminate\Support\Facades\Http;

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
        if (! $this->isConfigured()) {
            return ['ok' => false, 'detail' => 'Mocean is not configured (missing MOCEAN_API_TOKEN).'];
        }

        $to = preg_replace('/\D/', '', $toDigits);
        if ($to === '') {
            return ['ok' => false, 'detail' => 'Recipient phone has no digits.'];
        }

        $from = $this->normalizeFrom((string) config('mocean.from', 'MOCEAN'));
        $url = (string) config('mocean.send_url');

        try {
            $response = Http::connectTimeout(15)
                ->timeout(30)
                ->retry(3, 2000, throw: false)
                ->withHeaders([
                    'Authorization' => 'Bearer '.(string) config('mocean.api_token'),
                ])
                ->asForm()
                ->post($url, [
                    'mocean-from' => $from,
                    'mocean-to' => $to,
                    'mocean-text' => $text,
                    'mocean-resp-format' => 'json',
                ]);
        } catch (\Throwable $e) {
            AppLogger::error('mocean.sms.request_failed', [
                'to_last4' => substr($to, -4),
            ], $e);

            return ['ok' => false, 'detail' => $e->getMessage()];
        }

        if (! $response->successful()) {
            AppLogger::warning('mocean.sms.http_error', [
                'status' => $response->status(),
                'to_last4' => substr($to, -4),
            ]);

            return ['ok' => false, 'detail' => 'HTTP '.$response->status()];
        }

        $data = $response->json();
        if (! is_array($data)) {
            AppLogger::warning('mocean.sms.invalid_response', [
                'to_last4' => substr($to, -4),
            ]);

            return ['ok' => false, 'detail' => 'Invalid Mocean response body.'];
        }

        $status = $data['messages'][0]['status'] ?? null;
        if ((int) $status !== 0) {
            $err = $data['messages'][0]['err_msg'] ?? 'Mocean rejected the message';

            AppLogger::warning('mocean.sms.rejected', [
                'mocean_status' => $status,
                'to_last4' => substr($to, -4),
                'detail' => $err,
            ]);

            return ['ok' => false, 'detail' => $err];
        }

        return ['ok' => true, 'detail' => 'Mocean status 0'];
    }

    /**
     * Mocean accepts alphanumeric sender IDs as-is, or numeric IDs with
     * country code and no punctuation (e.g. 18559154845).
     */
    private function normalizeFrom(string $from): string
    {
        $from = trim($from);
        if ($from === '') {
            return 'MOCEAN';
        }
        if (! preg_match('/[A-Za-z]/', $from)) {
            $digits = preg_replace('/\D/', '', $from);

            return $digits !== '' ? $digits : $from;
        }

        return $from;
    }
}
