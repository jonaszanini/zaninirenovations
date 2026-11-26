<?php

namespace App\Services;

/**
 * Per-quotation unit rates for the basement editor.
 *
 * Stored on the quote inputs payload as rate_overrides[workKey]. Missing
 * keys keep the shared library rate. Shared price rows are never written.
 */
class BasementQuoteRateOverrides
{
    /**
     * Work keys that have an editable rate in the basement editor.
     *
     * @return list<string>
     */
    public static function editorKeys(): array
    {
        return [
            'remove_floor',
            'flooring_install',
            'transition',
            'door_install',
            'baseboard',
            'casing_opening',
            'window_trim',
            'shoe_install',
            'shoe_caulk_paint',
            'paint_walls',
            'paint_ceiling',
            'paint_trim',
        ];
    }

    public static function unitLabel(string $key): string
    {
        $def = SharedWorkCatalog::definition($key);

        return is_array($def) ? (string) ($def['unit'] ?? 'unit') : 'unit';
    }

    /**
     * Short label when one quantity line carries two rates.
     */
    public static function shortLabel(string $key): string
    {
        return match ($key) {
            'shoe_install' => 'Install',
            'shoe_caulk_paint' => 'Caulk & paint',
            default => '',
        };
    }

    /**
     * Dollar figure that round-trips to the current shared productivity
     * when left unchanged. Square/linear rates are the library sell price.
     * Each-unit rates are hours × the basement settings owner rate, which
     * is the number the calculator already charges.
     */
    public static function sharedUnitRate(string $key, float $ownerHourly): ?float
    {
        $def = SharedWorkCatalog::definition($key);
        if (! is_array($def)) {
            return null;
        }

        $unit = (string) ($def['unit'] ?? '');
        if ($unit === 'sf' || $unit === 'lf') {
            $sell = SharedWorkCatalog::sellRate($key);

            return $sell > 0 ? round($sell, 2) : null;
        }

        if ($unit === 'each') {
            $work = SharedWorkCatalog::work($key);
            $hours = $work ? (float) $work->rate : (float) ($def['rate'] ?? 0);
            if ($hours > 0 && $ownerHourly > 0) {
                return round($hours * $ownerHourly, 2);
            }
            $sell = SharedWorkCatalog::sellRate($key);

            return $sell > 0 ? round($sell, 2) : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $posted
     * @param  array<string, mixed>  $existing
     * @return array<string, float>
     */
    public static function normalizePosted(array $posted, array $existing, float $ownerHourly): array
    {
        $out = [];
        foreach (self::editorKeys() as $key) {
            $existingRate = self::numericRate($existing[$key] ?? null);

            if (! array_key_exists($key, $posted)) {
                if ($existingRate !== null) {
                    $out[$key] = $existingRate;
                }
                continue;
            }

            $postedRate = self::parseMoney($posted[$key]);
            if ($postedRate === null) {
                continue;
            }
            $postedRate = round(max(0, $postedRate), 4);

            if ($existingRate !== null && abs($postedRate - $existingRate) < 0.001) {
                $out[$key] = $existingRate;
                continue;
            }

            $shared = self::sharedUnitRate($key, $ownerHourly);
            if ($shared !== null && abs($postedRate - round($shared, 2)) < 0.001) {
                continue;
            }

            $out[$key] = $postedRate;
        }

        return $out;
    }

    /**
     * Replace basement productivity entries for overridden works.
     * Square/linear: units per hour = owner / rate (same formula as the overlay).
     * Each: hours each = rate / owner.
     *
     * @param  array<string, float|int|string>  $prod
     * @param  array<string, mixed>  $overrides
     * @return array<string, float|int|string>
     */
    public static function applyToProductivity(array $prod, array $overrides, float $ownerHourly): array
    {
        if ($ownerHourly <= 0 || $overrides === []) {
            return $prod;
        }

        foreach ($overrides as $key => $raw) {
            if (! is_string($key)) {
                continue;
            }
            $rate = self::numericRate($raw);
            if ($rate === null) {
                continue;
            }
            $def = SharedWorkCatalog::definition($key);
            if (! is_array($def)) {
                continue;
            }
            $prodKeys = $def['productivity_keys']['basement'] ?? [];
            if ($prodKeys === []) {
                continue;
            }
            $unit = (string) ($def['unit'] ?? '');
            if ($unit === 'sf' || $unit === 'lf') {
                $derived = $rate > 0 ? ($ownerHourly / $rate) : 1000000.0;
            } elseif ($unit === 'each') {
                $derived = $rate > 0 ? ($rate / $ownerHourly) : 0.0;
            } else {
                continue;
            }
            foreach ($prodKeys as $prodKey) {
                $prod[$prodKey] = $derived;
            }
        }

        return $prod;
    }

    public static function parseMoney(mixed $raw): ?float
    {
        if (is_string($raw)) {
            $raw = trim(str_replace([',', '$', ' '], '', $raw));
            if ($raw === '') {
                return null;
            }
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (float) $raw;
    }

    private static function numericRate(mixed $raw): ?float
    {
        $parsed = self::parseMoney($raw);
        if ($parsed === null) {
            return null;
        }

        return round(max(0, $parsed), 4);
    }
}
