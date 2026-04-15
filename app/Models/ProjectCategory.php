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
    ];

    protected $attributes = [
        'sort_order' => 0,
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_project_category')
            ->withTimestamps();
    }
}
