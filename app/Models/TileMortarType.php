<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileMortarType extends Model
{
    protected $fillable = [
        'name',
        'bag_weight_lbs',
        'cost_price',
        'retail_price',
        'sort_order',
        'is_active',
    ];
    use HasFactory;
}
