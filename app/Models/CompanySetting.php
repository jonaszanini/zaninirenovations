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
        'gas_price_per_liter',
    ];

    protected $casts = [
        'home_address_latitude' => 'decimal:7',
        'home_address_longitude' => 'decimal:7',
        'gas_price_per_liter' => 'decimal:3',
    ];

    public static function current(): self
    {
        /** @var self $row */
        $row = static::query()->firstOrCreate(['id' => 1]);

        return $row;
    }

    public function hasHomeCoordinates(): bool
    {
        return $this->home_address_latitude !== null
            && $this->home_address_longitude !== null;
    }

    public function hasGasPrice(): bool
    {
        return $this->gas_price_per_liter !== null
            && (float) $this->gas_price_per_liter >= 0;
    }
}
