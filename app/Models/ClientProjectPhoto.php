<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectPhoto extends Model
{
    protected $fillable = [
        'client_project_id',
        'path',
        'sort_order',
    ];

    protected $casts = [
        'client_project_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /** Root-relative public URL (works when APP_URL differs from browser host). */
    public function url(): string
    {
        $path = ltrim(str_replace('\\', '/', $this->path), '/');
        $url = '/storage/'.$path;
        if ($this->updated_at !== null) {
            $url .= '?v='.$this->updated_at->timestamp;
        }

        return $url;
    }
}
