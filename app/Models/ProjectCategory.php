<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_category_id');
    }
}
