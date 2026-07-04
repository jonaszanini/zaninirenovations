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
        'is_favorite',
        'sort_order',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'sort_order' => 'integer',
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
}
