<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FlooringType extends Model
{
    protected $fillable = [
        'key',
        'name',
        'installation_price_per_sqft',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'installation_price_per_sqft' => 'decimal:4',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @param  Builder<FlooringType>  $query
     * @return Builder<FlooringType>
     */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
