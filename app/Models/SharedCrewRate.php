<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SharedCrewRate extends Model
{
    protected $fillable = [
        'owner_hourly_rate',
        'helper_hourly_rate',
        'hours_per_day',
    ];

    protected $casts = [
        'owner_hourly_rate' => 'decimal:2',
        'helper_hourly_rate' => 'decimal:2',
        'hours_per_day' => 'decimal:2',
    ];

    public static function current(): self
    {
        $row = static::query()->find(1);
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'id' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
        ]);
    }
}
