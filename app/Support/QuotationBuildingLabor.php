<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Optional labour surcharge for condo / multi-unit / commercial buildings.
 *
 * Off by default. When enabled, labour is increased by a percent (default 30).
 */
class QuotationBuildingLabor
{
    public const DEFAULT_PERCENT = 30.0;

    public const MAX_PERCENT = 200.0;

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function enabled(array $inputs): bool
    {
        return filter_var($inputs['include_building'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function percent(array $inputs): float
    {
        $raw = $inputs['building_labor_percent'] ?? null;
        if ($raw === null || $raw === '') {
            return self::DEFAULT_PERCENT;
        }

        return max(0.0, min(self::MAX_PERCENT, round((float) $raw, 2)));
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function surchargeAmount(float $laborPreTax, array $inputs): float
    {
        $base = max(0.0, $laborPreTax);
        if (! self::enabled($inputs) || $base <= 0) {
            return 0.0;
        }

        return round($base * (self::percent($inputs) / 100), 2);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{enabled: bool, percent: float, base: float, surcharge: float, total: float}
     */
    public static function apply(float $laborPreTax, array $inputs): array
    {
        $base = round(max(0.0, $laborPreTax), 2);
        $surcharge = self::surchargeAmount($base, $inputs);

        return [
            'enabled' => self::enabled($inputs),
            'percent' => self::percent($inputs),
            'base' => $base,
            'surcharge' => $surcharge,
            'total' => round($base + $surcharge, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array{key: string, section: string, label: string, detail: string, amount: float}|null
     */
    public static function breakdownRow(float $laborPreTax, array $inputs): ?array
    {
        $applied = self::apply($laborPreTax, $inputs);
        if ($applied['surcharge'] <= 0) {
            return null;
        }

        $pct = rtrim(rtrim(number_format($applied['percent'], 2, '.', ''), '0'), '.');
        if ($pct === '') {
            $pct = '0';
        }

        return [
            'key' => 'building',
            'section' => 'labor',
            'label' => 'Building access',
            'detail' => '+'.$pct.'% labour — condo / multi-unit / commercial',
            'amount' => $applied['surcharge'],
        ];
    }

    /**
     * @return array{include_building: bool, building_labor_percent: float}
     */
    public static function fromRequest(Request $request): array
    {
        $percent = $request->input('building_labor_percent');

        return [
            'include_building' => $request->boolean('include_building'),
            'building_labor_percent' => self::percent([
                'building_labor_percent' => ($percent === null || $percent === '')
                    ? self::DEFAULT_PERCENT
                    : $percent,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public static function mergeIntoInputs(array $inputs, Request $request): array
    {
        return array_merge($inputs, self::fromRequest($request));
    }

    /**
     * @return array<string, mixed>
     */
    public static function validationRules(): array
    {
        return [
            'include_building' => ['nullable', 'boolean'],
            'building_labor_percent' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PERCENT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'building_labor_percent.numeric' => 'Building labour percent must be a number.',
            'building_labor_percent.max' => 'Building labour percent cannot exceed '.self::MAX_PERCENT.'.',
        ];
    }
}
