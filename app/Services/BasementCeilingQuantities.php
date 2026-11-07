<?php

namespace App\Services;

/**
 * Ceiling Options area vs a saved Drywall ceiling hang figure.
 *
 * New quotes stamp ceiling_hang_unified and use ceiling_sf only. Hang, tape,
 * mud, sheets, drop tiles, and blank paint follow that one area (and the
 * ceiling type). Every preexisting quote that does not carry that flag keeps
 * the original hang/paint fallbacks — blank, missing, zero, or numeric hang —
 * until the user confirms adopting the single Ceiling area source. Missing
 * the key is not an implicit migration.
 */
class BasementCeilingQuantities
{
    /**
     * @param  array<string, mixed>  $originalInputs
     */
    public static function usesSplit(array $originalInputs): bool
    {
        return ! self::hasAdoptedUnifiedHang($originalInputs);
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
