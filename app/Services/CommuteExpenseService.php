<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\CompanySetting;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class CommuteExpenseService
{
    public function __construct(private GoogleMapsService $maps) {}

    /**
     * Round-trip fuel cost for a project (home → site → home).
     *
     * @return array{ok: bool, fuel_cost: ?float, message?: string}
     */
    public function estimateForProject(ClientProject $project): array
    {
        $settings = CompanySetting::current();
        $vehicle = Vehicle::active();

        if (! $maps->isConfigured()) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'Google Maps API key is not configured.',
            ];
        }

        if (! $maps->isDistanceMatrixConfigured()) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'Travel estimates need GOOGLE_MAPS_SERVER_API_KEY in .env.',
            ];
        }

        if (! $settings->hasHomeCoordinates()) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'Set your home / office address on the Vehicles page.',
            ];
        }

        if ($project->client_address_latitude === null || $project->client_address_longitude === null) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'This project needs a service address with map coordinates.',
            ];
        }

        if (! $vehicle || ! $vehicle->hasFuelConsumption()) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'Add an active vehicle with L/100 km on the Vehicles page.',
            ];
        }

        if (! $settings->hasGasPrice()) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => 'Set the company gas price on the Vehicles page.',
            ];
        }

        $distance = $this->maps->roundTripDrivingDistance(
            (float) $settings->home_address_latitude,
            (float) $settings->home_address_longitude,
            (float) $project->client_address_latitude,
            (float) $project->client_address_longitude
        );

        if (! $distance['ok'] || $distance['round_trip_km'] === null) {
            return [
                'ok' => false,
                'fuel_cost' => null,
                'message' => $distance['message'] ?? 'Could not calculate travel distance.',
            ];
        }

        $gasPrice = (float) $settings->gas_price_per_liter;
        $fuelCost = $vehicle->estimateFuelCost((float) $distance['round_trip_km'], $gasPrice);

        return [
            'ok' => true,
            'fuel_cost' => $fuelCost !== null ? round($fuelCost, 2) : null,
        ];
    }

    public function roundTripFuelCost(?ClientProject $project): ?float
    {
        if (! $project) {
            return null;
        }

        try {
            $result = $this->estimateForProject($project);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Commute round-trip estimate failed', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        return $result['ok'] ? $result['fuel_cost'] : null;
    }

    public function calculateAmount(
        ?float $roundTripCost,
        bool $include,
        float $percent,
        int $trips
    ): float {
        if (! $include || $roundTripCost === null || $roundTripCost <= 0) {
            return 0.0;
        }

        $pct = max(0.0, min(999.0, $percent));
        $tripCount = max(0, $trips);
        if ($tripCount <= 0) {
            return 0.0;
        }

        return round($roundTripCost * ($pct / 100.0) * $tripCount, 2);
    }

    /**
     * @return array{
     *   include_commute: bool,
     *   commute_trip_percent: float,
     *   commute_estimated_trips: int,
     *   commute_round_trip_cost: ?float,
     *   commute_amount: float
     * }
     */
    public function persistenceFromRequest(Request $request, ?ClientProject $project, bool $isNew): array
    {
        $includeRaw = $request->input('include_commute');
        if ($request->has('include_commute')) {
            if (is_array($includeRaw)) {
                $include = in_array('1', $includeRaw, true) || in_array(1, $includeRaw, true);
            } else {
                $include = filter_var($includeRaw, FILTER_VALIDATE_BOOLEAN);
            }
        } else {
            $include = $isNew;
        }

        $percentRaw = $request->input('commute_trip_percent', 100);
        $percent = is_numeric($percentRaw) ? (float) $percentRaw : 100.0;
        $percent = max(0.0, min(999.0, $percent));

        $tripsRaw = $request->input('commute_estimated_trips', 1);
        $trips = is_numeric($tripsRaw) ? (int) $tripsRaw : 1;
        $trips = max(1, min(999, $trips));

        $roundTrip = $this->roundTripFuelCost($project);
        $postedRoundTrip = $request->input('commute_round_trip_cost');
        if ($roundTrip === null && is_numeric($postedRoundTrip)) {
            $posted = round((float) $postedRoundTrip, 2);
            $roundTrip = $posted > 0 ? $posted : null;
        }

        $amount = $this->calculateAmount($roundTrip, $include, $percent, $trips);

        return [
            'include_commute' => $include,
            'commute_trip_percent' => round($percent, 2),
            'commute_estimated_trips' => $trips,
            'commute_round_trip_cost' => $roundTrip,
            'commute_amount' => $amount,
        ];
    }

    /**
     * @return array{description: string, amount: float, apply_hst: bool, hst_included: bool}|null
     */
    public static function projectLineItem(float $amount, int $trips): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        $description = 'Commute expense';
        if ($trips > 1) {
            $description .= ' — '.$trips.' trips';
        }

        return [
            'description' => $description,
            'amount' => round($amount, 2),
            'apply_hst' => false,
            'hst_included' => true,
        ];
    }

    /** @return array<string, mixed> */
    public static function commuteValidationRules(): array
    {
        return [
            'include_commute' => ['nullable'],
            'commute_trip_percent' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'commute_estimated_trips' => ['nullable', 'integer', 'min:1', 'max:999'],
            'commute_round_trip_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
