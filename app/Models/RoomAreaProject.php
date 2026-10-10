<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomAreaProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'client_project_id',
        'scale_pixels_per_foot',
        'wall_thickness_in',
        'background_path',
        'canvas_width',
        'canvas_height',
    ];

    protected $casts = [
        'scale_pixels_per_foot' => 'float',
        'wall_thickness_in' => 'float',
    ];

    public function clientProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(RoomAreaRoom::class)->orderBy('id');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(RoomAreaPhase::class)->orderBy('sort_order')->orderBy('id');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(RoomAreaSignature::class)->orderBy('id');
    }

    public function activeSignature(): ?RoomAreaSignature
    {
        $this->loadMissing('signatures');

        return $this->signatures->first(fn (RoomAreaSignature $row) => $row->isActive());
    }
}
