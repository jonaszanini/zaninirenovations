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

    /** @var null|callable(string): list<string> */
    private static $hostIpResolver = null;

    public static function setHostIpResolverForTesting(?callable $resolver): void
    {
        self::$hostIpResolver = $resolver;
    }

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
    ): SafeOutboundWebFetchResult {
        if ($deadline !== null && microtime(true) >= $deadline) {
            return self::fetchFailed('deadline_exceeded', 'Import timed out before the page could be fetched.');
        }

        $validated = self::validateWebRequestUrl($url);
        if ($validated === null) {
            return self::fetchFailed('invalid_url', 'That URL is not allowed (use a public http or https address).');
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

        $result = self::pinnedGet(
            $validated,
            $headers,
            $timeout,
            self::DEFAULT_MAX_BYTES,
            null,
            self::DEFAULT_CONNECT_TIMEOUT,
            requireHtml: false
        );

        return $result->response;
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
            'url' => $scheme.'://'.self::hostForUrl($host).($port === 80 || $port === 443 ? '' : ':'.$port).$path.$query.$fragment,
            'host' => $host,
            'scheme' => $scheme,
            'port' => $port,
            'resolve' => $resolve,
        ];
    }

    private static function hostForUrl(string $host): string
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '['.$host.']';
        }

        return $host;
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

        if (self::$hostIpResolver !== null) {
            $ips = (self::$hostIpResolver)($host);
            if (! is_array($ips)) {
                return [];
            }

            return self::filterPublicIps($ips);
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

        return self::filterPublicIps($ips);
    }

    /**
     * @param  list<string>  $ips
     * @return list<string>
     */
    private static function filterPublicIps(array $ips): array
    {
        $public = [];
        foreach (array_unique($ips) as $ip) {
            if (is_string($ip) && $ip !== '' && ! self::isBlockedIp($ip)) {
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
            $ipForResolve = $ip;
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ipForResolve = '['.$ip.']';
            }
            $entries[] = $host.':'.$port.':'.$ipForResolve;
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
    ): SafeOutboundWebFetchResult {
        $currentUrl = $validated['url'];

        for ($hop = 0; $hop <= 3; $hop++) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                return self::fetchFailed('deadline_exceeded', 'Import timed out before the page could be fetched.');
            }

            $validated = self::validateWebRequestUrl($currentUrl);
            if ($validated === null) {
                return self::fetchFailed(
                    'redirect_blocked',
                    'The website redirected to an address that is not allowed.',
                    ['url' => $currentUrl]
                );
            }

            $remaining = $deadline !== null ? max(1, (int) ceil($deadline - microtime(true))) : $timeout;
            $requestTimeout = min($timeout, $remaining);

            $resolveEntries = self::sortResolveEntriesIpv4First($validated['resolve']);
            $lastConnectDetail = null;

            foreach ($resolveEntries as $resolveEntry) {
                try {
                    $response = Http::withHeaders($headers)
                        ->connectTimeout($connectTimeout)
                        ->timeout($requestTimeout)
                        ->withOptions([
                            'curl' => [
                                CURLOPT_RESOLVE => [$resolveEntry],
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            ],
                            'allow_redirects' => false,
                        ])
                        ->get($validated['url']);
                } catch (\Throwable $e) {
                    $lastConnectDetail = self::describeThrowable($e, $resolveEntry);

                    continue;
                }

                $status = $response->status();
                if (in_array($status, [301, 302, 303, 307, 308], true)) {
                    $location = self::headerValue($response, 'Location');
                    if ($location === '') {
                        return self::fetchFailed(
                            'redirect_missing_location',
                            'The website returned a redirect without a destination URL.',
                            ['url' => $validated['url'], 'status' => $status]
                        );
                    }
                    $nextUrl = self::resolveRedirectUrl($validated['url'], $location);
                    if ($nextUrl === null) {
                        return self::fetchFailed(
                            'redirect_invalid',
                            'The website redirect could not be followed.',
                            ['url' => $validated['url'], 'location' => $location]
                        );
                    }
                    $currentUrl = $nextUrl;

                    continue 2;
                }

                $body = (string) $response->body();

                if (! $response->successful()) {
                    $blocked = self::detectAutomatedAccessBlock($status, $response, $body);
                    if ($blocked !== null) {
                        return self::fetchFailed(
                            $blocked['code'],
                            $blocked['user_message'],
                            ['url' => $validated['url'], 'status' => $status, 'resolve' => $resolveEntry]
                        );
                    }

                    return self::fetchFailed(
                        'http_error',
                        sprintf('The website homepage returned HTTP %d.', $status),
                        ['url' => $validated['url'], 'status' => $status, 'resolve' => $resolveEntry]
                    );
                }

                if (strlen($body) > $maxBytes) {
                    return self::fetchFailed(
                        'body_too_large',
                        'The website homepage is too large to import.',
                        ['url' => $validated['url'], 'bytes' => strlen($body)]
                    );
                }

                if ($requireHtml && ! self::responseIsHtml($response) && ! self::bodyLooksLikeHtml($body)) {
                    $type = self::headerValue($response, 'Content-Type');

                    return self::fetchFailed(
                        'not_html',
                        $type !== ''
                            ? sprintf('The website homepage is not HTML (Content-Type: %s).', $type)
                            : 'The website homepage is not HTML.',
                        ['url' => $validated['url'], 'content_type' => $type]
                    );
                }

                return new SafeOutboundWebFetchResult(new Response(new Psr7Response(
                    $response->status(),
                    $response->headers(),
                    $body
                )));
            }

            if ($lastConnectDetail !== null) {
                return self::fetchFailed(
                    $lastConnectDetail['code'],
                    $lastConnectDetail['user_message'],
                    ['url' => $validated['url'], 'detail' => $lastConnectDetail['detail']]
                );
            }

            return self::fetchFailed(
                'connection_failed',
                'Could not connect to the website server.',
                ['url' => $validated['url']]
            );
        }

        return self::fetchFailed(
            'too_many_redirects',
            'The website redirected too many times.',
            ['url' => $currentUrl]
        );
    }

    /**
     * @param  list<string>  $entries
     * @return list<string>
     */
    private static function sortResolveEntriesIpv4First(array $entries): array
    {
        usort($entries, function (string $a, string $b): int {
            $ipA = self::ipFromResolveEntry($a);
            $ipB = self::ipFromResolveEntry($b);
            $v4A = filter_var($ipA, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            $v4B = filter_var($ipB, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
            if ($v4A === $v4B) {
                return 0;
            }

            return $v4A ? -1 : 1;
        });

        return $entries;
    }

    private static function ipFromResolveEntry(string $entry): string
    {
        if (preg_match('/:\[([^\]]+)\]$/', $entry, $matches)) {
            return $matches[1];
        }
        $pos = strrpos($entry, ':');
        if ($pos === false) {
            return '';
        }

        return substr($entry, $pos + 1);
    }

    /**
     * @return array{code: string, user_message: string, detail: string}
     */
    /**
     * @return array{code: string, user_message: string}|null
     */
    private static function detectAutomatedAccessBlock(int $status, Response $response, string $body): ?array
    {
        if (! in_array($status, [403, 429, 503], true)) {
            return null;
        }

        $lowerBody = strtolower(substr($body, 0, 4000));
        $cfRay = self::headerValue($response, 'cf-ray');
        $server = strtolower(self::headerValue($response, 'Server'));

        if ($cfRay !== ''
            || str_contains($server, 'cloudflare')
            || str_contains($lowerBody, 'cloudflare')
            || str_contains($lowerBody, 'just a moment')
            || str_contains($lowerBody, 'attention required')) {
            return [
                'code' => 'bot_blocked',
                'user_message' => sprintf(
                    'The website blocked automated access (HTTP %d). Open the site in a browser and enter details manually.',
                    $status
                ),
            ];
        }

        return null;
    }

    private static function describeThrowable(\Throwable $e, ?string $resolveEntry = null): array
    {
        $detail = $e->getMessage();
        if ($resolveEntry !== null) {
            $detail = 'resolve='.$resolveEntry.'; '.$detail;
        }
        $lower = strtolower($detail);

        if (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
            return [
                'code' => 'ssl_error',
                'user_message' => 'Could not verify the website\'s SSL certificate.',
                'detail' => $detail,
            ];
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout') || str_contains($lower, 'curl error 28')) {
            return [
                'code' => 'timeout',
                'user_message' => 'The website took too long to respond.',
                'detail' => $detail,
            ];
        }

        if (str_contains($lower, 'could not resolve host')
            || str_contains($lower, 'curl error 6')
            || str_contains($lower, 'failed to connect')
            || str_contains($lower, 'curl error 7')) {
            return [
                'code' => 'connection_failed',
                'user_message' => 'Could not connect to the website server.',
                'detail' => $detail,
            ];
        }

        return [
            'code' => 'request_failed',
            'user_message' => 'Could not fetch the website homepage.',
            'detail' => $detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function fetchFailed(string $code, string $userMessage, array $context = []): SafeOutboundWebFetchResult
    {
        $logDetail = $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : null;

        return new SafeOutboundWebFetchResult(null, $code, $userMessage, $logDetail);
    }

    private static function headerValue(Response $response, string $name): string
    {
        $value = $response->header($name);
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim((string) $value);
    }

    private static function bodyLooksLikeHtml(string $body): bool
    {
        $start = strtolower(ltrim(substr($body, 0, 512)));

        return str_starts_with($start, '<!doctype html')
            || str_starts_with($start, '<html')
            || str_starts_with($start, '<?xml');
    }

    private static function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $location)) {
            return self::validateWebRequestUrl($location) !== null ? $location : null;
        }
        $base = parse_url($baseUrl);
        if (! is_array($base) || empty($base['host'])) {
            return null;
        }
        $scheme = (string) ($base['scheme'] ?? 'https');
        $host = (string) $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';
        if (str_starts_with($location, '//')) {
            $absolute = $scheme.':'.$location;

            return self::validateWebRequestUrl($absolute) !== null ? $absolute : null;
        }
        if (str_starts_with($location, '/')) {
            $absolute = $scheme.'://'.self::hostForUrl($host).$port.$location;

            return self::validateWebRequestUrl($absolute) !== null ? $absolute : null;
        }

        $path = (string) ($base['path'] ?? '/');
        $dir = rtrim(str_replace(basename($path), '', $path), '/');
        $absolute = $scheme.'://'.self::hostForUrl($host).$port.$dir.'/'.$location;

        return self::validateWebRequestUrl($absolute) !== null ? $absolute : null;
    }

    private static function responseIsHtml(Response $response): bool
    {
        $type = strtolower(self::headerValue($response, 'Content-Type'));
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
        if ($packed === inet_pton('::1') || $packed === @inet_pton('::127.0.0.1')) {
            return true;
        }

        if (self::ipv6InPrefix($packed, '2002::', 16)) {
            return true;
        }

        if (self::ipv6InPrefix($packed, '2001:0000::', 32)) {
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
