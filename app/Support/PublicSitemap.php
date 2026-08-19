<?php

namespace App\Support;

use App\Models\BlogPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PublicSitemap
{
    /**
     * @return list<array{loc: string, lastmod: string, changefreq: string, priority: string}>
     */
    public static function urls(?Collection $blogPosts = null): array
    {
        $blogPosts = $blogPosts ?? BlogPost::query()
            ->published()
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at']);

        $urls = [
            [
                'loc' => url('/'),
                'lastmod' => self::homeLastmod($blogPosts),
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

    protected static function homeLastmod(Collection $blogPosts): string
    {
        $candidates = [
            self::fileTimestamp(resource_path('views/site/home.blade.php')),
            self::fileTimestamp(config_path('seo.php')),
        ];

        $latestBlog = $blogPosts->max('updated_at');
        if ($latestBlog instanceof Carbon) {
            $candidates[] = $latestBlog->getTimestamp();
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
