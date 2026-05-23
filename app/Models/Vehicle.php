<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'name',
        'make',
        'model',
        'year',
        'license_plate',
        'fuel_consumption_l100km',
        'odometer_reading',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'fuel_consumption_l100km' => 'decimal:2',
        'odometer_reading' => 'integer',
        'is_active' => 'boolean',
    ];

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function activate(): void
    {
        static::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    public function displaySubtitle(): string
    {
        $parts = array_filter([
            trim(implode(' ', array_filter([$this->make, $this->model]))),
            $this->year ? (string) $this->year : null,
            $this->license_plate ? strtoupper($this->license_plate) : null,
        ]);

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    public function hasFuelConsumption(): bool
    {
        return $this->fuel_consumption_l100km !== null
            && (float) $this->fuel_consumption_l100km > 0;
    }

    /** Liters per kilometer × company gas price. */
    public function costPerKm(?float $gasPricePerLiter): ?float
    {
        if (! $this->hasFuelConsumption() || $gasPricePerLiter === null || $gasPricePerLiter < 0) {
            return null;
        }

        return round(((float) $this->fuel_consumption_l100km / 100) * $gasPricePerLiter, 4);
    }

    public function estimateFuelCost(float $distanceKm, ?float $gasPricePerLiter): ?float
    {
        $perKm = $this->costPerKm($gasPricePerLiter);

        return $perKm !== null ? round($perKm * max(0, $distanceKm), 2) : null;
    }
}
