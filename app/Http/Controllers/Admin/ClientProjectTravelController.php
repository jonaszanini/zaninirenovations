<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\CompanySetting;
use App\Models\Vehicle;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;

class ClientProjectTravelController extends Controller
{
    public function estimate(ClientProject $client_project, GoogleMapsService $maps): JsonResponse
    {
        $settings = CompanySetting::current();
        $vehicle = Vehicle::active();

        if (! $maps->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Google Maps API key is not configured. Add GOOGLE_MAPS_API_KEY to your environment.',
            ]);
        }

        if (! $maps->isDistanceMatrixConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Travel estimates need GOOGLE_MAPS_SERVER_API_KEY in .env — a separate key restricted by your Hostinger outbound IP with Distance Matrix API enabled. GOOGLE_MAPS_API_KEY stays on HTTP referrers for address autocomplete only. Then run php artisan config:clear.',
            ]);
        }

        if (! $settings->hasHomeCoordinates()) {
            return response()->json([
                'ok' => false,
                'message' => 'Set your home / office address on the Vehicles page (with a Google Places suggestion) to estimate travel.',
            ]);
        }

        if ($client_project->client_address_latitude === null || $client_project->client_address_longitude === null) {
            return response()->json([
                'ok' => false,
                'message' => 'This project needs a service address with map coordinates. Edit the project and pick an address from Google suggestions.',
            ]);
        }

        if (! $vehicle || ! $vehicle->hasFuelConsumption()) {
            return response()->json([
                'ok' => false,
                'message' => 'Add an active vehicle with L/100 km on the Vehicles page.',
            ]);
        }

        if (! $settings->hasGasPrice()) {
            return response()->json([
                'ok' => false,
                'message' => 'Set the company gas price on the Vehicles page.',
            ]);
        }

        $distance = $maps->roundTripDrivingDistance(
            (float) $settings->home_address_latitude,
            (float) $settings->home_address_longitude,
            (float) $client_project->client_address_latitude,
            (float) $client_project->client_address_longitude
        );

        if (! $distance['ok'] || $distance['round_trip_km'] === null) {
            return response()->json([
                'ok' => false,
                'message' => $distance['message'] ?? 'Could not calculate travel distance.',
            ]);
        }

        $gasPrice = (float) $settings->gas_price_per_liter;
        $outboundKm = (float) $distance['one_way_km'];
        $returnKm = (float) ($distance['return_km'] ?? 0);
        $outboundFuel = $vehicle->estimateFuelCost($outboundKm, $gasPrice);
        $returnFuel = $vehicle->estimateFuelCost($returnKm, $gasPrice);
        $fuelCost = $vehicle->estimateFuelCost((float) $distance['round_trip_km'], $gasPrice);

        return response()->json([
            'ok' => true,
            'one_way_km' => $distance['one_way_km'],
            'return_km' => $distance['return_km'] ?? null,
            'round_trip_km' => $distance['round_trip_km'],
            'outbound_duration_text' => $distance['outbound_duration_text'] ?? null,
            'return_duration_text' => $distance['return_duration_text'] ?? null,
            'duration_text' => $distance['duration_text'],
            'outbound_fuel_cost' => $outboundFuel,
            'outbound_fuel_cost_formatted' => $outboundFuel !== null
                ? ClientProject::formatMoney($outboundFuel)
                : null,
            'return_fuel_cost' => $returnFuel,
            'return_fuel_cost_formatted' => $returnFuel !== null
                ? ClientProject::formatMoney($returnFuel)
                : null,
            'fuel_cost' => $fuelCost,
            'fuel_cost_formatted' => $fuelCost !== null
                ? ClientProject::formatMoney($fuelCost)
                : null,
            'vehicle_name' => $vehicle->name,
            'gas_price' => $gasPrice,
            'fuel_consumption' => (float) $vehicle->fuel_consumption_l100km,
            'cached' => true,
        ]);
    }
}
