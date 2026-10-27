<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorTest extends TestCase
{
    public function test_extracts_json_ld_local_business_fields(): void
    {
        $html = <<<'HTML'
        <html><head>
        <script type="application/ld+json">
        {
          "@type": "LocalBusiness",
          "name": "Debline Landscaping",
          "telephone": "+1-613-555-0100",
          "email": "orders@debline.com",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": "100 Industrial Rd",
            "addressLocality": "Carleton Place",
            "addressRegion": "ON",
            "postalCode": "K7C 3P2"
          },
          "openingHoursSpecification": {
            "dayOfWeek": "Monday",
            "opens": "08:00",
            "closes": "17:00"
          }
        }
        </script>
        </head><body><a href="tel:6135550199">Call</a></body></html>
        HTML;

        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://debline.com', 'html' => $html],
        ]);

        $this->assertSame('Debline Landscaping', $result['name']);
        $this->assertContains('6135550100', $result['phones']);
        $this->assertContains('orders@debline.com', $result['emails']);
        $this->assertNotEmpty($result['addresses']);
        $this->assertStringContainsString('Industrial Rd', $result['addresses'][0]);
        $this->assertStringContainsString('Monday', (string) $result['hours']);
    }
}
