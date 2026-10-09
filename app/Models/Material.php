<?php

namespace App\Models;

use App\Services\MaterialScraping\AmazonCaUrls;
use App\Services\MaterialScraping\HomeDepotCaUrls;
use App\Support\CatalogMaterialDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Standalone material catalog — not linked to fence_materials or paving_stone_types.
 * Prices can be entered manually or refreshed via `php artisan materials:sync-prices` (local).
 */
class Material extends Model
{
    public const SCRAPE_OK = 'ok';

    public const SCRAPE_FAILED = 'failed';

    public const SCRAPE_SKIPPED = 'skipped';

    protected $fillable = [
        'name',
        'short_name',
        'description',
        'category_id',
        'vendor_id',
        'store_sku',
        'model',
        'price_cad',
        'delivery_cad',
        'product_url',
        'image_url',
        'unit',
        'quantity_per_package',
        'is_active',
        'last_scraped_at',
        'last_scrape_status',
        'last_scrape_error',
        'sort_order',
    ];

    protected $casts = [
        'price_cad' => 'decimal:2',
        'delivery_cad' => 'decimal:2',
        'quantity_per_package' => 'integer',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Material $material) {
            if (! $material->isHomeDepotCatalogRow()) {
                return;
            }

            $material->store_sku = HomeDepotCaUrls::normalizeStoredSku($material->store_sku);
        });
    }

    /**
     * Home Depot vendor, or a homedepot.ca product URL.
     */
    public function isHomeDepotCatalogRow(): bool
    {
        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId !== null && $this->vendor_id !== null && (int) $this->vendor_id === $homeDepotId) {
            return true;
        }

        return $this->isHomeDepotUrl();
    }

    /**
     * Find a catalog row whose stored SKU or product URL matches this Home Depot item number,
     * including legacy "SKU: 100-189-8921." formatting. Does not rewrite stored values.
     */
    public static function findByNormalizedHdSku(string $sku, ?int $ignoreId = null, bool $forUpdate = false): ?self
    {
        $sku = HomeDepotCaUrls::normalizeStoredSku($sku) ?? trim($sku);
        if (! HomeDepotCaUrls::isValidSku($sku)) {
            return null;
        }

        $query = static::query();
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($forUpdate) {
            $query->lockForUpdate();
        }

        try {
            $query->where(function ($inner) use ($sku) {
                $inner->where('store_sku', $sku)
                    ->orWhereRaw("REGEXP_REPLACE(IFNULL(store_sku,''), '[^0-9]', '') = ?", [$sku])
                    ->orWhere('product_url', 'like', '%/'.$sku)
                    ->orWhere('product_url', 'like', '%/'.$sku.'/%')
                    ->orWhere('product_url', 'like', '%/'.$sku.'?%');
            });
            $rows = $query
                ->orderByRaw('case when store_sku = ? then 0 else 1 end', [$sku])
                ->get();
        } catch (\Throwable $e) {
            $fallback = static::query();
            if ($ignoreId !== null) {
                $fallback->where('id', '!=', $ignoreId);
            }
            if ($forUpdate) {
                $fallback->lockForUpdate();
            }
            $rows = $fallback
                ->where(function ($inner) use ($sku) {
                    $inner->whereNotNull('store_sku')->where('store_sku', '!=', '')
                        ->orWhere('product_url', 'like', '%'.$sku.'%');
                })
                ->get();
        }

        return $rows->first(function (self $material) use ($sku) {
            if (HomeDepotCaUrls::normalizeStoredSku($material->store_sku) === $sku) {
                return true;
            }
            if (HomeDepotCaUrls::digitsOnly($material->store_sku) === $sku
                && HomeDepotCaUrls::isValidSku($sku)) {
                return true;
            }

            return HomeDepotCaUrls::normalizeSku((string) $material->product_url) === $sku;
        });
    }

    /**
     * Set store_sku when the normalised Home Depot value is not owned by another row.
     * Returns false when the assignment was skipped to avoid a duplicate.
     */
    public function assignStoreSkuIfAvailable(?string $sku): bool
    {
        $normalized = HomeDepotCaUrls::normalizeStoredSku($sku);
        if ($normalized === null) {
            $this->store_sku = null;

            return true;
        }

        if (HomeDepotCaUrls::isValidSku($normalized)) {
            $owner = static::findByNormalizedHdSku($normalized, $this->id ? (int) $this->id : null);
            if ($owner !== null) {
                return false;
            }
        }

        $this->store_sku = $normalized;

        return true;
    }

    public function isCatalogReady(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $name = trim((string) $this->name);
        $sku = trim((string) ($this->store_sku ?? ''));
        if ($name === '' || ($sku !== '' && strcasecmp($name, 'HD '.$sku) === 0)) {
            return false;
        }

        return is_numeric($this->price_cad);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(MaterialVendor::class, 'vendor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    public function categoryName(): ?string
    {
        return $this->category?->name;
    }

    public function vendorName(): ?string
    {
        return $this->vendor?->name;
    }

    /**
     * Label for admin lists, pickers, and quotations — short name when set.
     */
    public function displayName(): string
    {
        return filled($this->short_name) ? (string) $this->short_name : (string) $this->name;
    }

    /**
     * Common units for the materials admin form (value => label).
     *
     * @return array<string, string>
     */
    public static function unitOptions(): array
    {
        return [
            'each' => 'Each',
            'box' => 'Box',
            'bag' => 'Bag',
            'package' => 'Package',
            'sheet' => 'Sheet',
            'linear ft' => 'Linear foot',
            'sq ft' => 'Square foot',
            'roll' => 'Roll',
            'pair' => 'Pair',
            'set' => 'Set',
            'case' => 'Case',
            'pail' => 'Pail',
        ];
    }

    public function formattedPrice(): string
    {
        return ClientProject::formatMoney((float) $this->price_cad);
    }

    /**
     * Flat delivery fee (CAD). Added once on quotations that link this product.
     */
    public function deliveryCad(): float
    {
        return CatalogMaterialDelivery::cad($this);
    }

    public function formattedDelivery(): string
    {
        return ClientProject::formatMoney($this->deliveryCad());
    }

    public function hasProductUrl(): bool
    {
        return filled($this->product_url);
    }

    /**
     * Best product-page URL for admin links (stored URL, or derived from ASIN / Home Depot SKU).
     */
    public function storeProductUrl(): ?string
    {
        $asin = $this->resolveAsin();
        if ($asin !== null) {
            return AmazonCaUrls::productUrl($asin);
        }

        if (filled($this->product_url)) {
            return (string) $this->product_url;
        }

        $sku = trim((string) ($this->store_sku ?? ''));
        if ($sku !== '' && HomeDepotCaUrls::isValidSku($sku)) {
            return HomeDepotCaUrls::productUrl($sku);
        }

        return null;
    }

    /**
     * @return 'amazon'|'home_depot'|null
     */
    public function storeLinkVendor(): ?string
    {
        $url = strtolower((string) ($this->storeProductUrl() ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_contains($url, 'amazon.ca') || str_contains($url, 'amazon.com')) {
            return 'amazon';
        }

        if (HomeDepotCaUrls::hostIsHomeDepot($url)) {
            return 'home_depot';
        }

        if ($this->resolveAsin() !== null) {
            return 'amazon';
        }

        return null;
    }

    /**
     * URL for admin thumbnails and the edit-form hero image.
     * Uses the stored image_url, or the canonical Home Depot image from SKU when empty.
     */
    public function previewImageUrl(): ?string
    {
        if (filled($this->image_url)) {
            return (string) $this->image_url;
        }

        $sku = trim((string) ($this->store_sku ?? ''));
        if ($sku !== '' && HomeDepotCaUrls::isValidSku($sku)) {
            return HomeDepotCaUrls::imageUrl($sku);
        }

        return null;
    }

    public function isHomeDepotUrl(): bool
    {
        return HomeDepotCaUrls::hostIsHomeDepot((string) $this->product_url);
    }

    public function isAmazonUrl(): bool
    {
        return AmazonCaUrls::isAmazonCaUrl((string) $this->product_url);
    }

    public function resolveAsin(): ?string
    {
        $fromSku = AmazonCaUrls::normalizeAsin((string) ($this->store_sku ?? ''));
        if ($fromSku !== null) {
            return $fromSku;
        }

        if ($this->hasProductUrl()) {
            return AmazonCaUrls::asinFromUrl((string) $this->product_url);
        }

        return null;
    }

    public function isAmazonSyncable(): bool
    {
        if (! $this->is_active || $this->resolveAsin() === null) {
            return false;
        }

        $amazonId = MaterialVendor::amazonId();
        if ($amazonId !== null && (int) $this->vendor_id !== $amazonId) {
            return false;
        }

        return true;
    }

    public function isHomeDepotSyncable(): bool
    {
        if (! $this->is_active || ! $this->hasProductUrl() || ! $this->isHomeDepotUrl()) {
            return false;
        }

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId !== null && (int) $this->vendor_id !== $homeDepotId) {
            return false;
        }

        return true;
    }

    public function isSerpApiSyncable(): bool
    {
        return $this->isHomeDepotSyncable() || $this->isAmazonSyncable();
    }

    /**
     * Active price-sync channel when eligible, otherwise null.
     */
    public function priceSyncChannel(): ?string
    {
        if ($this->isAmazonSyncable()) {
            return 'amazon';
        }

        if ($this->isHomeDepotSyncable()) {
            return 'home_depot';
        }

        return null;
    }

    /**
     * Why this material cannot be marked active for automated price sync.
     */
    public function priceSyncEligibilityError(): ?string
    {
        if ($this->isSerpApiSyncable()) {
            return null;
        }

        $amazonId = MaterialVendor::amazonId();
        $homeDepotId = MaterialVendor::homeDepotId();
        $vendorId = $this->vendor_id !== null ? (int) $this->vendor_id : null;

        if ($vendorId !== null && $amazonId !== null && $vendorId === $amazonId) {
            return 'Amazon vendor selected: add a valid ASIN in Store SKU (10 characters).';
        }

        if ($vendorId !== null && $homeDepotId !== null && $vendorId === $homeDepotId) {
            return 'Home Depot vendor selected: add a homedepot.ca product URL.';
        }

        if ($this->resolveAsin() !== null) {
            return 'ASIN found — assign the Amazon vendor to include this material in price sync.';
        }

        if ($this->hasProductUrl() && $this->isHomeDepotUrl()) {
            return 'Home Depot URL found — assign the Home Depot vendor to include this material in price sync.';
        }

        if ($this->hasProductUrl() && $this->isAmazonUrl()) {
            return 'Amazon URL found — assign the Amazon vendor and a valid ASIN in Store SKU.';
        }

        return 'Select the Home Depot or Amazon vendor and provide the matching product URL or ASIN.';
    }

    /**
     * Active Home Depot vendor materials with a homedepot.ca product URL.
     */
    public function scopeHomeDepotSyncable(Builder $query): Builder
    {
        $query->where('is_active', true)
            ->whereNotNull('product_url')
            ->where('product_url', '!=', '')
            ->where('product_url', 'like', '%homedepot.ca%');

        $homeDepotId = MaterialVendor::homeDepotId();
        if ($homeDepotId !== null) {
            $query->where('vendor_id', $homeDepotId);
        }

        return $query;
    }

    /**
     * Active Amazon vendor materials with a valid ASIN in store SKU (or Amazon CA URL).
     */
    public function scopeAmazonSyncable(Builder $query): Builder
    {
        $query->where('is_active', true);

        $amazonId = MaterialVendor::amazonId();
        if ($amazonId !== null) {
            $query->where('vendor_id', $amazonId);
        }

        return $query->where(function (Builder $sub) {
            $sub->where(function (Builder $sku) {
                $sku->whereNotNull('store_sku')->where('store_sku', '!=', '');
            })->orWhere('product_url', 'like', '%amazon.ca%');
        });
    }

    /**
     * Home Depot (local/SerpApi HD) or Amazon (SerpApi product API) materials.
     */
    public function scopeSerpApiSyncable(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->where(function (Builder $hd) {
                $hd->homeDepotSyncable();
            })->orWhere(function (Builder $amazon) {
                $amazon->amazonSyncable();
            });
        });
    }

    /**
     * Never synced, or last sync was not successful.
     */
    public function scopeNeedsPriceSync(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->whereNull('last_scraped_at')
                ->orWhereNull('last_scrape_status')
                ->orWhere('last_scrape_status', '!=', self::SCRAPE_OK);
        });
    }
}
