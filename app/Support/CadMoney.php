<?php

namespace App\Support;

/**
 * Parse admin CAD money fields that may include thousands separators
 * from the shared `.cp-money-input` mask (e.g. "3,850.00").
 */
final class CadMoney
{
    /**
     * Strip grouping commas/spaces from a money string. Non-strings unchanged.
     */
    public static function stripGrouping(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return str_replace([',', ' '], '', $trimmed);
    }

    /**
     * @return float|null Null when empty or not a number.
     */
    public static function parse(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $stripped = self::stripGrouping(is_string($raw) ? $raw : (string) $raw);
        if ($stripped === '' || ! is_numeric($stripped)) {
            return null;
        }

        return (float) $stripped;
    }
}
