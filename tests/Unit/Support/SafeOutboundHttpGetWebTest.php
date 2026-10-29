<?php

namespace Tests\Unit\Support;

use App\Support\SafeOutboundHttp;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SafeOutboundHttpGetWebTest extends TestCase
{
    private const FIXTURE_HOST = 'supplier-import-fixture.test';

    protected function tearDown(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(null);
        parent::tearDown();
    }

    public function test_get_web_returns_html_response_from_faked_http_with_mock_dns(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn (string $host) => $host === self::FIXTURE_HOST
            ? ['93.184.216.34']
            : []);

        Http::fake([
            'https://'.self::FIXTURE_HOST.'/*' => Http::response(
                '<!doctype html><html><head><title>Demo</title></head><body>Hello</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertTrue($result->ok());
        $this->assertStringContainsString('Hello', (string) $result->response?->body());
    }

    public function test_get_web_surfaces_http_status_in_user_message(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);

        Http::fake([
            'https://'.self::FIXTURE_HOST.'/*' => Http::response('Forbidden', 403, ['Content-Type' => 'text/plain']),
        ]);

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('http_error', $result->failureCode);
        $this->assertSame('The website homepage returned HTTP 403.', $result->userMessage);
    }

    public function test_get_web_detects_cloudflare_bot_block_message(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);

        Http::fake([
            'https://'.self::FIXTURE_HOST.'/*' => Http::response(
                '<html>Just a moment...</html>',
                403,
                ['Content-Type' => 'text/html', 'cf-ray' => 'abc123']
            ),
        ]);

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertFalse($result->ok());
        $this->assertSame('bot_blocked', $result->failureCode);
        $this->assertStringContainsString('blocked automated access', (string) $result->userMessage);
    }

    public function test_get_web_accepts_html_without_content_type_when_body_looks_like_html(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['93.184.216.34']);

        Http::fake([
            'https://'.self::FIXTURE_HOST.'/*' => Http::response(
                '<!doctype html><html><body>Ok</body></html>',
                200,
                []
            ),
        ]);

        $result = SafeOutboundHttp::getWeb('https://'.self::FIXTURE_HOST.'/');

        $this->assertTrue($result->ok());
    }

    public function test_validate_web_request_url_builds_bracketed_ipv6_pins_with_explicit_port(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['2001:db8::1', '93.184.216.34']);

        $validated = SafeOutboundHttp::validateWebRequestUrl('https://'.self::FIXTURE_HOST.'/');

        $this->assertNotNull($validated);
        $this->assertContains('supplier-import-fixture.test:443:[2001:db8::1]', $validated['resolve']);
        $this->assertContains('supplier-import-fixture.test:443:93.184.216.34', $validated['resolve']);
    }

    public function test_sort_resolve_entries_prefers_ipv4(): void
    {
        $sort = new \ReflectionMethod(SafeOutboundHttp::class, 'sortResolveEntriesIpv4First');
        $sort->setAccessible(true);
        /** @var list<string> $sorted */
        $sorted = $sort->invoke(null, [
            'example.com:443:[2001:db8::1]',
            'example.com:443:93.184.216.34',
        ]);

        $this->assertSame('example.com:443:93.184.216.34', $sorted[0]);
    }

    public function test_mock_dns_teredo_address_is_blocked_for_ssrf(): void
    {
        SafeOutboundHttp::setHostIpResolverForTesting(fn () => ['2001:0000:4136:e378:8000:63bf:3fff:f2ff']);

        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('https://'.self::FIXTURE_HOST.'/'));
    }

    public function test_ipv6_literal_url_in_brackets_is_blocked(): void
    {
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('https://[::127.0.0.1]/'));
    }
}
