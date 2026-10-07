<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BlogPublicContent
{
    /**
     * Insert markup after the first closing paragraph, or append.
     */
    public static function insertAfterIntro(string $html, string $insert): string
    {
        if ($insert === '') {
            return $html;
        }
        if ($html === '') {
            return $insert;
        }
        if (preg_match('/<\/p>/i', $html, $match, PREG_OFFSET_CAPTURE)) {
            $pos = $match[0][1] + strlen($match[0][0]);

            return substr($html, 0, $pos).$insert.substr($html, $pos);
        }

        return $html.$insert;
    }

    /**
     * @return array{title: string, text: string, service_label: string, service_url: string}
     */
    public static function cta(BlogPost $post): array
    {
        $key = trim((string) ($post->cta_service ?? ''));
        $copy = config('blog.cta_copy.'.$key);
        if (! is_array($copy) || ($copy['title'] ?? '') === '') {
            $copy = config('blog.cta_copy.generic', [
                'title' => 'Planning a renovation?',
                'text' => 'Zanini Renovations can walk the site and send a free written estimate.',
            ]);
        }

        $labels = config('blog.cta_services', []);
        $serviceLabel = is_array($labels) ? (string) ($labels[$key] ?? 'Our services') : 'Our services';

        return [
            'title' => (string) ($copy['title'] ?? ''),
            'text' => (string) ($copy['text'] ?? ''),
            'service_label' => $serviceLabel,
            'service_url' => url((string) config('blog.service_url', '/#services')),
        ];
    }

    /**
     * @return Collection<int, Project>
     */
    public static function relatedProjects(BlogPost $post, int $limit = 3): Collection
    {
        $needles = collect([$post->cta_service])
            ->merge($post->categories->pluck('slug'))
            ->merge($post->categories->pluck('name'))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => Str::slug((string) $value))
            ->unique()
            ->values();

        if ($needles->isEmpty()) {
            return collect();
        }

        return Project::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereHas('categories', function ($query) use ($needles, $post) {
                $names = $post->categories->pluck('name')->filter()->all();
                $query->where(function ($inner) use ($needles, $names) {
                    $inner->whereIn('slug', $needles->all());
                    if ($names !== []) {
                        $inner->orWhereIn('name', $names);
                    }
                });
            })
            ->with(['categories', 'coverPhoto'])
            ->orderByCompletionLatest()
            ->limit($limit)
            ->get();
    }
}
