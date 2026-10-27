<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\SupplierSite;

class SupplierSiteDistanceService
{
    public function __construct(private GoogleMapsService $maps) {}

    /**
     * @return array{ok: bool, distance_km: ?float, drive_minutes: ?int, message: ?string}
     */
    public function drivingFromHome(string $destinationAddress): array
    {
        $settings = CompanySetting::current();

        if (! $this->maps->isDistanceMatrixConfigured()) {
            return [
                'ok' => false,
                'distance_km' => null,
                'drive_minutes' => null,
                'message' => 'Travel estimates need GOOGLE_MAPS_SERVER_API_KEY in .env.',
            ];
        }

        if (! $settings->hasHomeCoordinates()) {
            return [
                'ok' => false,
                'distance_km' => null,
                'drive_minutes' => null,
                'message' => 'Set your home / office address on the Vehicles page.',
            ];
        }

        $result = $this->maps->drivingDistanceToAddress(
            (float) $settings->home_address_latitude,
            (float) $settings->home_address_longitude,
            $destinationAddress
        );

        if (! $result['ok'] || $result['distance_km'] === null) {
            return [
                'ok' => false,
                'distance_km' => null,
                'drive_minutes' => null,
                'message' => $result['message'] ?? 'Could not calculate driving distance.',
            ];
        }

        $seconds = (int) ($result['duration_seconds'] ?? 0);
        $minutes = $seconds > 0 ? (int) max(1, (int) round($seconds / 60)) : null;

        return [
            'ok' => true,
            'distance_km' => (float) $result['distance_km'],
            'drive_minutes' => $minutes,
            'message' => null,
        ];
    }

    public function refresh(SupplierSite $site): bool
    {
        $address = trim((string) $site->address);
        if ($address === '') {
            $site->forceFill([
                'distance_km' => null,
                'drive_minutes' => null,
                'distance_checked_at' => null,
            ])->save();

            return false;
        }

        $result = $this->drivingFromHome($address);
        if (! $result['ok']) {
            $site->forceFill([
                'distance_km' => null,
                'drive_minutes' => null,
                'distance_checked_at' => now(),
            ])->save();

            return false;
        }

        $site->forceFill([
            'distance_km' => $result['distance_km'],
            'drive_minutes' => $result['drive_minutes'],
            'distance_checked_at' => now(),
        ])->save();

        return true;
    }

    public function clearDistanceIfAddressChanged(SupplierSite $site, ?string $previousAddress): void
    {
        $old = trim((string) $previousAddress);
        $new = trim((string) $site->address);

        if ($old === $new) {
            return;
        }

        $site->forceFill([
            'distance_km' => null,
            'drive_minutes' => null,
            'distance_checked_at' => null,
        ])->save();
    }

    /**
     * @param  list<string>  $addresses
     * @return array{address: string, distance_km: float, drive_minutes: ?int}|null
     */
    public function pickClosestAddress(array $addresses): ?array
    {
        $unique = [];
        foreach ($addresses as $address) {
            $trimmed = trim($address);
            if ($trimmed === '') {
                continue;
            }
            $key = mb_strtolower($trimmed);
            if (! isset($unique[$key])) {
                $unique[$key] = $trimmed;
            }
        }

        if ($unique === []) {
            return null;
        }

        $best = null;
        foreach ($unique as $address) {
            $result = $this->drivingFromHome($address);
            if (! $result['ok'] || $result['distance_km'] === null) {
                continue;
            }
            if ($best === null || $result['distance_km'] < $best['distance_km']) {
                $best = [
                    'address' => $address,
                    'distance_km' => $result['distance_km'],
                    'drive_minutes' => $result['drive_minutes'],
                ];
            }
        }

        return $best;
    }
}
