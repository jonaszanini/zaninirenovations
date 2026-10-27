<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Server-side GET of public HTTP(S) URLs (no private/link-local hosts).
 */
class SafeOutboundHttp
{
    public static function isAllowedPublicHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        return self::isAllowedPublicHost($parts);
    }

    /** @param  array<string, mixed>  $parts */
    public static function isAllowedPublicWebUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return self::isAllowedPublicHost($parts);
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private static function isAllowedPublicHost(array $parts): bool
    {
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! self::isPrivateIp($host);
        }

        $ips = @gethostbynamel($host);
        if (! is_array($ips) || $ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (self::isPrivateIp((string) $ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function getWeb(
        string $url,
        array $headers = [],
        int $timeout = 15,
        int $maxBytes = 2_097_152
    ): ?Response {
        if (! self::isAllowedPublicWebUrl($url)) {
            return null;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'strict' => true,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => static function ($request, $response, $uri) {
                            if (! self::isAllowedPublicWebUrl((string) $uri)) {
                                throw new \RuntimeException('Redirect to disallowed URL blocked.');
                            }
                        },
                    ],
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if ($response === null) {
            return null;
        }

        $body = (string) $response->body();
        if (strlen($body) > $maxBytes) {
            return null;
        }

        return $response;
    }

    public static function get(string $url, array $headers = [], int $timeout = 30): ?Response
    {
        if (! self::isAllowedPublicHttpsUrl($url)) {
            return null;
        }

        try {
            return Http::withHeaders($headers)->timeout($timeout)->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.168.0.0', '192.168.255.255'],
            ];
            foreach ($ranges as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return true;
                }
            }

            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = @inet_pton($ip);
            if ($packed === false) {
                return true;
            }
            $v4mapped = substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
            if ($v4mapped) {
                return self::isPrivateIp(inet_ntop(substr($packed, 12)));
            }

            return $ip === '::1' || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')
                || str_starts_with($ip, 'fe8') || str_starts_with($ip, 'fe9')
                || str_starts_with($ip, 'fea') || str_starts_with($ip, 'feb');
        }

        return true;
    }
}
