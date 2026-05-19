<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class StairsSettings extends Model
{
    protected $table = 'stairs_settings';

    protected $fillable = [
        'stain_per_step',
        'clear_coat_per_step',
        'carpet_removal_per_step',
        'nosing_option_photos',
    ];

    protected $casts = [
        'stain_per_step' => 'decimal:2',
        'clear_coat_per_step' => 'decimal:2',
        'carpet_removal_per_step' => 'decimal:2',
        'nosing_option_photos' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->find(1) ?? new self([
            'stain_per_step' => 0,
            'clear_coat_per_step' => 0,
            'carpet_removal_per_step' => 0,
            'nosing_option_photos' => [],
        ]);
    }

    /** Relative path on the `public` disk, or null. */
    public function nosingOptionPhotoRelativePath(string $key): ?string
    {
        if (! array_key_exists($key, StairsMaterialType::nosingChoiceLabels())) {
            return null;
        }
        $photos = is_array($this->nosing_option_photos) ? $this->nosing_option_photos : [];
        $path = $photos[$key] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        return $path !== '' ? $path : null;
    }

    /**
     * Signed URL through admin route so images load without a storage symlink.
     */
    public function nosingOptionPhotoUrl(string $key): ?string
    {
        $relative = $this->nosingOptionPhotoRelativePath($key);
        if ($relative === null || str_contains($relative, '..')) {
            return null;
        }

        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        if (! app()->runningInConsole()) {
            return route('admin.quotations.stairs.nosing-option-photo', ['key' => $key]);
        }

        return asset('storage/'.$relative);
    }
}
