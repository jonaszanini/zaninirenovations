<?php

namespace App\Services\MaterialScraping;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Store-localized Home Depot CA pricing via the FBT service.
 *
 * The embedded hdca-state "price" is unlocalized and must not be used when a store ID is set.
 */
class HomeDepotCaLocalizedPriceFetcher
{
    private ?string $lastFailureReason = null;

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

            $this->lastFailureReason = 'Store API responded but returned no localized price for this SKU.';

            return null;
        } catch (\Throwable $e) {
            $this->lastFailureReason = $e->getMessage();

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
        $lastError = 'Unknown API error';

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $jar = new CookieJar;
                $this->warmAkamaiSession($sku, $jar, $timeout);
                $response = $this->requestStoreApi($url, $sku, $jar, $timeout);

                if ($response->successful()) {
                    $payload = $response->json();
                    if (! is_array($payload)) {
                        throw new RuntimeException('Invalid JSON response');
                    }

                    return $payload;
                }

                $lastError = 'HTTP '.$response->status();
                if ($this->shouldRetryHttpStatus($response->status()) && $attempt < $retries) {
                    usleep($retryDelayMs * 1000);

                    continue;
                }

                throw new RuntimeException($lastError);
            } catch (ConnectionException $e) {
                $lastError = $e->getMessage();
                if ($attempt < $retries) {
                    usleep($retryDelayMs * 1000);

                    continue;
                }

                throw $e;
            } catch (RuntimeException $e) {
                $lastError = $e->getMessage();
                if ($attempt < $retries && $this->shouldRetryHttpErrorMessage($lastError)) {
                    usleep($retryDelayMs * 1000);

                    continue;
                }

                throw $e;
            }
        }

        throw new RuntimeException($lastError);
    }

    private function warmAkamaiSession(string $sku, CookieJar $jar, int $timeout): void
    {
        $productUrl = $this->productPageUrl($sku);

        Http::withOptions($this->httpClientOptions($jar))
            ->withHeaders($this->pageWarmupHeaders($productUrl))
            ->timeout(max($timeout, 15))
            ->connectTimeout(min(5, $timeout))
            ->get($productUrl);
    }

    private function requestStoreApi(string $url, string $sku, CookieJar $jar, int $timeout): Response
    {
        return Http::withOptions($this->httpClientOptions($jar))
            ->withHeaders($this->apiRequestHeaders($sku))
            ->timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->get($url);
    }

    /**
     * @return array<string, mixed>
     */
    private function httpClientOptions(CookieJar $jar): array
    {
        return [
            'version' => 1.1,
            'cookies' => $jar,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ];
    }

    private function productPageUrl(string $sku): string
    {
        return 'https://www.homedepot.ca/product/p/'.rawurlencode($sku);
    }

    private function shouldRetryHttpStatus(int $status): bool
    {
        return in_array($status, [403, 429, 500, 502, 503, 504], true);
    }

    private function shouldRetryHttpErrorMessage(string $message): bool
    {
        return (bool) preg_match('/HTTP (403|429|500|502|503|504)\b/', $message)
            || str_contains(strtolower($message), 'timed out')
            || str_contains(strtolower($message), 'timeout');
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
     * @return array<string, string>
     */
    private function apiRequestHeaders(string $sku): array
    {
        $productUrl = $this->productPageUrl($sku);

        return array_merge($this->browserClientHeaders(), [
            'Accept' => 'application/json, text/plain, */*',
            'Referer' => $productUrl,
            'Origin' => 'https://www.homedepot.ca',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-origin',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function pageWarmupHeaders(string $productUrl): array
    {
        return array_merge($this->browserClientHeaders(), [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Referer' => 'https://www.homedepot.ca/',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-user' => '?1',
            'upgrade-insecure-requests' => '1',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function browserClientHeaders(): array
    {
        return [
            'User-Agent' => (string) config('materials.scraper.user_agent'),
            'Accept-Language' => 'en-CA,en;q=0.9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'sec-ch-ua' => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
        ];
    }
}
