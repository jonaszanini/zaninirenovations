<?php

namespace App\Support;

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Server-side GET of public HTTP(S) URLs (no private/link-local hosts).
 */
class SafeOutboundHttp
{
    public const DEFAULT_MAX_BYTES = 2_097_152;

    public const DEFAULT_CONNECT_TIMEOUT = 5;

    /**
     * @param  array<string, string>  $headers
     */
    public static function getWeb(
        string $url,
        array $headers = [],
        int $timeout = 15,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
        ?float $deadline = null,
        int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT
    ): ?Response {
        if ($deadline !== null && microtime(true) >= $deadline) {
            return null;
        }

        $validated = self::validateWebRequestUrl($url);
        if ($validated === null) {
            return null;
        }

        return self::pinnedGet(
            $validated,
            $headers,
            $timeout,
            $maxBytes,
            $deadline,
            $connectTimeout,
            requireHtml: true
        );
    }

    public static function get(string $url, array $headers = [], int $timeout = 30): ?Response
    {
        $parts = self::parseUrlParts($url);
        if ($parts === null || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }

        $validated = self::validateWebRequestUrl($url);
        if ($validated === null) {
            return null;
        }

        return self::pinnedGet(
            $validated,
            $headers,
            $timeout,
            self::DEFAULT_MAX_BYTES,
            null,
            self::DEFAULT_CONNECT_TIMEOUT,
            requireHtml: false
        );
    }

    public static function isAllowedPublicHttpsUrl(string $url): bool
    {
        $parts = self::parseUrlParts($url);
        if ($parts === null) {
            return false;
        }
        if (strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        return self::hostIsAllowedPublic((string) ($parts['host'] ?? ''));
    }

    public static function isAllowedPublicWebUrl(string $url): bool
    {
        return self::validateWebRequestUrl($url) !== null;
    }

    /**
     * @return array{url: string, host: string, scheme: string, port: int, resolve: list<string>}|null
     */
    public static function validateWebRequestUrl(string $url): ?array
    {
        $parts = self::parseUrlParts($url);
        if ($parts === null) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = (string) ($parts['host'] ?? '');
        if ($host === '' || ! self::hostIsAllowedPublic($host)) {
            return null;
        }

        $port = self::effectivePort($parts, $scheme);
        if (! in_array($port, [80, 443], true)) {
            return null;
        }

        $resolve = self::buildResolveEntries($host, $port);
        if ($resolve === []) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return [
            'url' => $scheme.'://'.$host.($port === 80 || $port === 443 ? '' : ':'.$port).$path.$query.$fragment,
            'host' => $host,
            'scheme' => $scheme,
            'port' => $port,
            'resolve' => $resolve,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function parseUrlParts(string $url): ?array
    {
        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $host = (string) $parts['host'];
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $parts['host'] = substr($host, 1, -1);
        }

        return $parts;
    }

    private static function effectivePort(array $parts, string $scheme): int
    {
        if (isset($parts['port'])) {
            return (int) $parts['port'];
        }

        return $scheme === 'https' ? 443 : 80;
    }

    public static function hostIsAllowedPublic(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ! self::isBlockedIp($host);
        }

        return self::resolveHostIps($host) !== [];
    }

    /**
     * @return list<string>
     */
    public static function resolveHostIps(string $host): array
    {
        $host = strtolower(trim($host));
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isBlockedIp($host) ? [] : [$host];
        }

        $ips = [];
        $aRecords = @dns_get_record($host, DNS_A);
        if (is_array($aRecords)) {
            foreach ($aRecords as $row) {
                if (! empty($row['ip']) && is_string($row['ip'])) {
                    $ips[] = $row['ip'];
                }
            }
        }
        $aaaaRecords = @dns_get_record($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $row) {
                if (! empty($row['ipv6']) && is_string($row['ipv6'])) {
                    $ips[] = $row['ipv6'];
                }
            }
        }

        $public = [];
        foreach (array_unique($ips) as $ip) {
            if (! self::isBlockedIp($ip)) {
                $public[] = $ip;
            }
        }

        return $public;
    }

    /**
     * @return list<string>
     */
    private static function buildResolveEntries(string $host, int $port): array
    {
        $ips = self::resolveHostIps($host);
        $entries = [];
        foreach ($ips as $ip) {
            $entries[] = $host.':'.$port.':'.$ip;
        }

        return $entries;
    }

    /**
     * @param  array{url: string, host: string, scheme: string, port: int, resolve: list<string>}  $validated
     * @param  array<string, string>  $headers
     */
    private static function pinnedGet(
        array $validated,
        array $headers,
        int $timeout,
        int $maxBytes,
        ?float $deadline,
        int $connectTimeout,
        bool $requireHtml
    ): ?Response {
        $remaining = $deadline !== null ? max(1, (int) ceil($deadline - microtime(true))) : $timeout;
        $requestTimeout = min($timeout, $remaining);

        $currentUrl = $validated['url'];

        for ($hop = 0; $hop <= 3; $hop++) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                return null;
            }

            $validated = self::validateWebRequestUrl($currentUrl);
            if ($validated === null) {
                return null;
            }

            $remaining = $deadline !== null ? max(1, (int) ceil($deadline - microtime(true))) : $timeout;
            $requestTimeout = min($timeout, $remaining);

            try {
                $response = Http::withHeaders($headers)
                    ->connectTimeout($connectTimeout)
                    ->timeout($requestTimeout)
                    ->withOptions([
                        'curl' => [
                            CURLOPT_RESOLVE => $validated['resolve'],
                        ],
                        'allow_redirects' => false,
                        'stream' => true,
                    ])
                    ->get($validated['url']);
            } catch (\Throwable) {
                return null;
            }

            $status = $response->status();
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = (string) $response->header('Location');
                if ($location === '') {
                    return null;
                }
                $currentUrl = self::resolveRedirectUrl($validated['url'], $location);
                if ($currentUrl === null) {
                    return null;
                }

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            if ($requireHtml && ! self::responseIsHtml($response)) {
                return null;
            }

            $body = self::readStreamBody($response, $maxBytes);
            if ($body === null) {
                return null;
            }

            return new Response(new Psr7Response(
                $response->status(),
                $response->headers(),
                $body
            ));
        }

        return null;
    }

    private static function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['host'])) {
            return null;
        }
        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $path = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace(basename($path), '', $path), '/');

        return $scheme.'://'.$host.$port.$dir.'/'.$location;
    }

    private static function responseIsHtml(Response $response): bool
    {
        $type = strtolower((string) $response->header('Content-Type'));
        if ($type === '') {
            return false;
        }

        return str_contains($type, 'text/html') || str_contains($type, 'application/xhtml');
    }

    private static function readStreamBody(Response $response, int $maxBytes): ?string
    {
        $stream = $response->toPsrResponse()->getBody();
        $buffer = '';
        while (! $stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                break;
            }
            $buffer .= $chunk;
            if (strlen($buffer) > $maxBytes) {
                return null;
            }
        }

        return $buffer;
    }

    public static function isPrivateIp(string $ip): bool
    {
        return self::isBlockedIp($ip);
    }

    public static function isBlockedIp(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '') {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isBlockedIpv4($ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isBlockedIpv6($ip);
        }

        return true;
    }

    private static function isBlockedIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        $ranges = [
            ['0.0.0.0', '0.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['100.64.0.0', '100.127.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.0.0.0', '192.0.0.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['198.18.0.0', '198.19.255.255'],
            ['224.0.0.0', '255.255.255.255'],
        ];
        foreach ($ranges as [$start, $end]) {
            if ($long >= ip2long($start) && $long <= ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    private static function isBlockedIpv6(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return true;
        }

        if ($packed === inet_pton('::')) {
            return true;
        }

        $v4mapped = substr($packed, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
        if ($v4mapped) {
            $v4 = inet_ntop(substr($packed, 12));

            return $v4 === false || self::isBlockedIpv4($v4);
        }

        if (self::ipv6StartsWith($packed, 'fe80', 10)) {
            return true;
        }
        if (self::ipv6StartsWith($packed, 'fc', 7) || self::ipv6StartsWith($packed, 'fd', 7)) {
            return true;
        }
        if ($packed === inet_pton('::1')) {
            return true;
        }

        if (self::ipv6InPrefix($packed, '64:ff9b::', 96)) {
            return true;
        }

        $first = ord($packed[0]);
        if ($first >= 0xF0) {
            return true;
        }

        return false;
    }

    private static function ipv6StartsWith(string $packed, string $prefixHex, int $prefixBits): bool
    {
        $prefixPacked = @inet_pton($prefixHex);
        if ($prefixPacked === false) {
            return false;
        }
        $fullBytes = intdiv($prefixBits, 8);
        if (substr($packed, 0, $fullBytes) !== substr($prefixPacked, 0, $fullBytes)) {
            return false;
        }
        $remainder = $prefixBits % 8;
        if ($remainder === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($packed[$fullBytes]) & $mask) === (ord($prefixPacked[$fullBytes]) & $mask);
    }

    private static function ipv6InPrefix(string $packed, string $prefix, int $bits): bool
    {
        return self::ipv6StartsWith($packed, $prefix, $bits);
    }
}
