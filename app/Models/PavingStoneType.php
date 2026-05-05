<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PavingStoneType extends Model
{
    protected $fillable = [
        'name',
        'sq_ft_per_stone',
        'price_per_stone',
        'sort_order',
    ];

    protected $casts = [
        'sq_ft_per_stone' => 'decimal:4',
        'price_per_stone' => 'decimal:2',
        'sort_order' => 'integer',
    ];
}
