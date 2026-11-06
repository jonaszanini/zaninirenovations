<?php

namespace App\Services;

/**
 * Ceiling Options area vs Drywall ceiling area.
 *
 * They are the same physical ceiling. New quotes use ceiling_sf only.
 * Saved quotes that stored two different numbers keep the old hang figure
 * so agreed totals are not silently repriced.
 */
class BasementCeilingQuantities
{
    /**
     * @param  array<string, mixed>  $originalInputs
     */
    public static function usesSplit(array $originalInputs): bool
    {
        if (! self::hasExplicitNumeric($originalInputs, 'drywall_ceiling_sf')) {
            return false;
        }
        if (! self::hasExplicitNumeric($originalInputs, 'ceiling_sf')) {
            return true;
        }

        return abs((float) $originalInputs['drywall_ceiling_sf'] - (float) $originalInputs['ceiling_sf']) > 0.009;
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function hasExplicitNumeric(array $inputs, string $key): bool
    {
        if (! array_key_exists($key, $inputs) || $inputs[$key] === null || $inputs[$key] === '') {
            return false;
        }

        return is_numeric($inputs[$key]);
    }
}
