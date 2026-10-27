<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorGoogleMapsAddressTest extends TestCase
{
    public function test_extracts_address_from_google_maps_link_siblings_on_contact_page(): void
    {
        $html = <<<'HTML'
        <html><head><title>Contact | Raw Glass Inc.</title></head><body>
        <p><a href="https://www.google.com/maps?cid=123">140 Industrial Ave., Unit 3, Carleton Place, ON</a>, <a href="https://www.google.com/maps?cid=123">K7C 3T2</a></p>
        </body></html>
        HTML;

        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://rawglass.ca/contact/', 'html' => $html],
        ]);

        $this->assertNotEmpty($result['addresses']);
        $this->assertSame(
            '140 Industrial Ave., Unit 3, Carleton Place, ON K7C 3T2',
            $result['addresses'][0]
        );
    }
}
