<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AiGeneratedImage extends Model
{
    public const MODE_CREATE = 'create';

    public const MODE_MODIFY = 'modify';

    protected $fillable = [
        'mode',
        'prompt',
        'original_image_path',
        'webp_image_path',
        'source_image_path',
        'aspect_ratio',
        'image_size',
    ];

    public function isModify(): bool
    {
        return $this->mode === self::MODE_MODIFY;
    }

    public function isCreate(): bool
    {
        return $this->mode === self::MODE_CREATE || $this->mode === null;
    }

    /**
     * Gallery preview URL — WebP for create flow; original for modify flow.
     */
    public function imageUrl(): ?string
    {
        if ($this->isModify()) {
            return $this->storageUrl($this->original_image_path);
        }

        return $this->storageUrl($this->webp_image_path) ?? $this->storageUrl($this->original_image_path);
    }

    public function sourceImageUrl(): ?string
    {
        return $this->storageUrl($this->source_image_path);
    }

    public function originalImageUrl(): ?string
    {
        return $this->storageUrl($this->original_image_path);
    }

    public function displayCreatedAt(): ?string
    {
        return $this->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A');
    }

    public function displayMeta(): string
    {
        if ($this->isModify()) {
            return 'Modified · '.($this->displayCreatedAt() ?? '');
        }

        $parts = array_filter([
            $this->aspect_ratio,
            $this->image_size,
            $this->displayCreatedAt(),
        ]);

        return implode(' · ', $parts);
    }

    public function hasOriginalDownload(): bool
    {
        return filled($this->original_image_path)
            && Storage::disk('public')->exists($this->original_image_path);
    }

    public function hasWebpDownload(): bool
    {
        return filled($this->webp_image_path)
            && Storage::disk('public')->exists($this->webp_image_path);
    }

    private function storageUrl(?string $relativePath): ?string
    {
        if (! filled($relativePath)) {
            return null;
        }

        $url = Storage::disk('public')->url($relativePath);
        $absolute = Storage::disk('public')->path($relativePath);
        if (is_file($absolute)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.filemtime($absolute);
        } elseif ($this->created_at !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$this->created_at->getTimestamp();
        }

        return $url;
    }
}
