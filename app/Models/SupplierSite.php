<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupplierSite extends Model
{
    protected $fillable = [
        'name',
        'url',
        'notes',
        'phone',
        'email',
        'address',
        'distance_km',
        'drive_minutes',
        'distance_checked_at',
        'distance_error',
        'is_favorite',
        'sort_order',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'sort_order' => 'integer',
        'distance_km' => 'decimal:2',
        'drive_minutes' => 'integer',
        'distance_checked_at' => 'datetime',
    ];

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SupplierTag::class, 'supplier_site_supplier_tag');
    }

    /** Comma-separated tag names for form repopulation. */
    public function tagsInputValue(): string
    {
        return $this->tags->pluck('name')->implode(', ');
    }

    public function distanceDisplayLabel(): string
    {
        if ($this->distance_km !== null) {
            $km = rtrim(rtrim(number_format((float) $this->distance_km, 1, '.', ''), '0'), '.');
            if ($this->drive_minutes !== null && $this->drive_minutes > 0) {
                return $km.' km · '.$this->drive_minutes.' min';
            }

            return $km.' km';
        }

        if (filled($this->distance_error)) {
            return \Illuminate\Support\Str::limit((string) $this->distance_error, 48);
        }

        return '—';
    }

    public function distanceErrorForTip(): ?string
    {
        return filled($this->distance_error) ? (string) $this->distance_error : null;
    }
}
