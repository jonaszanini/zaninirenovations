<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorAddressCapTest extends TestCase
{
    public function test_caps_extracted_addresses_at_five(): void
    {
        $addresses = [];
        for ($i = 1; $i <= 8; $i++) {
            $addresses[] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $i.' Main St',
                'addressLocality' => 'Ottawa',
                'addressRegion' => 'ON',
            ];
        }
        $json = json_encode([
            '@type' => 'Store',
            'address' => $addresses,
        ], JSON_THROW_ON_ERROR);

        $html = '<script type="application/ld+json">'.$json.'</script>';
        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://example.com', 'html' => $html],
        ]);

        $this->assertCount(5, $result['addresses']);
    }
}
