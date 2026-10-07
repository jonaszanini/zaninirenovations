<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PublicSitemap
{
    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public static function urls(?Collection $blogPosts = null, ?Collection $projects = null): array
    {
        $blogPosts = $blogPosts ?? BlogPost::query()
            ->indexable()
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $projects = $projects ?? Project::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $urls = [
            [
                'loc' => url('/'),
                'lastmod' => self::homeLastmod($blogPosts, $projects),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
            [
                'loc' => url('/warranty'),
                'lastmod' => self::lastmodFromFile(resource_path('views/site/warranty.blade.php')),
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'loc' => url('/privacy-policy'),
                'lastmod' => self::lastmodFromFile(resource_path('views/site/privacy-policy.blade.php')),
                'changefreq' => 'yearly',
                'priority' => '0.5',
            ],
            [
                'loc' => url('/terms-and-conditions'),
                'lastmod' => self::lastmodFromFile(resource_path('views/site/terms-and-conditions.blade.php')),
                'changefreq' => 'yearly',
                'priority' => '0.5',
            ],
            [
                'loc' => url('/blog'),
                'lastmod' => self::blogIndexLastmod($blogPosts),
                'changefreq' => 'weekly',
                'priority' => '0.85',
            ],
            [
                'loc' => url('/portfolio'),
                'lastmod' => self::portfolioIndexLastmod($projects),
                'changefreq' => 'weekly',
                'priority' => '0.85',
            ],
        ];

        foreach ($blogPosts as $post) {
            $updated = $post->updated_at;
            $urls[] = [
                'loc' => url('/blog/'.$post->slug),
                'lastmod' => $updated instanceof Carbon
                    ? $updated->toAtomString()
                    : self::lastmodFromTimestamp(time()),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        foreach ($projects as $project) {
            $slug = trim((string) $project->slug);
            if ($slug === '') {
                continue;
            }
            $updated = $project->updated_at;
            $urls[] = [
                'loc' => url('/portfolio/'.$slug),
                'lastmod' => $updated instanceof Carbon
                    ? $updated->toAtomString()
                    : self::lastmodFromTimestamp(time()),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        return $urls;
    }

    public static function lastmodFromFile(string $path): string
    {
        $ts = is_file($path) ? (int) filemtime($path) : time();

        return self::lastmodFromTimestamp($ts);
    }

    public static function lastmodFromTimestamp(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->toAtomString();
    }

    protected static function homeLastmod(Collection $blogPosts, Collection $projects): string
    {
        $candidates = [
            self::fileTimestamp(resource_path('views/site/home.blade.php')),
            self::fileTimestamp(config_path('seo.php')),
        ];

        $latestBlog = $blogPosts->max('updated_at');
        if ($latestBlog instanceof Carbon) {
            $candidates[] = $latestBlog->getTimestamp();
        }

        $latestProject = $projects->max('updated_at');
        if ($latestProject instanceof Carbon) {
            $candidates[] = $latestProject->getTimestamp();
        }

        return self::lastmodFromTimestamp(max($candidates));
    }

    protected static function portfolioIndexLastmod(Collection $projects): string
    {
        $candidates = [
            self::fileTimestamp(resource_path('views/site/portfolio/index.blade.php')),
        ];

        $latestProject = $projects->max('updated_at');
        if ($latestProject instanceof Carbon) {
            $candidates[] = $latestProject->getTimestamp();
        }

        return self::lastmodFromTimestamp(max($candidates));
    }

    protected static function blogIndexLastmod(Collection $blogPosts): string
    {
        $candidates = [
            self::fileTimestamp(resource_path('views/site/blog/index.blade.php')),
        ];

        $latestBlog = $blogPosts->max('updated_at');
        if ($latestBlog instanceof Carbon) {
            $candidates[] = $latestBlog->getTimestamp();
        }

        return self::lastmodFromTimestamp(max($candidates));
    }

    protected static function fileTimestamp(string $path): int
    {
        return is_file($path) ? (int) filemtime($path) : time();
    }
}
