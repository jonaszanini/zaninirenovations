<?php

namespace Tests\Unit\Support;

use App\Support\SafeOutboundHttp;
use Tests\TestCase;

class SafeOutboundHttpTest extends TestCase
{
    public function test_ipv6_literal_urls_in_brackets_are_blocked(): void
    {
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('http://[::1]/'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('https://[0:0:0:0:0:0:0:1]/'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('http://[::ffff:127.0.0.1]/'));
    }

    public function test_blocked_ipv4_ranges(): void
    {
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('127.0.0.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('10.0.0.5'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('100.64.0.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('192.0.0.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('198.18.0.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('224.0.0.1'));
    }

    public function test_ipv4_mapped_loopback_is_blocked(): void
    {
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('::ffff:127.0.0.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('::127.0.0.1'));
    }

    public function test_six_to_four_and_teredo_ipv6_prefixes_are_blocked(): void
    {
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('2002:c000:0201::1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('2001:0000:4136:e378:8000:63bf:3fff:f2ff'));
    }

    public function test_ipv4_compatible_ipv6_literals_are_blocked(): void
    {
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('::192.168.1.1'));
        $this->assertTrue(SafeOutboundHttp::isBlockedIp('::a00:1'));
    }

    public function test_non_standard_ports_are_rejected(): void
    {
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('http://example.com:8080/'));
        $this->assertFalse(SafeOutboundHttp::isAllowedPublicWebUrl('https://example.com:8443/'));
    }

    public function test_resolve_host_ips_rejects_all_private_records(): void
    {
        $this->assertSame([], SafeOutboundHttp::resolveHostIps('127.0.0.1'));
        $this->assertSame([], SafeOutboundHttp::resolveHostIps('::1'));
    }
}
