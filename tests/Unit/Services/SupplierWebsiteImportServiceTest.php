<?php

namespace Tests\Unit\Services;

use App\Services\SupplierSiteDistanceService;
use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use App\Services\SupplierWebsite\SupplierWebsiteImportService;
use App\Services\SupplierWebsite\SupplierWebsitePageFetcher;
use Mockery;
use Tests\TestCase;

class SupplierWebsiteImportServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_preview_picks_closest_address_when_multiple_found(): void
    {
        $fetcher = Mockery::mock(SupplierWebsitePageFetcher::class);
        $fetcher->shouldReceive('fetchSitePages')
            ->once()
            ->with('https://supply.test')
            ->andReturn([
                [
                    'url' => 'https://supply.test',
                    'html' => '<script type="application/ld+json">{"@type":"Store","name":"Test Supply","address":[{"@type":"PostalAddress","streetAddress":"1 Far St","addressLocality":"Ottawa","addressRegion":"ON"},{"@type":"PostalAddress","streetAddress":"2 Near St","addressLocality":"Carleton Place","addressRegion":"ON"}]}</script>',
                ],
            ]);

        $distances = Mockery::mock(SupplierSiteDistanceService::class);
        $distances->shouldReceive('pickClosestAddress')
            ->once()
            ->andReturn([
                'address' => '2 Near St, Carleton Place, ON',
                'distance_km' => 5.0,
                'drive_minutes' => 10,
            ]);

        $service = new SupplierWebsiteImportService(
            $fetcher,
            new SupplierWebsiteDataExtractor,
            $distances
        );

        $result = $service->preview('https://supply.test');

        $this->assertTrue($result['ok']);
        $this->assertSame('Test Supply', $result['data']['name']);
        $this->assertSame('2 Near St, Carleton Place, ON', $result['data']['address']);
        $this->assertNotEmpty($result['data']['other_addresses']);
        $this->assertStringContainsString('5.0', (string) $result['data']['distance_note']);
    }

    public function test_preview_returns_logo_and_tags_without_logo_in_notes(): void
    {
        $fetcher = Mockery::mock(SupplierWebsitePageFetcher::class);
        $fetcher->shouldReceive('fetchSitePages')
            ->once()
            ->andReturn([
                [
                    'url' => 'https://glass.test',
                    'html' => '<html><head><meta name="keywords" content="glass, laminated glass"><meta property="og:image" content="https://glass.test/logo.png"></head><body><h2>Fire-rated glass</h2></body></html>',
                ],
            ]);

        $distances = Mockery::mock(SupplierSiteDistanceService::class);

        $service = new SupplierWebsiteImportService(
            $fetcher,
            new SupplierWebsiteDataExtractor,
            $distances
        );

        $result = $service->preview('https://glass.test');

        $this->assertTrue($result['ok']);
        $this->assertSame('https://glass.test/logo.png', $result['data']['logo_url']);
        $this->assertNotNull($result['data']['tags']);
        $this->assertStringNotContainsString('Logo:', (string) ($result['data']['notes'] ?? ''));
    }
}
