<?php

namespace App\Services\SupplierWebsite;

use App\Support\SafeOutboundHttp;
use Illuminate\Http\Client\Response;

class SupplierWebsitePageFetcher
{
    public const MAX_PAGES = 5;

    public const MAX_BYTES = 2_097_152;

    public const TIMEOUT_SECONDS = 12;

    /**
     * @return list<array{url: string, html: string}>
     */
    public function fetchSitePages(string $startUrl): array
    {
        $normalized = $this->normalizeUrl($startUrl);
        if ($normalized === null) {
            throw new \InvalidArgumentException('Enter a valid http or https website URL.');
        }

        if (! SafeOutboundHttp::isAllowedPublicWebUrl($normalized)) {
            throw new \RuntimeException('That URL is not allowed (use a public http or https address).');
        }

        $homeResponse = $this->get($normalized);
        if ($homeResponse === null || ! $homeResponse->successful()) {
            throw new \RuntimeException('Could not fetch the website homepage.');
        }

        $homeHtml = (string) $homeResponse->body();
        $pages = [
            ['url' => $normalized, 'html' => $homeHtml],
        ];

        $candidates = $this->discoverCandidateUrls($normalized, $homeHtml);
        foreach ($candidates as $url) {
            if (count($pages) >= self::MAX_PAGES) {
                break;
            }
            if ($url === $normalized) {
                continue;
            }
            $response = $this->get($url);
            if ($response === null || ! $response->successful()) {
                continue;
            }
            $html = (string) $response->body();
            if (strlen(trim($html)) < 80) {
                continue;
            }
            $pages[] = ['url' => $url, 'html' => $html];
        }

        return $pages;
    }

    private function get(string $url): ?Response
    {
        return SafeOutboundHttp::getWeb($url, [
            'User-Agent' => 'ZaniniAdminSupplierImport/1.0 (+https://zaninirenovations.ca)',
            'Accept' => 'text/html,application/xhtml+xml',
            'Accept-Language' => 'en-CA,en;q=0.9',
        ], self::TIMEOUT_SECONDS, self::MAX_BYTES);
    }

    /**
     * @return list<string>
     */
    private function discoverCandidateUrls(string $homeUrl, string $html): array
    {
        $parts = parse_url($homeUrl);
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return [];
        }

        $keywords = [
            'contact' => 10,
            'location' => 9,
            'locations' => 9,
            'find-us' => 9,
            'findus' => 9,
            'about' => 7,
            'hours' => 6,
            'store' => 5,
            'visit' => 4,
        ];

        $scores = [];
        if (preg_match_all('/<a\s[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $absolute = $this->resolveUrl($homeUrl, trim($href));
                if ($absolute === null) {
                    continue;
                }
                $linkParts = parse_url($absolute);
                if (! is_array($linkParts)) {
                    continue;
                }
                if (strtolower((string) ($linkParts['host'] ?? '')) !== $host) {
                    continue;
                }
                $path = strtolower((string) ($linkParts['path'] ?? '/'));
                $score = 0;
                foreach ($keywords as $word => $weight) {
                    if (str_contains($path, $word)) {
                        $score += $weight;
                    }
                }
                if ($score > 0) {
                    $scores[$absolute] = max($scores[$absolute] ?? 0, $score);
                }
            }
        }

        arsort($scores);

        return array_keys($scores);
    }

    private function normalizeUrl(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }

        return $scheme.'://'.$host.$port.$path;
    }

    private function resolveUrl(string $base, string $href): ?string
    {
        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $this->normalizeUrl($href);
        }
        $parts = parse_url($base);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $scheme = (string) ($parts['scheme'] ?? 'https');
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if (str_starts_with($href, '//')) {
            return $this->normalizeUrl($scheme.':'.$href);
        }
        if (str_starts_with($href, '/')) {
            return $this->normalizeUrl($scheme.'://'.$host.$port.$href);
        }
        $basePath = (string) ($parts['path'] ?? '/');
        $dir = rtrim(str_replace(basename($basePath), '', $basePath), '/');

        return $this->normalizeUrl($scheme.'://'.$host.$port.$dir.'/'.$href);
    }
}
