<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = [
        'title',
        'description',
        'completed_on',
    ];

    protected $casts = [
        'completed_on' => 'date',
    ];

    /**
     * Portfolio order: most recently completed first, then newest records.
     */
    public function scopeOrderByCompletionLatest($query)
    {
        return $query
            ->orderByDesc('completed_on')
            ->orderByDesc('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('sort_order');
    }

    /** First gallery image (lowest sort_order) for list thumbnails. */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(ProjectPhoto::class)->ofMany('sort_order', 'min');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'project_project_category')
            ->withTimestamps();
    }

    /** Comma-separated category names for tables and cards. */
    public function categoryLabel(): string
    {
        if ($this->categories->isEmpty()) {
            return '—';
        }

        return $this->categories
            ->sortBy(fn ($c) => [$c->sort_order, strtolower($c->name)])
            ->pluck('name')
            ->unique()
            ->implode(', ');
    }
}
