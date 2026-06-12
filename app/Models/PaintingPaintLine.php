<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaintingPaintLine extends Model
{
    protected $fillable = [
        'key',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActiveOrdered($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
