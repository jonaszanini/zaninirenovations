<?php

namespace Tests\Unit\Services;

use App\Services\MaterialScraping\AmazonCaUrls;
use PHPUnit\Framework\TestCase;

class AmazonCaUrlsTest extends TestCase
{
    /**
     * @dataProvider validAsinProvider
     */
    public function test_normalize_asin_accepts_valid_values(string $input, string $expected): void
    {
        $this->assertSame($expected, AmazonCaUrls::normalizeAsin($input));
    }

    public static function validAsinProvider(): array
    {
        return [
            'plain asin' => ['B07VVM8FBV', 'B07VVM8FBV'],
            'lowercase' => ['b07vvm8fbv', 'B07VVM8FBV'],
            'amazon ca dp url' => [
                'https://www.amazon.ca/dp/B07VVM8FBV/ref=abc',
                'B07VVM8FBV',
            ],
            'amazon ca asin query' => [
                'https://www.amazon.ca/gp/product/B07VVM8FBV?asin=B07VVM8FBV',
                'B07VVM8FBV',
            ],
        ];
    }

    public function test_normalize_asin_rejects_invalid_values(): void
    {
        $this->assertNull(AmazonCaUrls::normalizeAsin(''));
        $this->assertNull(AmazonCaUrls::normalizeAsin('too-short'));
        $this->assertNull(AmazonCaUrls::normalizeAsin('NOTAN-ASIN'));
        $this->assertNull(AmazonCaUrls::normalizeAsin('1000790080'));
    }

    public function test_is_amazon_ca_url(): void
    {
        $this->assertTrue(AmazonCaUrls::isAmazonCaUrl('https://www.amazon.ca/dp/B07VVM8FBV'));
        $this->assertFalse(AmazonCaUrls::isAmazonCaUrl('https://www.homedepot.ca/product/123'));
    }
}
