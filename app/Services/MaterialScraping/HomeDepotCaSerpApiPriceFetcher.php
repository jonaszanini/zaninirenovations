<?php

namespace App\Services\MaterialScraping;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Store-localized Home Depot CA pricing via SerpApi Home Depot Search.
 *
 * @see https://serpapi.com/home-depot-search-api
 */
class HomeDepotCaSerpApiPriceFetcher
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

    public function tryFetch(string $sku, ?string $storeId = null): ?ParsedProductData
    {
        $this->lastFailureReason = null;

        try {
            $parsed = $this->fetch($sku, $storeId);
            if ($parsed->isComplete()) {
                return $parsed;
            }

            $this->lastFailureReason = 'SerpApi returned no localized price for this SKU at the configured store.';

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();

            return null;
        }
    }

    public function fetch(string $sku, ?string $storeId = null): ParsedProductData
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('SerpApi is not configured (MATERIALS_SCRAPER_SERPAPI_KEY).');
        }

        $storeId = $storeId ?? $this->configuredStoreId();
        if (! filled($storeId)) {
            return new ParsedProductData(source: 'serpapi-hd');
        }

        $timeout = max(15, (int) config('materials.scraper.timeout_seconds', 30));

        $response = Http::timeout($timeout)
            ->connectTimeout(10)
            ->get(self::API_URL, [
                'engine' => 'home_depot',
                'q' => $sku,
                'country' => 'ca',
                'store' => $storeId,
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

        $product = $this->matchProduct($payload, $sku);
        if ($product === null) {
            return new ParsedProductData(source: 'serpapi-hd');
        }

        return $this->parseProduct($product, $sku)->withTitlePackagingHints();
    }

    public function configuredStoreId(): ?string
    {
        $storeId = trim((string) config('materials.scraper.homedepot_store_id', ''));

        return $storeId !== '' ? $storeId : null;
    }

    private function apiKey(): ?string
    {
        $key = trim((string) config('materials.scraper.serpapi_key', ''));

        return $key !== '' ? $key : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function matchProduct(array $payload, string $sku): ?array
    {
        $products = $payload['products'] ?? null;
        if (! is_array($products) || $products === []) {
            return null;
        }

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $productId = (string) ($product['product_id'] ?? $product['sku'] ?? '');
            if ($productId !== '' && $productId === $sku) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function parseProduct(array $product, string $sku): ParsedProductData
    {
        $price = isset($product['price']) && is_numeric($product['price'])
            ? round((float) $product['price'], 2)
            : null;

        $unit = filled($product['unit'] ?? null) ? strtolower((string) $product['unit']) : null;

        return new ParsedProductData(
            name: filled($product['title'] ?? null) ? (string) $product['title'] : null,
            sku: filled($product['product_id'] ?? null) ? (string) $product['product_id'] : $sku,
            priceCad: $price,
            imageUrl: $this->resolveImageUrl($product, $sku),
            model: filled($product['model_number'] ?? null) ? (string) $product['model_number'] : null,
            source: 'serpapi-hd',
            productUrl: filled($product['link'] ?? null) ? (string) $product['link'] : null,
            unit: $unit,
        );
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function resolveImageUrl(array $product, string $sku): ?string
    {
        $thumbnails = $product['thumbnails'] ?? null;
        if (is_array($thumbnails) && isset($thumbnails[0]) && is_array($thumbnails[0])) {
            $sizes = $thumbnails[0];
            for ($i = count($sizes) - 1; $i >= 0; $i--) {
                $url = $sizes[$i] ?? null;
                if (is_string($url) && $url !== '') {
                    return $this->normalizeImageUrl($url, $sku);
                }
            }
        }

        return HomeDepotCaUrls::imageUrl($sku);
    }

    private function normalizeImageUrl(string $url, string $sku): string
    {
        if (preg_match('~^https?://(?:www\.)?homedepot\.ca/productimages/p_\d+\.jpg~i', $url)) {
            return HomeDepotCaUrls::imageUrl($sku);
        }

        return $url;
    }
}
