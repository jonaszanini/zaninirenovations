<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Support\HtmlContentSanitizer;
use App\Support\SafeOutboundHttp;
use App\Support\SeoStructuredData;
use App\Support\SignedPublicLink;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    public function test_html_sanitizer_strips_script_and_event_handlers(): void
    {
        $dirty = '<p>Hello</p><script>alert(1)</script><img src="/ok.webp" onerror="alert(1)">';
        $clean = HtmlContentSanitizer::sanitize($dirty);

        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringContainsString('src="/ok.webp"', $clean);
    }

    public function test_json_ld_escapes_script_breakout(): void
    {
        $json = SeoStructuredData::toJsonLd(['name' => '</script><script>alert(1)']);

        $this->assertStringNotContainsString('</script>', $json);
        $this->assertStringContainsString('\\u003C', $json);
    }

    public function test_home_depot_host_check_rejects_substring_spoof(): void
    {
        $this->assertTrue(HomeDepotCaUrls::hostIsHomeDepot('https://www.homedepot.ca/product/123'));
        $this->assertTrue(HomeDepotCaUrls::hostIsHomeDepot('www.homedepot.ca/product/123'));
        $this->assertFalse(HomeDepotCaUrls::hostIsHomeDepot('https://evilhomedepot.ca/product/123'));
        $this->assertFalse(HomeDepotCaUrls::hostIsHomeDepot('https://example.com/?q=homedepot.ca'));
    }

    public function test_private_ipv4_is_rejected_for_outbound_http(): void
    {
        $this->assertTrue(SafeOutboundHttp::isPrivateIp('127.0.0.1'));
        $this->assertTrue(SafeOutboundHttp::isPrivateIp('10.0.0.5'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicHttpsUrl('http://example.com/x'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicHttpsUrl('https://127.0.0.1/secret'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('https://127.0.0.1/secret'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('ftp://example.com/x'));
        $this->assertTrue(SafeOutboundHttp::isAllowedPublicWebUrl('http://example.com/x'));
    }

    public function test_missing_role_is_not_admin(): void
    {
        $user = new User(['role' => null]);
        $this->assertFalse($user->isAdmin());

        $admin = new User(['role' => User::ROLE_ADMIN]);
        $this->assertTrue($admin->isAdmin());
    }

    public function test_signed_public_link_ttl_is_two_weeks(): void
    {
        $this->assertSame(14, SignedPublicLink::TTL_DAYS);
        $this->assertTrue(SignedPublicLink::expiresAt()->lessThan(now()->addDays(15)));
        $this->assertTrue(SignedPublicLink::expiresAt()->greaterThan(now()->addDays(13)));
    }
}
