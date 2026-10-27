<?php

namespace Tests\Unit\Services;

use App\Services\SupplierWebsite\SupplierWebsiteDataExtractor;
use Tests\TestCase;

class SupplierWebsiteDataExtractorAddressFinalizeTest extends TestCase
{
    public function test_prefers_full_address_over_partial_and_ignores_directions_label(): void
    {
        $html = <<<'HTML'
        <html><body>
        <p>140 Industrial Ave.</p>
        <p>
          <a href="https://www.google.com/maps?cid=1">Directions</a>
          <a href="https://www.google.com/maps?cid=1">140 Industrial Ave., Unit 3, Carleton Place, ON</a>,
          <a href="https://www.google.com/maps?cid=1">K7C 3T2</a>
        </p>
        <p>Call 613-257-7515 K7C 3T2 for orders</p>
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
        $joined = implode(' | ', $result['addresses']);
        $this->assertStringNotContainsString('Directions', $joined);
        $this->assertStringNotContainsString('613-257-7515', $joined);
    }
}
