<?php

namespace App\Services\MaterialScraping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Store-localized Home Depot CA pricing via the FBT service.
 *
 * The embedded hdca-state "price" is unlocalized and must not be used when a store ID is set.
 */
class HomeDepotCaLocalizedPriceFetcher
{
    public function tryFetch(string $sku, ?string $storeId = null): ?ParsedProductData
    {
        try {
            $parsed = $this->fetch($sku, $storeId);

            return $parsed->isComplete() ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function fetch(string $sku, ?string $storeId = null): ParsedProductData
    {
        $storeId = $storeId ?? $this->configuredStoreId();
        if (! filled($storeId)) {
            return new ParsedProductData(source: 'fbt-store');
        }

        $url = $this->apiUrl($sku, $storeId);

        try {
            $payload = $this->requestJsonViaHttp($url, $sku);

            return $this->parsePayload($payload, $sku);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Store price API failed for SKU '.$sku.' at store '.$storeId.': '.$e->getMessage(),
                0,
                $e,
            );
        }
    }

    public function configuredStoreId(): ?string
    {
        $storeId = trim((string) config('materials.scraper.homedepot_store_id', ''));

        return $storeId !== '' ? $storeId : null;
    }

    public function apiUrl(string $sku, string $storeId): string
    {
        return sprintf(
            'https://www.homedepot.ca/api/fbtsvc/v1/fbt/products/%s/store/%s?checkStockAndPrice=true',
            rawurlencode($sku),
            rawurlencode($storeId),
        );
    }

    public static function skuFromUrl(string $pageUrl): ?string
    {
        if (preg_match('~/product/(?:[^/]+/)?(\d{6,})~', $pageUrl, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function isHomeDepotCaUrl(string $url): bool
    {
        return (bool) preg_match('~^https?://(?:www\.)?homedepot\.ca/~i', $url);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJsonViaHttp(string $url, string $sku): array
    {
        $timeout = max(3, (int) config('materials.scraper.api_timeout_seconds', 5));
        $retries = max(1, (int) config('materials.scraper.api_retries', 3));
        $retryDelayMs = max(50, (int) config('materials.scraper.api_retry_delay_ms', 100));

        $response = Http::withHeaders($this->requestHeaders($sku))
            ->timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->retry($retries, $retryDelayMs, fn ($e) => $this->shouldRetryApiRequest($e), throw: false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status());
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Invalid JSON response');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function parsePayload(array $payload, string $sku): ParsedProductData
    {
        $article = is_array($payload['anchorArticle'] ?? null) ? $payload['anchorArticle'] : null;
        if ($article === null) {
            return new ParsedProductData(source: 'fbt-store');
        }

        $displayPrice = $article['optimizedPrice']['displayPrice'] ?? null;
        $price = null;
        if (is_array($displayPrice) && isset($displayPrice['value'])) {
            $price = round((float) $displayPrice['value'], 2);
        }

        $image = isset($article['images'][0]['url']) ? (string) $article['images'][0]['url'] : null;

        return new ParsedProductData(
            name: filled($article['name'] ?? null) ? (string) $article['name'] : null,
            sku: filled($article['code'] ?? null) ? (string) $article['code'] : $sku,
            priceCad: $price,
            imageUrl: $image,
            model: filled($article['modelNumber'] ?? null) ? (string) $article['modelNumber'] : null,
            source: 'fbt-store',
        );
    }

    /**
     * Retry transient connection errors only — not API hangs (curl 28 timeout).
     */
    private function shouldRetryApiRequest(\Throwable $e): bool
    {
        if (! $e instanceof ConnectionException) {
            return false;
        }

        $message = strtolower($e->getMessage());

        return ! str_contains($message, 'timed out')
            && ! str_contains($message, 'timeout');
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(string $sku): array
    {
        return [
            'User-Agent' => (string) config('materials.scraper.user_agent'),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-CA,en;q=0.9',
            'Referer' => 'https://www.homedepot.ca/product/'.rawurlencode($sku),
        ];
    }
}
