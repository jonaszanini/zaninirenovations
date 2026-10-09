<?php

namespace App\Services\Millwork;

/**
 * Parses a single millwork dimension in the field's selected unit.
 *
 * Accepts 23, 23.5, 23 1/2, 23-1/2, 1/2, and optional " / in / ft suffixes.
 * Used by the v2 builder only — legacy still casts with (float).
 */
class MillworkDimensionParser
{
    public static function parse(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_int($raw) || is_float($raw)) {
            return $raw > 0 ? (float) $raw : 0.0;
        }

        $s = trim((string) $raw);
        if ($s === '') {
            return 0.0;
        }

        $s = str_replace(["\u{2033}", "\u{201D}", "\u{00B4}"], '"', $s);
        $s = str_replace(["\u{2032}", "\u{2019}"], "'", $s);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = preg_replace('/\s*(?:inches|inch|in|feet|foot|ft|["\'″′])\s*/i', ' ', $s) ?? $s;
        $s = trim((string) $s, " \t\n\r\0\x0B-");
        if ($s === '') {
            return 0.0;
        }

        if (is_numeric($s)) {
            $n = (float) $s;

            return $n > 0 ? $n : 0.0;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*[-\s]\s*(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            $den = (float) $m[3];
            if ($den <= 0) {
                return 0.0;
            }
            $n = (float) $m[1] + ((float) $m[2] / $den);

            return $n > 0 ? $n : 0.0;
        }

        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            $den = (float) $m[2];
            if ($den <= 0) {
                return 0.0;
            }
            $n = (float) $m[1] / $den;

            return $n > 0 ? $n : 0.0;
        }

        return 0.0;
    }
}
