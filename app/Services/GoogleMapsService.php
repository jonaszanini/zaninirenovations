<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleMapsService
{
    public function isConfigured(): bool
    {
        return filled(config('google_maps.api_key'));
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
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'Google Maps API key is not configured.',
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

        return Cache::remember($cacheKey, $ttl, function () use ($originLat, $originLng, $destLat, $destLng) {
            return $this->fetchDrivingDistance($originLat, $originLng, $destLat, $destLng);
        });
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
        $oneWay = $this->drivingDistance($originLat, $originLng, $destLat, $destLng);

        if (! $oneWay['ok'] || $oneWay['distance_km'] === null) {
            return [
                'ok' => false,
                'one_way_km' => null,
                'round_trip_km' => null,
                'duration_text' => null,
                'message' => $oneWay['message'] ?? 'Could not calculate driving distance.',
            ];
        }

        $oneWayKm = (float) $oneWay['distance_km'];

        return [
            'ok' => true,
            'one_way_km' => round($oneWayKm, 1),
            'round_trip_km' => round($oneWayKm * 2, 1),
            'duration_text' => $oneWay['duration_text'],
            'message' => null,
        ];
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
                'key' => config('google_maps.api_key'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google Distance Matrix request failed', ['message' => $e->getMessage()]);

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
            Log::warning('Google Distance Matrix API error', ['status' => $status, 'body' => $payload]);

            return [
                'ok' => false,
                'distance_km' => null,
                'duration_text' => null,
                'duration_seconds' => null,
                'message' => 'Distance could not be calculated ('.$status.').',
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
}
