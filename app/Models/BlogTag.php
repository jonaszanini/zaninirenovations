<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class BlogTag extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'tag';
        $candidate = $slug;
        $n = 2;

        while (static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    /** Find or create tags from comma-separated or array of names. */
    public static function syncFromNames(array $names): array
    {
        $ids = [];
        foreach ($names as $raw) {
            $name = trim((string) $raw);
            if ($name === '') {
                continue;
            }
            $tag = static::query()->whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
            if (! $tag) {
                $tag = static::create([
                    'name' => $name,
                    'slug' => static::uniqueSlug($name),
                ]);
            }
            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }
}
