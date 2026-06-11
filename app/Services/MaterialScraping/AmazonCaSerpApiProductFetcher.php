<?php

namespace App\Services\MaterialScraping;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Amazon CA product details via SerpApi Amazon Product API.
 *
 * @see https://serpapi.com/amazon-product-api
 */
class AmazonCaSerpApiProductFetcher
{
    private const API_URL = 'https://serpapi.com/search.json';

    private ?string $lastFailureReason = null;

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    public function lastFailureReason(): ?string
    {
        return $this->lastFailureReason;
    }

    public function tryFetch(string $asin): ?ParsedProductData
    {
        $this->lastFailureReason = null;

        try {
            $parsed = $this->fetch($asin);
            if ($parsed->isComplete()) {
                return $parsed;
            }

            $this->lastFailureReason = 'SerpApi returned no price for this ASIN on Amazon Canada.';

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();

            return null;
        }
    }

    public function fetch(string $asin): ParsedProductData
    {
        $asin = AmazonCaUrls::normalizeAsin($asin);
        if ($asin === null) {
            throw new RuntimeException('Invalid Amazon ASIN.');
        }

        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('SerpApi is not configured (MATERIALS_SCRAPER_SERPAPI_KEY).');
        }

        $timeout = max(15, (int) config('materials.scraper.timeout_seconds', 30));

        $response = Http::timeout($timeout)
            ->connectTimeout(10)
            ->get(self::API_URL, [
                'engine' => 'amazon_product',
                'asin' => $asin,
                'amazon_domain' => (string) config('materials.scraper.amazon_domain', 'amazon.ca'),
                'language' => (string) config('materials.scraper.amazon_language', 'en_CA'),
                'api_key' => $apiKey,
                'no_cache' => config('materials.scraper.serpapi_no_cache', false) ? 'true' : 'false',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('SerpApi HTTP '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('SerpApi returned invalid JSON');
        }

        $status = (string) ($payload['search_metadata']['status'] ?? '');
        if ($status !== '' && strcasecmp($status, 'Success') !== 0) {
            $error = (string) ($payload['error'] ?? $status);

            throw new RuntimeException('SerpApi error: '.$error);
        }

        return $this->parsePayload($payload, $asin);
    }

    private function parsePayload(array $payload, string $asin): ParsedProductData
    {
        $product = is_array($payload['product_results'] ?? null) ? $payload['product_results'] : [];

        $price = $this->extractPrice($product, $payload);
        $title = filled($product['title'] ?? null) ? (string) $product['title'] : null;

        $image = null;
        $thumbnails = $product['thumbnails'] ?? null;
        if (is_array($thumbnails)) {
            foreach ($thumbnails as $thumb) {
                if (is_string($thumb) && $thumb !== '' && str_contains($thumb, 'media-amazon.com/images/I/')) {
                    $image = $thumb;
                    break;
                }
            }
        }

        $productUrl = AmazonCaUrls::productUrl($asin);
        $metadataUrl = (string) ($payload['search_metadata']['amazon_product_url'] ?? '');
        if ($metadataUrl !== '' && AmazonCaUrls::isAmazonCaUrl($metadataUrl)) {
            $productUrl = $metadataUrl;
        } elseif (filled($product['link'] ?? null) && AmazonCaUrls::isAmazonCaUrl((string) $product['link'])) {
            $productUrl = (string) $product['link'];
        }

        $model = null;
        $specs = $product['item_specifications'] ?? null;
        if (is_array($specs)) {
            foreach (['model number', 'model', 'part number', 'item model number'] as $key) {
                foreach ($specs as $specKey => $value) {
                    if (strcasecmp((string) $specKey, $key) === 0 && filled($value)) {
                        $model = (string) $value;
                        break 2;
                    }
                }
            }
        }

        return (new ParsedProductData(
            name: $title,
            sku: $asin,
            priceCad: $price,
            imageUrl: $image,
            model: $model,
            source: 'serpapi-amazon-ca',
            productUrl: $productUrl,
        ))->withTitlePackagingHints();
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $payload
     */
    private function extractPrice(array $product, array $payload): ?float
    {
        if (isset($product['extracted_price']) && is_numeric($product['extracted_price'])) {
            return round((float) $product['extracted_price'], 2);
        }

        $purchaseOptions = is_array($payload['purchase_options'] ?? null) ? $payload['purchase_options'] : [];
        foreach (['buy_new', 'one_time_purchase'] as $key) {
            $option = $purchaseOptions[$key] ?? null;
            if (is_array($option) && isset($option['extracted_price']) && is_numeric($option['extracted_price'])) {
                return round((float) $option['extracted_price'], 2);
            }
        }

        if (isset($product['price']) && is_string($product['price'])) {
            if (preg_match('/([\d,.]+)/', $product['price'], $match)) {
                $amount = (float) str_replace(',', '', $match[1]);

                return round($amount, 2);
            }
        }

        return null;
    }

    private function apiKey(): ?string
    {
        $key = trim((string) config('materials.scraper.serpapi_key', ''));

        return $key !== '' ? $key : null;
    }
}
