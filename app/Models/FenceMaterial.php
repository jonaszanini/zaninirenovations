<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FenceMaterial — one row per material in the fence catalogue. The
 * list is fixed (seeded by migration); admins link each row to a row
 * in the standalone `materials` catalog for live prices and product data.
 *
 * Look materials up by their stable `key` (e.g. "post_10", "board_6")
 * rather than by id; that way the calculator stays readable even if
 * seeding order ever changes.
 */
class FenceMaterial extends Model
{
    public const KEY_POST_10 = 'post_10';

    public const KEY_POST_12 = 'post_12';

    public const KEY_POST_6X6_10 = 'post_6x6_10';

    public const KEY_POST_6X6_12 = 'post_6x6_12';

    public const KEY_BOARD_6 = 'board_6';

    public const KEY_BOARD_8 = 'board_8';

    public const KEY_RAIL = 'rail';

    public const KEY_CONCRETE = 'concrete';

    public const KEY_NAILS = 'nails';

    public const KEY_BRACKET = 'bracket';

    public const KEY_POST_CAP = 'post_cap';

    public const KEY_GATE_HARDWARE = 'gate_hardware';

    /**
     * Material keys included on the fence quotation “purchasing list”
     * printout (Home Depot list). Order is the print order. Excludes nails
     * and gate hardware.
     *
     * @var list<string>
     */
    public const PURCHASE_LIST_KEYS = [
        self::KEY_POST_10,
        self::KEY_POST_12,
        self::KEY_POST_6X6_10,
        self::KEY_POST_6X6_12,
        self::KEY_BOARD_6,
        self::KEY_BOARD_8,
        self::KEY_RAIL,
        self::KEY_CONCRETE,
        self::KEY_BRACKET,
        self::KEY_POST_CAP,
    ];

    /** @var list<string> */
    public const POST_KEYS = [
        self::KEY_POST_10,
        self::KEY_POST_12,
        self::KEY_POST_6X6_10,
        self::KEY_POST_6X6_12,
    ];

    /**
     * Catalogue key for structural posts based on fence height and post cross-section.
     */
    public static function postKeyFor(int $fenceHeightFt, string $postSize): string
    {
        $use6x6 = $postSize === '6x6';

        if ($fenceHeightFt === 8) {
            return $use6x6 ? self::KEY_POST_6X6_12 : self::KEY_POST_12;
        }

        return $use6x6 ? self::KEY_POST_6X6_10 : self::KEY_POST_10;
    }

    protected $fillable = [
        'key',
        'material_id',
        'name',
        'long_name',
        'model',
        'store_sku',
        'price_cad',
        'url',
        'quantity_per_box',
        'sort_order',
    ];

    protected $casts = [
        'material_id' => 'integer',
        'price_cad' => 'decimal:2',
        'quantity_per_box' => 'integer',
        'sort_order' => 'integer',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function effectiveName(): string
    {
        $catalog = $this->material;
        if ($catalog !== null) {
            return $catalog->displayName();
        }

        return (string) $this->name;
    }

    public function effectiveLongName(): ?string
    {
        $catalog = $this->material;

        return filled($catalog?->name) ? $catalog->name : $this->long_name;
    }

    public function effectiveModel(): ?string
    {
        $catalog = $this->material;

        return filled($catalog?->model) ? $catalog->model : $this->model;
    }

    public function effectiveStoreSku(): ?string
    {
        $catalog = $this->material;

        return filled($catalog?->store_sku) ? $catalog->store_sku : $this->store_sku;
    }

    public function effectiveUrl(): ?string
    {
        $catalog = $this->material;

        return filled($catalog?->product_url) ? $catalog->product_url : $this->url;
    }

    public function effectivePriceCad(): float
    {
        $catalog = $this->material;
        if ($catalog !== null) {
            return round((float) $catalog->price_cad, 2);
        }

        return round((float) $this->price_cad, 2);
    }

    public function effectiveQuantityPerBox(): ?int
    {
        if ($this->key !== self::KEY_NAILS) {
            return null;
        }

        $fromCatalog = $this->material?->quantity_per_package;
        if ($fromCatalog !== null && (int) $fromCatalog > 0) {
            return (int) $fromCatalog;
        }

        return $this->quantity_per_box !== null ? (int) $this->quantity_per_box : null;
    }

    /** True when this material is sold by the box (only nails today). */
    public function soldByBox(): bool
    {
        return $this->key === self::KEY_NAILS && (int) $this->effectiveQuantityPerBox() > 0;
    }

    /**
     * Per-unit price. For box-sold materials returns the price divided
     * by the box quantity (so the calculator can price per-nail even
     * though we only enter a per-box price).
     */
    public function unitPrice(): float
    {
        $price = $this->effectivePriceCad();

        if ($this->soldByBox()) {
            $qty = (int) $this->effectiveQuantityPerBox();

            return $qty > 0 ? round($price / $qty, 6) : 0.0;
        }

        return round($price, 2);
    }

    /** Fetch the catalogue as a key-indexed collection for the calculator. */
    public static function catalogueByKey()
    {
        return static::query()
            ->with('material')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('key');
    }
}
