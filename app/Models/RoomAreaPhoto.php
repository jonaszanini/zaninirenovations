<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAreaPhoto extends Model
{
    protected $fillable = [
        'room_area_room_id',
        'path',
        'caption',
        'original_filename',
        'mime',
        'width',
        'height',
        'sort_order',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(RoomAreaRoom::class, 'room_area_room_id');
    }
}
