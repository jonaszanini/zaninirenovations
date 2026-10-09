<?php

namespace App\Models;

use App\Support\EasternStandardTimeDisplay;
use App\Support\PlainTextLimiter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const EXCERPT_MAX = 500;

    public const SEO_TITLE_MAX = 70;

    public const META_SNIPPET_MAX = 155;

    public const FEATURED_ALT_MAX = 255;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'html_content',
        'featured_image_path',
        'featured_image_alt',
        'ai_image_prompt',
        'meta_description',
        'seo_title',
        'status',
        'published_at',
        'views_count',
        'author_name',
        'cta_service',
        'noindex',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'noindex' => 'boolean',
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

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(BlogPostSlugRedirect::class);
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

        $url = Storage::disk('public')->url($this->featured_image_path);
        $absolute = Storage::disk('public')->path($this->featured_image_path);
        if (is_file($absolute)) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.filemtime($absolute);
        } elseif ($this->updated_at !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'v='.$this->updated_at->getTimestamp();
        }

        return $url;
    }

    /**
     * Same-origin list src. Returns null when the stored file is missing
     * so the admin table never paints a broken-image icon.
     */
    public function featuredImageListSrc(): ?string
    {
        if (! filled($this->featured_image_path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path((string) $this->featured_image_path);
        if (! is_file($absolute)) {
            return null;
        }

        $url = Storage::disk('public')->url($this->featured_image_path);
        $path = parse_url($url, PHP_URL_PATH) ?: '/storage/'.ltrim((string) $this->featured_image_path, '/');

        return $path.'?v='.filemtime($absolute);
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
            return Str::limit(trim((string) $this->excerpt), $limit);
        }

        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->html_content)) ?? '');

        return Str::limit($plain, $limit);
    }

    public function incrementViews(): void
    {
        if ($this->id === null || Auth::check()) {
            return;
        }

        $ua = strtolower((string) request()->userAgent());
        if ($ua !== '' && self::isAutomatedUserAgent($ua)) {
            return;
        }

        static::query()->whereKey($this->id)->toBase()->increment('views_count');
        $this->views_count = (int) $this->views_count + 1;
    }

    public static function isAutomatedUserAgent(string $userAgent): bool
    {
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|wget|curl|python-requests|headless|uptime|pingdom|ahrefs|semrush|duckduck/i',
            $userAgent
        );
    }

    public function approvedCommentsCount(): int
    {
        return $this->approvedComments()->count();
    }

    public static function slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'post';
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $transliterated = $transliterator->transliterate($value);
                if (is_string($transliterated) && trim($transliterated) !== '') {
                    $value = $transliterated;
                }
            }
        }

        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'post';
    }

    public static function slugIsTaken(string $slug, ?int $ignoreId = null): bool
    {
        $taken = static::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();

        if ($taken) {
            return true;
        }

        return BlogPostSlugRedirect::query()
            ->when($ignoreId, fn ($q) => $q->where('blog_post_id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists();
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = static::slugify($base);
        $candidate = $slug;
        $n = 2;

        while (static::slugIsTaken($candidate, $ignoreId)) {
            $candidate = $slug.'-'.$n;
            $n++;
        }

        return $candidate;
    }

    /**
     * @return list<string>
     */
    public static function authorOptions(): array
    {
        $names = static::query()
            ->whereNotNull('author_name')
            ->where('author_name', '!=', '')
            ->distinct()
            ->orderBy('author_name')
            ->pluck('author_name')
            ->all();

        $users = User::query()->orderBy('name')->pluck('name')->all();
        $merged = array_merge([(string) config('blog.author_name')], $users, $names);

        return array_values(array_unique(array_filter($merged, fn ($name) => trim((string) $name) !== '')));
    }

    /**
     * @return list<string>
     */
    public function seoIssueKeys(): array
    {
        $keys = [];
        if (! filled($this->meta_description)) {
            $keys[] = 'meta';
        }
        if (! filled($this->seo_title)) {
            $keys[] = 'seo';
        }
        if (! filled($this->featured_image_path)) {
            $keys[] = 'image';
        } elseif (! filled($this->featured_image_alt)) {
            $keys[] = 'alt';
        }

        return $keys;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();
        $post = static::query()->where($field, $value)->first();
        if ($post) {
            return $post;
        }

        if ($field === 'slug') {
            $redirect = BlogPostSlugRedirect::query()
                ->where('slug', $value)
                ->with('post')
                ->first();

            if ($redirect?->post && request()->isMethod('GET') && request()->routeIs('blog.show')) {
                throw new HttpResponseException(
                    redirect()->to($redirect->post->publicUrl(), 301)
                );
            }
        }

        return null;
    }

    public function publicUrl(): string
    {
        return route('blog.show', $this);
    }

    public function displayDate(): string
    {
        return EasternStandardTimeDisplay::formatCalendarDate($this->published_at ?? $this->created_at);
    }

    /** Public blog date, e.g. "Oct 6, 2026". */
    public function displayDateLong(): string
    {
        return EasternStandardTimeDisplay::formatCalendarMediumDate($this->published_at ?? $this->created_at);
    }

    public function documentTitle(): string
    {
        $max = 60;
        $brand = ' | Zanini';

        if (filled($this->seo_title)) {
            return PlainTextLimiter::trimToLength(trim((string) $this->seo_title), $max);
        }

        $title = trim((string) $this->title);
        $withBrand = $title.$brand;
        if (mb_strlen($withBrand) <= $max) {
            return $withBrand;
        }

        $room = $max - mb_strlen($brand);
        if ($room >= 20) {
            $truncated = PlainTextLimiter::trimToLength($title, $room);
            $combined = $truncated.$brand;
            if ($truncated !== '' && mb_strlen($combined) <= $max) {
                return $combined;
            }
        }

        return PlainTextLimiter::trimToLength($title !== '' ? $title : 'Blog', $max);
    }

    public function seoDescription(): string
    {
        if (filled($this->meta_description)) {
            $raw = trim((string) $this->meta_description);
        } elseif (filled($this->excerpt)) {
            $raw = trim((string) $this->excerpt);
        } else {
            $raw = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->html_content)) ?? '');
        }

        return PlainTextLimiter::trimToLength($raw, self::META_SNIPPET_MAX);
    }

    public function featuredImageAlt(): string
    {
        return filled($this->featured_image_alt)
            ? trim((string) $this->featured_image_alt)
            : (string) $this->title;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public function featuredImageDimensions(): ?array
    {
        if (! filled($this->featured_image_path)) {
            return null;
        }

        $absolute = Storage::disk('public')->path((string) $this->featured_image_path);
        if (is_file($absolute)) {
            $info = @getimagesize($absolute);
            if (is_array($info) && (int) ($info[0] ?? 0) > 0 && (int) ($info[1] ?? 0) > 0) {
                return ['width' => (int) $info[0], 'height' => (int) $info[1]];
            }
        }

        return ['width' => 1600, 'height' => 900];
    }

    public function scopeIndexable(Builder $query): Builder
    {
        return $query->published()->where('noindex', false);
    }
}
