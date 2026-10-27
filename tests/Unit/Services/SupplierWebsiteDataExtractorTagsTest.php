<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorTagsTest extends TestCase
{
    public function test_derives_tags_from_keywords_and_headings(): void
    {
        $html = <<<'HTML'
        <html><head>
        <meta name="keywords" content="glass, fire-rated glass, laminated glass">
        <script type="application/ld+json">{"knowsAbout":["<strong>laminated glass</strong>","Shop Now"]}</script>
        </head><body>
        <nav><a href="/">Home</a><a href="/faq/">FAQ</a><a href="/services/">Elevator glass</a></nav>
        <h2>Welcome to Raw Glass</h2>
        <h2>Custom shower enclosures</h2>
        </body></html>
        HTML;

        $extractor = new SupplierWebsiteDataExtractor;
        $result = $extractor->extract([
            ['url' => 'https://glass.test', 'html' => $html],
        ]);

        $this->assertNotEmpty($result['tags']);
        $joined = implode(', ', $result['tags']);
        $this->assertStringContainsString('Glass', $joined);
        $this->assertMatchesRegularExpression('/fire[- ]rated glass/i', $joined);
        $this->assertStringContainsString('Laminated Glass', $joined);
        $this->assertStringNotContainsString('Shop Now', $joined);
        $this->assertStringNotContainsString('FAQ', $joined);
        $this->assertStringNotContainsString('Welcome', $joined);
        $this->assertLessThanOrEqual(6, count($result['tags']));
    }
}
