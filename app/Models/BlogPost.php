<?php

namespace App\Models;

use App\Support\EasternStandardTimeDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'html_content',
        'featured_image_path',
        'meta_description',
        'status',
        'published_at',
        'views_count',
        'author_name',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_category_post');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('is_approved', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('published_at', '>', now());
    }

    public function isPublishedNow(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return $this->published_at === null || $this->published_at->lte(now());
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isFuture();
    }

    public function featuredImageUrl(): ?string
    {
        if (! filled($this->featured_image_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->featured_image_path);
    }

    public function readTimeMinutes(): int
    {
        $text = strip_tags((string) $this->html_content);
        $words = str_word_count($text);

        return max(1, (int) ceil($words / max(1, (int) config('blog.words_per_minute', 200))));
    }

    public function displayExcerpt(int $limit = 220): string
    {
        if (filled($this->excerpt)) {
            return $this->excerpt;
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->html_content)));

        return Str::limit($plain, $limit);
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function approvedCommentsCount(): int
    {
        return $this->approvedComments()->count();
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'post';
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

    public function publicUrl(): string
    {
        return route('blog.show', $this);
    }

    public function displayDate(): string
    {
        return EasternStandardTimeDisplay::formatCalendarDate($this->published_at ?? $this->created_at);
    }
}
