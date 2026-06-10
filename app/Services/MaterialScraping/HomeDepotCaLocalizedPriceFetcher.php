<?php

namespace App\Services\MaterialScraping;

/**
 * Routes store-localized Home Depot CA pricing to the correct backend:
 *
 * - SerpApi (production admin sync when MATERIALS_SCRAPER_SERPAPI_KEY is set)
 * - FBT store API + Browsershot fallback (local php artisan materials:sync-prices)
 */
class HomeDepotCaLocalizedPriceFetcher
{
    private HomeDepotCaSerpApiPriceFetcher $serpApi;

    private HomeDepotCaFbtPriceFetcher $fbt;

    public function __construct(
        ?HomeDepotCaSerpApiPriceFetcher $serpApi = null,
        ?HomeDepotCaFbtPriceFetcher $fbt = null,
    ) {
        $this->serpApi = $serpApi ?? new HomeDepotCaSerpApiPriceFetcher;
        $this->fbt = $fbt ?? new HomeDepotCaFbtPriceFetcher;
    }

    public function usesSerpApi(): bool
    {
        return $this->serpApi->isConfigured();
    }

    public function lastFailureReason(): ?string
    {
        return $this->activeFetcher()->lastFailureReason();
    }

    public function tryFetch(string $sku, ?string $storeId = null): ?ParsedProductData
    {
        return $this->activeFetcher()->tryFetch($sku, $storeId);
    }

    public function fetch(string $sku, ?string $storeId = null): ParsedProductData
    {
        return $this->activeFetcher()->fetch($sku, $storeId);
    }

    public function configuredStoreId(): ?string
    {
        return $this->activeFetcher()->configuredStoreId();
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

    private function activeFetcher(): HomeDepotCaSerpApiPriceFetcher|HomeDepotCaFbtPriceFetcher
    {
        return $this->usesSerpApi() ? $this->serpApi : $this->fbt;
    }
}
