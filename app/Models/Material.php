<?php

namespace App\Models;

use App\Services\MaterialScraping\AmazonCaUrls;
use App\Services\MaterialScraping\HomeDepotCaUrls;
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
        'quantity_per_package' => 'integer',
        'is_active' => 'boolean',
        'last_scraped_at' => 'datetime',
        'sort_order' => 'integer',
    ];

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

        if (str_contains($url, 'homedepot.ca')) {
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
        $url = strtolower((string) $this->product_url);

        return str_contains($url, 'homedepot.ca');
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
