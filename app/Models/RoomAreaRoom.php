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

    public function resolvedScalePixelsPerFoot(): float
    {
        $scale = (float) ($this->scale_pixels_per_foot ?? 0);
        if ($scale > 0) {
            return $scale;
        }

        return (float) ($this->project?->scale_pixels_per_foot ?? 0);
    }

    /**
     * @return array{area_sqft: float, perimeter_lf: float, inside_corners: int, outside_corners: int, polygon_count: int}
     */
    public function layoutMetrics(): array
    {
        $scale = $this->resolvedScalePixelsPerFoot();
        if ($scale <= 0) {
            return app(RoomAreaCanvasData::class)->emptyLayoutMetrics();
        }

        return app(RoomAreaCanvasData::class)->layoutMetrics($this->canvasData(), $scale);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }
}
