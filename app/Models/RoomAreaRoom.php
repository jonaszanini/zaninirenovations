<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAreaRoom extends Model
{
    protected $fillable = [
        'room_area_project_id',
        'name',
        'vertices',
        'area_sqft',
    ];

    protected $casts = [
        'vertices' => 'array',
        'area_sqft' => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }
}
