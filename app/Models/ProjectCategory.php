<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectCategory extends Model
{
    /** Public homepage filters that should not appear as buttons. */
    public const HIDDEN_PUBLIC_FILTER_SLUGS = [
        'plumbing-fixtures',
        'plumbing_fixtures',
        'plumbing-fixture',
    ];

    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_project_category')
            ->withTimestamps();
    }

    public function isPublicFilter(): bool
    {
        $slug = strtolower(str_replace('_', '-', trim((string) $this->slug)));
        if (in_array($slug, self::HIDDEN_PUBLIC_FILTER_SLUGS, true) || str_contains($slug, 'plumbing')) {
            return false;
        }

        $name = strtolower(trim((string) $this->name));

        return $name !== 'plumbing fixtures' && ! str_contains($name, 'plumbing fixture');
    }
}
