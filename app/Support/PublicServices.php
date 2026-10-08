<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Collection;

class PublicServices
{
    /**
     * @return array<string, mixed>
     */
    public static function hub(): array
    {
        return (array) config('public_services.hub', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function cta(): array
    {
        return (array) config('public_services.cta', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function about(): array
    {
        return (array) config('public_services.about', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviews(): array
    {
        return (array) config('public_services.reviews', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function contact(): array
    {
        return (array) config('public_services.contact', []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function pages(): array
    {
        $pages = config('public_services.pages', []);

        return is_array($pages) ? $pages : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publishedPages(): array
    {
        $out = [];
        foreach (self::pages() as $page) {
            if (! is_array($page) || ! ($page['published'] ?? false)) {
                continue;
            }
            if (trim((string) ($page['slug'] ?? '')) === '') {
                continue;
            }
            $out[] = $page;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function publishedPage(string $slug): ?array
    {
        $slug = trim($slug);
        foreach (self::publishedPages() as $page) {
            if (($page['slug'] ?? '') === $slug) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return Collection<int, Project>
     */
    public static function projectsForPage(array $page): Collection
    {
        $slugs = [];
        foreach ((array) ($page['projects'] ?? []) as $row) {
            if (is_array($row) && trim((string) ($row['slug'] ?? '')) !== '') {
                $slugs[] = (string) $row['slug'];
            }
        }
        foreach ((array) ($page['more_projects'] ?? []) as $row) {
            if (is_array($row) && trim((string) ($row['slug'] ?? '')) !== '') {
                $slugs[] = (string) $row['slug'];
            }
        }
        $hero = trim((string) ($page['hero_project_slug'] ?? ''));
        if ($hero !== '') {
            $slugs[] = $hero;
        }

        return ServiceAreas::projectsForSlugs($slugs);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array{src: string, alt: string, lazy: bool}
     */
    public static function heroImage(array $page, Collection $projects): array
    {
        $alt = (string) ($page['hero_alt'] ?? ($page['h1'] ?? 'Zanini Renovations project'));
        $fallback = ltrim((string) ($page['fallback_image'] ?? ''), '/');
        $heroSlug = trim((string) ($page['hero_project_slug'] ?? ''));

        if ($heroSlug !== '') {
            $match = $projects->first(fn (Project $project) => $project->slug === $heroSlug);
            if ($match instanceof Project) {
                $cover = $match->coverImageUrl();
                if (is_string($cover) && $cover !== '') {
                    return [
                        'src' => $cover,
                        'alt' => $alt,
                        'lazy' => false,
                    ];
                }
            }
        }

        return [
            'src' => $fallback !== '' ? url($fallback) : '',
            'alt' => $alt,
            'lazy' => false,
        ];
    }

    /**
     * @return list<array{name: string, url: string, service_type: string}>
     */
    public static function offerCatalogItems(): array
    {
        $items = [];
        foreach (self::publishedPages() as $page) {
            $items[] = [
                'name' => (string) ($page['hub_label'] ?? $page['nav_label'] ?? $page['h1']),
                'url' => url('/services/'.$page['slug']),
                'service_type' => (string) ($page['service_type'] ?? $page['hub_label'] ?? ''),
            ];
        }

        return $items;
    }
}
