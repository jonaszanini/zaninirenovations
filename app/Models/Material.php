<?php

namespace App\Models;

use App\Services\MaterialScraping\AmazonCaUrls;
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
        'description',
        'category',
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

    public function vendorName(): ?string
    {
        return $this->vendor?->name;
    }

    public function formattedPrice(): string
    {
        return ClientProject::formatMoney((float) $this->price_cad);
    }

    public function hasProductUrl(): bool
    {
        return filled($this->product_url);
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
