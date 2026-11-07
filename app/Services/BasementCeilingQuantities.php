<?php

namespace App\Services;

/**
 * Ceiling Options area vs a saved Drywall ceiling hang figure.
 *
 * New quotes use ceiling_sf only. Hang, tape, mud, sheets, drop tiles, and
 * blank paint follow that one area (and the ceiling type). Saved quotes that
 * stored an explicit drywall_ceiling_sf keep that hang — including drop,
 * skip, ceiling-off, and matching numbers — until the user confirms adopting
 * the single Ceiling area source.
 */
class BasementCeilingQuantities
{
    /**
     * @param  array<string, mixed>  $originalInputs
     */
    public static function usesSplit(array $originalInputs): bool
    {
        if (self::hasAdoptedUnifiedHang($originalInputs)) {
            return false;
        }

        return self::hasExplicitNumeric($originalInputs, 'drywall_ceiling_sf');
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function hasAdoptedUnifiedHang(array $inputs): bool
    {
        $flag = $inputs['ceiling_hang_unified'] ?? null;

        return $flag === true || $flag === 1 || $flag === '1';
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
