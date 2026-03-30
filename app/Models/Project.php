<?php

namespace App\Models;

use App\Support\ProjectCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'title',
        'description',
        'category',
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('sort_order');
    }

    public function categoryLabel(): string
    {
        return ProjectCategory::label($this->category);
    }
}
