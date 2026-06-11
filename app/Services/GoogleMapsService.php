<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Support\Logging\AppLogger;

class GoogleMapsService
{
    public function isConfigured(): bool
    {
        return filled(config('google_maps.api_key'));
    }

    public function isDistanceMatrixConfigured(): bool
    {
        /** @see distanceMatrixApiKey — server key only; referrer-restricted browser keys cannot call Distance Matrix. */
        return filled(config('google_maps.server_api_key'));
    }

    /**
     * One-way driving distance in km and duration text from Distance Matrix API.
     *
     * @return array{ok: bool, distance_km: ?float, duration_text: ?string, duration_seconds: ?int, message: ?string}
     */
    public function drivingDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        if (! $this->isDistanceMatrixConfigured()) {
            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'Google Maps server API key is not configured.',
            ];
        }

        $cacheKey = sprintf(
            'google_dm:%s:%s:%s:%s',
            round($originLat, 5),
            round($originLng, 5),
            round($destLat, 5),
            round($destLng, 5)
        );

        $ttl = (int) config('google_maps.distance_cache_ttl', 86400);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetchDrivingDistance($originLat, $originLng, $destLat, $destLng);
        if ($result['ok']) {
            Cache::put($cacheKey, $result, $ttl);
        }

        return $result;
    }

    /**
     * Round-trip driving distance (km) — there and back.
     *
     * @return array{ok: bool, one_way_km: ?float, round_trip_km: ?float, duration_text: ?string, message: ?string}
     */
    public function roundTripDrivingDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        $outbound = $this->drivingDistance($originLat, $originLng, $destLat, $destLng);

        if (! $outbound['ok'] || $outbound['distance_km'] === null) {
            return [
                'ok' => false,
                'one_way_km' => null,
                'round_trip_km' => null,
                'duration_text' => null,
                'message' => $outbound['message'] ?? 'Could not calculate driving distance.',
            ];
        }

        $return = $this->drivingDistance($destLat, $destLng, $originLat, $originLng);

        if (! $return['ok'] || $return['distance_km'] === null) {
            return [
                'ok' => false,
                'one_way_km' => null,
                'round_trip_km' => null,
                'duration_text' => null,
                'message' => $return['message'] ?? 'Could not calculate return driving distance.',
            ];
        }

        $outboundKm = (float) $outbound['distance_km'];
        $returnKm = (float) $return['distance_km'];
        $outboundSeconds = (int) ($outbound['duration_seconds'] ?? 0);
        $returnSeconds = (int) ($return['duration_seconds'] ?? 0);
        $totalDurationSeconds = $outboundSeconds + $returnSeconds;

        return [
            'ok' => true,
            'one_way_km' => round($outboundKm, 1),
            'return_km' => round($returnKm, 1),
            'round_trip_km' => round($outboundKm + $returnKm, 1),
            'outbound_duration_text' => $this->formatDurationSeconds($outboundSeconds),
            'return_duration_text' => $this->formatDurationSeconds($returnSeconds),
            'duration_text' => $this->formatDurationSeconds($totalDurationSeconds, true),
            'message' => null,
        ];
    }

    private function formatDurationSeconds(int $totalSeconds, bool $roundTripLabel = false): ?string
    {
        if ($totalSeconds <= 0) {
            return null;
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $suffix = $roundTripLabel ? ' round trip' : '';

        if ($hours > 0 && $minutes > 0) {
            return sprintf('~%d hr %d min%s', $hours, $minutes, $suffix);
        }

        if ($hours > 0) {
            return sprintf('~%d hr%s', $hours, $suffix);
        }

        return sprintf('~%d min%s', max(1, $minutes), $suffix);
    }

    /** @return array{ok: bool, distance_km: ?float, duration_text: ?string, duration_seconds: ?int, message: ?string} */
    private function fetchDrivingDistance(
        float $originLat,
        float $originLng,
        float $destLat,
        float $destLng
    ): array {
        $origin = $originLat.','.$originLng;
        $destination = $destLat.','.$destLng;

        try {
            $response = Http::timeout(12)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                'origins' => $origin,
                'destinations' => $destination,
                'mode' => 'driving',
                'units' => 'metric',
                'key' => $this->distanceMatrixApiKey(),
            ]);
        } catch (\Throwable $e) {
            AppLogger::warning('google.distance_matrix.request_failed', [], $e);

            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'Could not reach Google Maps. Try again later.',
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'Google Maps returned an unexpected response.',
            ];
        }

        $payload = $response->json();
        $status = (string) ($payload['status'] ?? '');

        if ($status !== 'OK') {
            AppLogger::warning('google.distance_matrix.api_error', [
                'status' => $status,
                'error_message' => $payload['error_message'] ?? null,
            ]);

            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => $this->distanceMatrixErrorMessage($status, $payload),
            ];
        }

        $element = $payload['rows'][0]['elements'][0] ?? null;
        $elementStatus = (string) ($element['status'] ?? '');

        if ($elementStatus !== 'OK') {
            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'No driving route found between home and project address.',
            ];
        }

        $meters = (int) ($element['distance']['value'] ?? 0);
        $durationText = (string) ($element['duration']['text'] ?? '');
        $durationSeconds = (int) ($element['duration']['value'] ?? 0);

        return [
            'ok' => true,
            'distance_km' => round($meters / 1000, 2),
            'duration_text' => $durationText,
            'duration_seconds' => $durationSeconds,
            'message' => null,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function distanceMatrixErrorMessage(string $status, array $payload): string
    {
        $googleMessage = trim((string) ($payload['error_message'] ?? ''));

        if ($status === 'REQUEST_DENIED') {
            $hint = 'Verify GOOGLE_MAPS_SERVER_API_KEY in Google Cloud: enable Distance Matrix API; Application restrictions → IP addresses (your Hostinger server outbound IP); API restrictions → Distance Matrix. From SSH: `curl -s https://api.ipify.org` shows egress IP.';

            if ($googleMessage !== '') {
                return 'Google Distance Matrix: '.$googleMessage.' '.$hint;
            }

            return 'Google Distance Matrix rejected this request (REQUEST_DENIED). '.$hint;
        }

        if ($status === 'OVER_QUERY_LIMIT') {
            return 'Google Distance Matrix quota exceeded. Check billing and usage limits in Google Cloud Console.';
        }

        if ($googleMessage !== '') {
            return 'Distance could not be calculated: '.$googleMessage;
        }

        return 'Distance could not be calculated ('.$status.').';
    }

    /**
     * Distance Matrix requests originate from Laravel (server IP). They must use a key
     * restricted by IP — never the browser referrer key.
     */
    private function distanceMatrixApiKey(): ?string
    {
        $serverKey = config('google_maps.server_api_key');

        return filled($serverKey) ? $serverKey : null;
    }
}
