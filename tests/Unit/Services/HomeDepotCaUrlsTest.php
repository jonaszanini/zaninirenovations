<?php

namespace Tests\Unit\Services;

use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Services\MaterialScraping\MaterialPriceSyncService;
use Illuminate\Http\Client\ConnectionException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class HomeDepotCaUrlsTest extends TestCase
{
    public function test_normalize_stored_sku_trims_and_keeps_digits_only_for_item_numbers(): void
    {
        $this->assertSame('1001898921', HomeDepotCaUrls::normalizeStoredSku(' 100-189-8921 '));
        $this->assertSame('1001898921', HomeDepotCaUrls::normalizeStoredSku('1001898921'));
        $this->assertSame('1001898921', HomeDepotCaUrls::normalizeStoredSku('100 189 8921'));
        $this->assertSame('1001898921', HomeDepotCaUrls::normalizeStoredSku('SKU 1001898921'));
        $this->assertSame('1001898921', HomeDepotCaUrls::normalizeStoredSku('sku: 100-189-8921'));
        $this->assertNull(HomeDepotCaUrls::normalizeStoredSku('   '));
        $this->assertNull(HomeDepotCaUrls::normalizeStoredSku(null));
    }

    public function test_normalize_stored_sku_keeps_asins_and_letter_codes(): void
    {
        $this->assertSame('B07VVMBFBV', HomeDepotCaUrls::normalizeStoredSku(' B07VVMBFBV '));
        $this->assertSame('DM-2X4', HomeDepotCaUrls::normalizeStoredSku('DM-2X4'));
    }

    public function test_is_unreachable_error_detects_timeouts_and_connection_failures(): void
    {
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new ConnectionException('cURL error 28: Operation timed out')
        ));
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('cURL error 6: Could not resolve host: www.homedepot.ca')
        ));
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('SerpApi connection failed: failed to connect')
        ));
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('HTTP 503 fetching https://www.homedepot.ca/product/1001898921')
        ));
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('SerpApi HTTP 429')
        ));
        $this->assertTrue(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('Your account has exceeded its API quota')
        ));
        $this->assertFalse(MaterialPriceSyncService::isUnreachableError(
            new RuntimeException('Could not extract name and price from page.')
        ));
    }
}
