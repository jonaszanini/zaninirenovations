<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Parses tape-measure lengths into feet.
 *
 * Accepts forms like 12' 6", 12'6, 12 6, 12.5, 150", 12' 6 1/2",
 * and metric (3.5 m / 350 cm / 3810 mm). Explicit unit suffixes always win
 * over the selected display mode so 12' 6", 3.81 m, and 3810 mm all parse.
 * Keep in sync with parseLengthInput() in public/js/admin-utilities-room-area.js.
 */
class RoomAreaMeasurementParser
{
    public const UNIT_METER = 'meter';

    public const UNIT_INCH = 'inch';

    public const UNIT_FEET_DECIMAL = 'feet_decimal';

    public const UNIT_FEET_INCH_FRAC = 'feet_inch_frac';

    public const INCHES_PER_FOOT = 12.0;

    public const FEET_TO_METERS = 0.3048;

    /**
     * @return float Length in feet
     */
    public function parse(string $raw, string $unit = self::UNIT_FEET_INCH_FRAC): float
    {
        $detected = $this->detectExplicitUnit($raw);
        if ($detected === 'mm') {
            return $this->parseMillimeters($raw);
        }
        if ($detected !== null) {
            $unit = $detected;
        } else {
            $unit = $this->normalizeUnit($unit);
        }

        return match ($unit) {
            self::UNIT_METER => $this->parseMeters($raw),
            self::UNIT_INCH => $this->parseInchesOnly($raw),
            self::UNIT_FEET_DECIMAL => $this->parseFeetDecimal($raw),
            default => $this->parseFeetInchFrac($raw),
        };
    }

    /**
     * Detect an explicit unit suffix or feet-inch marks, independent of display mode.
     */
    public function detectExplicitUnit(string $raw): ?string
    {
        $s = $this->normalizeRaw($raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/\bmm\b/i', $s) || preg_match('/millimet/i', $s)) {
            return 'mm';
        }
        if (preg_match('/\bcm\b/i', $s) || preg_match('/\bm(eters?|etres?)?\b/i', $s)) {
            return self::UNIT_METER;
        }
        if (preg_match('/[\'′]/', $s) || preg_match('/\b(ft|feet|foot)\b/i', $s)) {
            return self::UNIT_FEET_INCH_FRAC;
        }
        if (preg_match('/["″]/', $s) || preg_match('/\bin(ch(es)?)?\b/i', $s)) {
            return self::UNIT_INCH;
        }

        return null;
    }

    public function normalizeUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));

        return match ($unit) {
            'm', 'meter', 'meters', 'metre', 'metres', 'metric' => self::UNIT_METER,
            'in', 'inch', 'inches' => self::UNIT_INCH,
            'ft', 'feet', 'foot', 'feet_decimal', 'decimal' => self::UNIT_FEET_DECIMAL,
            default => self::UNIT_FEET_INCH_FRAC,
        };
    }

    /**
     * Format a length in feet as construction feet/inches with sixteenths
     * (e.g. 12' 6", 1½", 3/4").
     */
    public function formatFeetInchFrac(float $feet): string
    {
        $totalInches = $feet * self::INCHES_PER_FOOT;
        $rounded = round($totalInches * 16) / 16;
        if ($rounded < 1 / 16) {
            return '0"';
        }

        $wholeFeet = (int) floor($rounded / 12);
        $rem = $rounded - ($wholeFeet * 12);
        $wholeIn = (int) floor($rem);
        $frac = (int) round(($rem - $wholeIn) * 16);
        if ($frac === 16) {
            $wholeIn++;
            $frac = 0;
        }
        if ($wholeIn === 12) {
            $wholeFeet++;
            $wholeIn = 0;
        }

        $fracStr = $this->formatSixteenth($frac);

        if ($wholeFeet === 0) {
            if ($fracStr === '') {
                return $wholeIn.'"';
            }
            if ($wholeIn === 0) {
                return $fracStr.'"';
            }

            return $wholeIn.' '.$fracStr.'"';
        }
        if ($wholeIn === 0 && $fracStr === '') {
            return $wholeFeet."'";
        }
        if ($wholeIn === 0) {
            return $wholeFeet."' ".$fracStr.'"';
        }
        if ($fracStr === '') {
            return $wholeFeet."' ".$wholeIn.'"';
        }

        return $wholeFeet."' ".$wholeIn.' '.$fracStr.'"';
    }

    /**
     * Short closing-error label, e.g. 1½" or 6".
     */
    public function formatClosingGap(float $inches): string
    {
        $abs = abs($inches);
        $rounded = round($abs * 16) / 16;
        if ($rounded < 1 / 16) {
            return '0"';
        }

        $whole = (int) floor($rounded);
        $frac = (int) round(($rounded - $whole) * 16);
        if ($frac === 16) {
            $whole++;
            $frac = 0;
        }
        $fracStr = $this->formatSixteenth($frac);
        if ($fracStr === '') {
            return $whole.'"';
        }
        if ($whole === 0) {
            return $fracStr.'"';
        }

        return $whole.$this->vulgarOrSpace($fracStr).'"';
    }

    private function vulgarOrSpace(string $fracStr): string
    {
        return match ($fracStr) {
            '1/2' => '½',
            '1/4' => '¼',
            '3/4' => '¾',
            default => ' '.$fracStr,
        };
    }

    private function formatSixteenth(int $sixteenths): string
    {
        if ($sixteenths <= 0) {
            return '';
        }
        $g = $this->gcd($sixteenths, 16);
        $num = $sixteenths / $g;
        $den = 16 / $g;
        if ($num === $den) {
            return '1';
        }

        return $num.'/'.$den;
    }

    private function gcd(int $a, int $b): int
    {
        $a = abs($a);
        $b = abs($b);
        while ($b !== 0) {
            $t = $b;
            $b = $a % $b;
            $a = $t;
        }

        return $a ?: 1;
    }

    private function parseMillimeters(string $raw): float
    {
        $s = $this->normalizeRaw($raw);
        $s = preg_replace('/\s*(mm|millimet(er|re)s?)\s*$/i', '', $s) ?? $s;
        $s = trim($s);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a length in millimetres.');
        }

        return $this->positive(($this->toFloat($s) / 1000.0) / self::FEET_TO_METERS);
    }

    private function parseMeters(string $raw): float
    {
        $s = $this->normalizeRaw($raw);
        if (preg_match('/^([\d.,]+)\s*mm(s)?$/i', $s, $m)) {
            return $this->parseMillimeters($raw);
        }
        if (preg_match('/^([\d.,]+)\s*cm(s)?$/i', $s, $m)) {
            $cm = $this->toFloat($m[1]);

            return $this->positive(($cm / 100.0) / self::FEET_TO_METERS);
        }
        $s = preg_replace('/\s*m(eters?|etres?)?\s*$/i', '', $s) ?? $s;
        $s = trim($s);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a length in meters.');
        }

        return $this->positive($this->toFloat($s) / self::FEET_TO_METERS);
    }

    private function parseInchesOnly(string $raw): float
    {
        $s = $this->normalizeRaw($raw);
        $s = preg_replace('/["″]\s*$/', '', $s) ?? $s;
        $s = preg_replace('/\s*in(ch(es)?)?\s*$/i', '', $s) ?? $s;
        $s = trim($s);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a length in inches.');
        }

        return $this->positive($this->parseInchAmount($s) / self::INCHES_PER_FOOT);
    }

    private function parseFeetDecimal(string $raw): float
    {
        $s = $this->normalizeRaw($raw);
        $s = preg_replace('/\s*(ft|feet|foot)\s*$/i', '', $s) ?? $s;
        $s = trim($s);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a length in feet.');
        }

        return $this->positive($this->toFloat($s));
    }

    private function parseFeetInchFrac(string $raw): float
    {
        $s = $this->normalizeRaw($raw);
        if ($s === '') {
            throw new InvalidArgumentException('Enter a length (e.g. 12\' 6", 12.5, or 150").');
        }

        $s = preg_replace('/feet|foot/i', "'", $s) ?? $s;
        $s = preg_replace('/\bft\b/i', "'", $s) ?? $s;
        $s = preg_replace('/inches|inch/i', '"', $s) ?? $s;
        $s = preg_replace('/\bin\b/i', '"', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        $s = trim($s);

        if (preg_match('/^([\d.]+)\s*[\'′]\s*([\d.]+)\s+(\d+)\s*\/\s*(\d+)\s*["″]?$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + ($this->mixedInches($m[2], $m[3], $m[4]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s*[\'′]\s*(\d+)\s*\/\s*(\d+)\s*["″]?$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + (((float) $m[2] / (float) $m[3]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s*[\'′]\s*([\d.]+)\s*["″]?$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + (((float) $m[2]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s*-\s*([\d.]+)$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + (((float) $m[2]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s+([\d.]+)\s+(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + ($this->mixedInches($m[2], $m[3], $m[4]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s+(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + (((float) $m[2] / (float) $m[3]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s+([\d.]+)$/', $s, $m)) {
            return $this->positive(((float) $m[1]) + (((float) $m[2]) / self::INCHES_PER_FOOT));
        }
        if (preg_match('/^([\d.]+)\s*[\'′]\s*$/', $s, $m)) {
            return $this->positive((float) $m[1]);
        }
        if (preg_match('/^([\d.]+)\s*["″]\s*$/', $s, $m)) {
            return $this->positive(((float) $m[1]) / self::INCHES_PER_FOOT);
        }
        if (preg_match('/^(\d+)\s*\/\s*(\d+)\s*["″]?$/', $s, $m)) {
            return $this->positive(((float) $m[1] / (float) $m[2]) / self::INCHES_PER_FOOT);
        }
        if (preg_match('/^[\d.,]+$/', $s)) {
            $n = $this->toFloat($s);
            if (str_contains($s, '.')) {
                return $this->positive($n);
            }

            return $this->positive($n / self::INCHES_PER_FOOT);
        }

        throw new InvalidArgumentException('Use 12\' 6", 12.5 ft, or 150" (inches).');
    }

    private function parseInchAmount(string $s): float
    {
        if (preg_match('/^([\d.]+)\s+(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            return $this->mixedInches($m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            return (float) $m[1] / (float) $m[2];
        }

        return $this->toFloat($s);
    }

    private function mixedInches(string $whole, string $num, string $den): float
    {
        $d = (float) $den;
        if ($d == 0.0) {
            throw new InvalidArgumentException('Enter a valid length greater than zero.');
        }

        return ((float) $whole) + (((float) $num) / $d);
    }

    private function normalizeRaw(string $raw): string
    {
        $s = trim($raw);
        $s = str_replace(["\u{00A0}", "\t"], ' ', $s);
        $map = [
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
        $s = strtr($s, $map);

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    private function toFloat(string $s): float
    {
        $n = (float) str_replace(',', '', $s);
        if (is_nan($n) || is_infinite($n)) {
            throw new InvalidArgumentException('Enter a valid length greater than zero.');
        }

        return $n;
    }

    private function positive(float $n): float
    {
        if (is_nan($n) || is_infinite($n) || $n <= 0) {
            throw new InvalidArgumentException('Enter a valid length greater than zero.');
        }

        return $n;
    }
}
