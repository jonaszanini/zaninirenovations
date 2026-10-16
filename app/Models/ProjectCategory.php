<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
        'hide_from_public_filters',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'hide_from_public_filters' => false,
    ];

    protected $casts = [
        'hide_from_public_filters' => 'boolean',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_project_category')
            ->withTimestamps();
    }

    public function isPublicFilter(): bool
    {
        return ! (bool) $this->hide_from_public_filters;
    }
}
