<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * After saving a quotation, optionally send the admin to another admin URL
 * (price configuration, project, list) instead of staying on the editor.
 */
class QuotationAfterSaveRedirect
{
    public static function apply(Request $request, RedirectResponse $fallback): RedirectResponse
    {
        $url = self::safeUrl((string) $request->input('after_save_url', ''), $request);
        if ($url === null) {
            return $fallback;
        }

        if ($request->boolean('after_save_attach_return')) {
            $url = self::mergeQuery($url, [
                'return' => self::pathAndQuery($fallback->getTargetUrl()),
            ]);
        }

        $fallback->setTargetUrl($request->getSchemeAndHttpHost().$url);

        return $fallback;
    }

    public static function fromRequest(Request $request): ?string
    {
        return self::safeUrl((string) $request->input('return', $request->query('return', '')), $request);
    }

    public static function withReturnQuery(Request $request, array $params = []): array
    {
        $return = self::fromRequest($request);
        if ($return !== null) {
            $params['return'] = $return;
        }

        return $params;
    }

    public static function safeUrl(string $raw, Request $request): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || str_contains($raw, '\\') || str_contains($raw, '..')) {
            return null;
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $raw)) {
            return null;
        }

        $parts = parse_url($raw);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        if ($host !== null && strcasecmp($host, (string) $request->getHost()) !== 0) {
            return null;
        }

        $path = $parts['path'] ?? '';
        if ($path === '' || ! str_starts_with($path, '/admin/') || str_contains($path, '//')) {
            return null;
        }

        $url = $path;
        if (! empty($parts['query'])) {
            $url .= '?'.$parts['query'];
        }
        if (! empty($parts['fragment'])) {
            $url .= '#'.$parts['fragment'];
        }

        return $url;
    }

    /**
     * @param  array<string, scalar>  $params
     */
    public static function mergeQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        foreach ($params as $key => $value) {
            $query[$key] = (string) $value;
        }

        $out = $parts['path'] ?? '/';
        $qs = http_build_query($query);
        if ($qs !== '') {
            $out .= '?'.$qs;
        }
        if (! empty($parts['fragment'])) {
            $out .= '#'.$parts['fragment'];
        }

        return $out;
    }

    public static function pathAndQuery(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '/';
        if (! empty($parts['query'])) {
            $path .= '?'.$parts['query'];
        }
        if (! empty($parts['fragment'])) {
            $path .= '#'.$parts['fragment'];
        }

        return $path;
    }
}
