<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileMortarType extends Model
{
    protected $fillable = [
        'name',
        'bag_weight_lbs',
        'coverage_14_14',
        'coverage_14_38',
        'coverage_12_12',
        'cost_price',
        'sort_order',
        'is_active',
    ];
    use HasFactory;
}
