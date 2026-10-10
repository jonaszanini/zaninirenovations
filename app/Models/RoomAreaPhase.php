<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomAreaPhase extends Model
{
    protected $fillable = [
        'room_area_project_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(RoomAreaRoom::class, 'phase_id')->orderBy('id');
    }
}
