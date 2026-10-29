<?php

namespace Tests\Unit\Support;

use App\Support\PinnedCurlAttemptResult;
use App\Support\SafeOutboundHttp;
use Tests\TestCase;

class SafeOutboundHttpGetWebTest extends TestCase
{
    private const FIXTURE_HOST = 'supplier-import-fixture.test';

    protected function tearDown(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(null);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(null);
        parent::tearDown();
    }

    public function test_get_web_returns_html_response_via_stubbed_curl_transport(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn (string $host) => $host === self::FIXTURE_HOST
            ? ['93.184.216.34']
            : []);

        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn (string $url, array $headers, string $resolveEntry, int $timeout, int $connectTimeout, int $maxBytes) => new PinnedCurlAttemptResult(
                true,
                200,
                ['content-type' => ['text/html; charset=UTF-8']],
                '<!doctype html><html><body>Hello</body></html>'
            )
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertTrue($result->ok());
        $this->assertStringContainsString('Hello', (string) $result->response?->body());
    }

    public function test_get_web_surfaces_http_status_in_user_message(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn () => new PinnedCurlAttemptResult(true, 403, ['content-type' => ['text/plain']], 'Forbidden')
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('http_error', $result->failureCode);
        $this->assertSame('The website homepage returned HTTP 403.', $result->userMessage);
    }

    public function test_get_web_detects_cloudflare_challenge_only_with_real_signals(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn () => new PinnedCurlAttemptResult(
                true,
                403,
                ['content-type' => ['text/html'], 'cf-ray' => ['abc123']],
                '<html><script src="/cdn-cgi/challenge-platform/h/g/orchestrate/chl_page/v1"></script></html>'
            )
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('bot_blocked', $result->failureCode);
    }

    public function test_get_web_does_not_flag_bot_blocked_for_cloudflare_mention_alone(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn () => new PinnedCurlAttemptResult(
                true,
                403,
                ['content-type' => ['text/html'], 'server' => ['cloudflare']],
                '<html><p>Powered by Cloudflare</p></html>'
            )
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('http_error', $result->failureCode);
    }

    public function test_validate_web_request_url_builds_bracketed_ipv6_pins_with_explicit_port(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['2001:db8::1', '93.184.216.34']);

        $validated = SafeOutboundHttp::validateWebRequestUrl('https://'.self::FIXTURE_HOST.'/');

        $this->assertNotNull($validated);
        $this->assertSame('https://'.self::FIXTURE_HOST.':443/', $validated['url']);
        $this->assertContains(self::FIXTURE_HOST.':443:[2001:db8::1]', $validated['resolve']);
        $this->assertContains(self::FIXTURE_HOST.':443:93.184.216.34', $validated['resolve']);
    }

    public function test_pinned_resolve_entry_reaches_curl_layer(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['203.0.113.77']);
        $seenResolve = null;
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            function (string $url, array $headers, string $resolveEntry) use (&$seenResolve) {
                $seenResolve = $resolveEntry;

                return new PinnedCurlAttemptResult(
                    false,
                    failureCode: 'connection_failed',
                    curlError: 'Connection refused',
                    curlErrno: CURLE_COULDNT_CONNECT,
                );
            }
        );

        $result = SafeOutboundHttp::getWeb('https://pin-target.test/');

        $this->assertFalse($result->ok());
        $this->assertSame('pin-target.test:443:203.0.113.77', $seenResolve);
        $this->assertStringContainsString('pin-target.test:443:203.0.113.77', (string) $result->logDetail);
    }

    public function test_unreachable_pinned_ip_fails_without_real_dns_leak(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['203.0.113.199']);

        $result = SafeOutboundHttp::getWeb('https://pin-target.test/');

        $this->assertFalse($result->ok());
        $this->assertContains($result->failureCode, ['connection_failed', 'timeout', 'request_failed']);
        $this->assertStringContainsString('pin-target.test:443:203.0.113.199', (string) $result->logDetail);
    }

    public function test_ipv4_resolve_failure_falls_back_to_second_pinned_ip(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['203.0.113.1', '203.0.113.2']);
        $attempt = 0;
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            function (string $url, array $headers, string $resolveEntry) use (&$attempt) {
                $attempt++;
                if (str_contains($resolveEntry, '203.0.113.1')) {
                    return new PinnedCurlAttemptResult(
                        false,
                        failureCode: 'connection_failed',
                        curlError: 'Connection refused',
                        curlErrno: CURLE_COULDNT_CONNECT,
                    );
                }

                return new PinnedCurlAttemptResult(
                    true,
                    200,
                    ['content-type' => ['text/html; charset=UTF-8']],
                    '<!doctype html><html><body>Ok</body></html>'
                );
            }
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertTrue($result->ok());
        $this->assertSame(2, $attempt);
    }

    public function test_redirect_revalidates_and_repins_target_host(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(function (string $host) {
            return match ($host) {
                'origin.test' => ['203.0.113.10'],
                'dest.test' => ['203.0.113.11'],
                default => [],
            };
        });

        $calls = [];
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            function (string $url, array $headers, string $resolveEntry) use (&$calls) {
                $calls[] = [$url, $resolveEntry];
                if (count($calls) === 1) {
                    return new PinnedCurlAttemptResult(
                        true,
                        302,
                        ['location' => ['https://dest.test:443/welcome']],
                        ''
                    );
                }

                return new PinnedCurlAttemptResult(
                    true,
                    200,
                    ['content-type' => ['text/html; charset=UTF-8']],
                    '<!doctype html><html><body>Dest</body></html>'
                );
            }
        );

        $result = SafeOutboundHttp::getWeb('https://origin.test:443/');

        $this->assertTrue($result->ok());
        $this->assertCount(2, $calls);
        $this->assertSame('https://origin.test:443/', $calls[0][0]);
        $this->assertSame('https://dest.test:443/welcome', $calls[1][0]);
        $this->assertStringContainsString('dest.test:443:203.0.113.11', $calls[1][1]);
    }

    public function test_body_larger_than_cap_aborts_via_transport(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);
        SafeOutboundHttp::setPinnedCurlTransportForTesting(
            fn () => new PinnedCurlAttemptResult(
                false,
                status: 200,
                failureCode: 'body_too_large',
                curlError: 'Maximum file size exceeded',
                curlErrno: 23,
            )
        );

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('body_too_large', $result->failureCode);
    }
}
