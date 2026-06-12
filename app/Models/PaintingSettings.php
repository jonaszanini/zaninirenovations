<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaintingSettings extends Model
{
    protected $table = 'painting_settings';

    protected $fillable = [
        'labor_per_sqf',
        'paint_per_gallon',
        'material_addon_fee',
        'sqf_per_gallon',
    ];

    protected $casts = [
        'labor_per_sqf' => 'decimal:4',
        'paint_per_gallon' => 'decimal:2',
        'material_addon_fee' => 'decimal:2',
        'sqf_per_gallon' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->find(1) ?? new self([
            'labor_per_sqf' => 2.51,
            'paint_per_gallon' => 55.00,
            'material_addon_fee' => 100.00,
            'sqf_per_gallon' => 70.00,
        ]);
    }
}
