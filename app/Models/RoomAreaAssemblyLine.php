<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAreaAssemblyLine extends Model
{
    protected $fillable = [
        'room_area_assembly_id',
        'name',
        'formula',
        'unit',
        'whole_units',
        'material_id',
        'sort_order',
    ];

    protected $casts = [
        'whole_units' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(RoomAreaAssembly::class, 'room_area_assembly_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        $material = $this->relationLoaded('material') ? $this->material : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'formula' => $this->formula,
            'unit' => $this->unit,
            'whole_units' => (bool) $this->whole_units,
            'material_id' => $this->material_id,
            'material_name' => $material?->displayName(),
            'unit_price' => $material && is_numeric($material->price_cad)
                ? (float) $material->price_cad
                : null,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
