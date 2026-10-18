<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectSlugRedirect;
use Illuminate\Support\Collection;

class ServiceAreas
{
    /**
     * @return array<string, mixed>
     */
    public static function hub(): array
    {
        return (array) config('service_areas.hub', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function cta(): array
    {
        return (array) config('service_areas.cta', []);
    }

    public static function mapEmbedSrc(): string
    {
        return trim((string) config('service_areas.map_embed_src', ''));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function pages(): array
    {
        $pages = config('service_areas.pages', []);

        return is_array($pages) ? $pages : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function publishedPages(): array
    {
        $out = [];
        foreach (self::pages() as $page) {
            if (! is_array($page)) {
                continue;
            }
            if (! ($page['published'] ?? false)) {
                continue;
            }
            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug === '') {
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
     * Unique body copy for a town page (CTA is excluded).
     *
     * @param  array<string, mixed>  $page
     */
    public static function uniqueCopyText(array $page): string
    {
        $chunks = [];
        foreach (['intro_paragraphs', 'permits_paragraphs', 'map_paragraphs'] as $key) {
            foreach ((array) ($page[$key] ?? []) as $paragraph) {
                $chunks[] = (string) $paragraph;
            }
        }
        $chunks[] = (string) ($page['services_intro'] ?? '');
        $chunks[] = (string) ($page['projects_intro'] ?? '');
        $chunks[] = (string) ($page['projects_note'] ?? '');
        foreach ((array) ($page['services'] ?? []) as $service) {
            if (! is_array($service)) {
                continue;
            }
            $chunks[] = (string) ($service['title'] ?? '');
            $chunks[] = (string) ($service['text'] ?? '');
        }
        foreach ((array) ($page['faqs'] ?? []) as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $chunks[] = (string) ($faq['question'] ?? '');
            $chunks[] = (string) ($faq['answer'] ?? '');
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $chunks)) ?? '');
    }

    /**
     * Escape town-page copy and turn [label](/path) or [label](https://…) into links.
     */
    public static function richText(string $text): string
    {
        $escaped = e($text);

        $linked = preg_replace_callback(
            '/\[([^\]\n]+)\]\(([^)\s]+)\)/',
            static function (array $match): string {
                $href = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! self::isSafePublicHref($href)) {
                    return $match[0];
                }

                return '<a href="'.e($href).'">'.$match[1].'</a>';
            },
            $escaped
        );

        return is_string($linked) ? $linked : $escaped;
    }

    public static function isSafePublicHref(string $href): bool
    {
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//')) {
            return true;
        }

        return (bool) preg_match('#^https://[a-z0-9.-]+(?:/|$)#i', $href);
    }

    /**
     * @param  array<string, mixed>  $page
     */
    public static function wordCount(array $page): int
    {
        $text = self::uniqueCopyText($page);
        if ($text === '') {
            return 0;
        }

        return count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /**
     * Map each requested slug to a live project (follows 301 aliases).
     *
     * @param  list<string>  $slugs
     * @return array<string, Project>
     */
    public static function projectsKeyedBySlug(array $slugs): array
    {
        $slugs = array_values(array_unique(array_filter(array_map(
            static fn ($slug) => trim((string) $slug),
            $slugs
        ), static fn ($slug) => $slug !== '')));

        if ($slugs === []) {
            return [];
        }

        $bySlug = Project::query()
            ->whereIn('slug', $slugs)
            ->with(['photos' => fn ($q) => $q->orderBy('sort_order'), 'categories'])
            ->get()
            ->keyBy('slug');

        $missing = array_values(array_filter($slugs, static fn ($slug) => ! $bySlug->has($slug)));
        if ($missing !== []) {
            $redirects = ProjectSlugRedirect::query()
                ->whereIn('slug', $missing)
                ->with(['project' => fn ($q) => $q->with([
                    'photos' => fn ($photos) => $photos->orderBy('sort_order'),
                    'categories',
                ])])
                ->get();
            foreach ($redirects as $redirect) {
                if ($redirect->project) {
                    $bySlug[$redirect->slug] = $redirect->project;
                }
            }
        }

        $map = [];
        foreach ($slugs as $slug) {
            $project = $bySlug->get($slug);
            if ($project instanceof Project) {
                $map[$slug] = $project;
            }
        }

        return $map;
    }

    /**
     * Resolve configured slugs to live portfolio projects (follows 301 aliases).
     *
     * @param  list<string>  $slugs
     * @return Collection<int, Project>
     */
    public static function projectsForSlugs(array $slugs): Collection
    {
        $ordered = collect();
        $seen = [];
        foreach (self::projectsKeyedBySlug($slugs) as $project) {
            if (isset($seen[$project->id])) {
                continue;
            }
            $seen[$project->id] = true;
            $ordered->push($project);
        }

        return $ordered;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return Collection<int, Project>
     */
    public static function projectsForPage(array $page): Collection
    {
        return self::projectsForSlugs((array) ($page['project_slugs'] ?? []));
    }

    /**
     * Hub groups with live project records attached where slugs exist.
     *
     * @return list<array<string, mixed>>
     */
    public static function hubGroups(): array
    {
        $groups = config('service_areas.hub_groups', []);
        if (! is_array($groups)) {
            return [];
        }

        $allSlugs = [];
        foreach ($groups as $group) {
            foreach ((array) ($group['towns'] ?? []) as $town) {
                foreach ((array) ($town['project_slugs'] ?? []) as $slug) {
                    $allSlugs[] = (string) $slug;
                }
            }
        }
        $resolved = self::projectsKeyedBySlug($allSlugs);

        $pages = self::pages();
        $out = [];
        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }
            $towns = [];
            foreach ((array) ($group['towns'] ?? []) as $town) {
                if (! is_array($town)) {
                    continue;
                }
                $pageKey = trim((string) ($town['page'] ?? ''));
                $page = $pageKey !== '' ? ($pages[$pageKey] ?? null) : null;
                $href = null;
                if (is_array($page) && ($page['published'] ?? false)) {
                    $href = url('/service-areas/'.$page['slug']);
                }
                $townProjects = collect();
                foreach ((array) ($town['project_slugs'] ?? []) as $slug) {
                    $project = $resolved[(string) $slug] ?? null;
                    if ($project instanceof Project && ! $townProjects->contains(fn ($row) => $row->id === $project->id)) {
                        $townProjects->push($project);
                    }
                }
                $towns[] = [
                    'name' => (string) ($town['name'] ?? ''),
                    'note' => (string) ($town['note'] ?? ''),
                    'href' => $href,
                    'projects' => $townProjects,
                ];
            }
            $out[] = [
                'heading' => (string) ($group['heading'] ?? ''),
                'intro' => (string) ($group['intro'] ?? ''),
                'towns' => $towns,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return list<array{name: string, url: string}>
     */
    public static function nearbyPublished(array $page): array
    {
        $out = [];
        foreach ((array) ($page['nearby'] ?? []) as $slug) {
            $nearby = self::publishedPage((string) $slug);
            if ($nearby === null) {
                continue;
            }
            $out[] = [
                'name' => (string) ($nearby['hub_label'] ?? $nearby['name'] ?? $nearby['slug']),
                'url' => url('/service-areas/'.$nearby['slug']),
            ];
        }

        return $out;
    }
}
