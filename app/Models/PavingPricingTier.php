<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PavingPricingTier extends Model
{
    protected $fillable = [
        'area_sq_ft',
        'price_per_sq_ft',
        'sort_order',
    ];

    protected $casts = [
        'area_sq_ft' => 'decimal:2',
        'price_per_sq_ft' => 'decimal:4',
        'sort_order' => 'integer',
    ];
}
