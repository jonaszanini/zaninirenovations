<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FenceMaterial — one row per material in the fence catalogue. The
 * list is fixed (seeded by migration); admins can edit catalogue fields
 * (`long_name`, `model`, `store_sku`, `price_cad`, `url`, and for nails
 * `quantity_per_box`) but not `key` or `name`.
 *
 * Look materials up by their stable `key` (e.g. "post_10", "board_6")
 * rather than by id; that way the calculator stays readable even if
 * seeding order ever changes.
 */
class FenceMaterial extends Model
{
    public const KEY_POST_10 = 'post_10';
    public const KEY_POST_12 = 'post_12';
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
        self::KEY_BOARD_6,
        self::KEY_BOARD_8,
        self::KEY_RAIL,
        self::KEY_CONCRETE,
        self::KEY_BRACKET,
        self::KEY_POST_CAP,
    ];

    protected $fillable = [
        'key',
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
        'price_cad' => 'decimal:2',
        'quantity_per_box' => 'integer',
        'sort_order' => 'integer',
    ];

    /** True when this material is sold by the box (only nails today). */
    public function soldByBox(): bool
    {
        return !empty($this->quantity_per_box);
    }

    /**
     * Per-unit price. For box-sold materials returns the price divided
     * by the box quantity (so the calculator can price per-nail even
     * though we only enter a per-box price).
     */
    public function unitPrice(): float
    {
        if ($this->soldByBox() && (int) $this->quantity_per_box > 0) {
            return round(((float) $this->price_cad) / (int) $this->quantity_per_box, 6);
        }

        return round((float) $this->price_cad, 2);
    }

    /** Fetch the catalogue as a key-indexed collection for the calculator. */
    public static function catalogueByKey()
    {
        return static::query()->orderBy('sort_order')->get()->keyBy('key');
    }
}
