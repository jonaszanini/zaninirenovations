<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhoto extends Model
{
    protected $fillable = [
        'project_id',
        'path',
        'sort_order',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Root-relative URL so images work when APP_URL does not match the browser host
     * (e.g. 127.0.0.1:8000 vs http://localhost).
     */
    public function url(): string
    {
        $path = ltrim(str_replace('\\', '/', $this->path), '/');

        return '/storage/'.$path;
    }
}
