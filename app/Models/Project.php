<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\PlainTextLimiter;
use Illuminate\Support\Str;

class Project extends Model
{
    /** Short public Town labels (no ward or neighbourhood). */
    public const APPROVED_TOWNS = [
        'Almonte',
        'Alta Vista',
        'Ashton',
        'Barrhaven',
        'Carleton Place',
        'Gatineau, QC',
        'Kanata',
        'Nepean',
        'Perth',
        'Riverside South',
        'South Keys',
        'Stittsville',
        'Toronto',
        'West Carleton',
    ];

    public const OUT_OF_MARKET_TOWN = 'Toronto';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'meta_title',
        'meta_description',
        'completed_on',
        'town',
    ];

    protected $casts = [
        'completed_on' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (trim((string) $project->slug) === '') {
                $project->slug = static::uniqueSlug((string) $project->title);
            }
        });
    }

    /**
     * Portfolio order: most recently completed first, then newest records.
     */
    public function scopeOrderByCompletionLatest($query)
    {
        return $query
            ->orderByDesc('completed_on')
            ->orderByDesc('id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProjectPhoto::class)->orderBy('sort_order');
    }

    /** First gallery image (lowest sort_order) for list thumbnails. */
    public function coverPhoto(): HasOne
    {
        return $this->hasOne(ProjectPhoto::class)->ofMany('sort_order', 'min');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProjectCategory::class, 'project_project_category')
            ->withTimestamps();
    }

    /** Comma-separated category names for tables and cards. */
    public function categoryLabel(): string
    {
        $visible = $this->categories
            ->filter(fn ($c) => $c->isPublicFilter())
            ->sortBy(fn ($c) => [$c->sort_order, strtolower($c->name)])
            ->pluck('name')
            ->unique()
            ->filter();

        if ($visible->isEmpty()) {
            return '—';
        }

        return $visible->implode(', ');
    }

    public function slugRedirects(): HasMany
    {
        return $this->hasMany(ProjectSlugRedirect::class);
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'project';
        $slug = rtrim(substr($slug, 0, 180), '-');
        if ($slug === '') {
            $slug = 'project';
        }

        $candidate = $slug;
        $n = 2;
        while (static::slugIsTaken($candidate, $ignoreId)) {
            $suffix = '-'.$n;
            $candidate = rtrim(substr($slug, 0, 180 - strlen($suffix)), '-').$suffix;
            $n++;
        }

        return $candidate;
    }

    public static function slugIsTaken(string $slug, ?int $ignoreId = null): bool
    {
        $projects = static::query()->where('slug', $slug);
        if ($ignoreId) {
            $projects->where('id', '!=', $ignoreId);
        }
        if ($projects->exists()) {
            return true;
        }

        $redirects = ProjectSlugRedirect::query()->where('slug', $slug);
        if ($ignoreId) {
            $redirects->where('project_id', '!=', $ignoreId);
        }

        return $redirects->exists();
    }

    public function publicUrl(): string
    {
        return route('portfolio.show', ['slug' => $this->slug]);
    }

    public function townLabel(): string
    {
        return trim((string) $this->town);
    }

    public function isOutOfMarket(): bool
    {
        return strcasecmp($this->townLabel(), self::OUT_OF_MARKET_TOWN) === 0;
    }

    public function isInServiceArea(): bool
    {
        return ! $this->isOutOfMarket();
    }

    /**
     * In-market portfolio examples. Toronto jobs are never local examples.
     */
    public function scopeInServiceArea($query)
    {
        return $query->where(function ($inner) {
            $inner->whereNull('town')
                ->orWhere('town', '')
                ->orWhereRaw('LOWER(town) <> ?', [strtolower(self::OUT_OF_MARKET_TOWN)]);
        });
    }

    /**
     * Projects in a named town that may be linked from local / "near X" context.
     * Toronto always yields an empty set.
     */
    public function scopeNearTown($query, string $town)
    {
        $town = trim($town);

        return $query->inServiceArea()->where('town', $town);
    }

    public function seoTitle(): string
    {
        $custom = trim((string) $this->meta_title);
        if ($custom !== '') {
            return $custom;
        }

        $title = trim((string) $this->title);

        return ($title !== '' ? $title : 'Project').' — Zanini Renovations';
    }

    public function seoDescription(): string
    {
        $custom = trim((string) $this->meta_description);
        if ($custom !== '') {
            return PlainTextLimiter::trimToLength($custom, 155);
        }

        $plain = $this->plainDescription(155);
        if ($plain !== '') {
            return $plain;
        }

        $category = $this->categoryLabel();
        if ($category !== '—') {
            return 'Completed '.$category.' project by Zanini Renovations in Carleton Place and the Ottawa region.';
        }

        return 'Completed carpentry and renovation project by Zanini Renovations in Carleton Place and the Ottawa region.';
    }

    public function plainDescription(int $limit = 160): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $this->description)) ?? '');

        return PlainTextLimiter::trimToLength($plain, $limit);
    }

    public function coverImageUrl(): ?string
    {
        $photo = null;
        if ($this->relationLoaded('photos')) {
            $photo = $this->photos->sortBy('sort_order')->first();
        } elseif ($this->relationLoaded('coverPhoto')) {
            $photo = $this->coverPhoto;
        } else {
            $photo = $this->coverPhoto()->first();
        }

        return $photo?->url();
    }
}
