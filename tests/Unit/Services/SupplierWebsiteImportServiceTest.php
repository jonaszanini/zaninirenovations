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
}
