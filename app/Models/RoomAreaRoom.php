<?php

namespace App\Models;

use App\Services\RoomAreaCanvasData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAreaRoom extends Model
{
    protected $fillable = [
        'room_area_project_id',
        'name',
        'vertices',
        'background_path',
        'scale_pixels_per_foot',
        'canvas_width',
        'canvas_height',
        'area_sqft',
    ];

    protected $casts = [
        'vertices' => 'array',
        'area_sqft' => 'float',
        'scale_pixels_per_foot' => 'float',
    ];

    public function canvasData(): array
    {
        return app(RoomAreaCanvasData::class)->normalize($this->vertices);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }
}
