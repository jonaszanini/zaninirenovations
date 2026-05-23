<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = [
        'home_address',
        'home_address_place_id',
        'home_address_latitude',
        'home_address_longitude',
        'average_km_per_month',
    ];

    protected $casts = [
        'home_address_latitude' => 'decimal:7',
        'home_address_longitude' => 'decimal:7',
        'average_km_per_month' => 'integer',
    ];

    public static function current(): self
    {
        /** @var self $row */
        $row = static::query()->firstOrCreate(['id' => 1], [
            'average_km_per_month' => 2000,
        ]);

        return $row;
    }

    public function hasHomeCoordinates(): bool
    {
        return $this->home_address_latitude !== null
            && $this->home_address_longitude !== null;
    }
}
