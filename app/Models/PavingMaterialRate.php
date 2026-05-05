<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PavingMaterialRate extends Model
{
    protected $fillable = [
        'rate_key',
        'label',
        'unit',
        'price_before_tax',
        'sort_order',
    ];

    protected $casts = [
        'price_before_tax' => 'decimal:2',
        'sort_order' => 'integer',
    ];
}
