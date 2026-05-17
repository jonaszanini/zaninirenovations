<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StairsSettings extends Model
{
    protected $table = 'stairs_settings';

    protected $fillable = [
        'stain_per_step',
        'clear_coat_per_step',
    ];

    protected $casts = [
        'stain_per_step' => 'decimal:2',
        'clear_coat_per_step' => 'decimal:2',
    ];

    public static function current(): self
    {
        return static::query()->find(1) ?? new self([
            'stain_per_step' => 0,
            'clear_coat_per_step' => 0,
        ]);
    }
}
