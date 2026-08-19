<?php

namespace App\Support;

/**
 * robots.txt for the public marketing site (not admin).
 */
class RobotsTxtGenerator
{
    /** @return list<string> */
    public static function disallowedPaths(): array
    {
        return [
            '/admin',
            '/admin/',
            '/client/',
            '/dashboard',
        ];
    }

    /** @return list<string> */
    public static function aiUserAgents(): array
    {
        $agents = config('seo.ai_crawler_user_agents', []);

        return is_array($agents) ? array_values(array_filter($agents, 'is_string')) : [];
    }

    public static function generate(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $disallowed = self::disallowedPaths();
        $lines = [
            '# Zanini Renovations — public website',
            '# https://zaninirenovations.ca',
            '',
            'User-agent: *',
        ];
        foreach ($disallowed as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = 'Allow: /';
        $lines[] = '';

        $aiAgents = self::aiUserAgents();
        if ($aiAgents !== []) {
            $lines[] = '# AI search, answer engines, and training crawlers (public pages only)';
            foreach ($aiAgents as $agent) {
                $lines[] = 'User-agent: '.$agent;
            }
            $lines[] = 'Allow: /';
            foreach ($disallowed as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
        }

        $lines[] = 'Sitemap: '.$base.'/sitemap.xml';
        $lines[] = '';
        $lines[] = '# LLM content index: '.$base.'/llms.txt';
        $lines[] = '';

        return implode("\n", $lines);
    }
}
