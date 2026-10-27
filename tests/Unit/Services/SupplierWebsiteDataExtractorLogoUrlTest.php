<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorLogoUrlTest extends TestCase
{
    public function test_resolves_relative_logo_paths_to_absolute_http_urls(): void
    {
        $html = <<<'HTML'
        <html><head>
        <meta property="og:image" content="/wp-content/uploads/logo.png">
        <script type="application/ld+json">{"@type":"Organization","logo":"/wp-content/logo.png"}</script>
        </head><body>
        <header><img class="site-logo" src="/wp-content/header-logo.jpg" alt="Logo"></header>
        </body></html>
        HTML;

        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://rawglass.ca/contact/', 'html' => $html],
        ]);

        $this->assertSame('https://rawglass.ca/wp-content/logo.png', $result['logo_url']);
    }

    public function test_resolves_relative_og_image_when_no_json_ld_logo(): void
    {
        $html = '<html><head><meta property="og:image" content="/wp-content/uploads/logo.png"></head><body></body></html>';

        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://rawglass.ca/', 'html' => $html],
        ]);

        $this->assertSame('https://rawglass.ca/wp-content/uploads/logo.png', $result['logo_url']);
    }

    public function test_drops_non_http_logo_schemes(): void
    {
        $extractor = new SupplierWebsiteDataExtractor;

        $this->assertNull($extractor->resolveHttpUrl('data:image/png;base64,abc', 'https://example.com'));
        $this->assertNull($extractor->resolveHttpUrl('javascript:alert(1)', 'https://example.com'));
        $this->assertSame('https://example.com/a.png', $extractor->resolveHttpUrl('/a.png', 'https://example.com/page'));
    }
}
