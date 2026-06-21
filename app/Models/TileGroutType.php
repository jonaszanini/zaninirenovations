<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileGroutType extends Model
{
    protected $fillable = [
        'name',
        'bag_weight_lbs',
        'density_factor',
        'cost_price',
        'sort_order',
        'is_active',
    ];

    use HasFactory;
}
