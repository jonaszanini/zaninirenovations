<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectSlugRedirect extends Model
{
    protected $fillable = [
        'project_id',
        'slug',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
