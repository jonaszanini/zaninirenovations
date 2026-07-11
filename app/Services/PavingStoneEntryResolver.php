<?php

namespace App\Services;

use App\Models\PavingStoneType;
use Illuminate\Validation\ValidationException;

/**
 * Resolves up to three paving stone line items (qty / sq ft / L×W) into areas and material costs.
 */
class PavingStoneEntryResolver
{
    public const MAX_ENTRIES = 3;

    /** @var list<string> */
    public const INPUT_MODES = ['qty', 'sqft', 'dimensions'];

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $inputs): array
    {
        if (! empty($inputs['stone_entries']) && is_array($inputs['stone_entries'])) {
            return array_values(array_map(
                fn (array $entry) => self::finalizeEntry($entry),
                $inputs['stone_entries']
            ));
        }

        $legacy = self::fromLegacyInputs($inputs);
        if ($legacy !== []) {
            return $legacy;
        }

        $area = max(0.0, (float) ($inputs['total_area_sq_ft'] ?? 0));
        if ($area <= 0) {
            return [];
        }

        return [self::finalizeEntry([
            'stone_type_id' => $inputs['paving_stone_type_id'] ?? null,
            'stone_name' => 'Paving stones',
            'input_mode' => 'sqft',
            'area_sq_ft' => $area,
            'sq_ft_per_stone' => isset($inputs['stone_sq_ft_per_stone']) ? (float) $inputs['stone_sq_ft_per_stone'] : null,
            'price_per_stone' => isset($inputs['stone_price_per_stone']) ? (float) $inputs['stone_price_per_stone'] : null,
            'price_per_sq_ft' => isset($inputs['stone_price_per_sq_ft']) ? (float) $inputs['stone_price_per_sq_ft'] : null,
            'length_in' => isset($inputs['stone_length_in']) ? (float) $inputs['stone_length_in'] : null,
            'width_in' => isset($inputs['stone_width_in']) ? (float) $inputs['stone_width_in'] : null,
            'height_in' => isset($inputs['stone_height_in']) ? (float) $inputs['stone_height_in'] : null,
        ])];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    public static function fromLegacyInputs(array $inputs): array
    {
        $entries = [];
        $total = max(0.0, (float) ($inputs['total_area_sq_ft'] ?? 0));

        if (! empty($inputs['paving_stone_type_id'])) {
            $stone = PavingStoneType::query()->find($inputs['paving_stone_type_id']);
            if ($stone) {
                $entries[] = self::finalizeEntry(array_merge(
                    self::catalogFields($stone),
                    [
                        'input_mode' => 'sqft',
                        'area_sq_ft' => round($total, 2),
                        'qty' => null,
                        'length_ft' => null,
                        'width_ft' => null,
                    ]
                ));
            }
        }

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawEntries
     * @return list<array<string, mixed>>
     */
    public static function resolveFromRequest(array $rawEntries): array
    {
        $entries = [];
        foreach (array_values($rawEntries) as $index => $raw) {
            if (empty($raw['stone_type_id'])) {
                continue;
            }

            $stone = PavingStoneType::query()->findOrFail($raw['stone_type_id']);
            $mode = in_array($raw['input_mode'] ?? '', self::INPUT_MODES, true)
                ? $raw['input_mode']
                : 'sqft';

            $entry = array_merge(self::catalogFields($stone), [
                'input_mode' => $mode,
                'qty' => null,
                'area_sq_ft' => null,
                'length_ft' => null,
                'width_ft' => null,
            ]);

            if ($mode === 'qty') {
                $qty = (int) ($raw['qty'] ?? 0);
                if ($qty < 1) {
                    throw ValidationException::withMessages([
                        "stone_entries.{$index}.qty" => 'Enter the number of stones (at least 1).',
                    ]);
                }
                $entry['qty'] = $qty;
                $sqFtPerStone = (float) ($stone->sq_ft_per_stone ?? 0);
                if ($sqFtPerStone <= 0) {
                    throw ValidationException::withMessages([
                        "stone_entries.{$index}.stone_type_id" => 'Quantity mode requires a stone type with sq ft per stone in the catalog.',
                    ]);
                }
                $entry['area_sq_ft'] = round($qty * $sqFtPerStone, 2);
            } elseif ($mode === 'dimensions') {
                $length = (float) ($raw['length_ft'] ?? 0);
                $width = (float) ($raw['width_ft'] ?? 0);
                if ($length <= 0 || $width <= 0) {
                    throw ValidationException::withMessages([
                        "stone_entries.{$index}.length_ft" => 'Enter length and width in feet.',
                    ]);
                }
                $entry['length_ft'] = round($length, 2);
                $entry['width_ft'] = round($width, 2);
                $entry['area_sq_ft'] = round($length * $width, 2);
            } else {
                $area = (float) ($raw['area_sq_ft'] ?? 0);
                if ($area <= 0) {
                    throw ValidationException::withMessages([
                        "stone_entries.{$index}.area_sq_ft" => 'Enter the stone area in square feet.',
                    ]);
                }
                $entry['area_sq_ft'] = round($area, 2);
            }

            $entries[] = self::finalizeEntry($entry);

            if (count($entries) >= self::MAX_ENTRIES) {
                break;
            }
        }

        if ($entries === []) {
            throw ValidationException::withMessages([
                'stone_entries' => 'Add at least one stone type with its area or quantity.',
            ]);
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    public static function resolvedArea(array $entry): float
    {
        return max(0.0, (float) ($entry['area_sq_ft'] ?? 0));
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    public static function totalArea(array $entries): float
    {
        $sum = 0.0;
        foreach ($entries as $entry) {
            $sum += self::resolvedArea($entry);
        }

        return round($sum, 2);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{qty: float|int, unit: string, unit_price: float, before_tax: float, note: string}
     */
    public static function materialLine(array $entry, float $waste, bool $qtyModeDirect): array
    {
        $area = self::resolvedArea($entry);
        $sqFtPerStone = (float) ($entry['sq_ft_per_stone'] ?? 0);
        $pricePerStone = (float) ($entry['price_per_stone'] ?? 0);
        $pricePerSqFt = (float) ($entry['price_per_sq_ft'] ?? 0);

        if ($qtyModeDirect && ($entry['input_mode'] ?? '') === 'qty' && ! empty($entry['qty'])) {
            $qty = (int) $entry['qty'];
            $qtyWithWaste = (int) ceil($qty * (1 + $waste) - 1e-9);
            $cost = $qtyWithWaste * $pricePerStone;

            return [
                'qty' => $qtyWithWaste,
                'unit' => 'stones',
                'unit_price' => $pricePerStone,
                'before_tax' => round($cost, 2),
                'note' => sprintf('%d stones × (1 + %s%% waste)', $qty, round($waste * 100, 1)),
            ];
        }

        if ($sqFtPerStone > 0 && $pricePerStone > 0) {
            $qty = $area > 0 ? (int) ceil(($area / $sqFtPerStone) * (1 + $waste) - 1e-9) : 0;
            $cost = $qty * $pricePerStone;

            return [
                'qty' => $qty,
                'unit' => 'stones',
                'unit_price' => $pricePerStone,
                'before_tax' => round($cost, 2),
                'note' => sprintf('%s sq ft ÷ sq ft per stone × (1 + waste)', self::formatQty($area)),
            ];
        }

        $qty = $area > 0 ? round($area * (1 + $waste), 2) : 0.0;
        $cost = $qty * $pricePerSqFt;

        return [
            'qty' => $qty,
            'unit' => 'sq ft',
            'unit_price' => $pricePerSqFt,
            'before_tax' => round($cost, 2),
            'note' => sprintf('%s sq ft × (1 + waste) × price per sq ft', self::formatQty($area)),
        ];
    }

    /**
     * Primary stone (largest area) for polymeric sand coverage tiers.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>|null
     */
    public static function primaryEntryForCoverage(array $entries): ?array
    {
        $best = null;
        $bestArea = -1.0;
        foreach ($entries as $entry) {
            $area = self::resolvedArea($entry);
            if ($area > $bestArea) {
                $bestArea = $area;
                $best = $entry;
            }
        }

        return $best;
    }

    /**
     * Max paver height among entries (for crushed base depth).
     *
     * @param  list<array<string, mixed>>  $entries
     */
    public static function maxPaverHeightIn(array $entries): float
    {
        $max = 0.0;
        foreach ($entries as $entry) {
            $h = (float) ($entry['height_in'] ?? 0);
            if ($h > $max) {
                $max = $h;
            }
        }

        return $max > 0 ? $max : 2.0;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<array<string, mixed>>
     */
    public static function entriesForForm(array $inputs): array
    {
        $entries = self::normalize($inputs);
        if ($entries === []) {
            $defaults = PavingQuotationCalculator::defaultInputs();
            $stoneId = $defaults['paving_stone_type_id'] ?? null;
            $stone = $stoneId ? PavingStoneType::query()->find($stoneId) : PavingStoneType::query()->orderBy('sort_order')->first();

            return [[
                'stone_type_id' => $stone?->id,
                'stone_name' => $stone?->name,
                'input_mode' => 'sqft',
                'qty' => '',
                'area_sq_ft' => $defaults['total_area_sq_ft'] ?? 168,
                'length_ft' => '',
                'width_ft' => '',
                'sq_ft_per_stone' => $stone?->sq_ft_per_stone,
                'price_per_stone' => $stone?->price_per_stone,
                'price_per_sq_ft' => $stone?->price_per_sq_ft,
            ]];
        }

        return array_map(function (array $entry) {
            return [
                'stone_type_id' => $entry['stone_type_id'] ?? null,
                'stone_name' => $entry['stone_name'] ?? '',
                'input_mode' => $entry['input_mode'] ?? 'sqft',
                'qty' => $entry['qty'] ?? '',
                'area_sq_ft' => $entry['area_sq_ft'] ?? '',
                'length_ft' => $entry['length_ft'] ?? '',
                'width_ft' => $entry['width_ft'] ?? '',
                'sq_ft_per_stone' => $entry['sq_ft_per_stone'] ?? null,
                'price_per_stone' => $entry['price_per_stone'] ?? null,
                'price_per_sq_ft' => $entry['price_per_sq_ft'] ?? null,
            ];
        }, $entries);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function catalogFields(PavingStoneType $stone): array
    {
        return [
            'stone_type_id' => (int) $stone->id,
            'stone_name' => $stone->name,
            'sq_ft_per_stone' => $stone->sq_ft_per_stone ? (float) $stone->sq_ft_per_stone : null,
            'price_per_stone' => $stone->price_per_stone ? (float) $stone->price_per_stone : null,
            'price_per_sq_ft' => $stone->price_per_sq_ft ? (float) $stone->price_per_sq_ft : null,
            'length_in' => $stone->length_in ? (float) $stone->length_in : null,
            'width_in' => $stone->width_in ? (float) $stone->width_in : null,
            'height_in' => $stone->height_in ? (float) $stone->height_in : null,
            'delivery_cost' => $stone->delivery_cost ? (float) $stone->delivery_cost : null,
            'additional_cost' => $stone->additional_cost ? (float) $stone->additional_cost : null,
            'additional_cost_description' => $stone->additional_cost_description,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected static function finalizeEntry(array $entry): array
    {
        if (isset($entry['area_sq_ft'])) {
            $entry['area_sq_ft'] = round((float) $entry['area_sq_ft'], 2);
        }

        return $entry;
    }

    protected static function formatQty(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
