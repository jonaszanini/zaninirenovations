<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileProduct extends Model
{
    protected $fillable = [
        'name',
        'url',
        'image_path',
        'length_inches',
        'width_inches',
        'thickness_inches',
        'cost_per_sqft',
        'sort_order',
        'is_active',
    ];
    use HasFactory;
}
