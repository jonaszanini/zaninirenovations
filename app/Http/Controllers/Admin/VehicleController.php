<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\CompanySetting;
use App\Models\Vehicle;
use App\Services\GoogleMapsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index(GoogleMapsService $maps)
    {
        $vehicles = Vehicle::query()->orderByDesc('is_active')->orderBy('name')->get();
        $settings = CompanySetting::current();
        $activeVehicle = Vehicle::active();

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'settings' => $settings,
            'activeVehicle' => $activeVehicle,
            'mapsConfigured' => $maps->isConfigured(),
        ]);
    }

    public function create()
    {
        return view('admin.vehicles.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateVehicle($request);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        DB::transaction(function () use ($data, $request) {
            $shouldActivate = (bool) ($data['is_active'] ?? false);
            unset($data['is_active']);

            $vehicle = Vehicle::create($data + ['is_active' => false]);
            $this->rememberSubmitSucceeded($request);

            if ($shouldActivate || Vehicle::query()->where('is_active', true)->doesntExist()) {
                $vehicle->activate();
            }
        });

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Vehicle added.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('admin.vehicles.edit', compact('vehicle'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $this->validateVehicle($request);

        DB::transaction(function () use ($vehicle, $data) {
            $shouldActivate = (bool) ($data['is_active'] ?? false);
            unset($data['is_active']);

            $vehicle->update($data + ['is_active' => $shouldActivate]);

            if ($shouldActivate) {
                $vehicle->activate();
            }
        });

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $wasActive = $vehicle->is_active;
        $vehicle->delete();

        if ($wasActive) {
            $next = Vehicle::query()->orderBy('name')->first();
            if ($next) {
                $next->activate();
            }
        }

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Vehicle removed.');
    }

    public function activate(Vehicle $vehicle)
    {
        $vehicle->activate();

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', $vehicle->name.' is now the active vehicle.');
    }

    public function updateHomeAddress(Request $request)
    {
        $settings = CompanySetting::current();

        $data = $request->validate([
            'home_address' => ['nullable', 'string', 'max:500'],
            'home_address_place_id' => ['nullable', 'string', 'max:255'],
            'home_address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'home_address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        foreach (['home_address_place_id', 'home_address_latitude', 'home_address_longitude'] as $key) {
            if (($data[$key] ?? '') === '') {
                $data[$key] = null;
            }
        }

        if (blank($data['home_address'] ?? null)) {
            $data['home_address'] = null;
            $data['home_address_place_id'] = null;
            $data['home_address_latitude'] = null;
            $data['home_address_longitude'] = null;
        }

        $settings->update($data);

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Home address updated.');
    }

    public function updateGasPrice(Request $request)
    {
        $settings = CompanySetting::current();

        $data = $request->validate([
            'gas_price_per_liter' => ['required', 'numeric', 'min:0', 'max:10'],
        ]);

        $settings->update($data);

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Gas price updated.');
    }

    public function updateBusinessContact(Request $request)
    {
        $settings = CompanySetting::current();

        $data = $request->validate([
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
        ]);

        $phone = trim((string) ($data['contact_phone'] ?? ''));
        $email = trim((string) ($data['contact_email'] ?? ''));
        $digits = ClientProject::phoneDigitsForSms($phone) ?? preg_replace('/\D/', '', $phone);
        $tel = $digits !== '' && $digits !== null ? '+'.$digits : null;

        $settings->update([
            'contact_phone' => $phone !== '' ? $phone : null,
            'contact_phone_tel' => $tel,
            'contact_email' => $email !== '' ? $email : null,
        ]);

        return redirect()
            ->route('admin.vehicles.index')
            ->with('success', 'Business contact updated.');
    }

    /** @return array<string, mixed> */
    private function validateVehicle(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'make' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'license_plate' => ['nullable', 'string', 'max:32'],
            'fuel_consumption_l100km' => ['nullable', 'numeric', 'min:0.01', 'max:100'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
