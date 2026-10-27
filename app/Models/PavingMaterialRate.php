<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PavingMaterialRate extends Model
{
    protected $fillable = [
        'rate_key',
        'label',
        'unit',
        'price_before_tax',
        'sort_order',
    ];

    protected $casts = [
        'price_before_tax' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /** @var list<string> */
    public const CATEGORY_ORDER = [
        'Materials',
        'Disposal & site',
        'Equipment',
        'Labour',
    ];

    /** @var list<string> */
    public const LABOUR_RATE_KEYS = [
        'helper_hourly_rate',
        'owner_hourly_rate',
    ];

    /**
     * Default helper/owner rates for new paving quotations (editable per quote).
     *
     * @return array{helper_hourly_rate: float, owner_hourly_rate: float}
     */
    public static function defaultQuotationLabourRates(): array
    {
        $rows = static::query()
            ->whereIn('rate_key', self::LABOUR_RATE_KEYS)
            ->pluck('price_before_tax', 'rate_key');

        return [
            'helper_hourly_rate' => (float) ($rows['helper_hourly_rate'] ?? 21),
            'owner_hourly_rate' => (float) ($rows['owner_hourly_rate'] ?? \App\Support\QuotationSharedLabourRates::LEGACY_OWNER_HOURLY),
        ];
    }

    public function category(): string
    {
        return match ((string) $this->rate_key) {
            'crushed_stone_cu_yd',
            'bedding_sand_cu_yd',
            'polymeric_sand_bag',
            'geotextile_roll',
            'fabric_staples_box',
            'edge_restraint_piece',
            'edging_spikes_pack' => 'Materials',
            'bin_rental_14yd',
            'disposal_clean_fill_ton' => 'Disposal & site',
            'mini_skid_steer_day',
            'plate_compactor_day' => 'Equipment',
            'helper_hourly_rate',
            'owner_hourly_rate' => 'Labour',
            default => 'Other',
        };
    }

    /**
     * Rates grouped for the admin price database UI, preserving sort order within each group.
     *
     * @return Collection<string, Collection<int, self>>
     */
    public static function groupedForAdmin(): Collection
    {
        $rates = static::query()->orderBy('sort_order')->get();
        $byCategory = $rates->groupBy(fn (self $rate) => $rate->category());

        $ordered = collect();
        foreach (self::CATEGORY_ORDER as $category) {
            if ($byCategory->has($category)) {
                $ordered->put($category, $byCategory->get($category));
            }
        }
        if ($byCategory->has('Other')) {
            $ordered->put('Other', $byCategory->get('Other'));
        }

        return $ordered;
    }
}
