<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomAreaAssembly extends Model
{
    protected $fillable = [
        'name',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(RoomAreaAssemblyLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(RoomAreaRoom::class, 'room_area_room_assemblies')
            ->withTimestamps();
    }

    /**
     * @return array<string, mixed>
     */
    public function toEditorArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'sort_order' => (int) $this->sort_order,
            'lines' => $this->lines->map(fn (RoomAreaAssemblyLine $line) => $line->toEditorArray())->values()->all(),
        ];
    }
}
