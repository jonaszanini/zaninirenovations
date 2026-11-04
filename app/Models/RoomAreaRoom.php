<?php

namespace App\Models;

use App\Services\RoomAreaCanvasData;
use App\Services\RoomAreaFinishMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'wall_height_ft',
        'ceiling_height_ft',
        'flooring_waste_percent',
        'flooring_direction',
        'flooring_box_sqft',
        'pdf_path',
        'legacy_canvas_data',
        'openplan3d_import_meta',
        'revision',
    ];

    protected $casts = [
        'vertices' => 'array',
        'legacy_canvas_data' => 'array',
        'openplan3d_import_meta' => 'array',
        'revision' => 'integer',
        'area_sqft' => 'float',
        'scale_pixels_per_foot' => 'float',
        'wall_height_ft' => 'float',
        'ceiling_height_ft' => 'float',
        'flooring_waste_percent' => 'float',
        'flooring_direction' => 'integer',
        'flooring_box_sqft' => 'float',
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

    public function resolvedWallHeightFt(): float
    {
        return app(RoomAreaCanvasData::class)->normalizeWallHeight($this->wall_height_ft);
    }

    public function resolvedCeilingHeightFt(): float
    {
        return app(RoomAreaFinishMath::class)->ceilingHeightFt($this->ceiling_height_ft, $this->resolvedWallHeightFt());
    }

    /**
     * @return array{ceiling_height_ft: ?float, flooring_waste_percent: float, flooring_direction: int, flooring_box_sqft: ?float}
     */
    public function finishSettings(): array
    {
        return [
            'ceiling_height_ft' => $this->ceiling_height_ft,
            'flooring_waste_percent' => $this->flooring_waste_percent,
            'flooring_direction' => $this->flooring_direction,
            'flooring_box_sqft' => $this->flooring_box_sqft,
        ];
    }

    /**
     * @return array{
     *   area_sqft: float,
     *   perimeter_lf: float,
     *   baseboard_lf: float,
     *   wall_height_ft: float,
     *   wall_area_sqft: float,
     *   door_count: int,
     *   window_count: int,
     *   opening_count: int,
     *   drywall_openings: int,
     *   inside_corners: int,
     *   outside_corners: int,
     *   polygon_count: int
     * }
     */
    public function layoutMetrics(bool $tolerant = false): array
    {
        $bridge = app(\App\Services\OpenPlan3DMeasurementBridge::class);
        try {
            if ($bridge->isPlan($this->vertices)) {
                if ($tolerant) {
                    return $bridge->safeLayoutMetrics($this->vertices, null, $this->finishSettings());
                }

                return $bridge->layoutMetrics($this->vertices, null, $this->finishSettings());
            }

            $scale = $this->resolvedScalePixelsPerFoot();
            $height = $this->resolvedWallHeightFt();
            if ($scale <= 0) {
                return app(RoomAreaCanvasData::class)->emptyLayoutMetrics($height);
            }

            $canvasData = app(RoomAreaCanvasData::class);
            if ($tolerant) {
                return $canvasData->safeLayoutMetrics($this->canvasData(), $scale, $height, $this->finishSettings());
            }

            return $canvasData->layoutMetrics($this->canvasData(), $scale, $height, $this->finishSettings());
        } catch (\InvalidArgumentException $e) {
            if (! $tolerant) {
                throw $e;
            }
            $empty = $bridge->emptyLayoutMetrics($this->resolvedWallHeightFt());
            $empty['geometry_unmeasurable'] = true;
            $message = trim($e->getMessage());
            $empty['geometry_message'] = $message !== '' ? $message : 'Room geometry could not be measured.';

            return $empty;
        }
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(RoomAreaProject::class, 'room_area_project_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomAreaPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(RoomAreaAssembly::class, 'room_area_room_assemblies')
            ->withTimestamps();
    }
}
