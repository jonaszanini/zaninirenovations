<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LowDeckMaterial — one row per material in the low deck catalogue. The
 * list is fixed (seeded by migration); admins link each row to a row in
 * the standalone `materials` catalog for live prices and product data.
 *
 * Look materials up by their stable `key` (e.g. "block_4x4",
 * "joist_2x6_12") rather than by id so the calculator stays readable
 * even if seeding order ever changes.
 */
class LowDeckMaterial extends Model
{
    // Foundation
    public const KEY_BLOCK_4X4 = 'block_4x4';

    public const KEY_POST_4X4_8 = 'post_4x4_8';

    public const KEY_POST_4X4_10 = 'post_4x4_10';

    public const KEY_POST_4X4_12 = 'post_4x4_12';

    public const KEY_GRAVEL_BAG = 'gravel_bag';

    public const KEY_WEED_FABRIC_3X50 = 'weed_fabric_3x50';

    public const KEY_WEED_FABRIC_3X100 = 'weed_fabric_3x100';

    public const KEY_FABRIC_SPIKES_30PACK = 'fabric_spikes_30pack';

    // Framing
    public const KEY_JOIST_2X6_8 = 'joist_2x6_8';

    public const KEY_JOIST_2X6_10 = 'joist_2x6_10';

    public const KEY_JOIST_2X6_12 = 'joist_2x6_12';

    public const KEY_JOIST_2X6_16 = 'joist_2x6_16';

    public const KEY_JOIST_HANGER_2X6 = 'joist_hanger_2x6';

    // Waterproofing
    public const KEY_JOIST_TAPE_50FT = 'joist_tape_50ft';

    // Decking
    public const KEY_BOARD_5_4X6_8 = 'board_5_4x6_8';

    public const KEY_BOARD_5_4X6_10 = 'board_5_4x6_10';

    public const KEY_BOARD_5_4X6_12 = 'board_5_4x6_12';

    // Fasteners
    public const KEY_SCREW_STRUCTURAL_6IN = 'screw_structural_6in';

    public const KEY_SCREW_DECK_2_5IN = 'screw_deck_2_5in';

    public const KEY_SCREW_FRAME_3IN = 'screw_frame_3in';

    /** Synthetic line key for the per-project waste dump fee (no DB row). */
    public const KEY_WASTE_DUMP_FEE = 'waste_dump_fee';

    /** Post stock lengths (feet) used by the bin-packer, longest first. */
    public const POST_STOCK_KEYS = [
        12 => self::KEY_POST_4X4_12,
        10 => self::KEY_POST_4X4_10,
        8 => self::KEY_POST_4X4_8,
    ];

    /** Joist / beam stock lengths (feet) used by the bin-packer, longest first. */
    public const JOIST_STOCK_KEYS = [
        16 => self::KEY_JOIST_2X6_16,
        12 => self::KEY_JOIST_2X6_12,
        10 => self::KEY_JOIST_2X6_10,
        8 => self::KEY_JOIST_2X6_8,
    ];

    /** Deck board stock lengths (feet) used by the bin-packer, longest first. */
    public const BOARD_STOCK_KEYS = [
        12 => self::KEY_BOARD_5_4X6_12,
        10 => self::KEY_BOARD_5_4X6_10,
        8 => self::KEY_BOARD_5_4X6_8,
    ];

    /** Material keys sold by the box/pack (bulk fasteners + tape rolls). */
    public const BOX_KEYS = [
        self::KEY_SCREW_STRUCTURAL_6IN,
        self::KEY_SCREW_DECK_2_5IN,
        self::KEY_SCREW_FRAME_3IN,
        self::KEY_JOIST_TAPE_50FT,
    ];

    /**
     * Material keys included on the low deck purchasing list (Home Depot
     * shopping sheet). Order is the print order. Excludes the synthetic
     * waste dump fee (a project cost, not a retail purchase).
     *
     * @var list<string>
     */
    public const PURCHASE_LIST_KEYS = [
        self::KEY_BLOCK_4X4,
        self::KEY_POST_4X4_8,
        self::KEY_POST_4X4_10,
        self::KEY_POST_4X4_12,
        self::KEY_GRAVEL_BAG,
        self::KEY_WEED_FABRIC_3X50,
        self::KEY_WEED_FABRIC_3X100,
        self::KEY_FABRIC_SPIKES_30PACK,
        self::KEY_JOIST_2X6_8,
        self::KEY_JOIST_2X6_10,
        self::KEY_JOIST_2X6_12,
        self::KEY_JOIST_2X6_16,
        self::KEY_JOIST_HANGER_2X6,
        self::KEY_JOIST_TAPE_50FT,
        self::KEY_BOARD_5_4X6_8,
        self::KEY_BOARD_5_4X6_10,
        self::KEY_BOARD_5_4X6_12,
        self::KEY_SCREW_STRUCTURAL_6IN,
        self::KEY_SCREW_DECK_2_5IN,
        self::KEY_SCREW_FRAME_3IN,
    ];

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

    /** Retail pack/box size for box-sold materials (screws, tape). */
    public function effectiveQuantityPerBox(): ?int
    {
        if (! in_array($this->key, self::BOX_KEYS, true)) {
            return null;
        }

        $fromCatalog = $this->material?->quantity_per_package;
        if ($fromCatalog !== null && (int) $fromCatalog > 0) {
            return (int) $fromCatalog;
        }

        return $this->quantity_per_box !== null ? (int) $this->quantity_per_box : null;
    }

    /** True when this material is sold by the box (bulk fasteners). */
    public function soldByBox(): bool
    {
        return in_array($this->key, self::BOX_KEYS, true)
            && (int) $this->effectiveQuantityPerBox() > 0;
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
