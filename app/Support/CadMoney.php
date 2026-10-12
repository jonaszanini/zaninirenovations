<?php

namespace App\Support;

/**
 * Parse admin CAD money fields that may include thousands separators
 * from the shared `.cp-money-input` mask (e.g. "3,850.00").
 *
 * A lone comma followed by 1–2 digits is the decimal point (4,75 → 4.75).
 * Mixed/ambiguous forms such as 1.234,56 are left unparseable.
 */
final class CadMoney
{
    /**
     * Strip grouping commas/spaces from a money string. Non-strings unchanged.
     * Ambiguous locale forms are returned unchanged so numeric validation fails.
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

        return self::normalizeString($trimmed) ?? $trimmed;
    }

    /**
     * @return float|null Null when empty, ambiguous, or not a number.
     */
    public static function parse(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $normalized = self::normalizeString(is_string($raw) ? $raw : (string) $raw);
        if ($normalized === null || $normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * @return string|null Normalized decimal string, or null when ambiguous/invalid.
     */
    private static function normalizeString(string $value): ?string
    {
        $s = trim(str_replace(["\u{00A0}", ' '], '', $value));
        if ($s === '') {
            return '';
        }
        if ($s === '.' || $s === ',') {
            return null;
        }

        $hasComma = str_contains($s, ',');
        $hasDot = str_contains($s, '.');

        if (! $hasComma && ! $hasDot) {
            return is_numeric($s) ? $s : null;
        }

        if ($hasComma && ! $hasDot) {
            if (preg_match('/^-?\d+,\d{1,2}$/', $s) === 1) {
                return str_replace(',', '.', $s);
            }
            if (preg_match('/^-?\d{1,3}(,\d{3})+$/', $s) === 1) {
                return str_replace(',', '', $s);
            }

            return null;
        }

        if ($hasDot && ! $hasComma) {
            return is_numeric($s) ? $s : null;
        }

        if (preg_match('/^-?\d{1,3}(,\d{3})+\.\d+$/', $s) === 1) {
            return str_replace(',', '', $s);
        }

        return null;
    }
}
