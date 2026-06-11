<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        'vendor',
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
