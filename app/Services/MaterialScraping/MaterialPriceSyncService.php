<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use App\Models\MaterialVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class MaterialPriceSyncService
{
    private MaterialPageFetcher $fetcher;

    private HomeDepotCaProductParser $homeDepotParser;

    private HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher;

    private AmazonCaSerpApiProductFetcher $amazonFetcher;

    public function __construct(
        MaterialPageFetcher $fetcher,
        HomeDepotCaProductParser $homeDepotParser,
        HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher,
        ?AmazonCaSerpApiProductFetcher $amazonFetcher = null,
    ) {
        $this->fetcher = $fetcher;
        $this->homeDepotParser = $homeDepotParser;
        $this->localizedPriceFetcher = $localizedPriceFetcher;
        $this->amazonFetcher = $amazonFetcher ?? new AmazonCaSerpApiProductFetcher;
    }

    /**
     * @return Collection<int, Material>
     */
    public function materialsToSync(?int $id = null, ?string $vendor = null, bool $unsyncedOnly = false): Collection
    {
        return Material::query()
            ->homeDepotSyncable()
            ->when($unsyncedOnly, fn ($q) => $q->needsPriceSync())
            ->when($id !== null, fn ($q) => $q->where('id', $id))
            ->when(filled($vendor), function ($q) use ($vendor) {
                $q->whereHas('vendor', function (Builder $sub) use ($vendor) {
                    $sub->where('slug', $vendor)
                        ->orWhere('name', 'like', '%'.$vendor.'%');
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function countNeedingSync(): int
    {
        return Material::query()
            ->homeDepotSyncable()
            ->needsPriceSync()
            ->count();
    }

    /**
     * @return Collection<int, Material>
     */
    public function serpApiMaterialsToSync(?int $id = null, bool $unsyncedOnly = false): Collection
    {
        return Material::query()
            ->serpApiSyncable()
            ->when($unsyncedOnly, fn ($q) => $q->needsPriceSync())
            ->when($id !== null, fn ($q) => $q->where('id', $id))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function countSerpApiSyncable(): int
    {
        return Material::query()->serpApiSyncable()->count();
    }

    public function countSerpApiNeedingSync(): int
    {
        return Material::query()
            ->serpApiSyncable()
            ->needsPriceSync()
            ->count();
    }

    /**
     * @return array{status: string, parsed: ?ParsedProductData, error: ?string, old_price: float}
     */
    public function syncOne(Material $material, bool $dryRun = false): array
    {
        $oldPrice = (float) $material->price_cad;

        if ($material->isAmazonSyncable()) {
            return $this->syncAmazonMaterial($material, $dryRun, $oldPrice);
        }

        if ($material->isHomeDepotSyncable()) {
            return $this->syncHomeDepotMaterial($material, $dryRun, $oldPrice);
        }

        return $this->record(
            $material,
            Material::SCRAPE_SKIPPED,
            null,
            $this->syncSkipReason($material),
            $dryRun,
            $oldPrice,
        );
    }

    /**
     * @return array{status: string, parsed: ?ParsedProductData, error: ?string, old_price: float}
     */
    private function syncAmazonMaterial(Material $material, bool $dryRun, float $oldPrice): array
    {
        if (! $this->amazonFetcher->isConfigured()) {
            return $this->record(
                $material,
                Material::SCRAPE_SKIPPED,
                null,
                'SerpApi is not configured (MATERIALS_SCRAPER_SERPAPI_KEY).',
                $dryRun,
                $oldPrice,
            );
        }

        $asin = $material->resolveAsin();
        if ($asin === null) {
            return $this->record(
                $material,
                Material::SCRAPE_SKIPPED,
                null,
                'Store SKU must be a valid Amazon ASIN (10 characters).',
                $dryRun,
                $oldPrice,
            );
        }

        try {
            $parsed = $this->amazonFetcher->fetch($asin);
            if (! $parsed->isComplete()) {
                $detail = $this->amazonFetcher->lastFailureReason();

                return $this->record(
                    $material,
                    Material::SCRAPE_FAILED,
                    $parsed,
                    $detail ?? 'Could not extract name and price from Amazon Canada.',
                    $dryRun,
                    $oldPrice,
                );
            }

            if (! $dryRun) {
                $this->applyParsedFields($material, $parsed);
                $material->last_scraped_at = now();
                $material->last_scrape_status = Material::SCRAPE_OK;
                $material->last_scrape_error = null;
                $material->save();
            }

            return [
                'status' => Material::SCRAPE_OK,
                'parsed' => $parsed,
                'error' => null,
                'old_price' => $oldPrice,
            ];
        } catch (\Throwable $e) {
            return $this->record($material, Material::SCRAPE_FAILED, null, $e->getMessage(), $dryRun, $oldPrice);
        }
    }

    /**
     * @return array{status: string, parsed: ?ParsedProductData, error: ?string, old_price: float}
     */
    private function syncHomeDepotMaterial(Material $material, bool $dryRun, float $oldPrice): array
    {
        $productUrl = (string) $material->product_url;

        try {
            $parsed = $this->resolveHomeDepotStorePrice($productUrl);
            if ($parsed === null) {
                $html = $this->fetcher->fetch($productUrl);
                $parsed = $this->homeDepotParser->parse($html, $productUrl);
            }

            if (! $parsed->isComplete()) {
                return $this->record(
                    $material,
                    Material::SCRAPE_FAILED,
                    $parsed,
                    'Could not extract name and price from page.',
                    $dryRun,
                    $oldPrice,
                );
            }

            if (! $dryRun) {
                $this->applyParsedFields($material, $parsed);
                $material->last_scraped_at = now();
                $material->last_scrape_status = Material::SCRAPE_OK;
                $material->last_scrape_error = null;
                $material->save();
            }

            return [
                'status' => Material::SCRAPE_OK,
                'parsed' => $parsed,
                'error' => null,
                'old_price' => $oldPrice,
            ];
        } catch (\Throwable $e) {
            return $this->record($material, Material::SCRAPE_FAILED, null, $e->getMessage(), $dryRun, $oldPrice);
        }
    }

    private function syncSkipReason(Material $material): string
    {
        if (! $material->is_active) {
            return 'Material is inactive.';
        }

        if ($material->resolveAsin() !== null && ! $material->isAmazonSyncable()) {
            return 'ASIN found but vendor is not Amazon. Assign the Amazon vendor in Materials config.';
        }

        if ($material->hasProductUrl() && $material->isHomeDepotUrl()) {
            return 'Home Depot URL found but vendor is not Home Depot.';
        }

        if ($material->hasProductUrl()) {
            return 'Only Home Depot (homedepot.ca) or Amazon (ASIN + Amazon vendor) materials can be synced.';
        }

        return 'Add a Home Depot product URL or an Amazon ASIN before syncing.';
    }

    /**
     * Fast path for Home Depot CA when a store ID is configured.
     */
    private function resolveHomeDepotStorePrice(string $productUrl): ?ParsedProductData
    {
        if (! HomeDepotCaLocalizedPriceFetcher::isHomeDepotCaUrl($productUrl)) {
            return null;
        }

        $storeId = $this->localizedPriceFetcher->configuredStoreId();
        if ($storeId === null) {
            return null;
        }

        $sku = HomeDepotCaLocalizedPriceFetcher::skuFromUrl($productUrl);
        if ($sku === null) {
            throw new \RuntimeException('Could not extract Home Depot SKU from product URL.');
        }

        $parsed = $this->localizedPriceFetcher->tryFetch($sku, $storeId);
        if ($parsed !== null) {
            return $parsed;
        }

        $detail = $this->localizedPriceFetcher->lastFailureReason();
        $via = $this->localizedPriceFetcher->usesSerpApi() ? 'SerpApi' : 'store API';
        $message = 'Could not fetch store-localized price for SKU '.$sku.' (store '.$storeId.') via '.$via.'.';
        if (filled($detail)) {
            $message .= ' '.$detail;
        } else {
            $message .= ' The product may be discontinued, unavailable, or not sold at this store.';
        }
        throw new \RuntimeException($message);
    }

    private function applyParsedFields(Material $material, ParsedProductData $parsed): void
    {
        $material->name = $parsed->name ?? $material->name;

        if (filled($parsed->sku)) {
            $material->store_sku = $parsed->sku;
        }

        if (filled($parsed->model)) {
            $material->model = $parsed->model;
        }

        if (filled($parsed->productUrl)) {
            $material->product_url = $parsed->productUrl;
        }

        if ($material->isHomeDepotUrl() || $parsed->source === 'serpapi-hd' || str_starts_with($parsed->source, 'fbt-')) {
            $homeDepotId = MaterialVendor::homeDepotId();
            if ($homeDepotId !== null) {
                $material->vendor_id = $homeDepotId;
            }
        } elseif ($parsed->source === 'serpapi-amazon-ca' || $material->isAmazonSyncable()) {
            $amazonId = MaterialVendor::amazonId();
            if ($amazonId !== null) {
                $material->vendor_id = $amazonId;
            }
        }

        if (filled($parsed->unit)) {
            $material->unit = $parsed->unit;
        }

        if ($parsed->quantityPerPackage !== null && $parsed->quantityPerPackage >= 2) {
            $material->quantity_per_package = $parsed->quantityPerPackage;
        }

        if ($this->shouldUpdateImageUrl($material, $parsed)) {
            $material->image_url = $parsed->imageUrl;
        }

        $material->price_cad = $parsed->priceCad;
    }

    private function shouldUpdateImageUrl(Material $material, ParsedProductData $parsed): bool
    {
        if (! filled($parsed->imageUrl)) {
            return false;
        }

        if ($parsed->source === 'serpapi-amazon-ca') {
            return true;
        }

        $sku = $parsed->sku ?? $material->store_sku;

        if (! filled($material->image_url)) {
            return true;
        }

        if (HomeDepotCaUrls::isGenericImageUrl((string) $material->image_url, $sku)) {
            return true;
        }

        return ! HomeDepotCaUrls::isGenericImageUrl($parsed->imageUrl, $sku);
    }

    /**
     * @return array{status: string, parsed: ?ParsedProductData, error: ?string, old_price: float}
     */
    private function record(
        Material $material,
        string $status,
        ?ParsedProductData $parsed,
        ?string $error,
        bool $dryRun,
        float $oldPrice,
    ): array {
        if (! $dryRun && $status !== Material::SCRAPE_OK) {
            $material->last_scraped_at = now();
            $material->last_scrape_status = $status;
            $material->last_scrape_error = $error;
            $material->save();
        }

        return [
            'status' => $status,
            'parsed' => $parsed,
            'error' => $error,
            'old_price' => $oldPrice,
        ];
    }
}
