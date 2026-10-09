<?php

namespace App\Services\Millwork;

/**
 * Parses a single millwork dimension into inches.
 *
 * Accepts plain numbers, 23 1/2, feet+inches (2' 6"), metric (762mm / 76.2cm),
 * comma decimals (1,5), unicode fractions (½), and explicit ft/in suffixes.
 * An explicit unit always wins over the field's In/Ft select, so "2 ft" typed
 * in an inch field is 24 inches.
 *
 * Used by the v2 builder. Legacy quotes go through toLegacyInchPiece() first.
 */
class MillworkDimensionParser
{
    public const STATUS_OK = 'ok';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_INVALID = 'invalid';

    public const INVALID_MESSAGE = 'Enter a valid size (for example 23 1/2, 2 ft, 2\' 6", or 762mm).';

    public const NEGATIVE_MESSAGE = 'Dimensions must be greater than zero.';

    private const UNICODE_FRACTIONS = [
        '½' => '1/2',
        '¼' => '1/4',
        '¾' => '3/4',
        '⅓' => '1/3',
        '⅔' => '2/3',
        '⅛' => '1/8',
        '⅜' => '3/8',
        '⅝' => '5/8',
        '⅞' => '7/8',
    ];

    /**
     * @return array{status: self::STATUS_OK|self::STATUS_EMPTY|self::STATUS_INVALID, inches: float, message: ?string}
     */
    public static function inspect(mixed $raw, string $fieldUnit = 'in'): array
    {
        if ($raw === null) {
            return self::empty();
        }
        if (is_int($raw) || is_float($raw)) {
            if (is_nan($raw) || is_infinite($raw)) {
                return self::invalid(self::INVALID_MESSAGE);
            }
            if ($raw < 0) {
                return self::invalid(self::NEGATIVE_MESSAGE);
            }
            if ($raw === 0 || $raw === 0.0) {
                return self::empty();
            }
            $inches = self::toInches((float) $raw, $fieldUnit);

            return self::okOrInvalid($inches);
        }

        $s = self::normalizeRaw((string) $raw);
        if ($s === '') {
            return self::empty();
        }
        if (str_starts_with($s, '-')) {
            return self::invalid(self::NEGATIVE_MESSAGE);
        }
        $s = ltrim($s, '+');
        if ($s === '') {
            return self::invalid(self::INVALID_MESSAGE);
        }

        $inches = self::parseNormalizedToInches($s, $fieldUnit);
        if ($inches === null) {
            return self::invalid(self::INVALID_MESSAGE);
        }

        return self::okOrInvalid($inches);
    }

    /**
     * Value in the field's own unit. Empty or invalid is 0 — prefer inspect().
     */
    public static function parse(mixed $raw, string $fieldUnit = 'in'): float
    {
        $parsed = self::inspect($raw, $fieldUnit);
        if ($parsed['status'] !== self::STATUS_OK) {
            return 0.0;
        }

        return strtolower($fieldUnit) === 'ft' ? $parsed['inches'] / 12.0 : $parsed['inches'];
    }

    /**
     * Rewrite a piece (and its sets) so the legacy (float) builder sees inches.
     *
     * @param  array<string, mixed>  $piece
     * @return array<string, mixed>
     */
    public static function toLegacyInchPiece(array $piece): array
    {
        $out = $piece;
        $out['unit'] = 'in';
        foreach (['width', 'height', 'depth', 'length', 'thickness'] as $key) {
            if (! array_key_exists($key, $piece)) {
                continue;
            }
            $unit = MillworkPartBuilder::dimUnit($piece, $key);
            $parsed = self::inspect($piece[$key], $unit);
            if ($parsed['status'] === self::STATUS_OK) {
                $out[$key] = round($parsed['inches'], 4);
                $out[$key.'_unit'] = 'in';
            } elseif ($parsed['status'] === self::STATUS_INVALID) {
                $out[$key] = 0;
                $out[$key.'_unit'] = 'in';
            }
        }
        foreach (['linear_ft', 'stock_length_ft'] as $key) {
            if (! array_key_exists($key, $piece)) {
                continue;
            }
            $parsed = self::inspect($piece[$key], 'ft');
            if ($parsed['status'] === self::STATUS_OK) {
                $out[$key] = round($parsed['inches'] / 12.0, 4);
            } elseif ($parsed['status'] === self::STATUS_INVALID) {
                $out[$key] = 0;
            }
        }
        if (isset($piece['sets']) && is_array($piece['sets'])) {
            $out['sets'] = [];
            foreach ($piece['sets'] as $set) {
                $out['sets'][] = is_array($set) ? self::toLegacyInchPiece($set) : $set;
            }
        }

        return $out;
    }

    public static function fieldUnitOf(string $key, array $row, ?string $fallbackUnit = null): string
    {
        if (in_array($key, ['linear_ft', 'stock_length_ft'], true)) {
            return 'ft';
        }

        return MillworkPartBuilder::dimUnit($row, $key, $fallbackUnit);
    }

    /**
     * @return array{status: self::STATUS_OK, inches: float, message: null}|array{status: self::STATUS_INVALID, inches: float, message: string}
     */
    private static function okOrInvalid(float $inches): array
    {
        if (! is_finite($inches) || $inches <= 0) {
            return self::invalid(is_finite($inches) && $inches <= 0
                ? self::NEGATIVE_MESSAGE
                : self::INVALID_MESSAGE);
        }

        return ['status' => self::STATUS_OK, 'inches' => $inches, 'message' => null];
    }

    /**
     * @return array{status: self::STATUS_EMPTY, inches: float, message: null}
     */
    private static function empty(): array
    {
        return ['status' => self::STATUS_EMPTY, 'inches' => 0.0, 'message' => null];
    }

    /**
     * @return array{status: self::STATUS_INVALID, inches: float, message: string}
     */
    private static function invalid(string $message): array
    {
        return ['status' => self::STATUS_INVALID, 'inches' => 0.0, 'message' => $message];
    }

    private static function parseNormalizedToInches(string $s, string $fieldUnit): ?float
    {
        if (preg_match('/^(.+?)\s*(?:mm|millimet(?:er|re)s?)$/iu', $s, $m)) {
            $n = self::parseAmount($m[1]);

            return $n === null ? null : $n / 25.4;
        }
        if (preg_match('/^(.+?)\s*cm(?:s)?$/iu', $s, $m)) {
            $n = self::parseAmount($m[1]);

            return $n === null ? null : $n / 2.54;
        }
        if (preg_match('/^(.+?)\s*m(?:eters?|etres?)?$/iu', $s, $m) && ! preg_match('/[cm]m$/iu', $s)) {
            $n = self::parseAmount($m[1]);

            return $n === null ? null : $n / 0.0254;
        }

        $feetInches = self::parseFeetAndInches($s);
        if ($feetInches !== null) {
            return $feetInches;
        }

        if (preg_match('/^(.+?)\s*(?:ft|feet|foot|\')\s*$/iu', $s, $m)) {
            $n = self::parseAmount($m[1]);

            return $n === null ? null : $n * 12.0;
        }
        if (preg_match('/^(.+?)\s*(?:in(?:ch(?:es)?)?|")\s*$/iu', $s, $m)) {
            return self::parseAmount($m[1]);
        }

        $n = self::parseAmount($s);
        if ($n === null) {
            return null;
        }

        return self::toInches($n, $fieldUnit);
    }

    private static function parseFeetAndInches(string $s): ?float
    {
        $s = preg_replace('/feet|foot/iu', 'ft', $s) ?? $s;
        $s = preg_replace('/inches|inch/iu', 'in', $s) ?? $s;

        if (preg_match('/^(.+?)\s*(?:ft|\')\s+(.+?)\s+(\d+)\s*\/\s*(\d+)\s*(?:in|")?\s*$/iu', $s, $m)) {
            $ft = self::parseAmount($m[1]);
            $whole = self::parseAmount($m[2]);
            $frac = self::fraction($m[3], $m[4]);
            if ($ft === null || $whole === null || $frac === null) {
                return null;
            }

            return ($ft * 12.0) + $whole + $frac;
        }
        if (preg_match('/^(.+?)\s*(?:ft|\')\s*(\d+)\s*\/\s*(\d+)\s*(?:in|")?\s*$/iu', $s, $m)) {
            $ft = self::parseAmount($m[1]);
            $frac = self::fraction($m[2], $m[3]);
            if ($ft === null || $frac === null) {
                return null;
            }

            return ($ft * 12.0) + $frac;
        }
        if (preg_match('/^(.+?)\s*(?:ft|\')\s*(.+?)\s*(?:in|")\s*$/iu', $s, $m)) {
            $ft = self::parseAmount($m[1]);
            $inch = self::parseAmount($m[2]);
            if ($ft === null || $inch === null) {
                return null;
            }

            return ($ft * 12.0) + $inch;
        }
        if (preg_match('/^(.+?)\s*(?:ft|\')\s+(.+)$/iu', $s, $m)) {
            $ft = self::parseAmount($m[1]);
            $inch = self::parseAmount($m[2]);
            if ($ft === null || $inch === null) {
                return null;
            }

            return ($ft * 12.0) + $inch;
        }

        return null;
    }

    private static function parseAmount(string $s): ?float
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*[-\s]\s*(\d+)\s*\/\s*(\d+)$/u', $s, $m)) {
            $whole = self::toFloat($m[1]);
            $frac = self::fraction($m[2], $m[3]);
            if ($whole === null || $frac === null) {
                return null;
            }

            return $whole + $frac;
        }
        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/u', $s, $m)) {
            return self::fraction($m[1], $m[2]);
        }

        return self::toFloat($s);
    }

    private static function fraction(string $num, string $den): ?float
    {
        $n = self::toFloat($num);
        $d = self::toFloat($den);
        if ($n === null || $d === null || $d == 0.0) {
            return null;
        }

        return $n / $d;
    }

    private static function toFloat(string $s): ?float
    {
        $s = trim($s);
        if ($s === '' || preg_match('/[eE]/', $s)) {
            return null;
        }
        if (str_contains($s, '.')) {
            $s = str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) {
            $last = strrpos($s, ',');
            $s = str_replace(',', '', substr($s, 0, $last)).'.'.substr($s, $last + 1);
        }
        if (! preg_match('/^[+]?\d+(?:\.\d+)?$/', $s)) {
            return null;
        }
        $n = (float) $s;
        if (is_nan($n) || is_infinite($n)) {
            return null;
        }

        return $n;
    }

    private static function toInches(float $value, string $unit): float
    {
        return strtolower($unit) === 'ft' ? $value * 12.0 : $value;
    }

    private static function normalizeRaw(string $raw): string
    {
        $s = trim($raw);
        $s = str_replace(["\u{00A0}", "\t"], ' ', $s);
        $s = str_replace(["\u{2033}", "\u{201D}", "\u{201C}", "\u{201F}"], '"', $s);
        $s = str_replace(["\u{2032}", "\u{2019}", "\u{2018}", "\u{201B}", "\u{00B4}"], "'", $s);
        foreach (self::UNICODE_FRACTIONS as $glyph => $repl) {
            $s = preg_replace('/(\d)'.preg_quote($glyph, '/').'/u', '$1 '.$repl, $s) ?? $s;
            $s = str_replace($glyph, $repl, $s);
        }

        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}
