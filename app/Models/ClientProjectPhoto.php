<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectPhoto extends Model
{
    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_PDF = 'pdf';

    protected $fillable = [
        'client_project_id',
        'path',
        'media_type',
        'original_filename',
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

    public function mediaType(): string
    {
        $stored = $this->media_type;
        if (in_array($stored, [self::TYPE_IMAGE, self::TYPE_VIDEO, self::TYPE_PDF], true)) {
            return $stored;
        }

        return self::inferMediaTypeFromPath($this->path);
    }

    public function isImage(): bool
    {
        return $this->mediaType() === self::TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->mediaType() === self::TYPE_VIDEO;
    }

    public function isPdf(): bool
    {
        return $this->mediaType() === self::TYPE_PDF;
    }

    public function displayName(): string
    {
        if (filled($this->original_filename)) {
            return (string) $this->original_filename;
        }

        return basename((string) $this->path);
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

    public static function inferMediaTypeFromPath(?string $path): string
    {
        $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return self::TYPE_PDF;
        }
        if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            return self::TYPE_VIDEO;
        }

        return self::TYPE_IMAGE;
    }
}
