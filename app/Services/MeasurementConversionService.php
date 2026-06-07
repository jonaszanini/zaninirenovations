<?php

namespace App\Services;

use App\Models\MeasurementRow;
use InvalidArgumentException;

class MeasurementConversionService
{
    public const MM_PER_INCH = 25.4;

    public const INCHES_PER_FOOT = 12;

    /** @return array<string, string> */
    public static function unitLabels(): array
    {
        return [
            MeasurementRow::UNIT_MM => 'Millimeters (mm)',
            MeasurementRow::UNIT_CM => 'Centimeters (cm)',
            MeasurementRow::UNIT_M => 'Meters (m)',
            MeasurementRow::UNIT_IN_DEC => 'Inch (decimal)',
            MeasurementRow::UNIT_IN_FRAC => 'Inch (fraction)',
            MeasurementRow::UNIT_FT_DEC => 'Feet (decimal)',
            MeasurementRow::UNIT_FT_IN_DEC => 'Feet/Inch (decimal)',
            MeasurementRow::UNIT_FT_IN_FRAC => 'Feet/Inch (fraction)',
        ];
    }

    /** @return list<string> */
    public static function unitKeys(): array
    {
        return array_keys(self::unitLabels());
    }

    /**
     * Parse user input in the given unit into millimetres (canonical base).
     */
    public function parseToMillimetres(string $unit, string $raw): float
    {
        $unit = trim($unit);
        $value = trim($raw);

        if ($value === '') {
            throw new InvalidArgumentException('Enter a measurement value.');
        }

        if (! in_array($unit, self::unitKeys(), true)) {
            throw new InvalidArgumentException('Unknown unit type.');
        }

        return match ($unit) {
            MeasurementRow::UNIT_MM => $this->parseDecimal($value),
            MeasurementRow::UNIT_CM => $this->parseDecimal($value) * 10,
            MeasurementRow::UNIT_M => $this->parseDecimal($value) * 1000,
            MeasurementRow::UNIT_IN_DEC => $this->parseDecimal($value) * self::MM_PER_INCH,
            MeasurementRow::UNIT_IN_FRAC => $this->parseInchFraction($value) * self::MM_PER_INCH,
            MeasurementRow::UNIT_FT_DEC => $this->parseDecimal($value) * self::INCHES_PER_FOOT * self::MM_PER_INCH,
            MeasurementRow::UNIT_FT_IN_DEC => $this->parseFeetInchDecimal($value) * self::MM_PER_INCH,
            MeasurementRow::UNIT_FT_IN_FRAC => $this->parseFeetInchFraction($value) * self::MM_PER_INCH,
        };
    }

    /**
     * Convert millimetres into all eight display formats.
     *
     * @return array<string, string>
     */
    public function formatsFromMillimetres(float $mm): array
    {
        $totalInches = $mm / self::MM_PER_INCH;
        $feet = (int) floor($totalInches / self::INCHES_PER_FOOT);
        $inchRemainder = $totalInches - ($feet * self::INCHES_PER_FOOT);

        return [
            MeasurementRow::UNIT_MM => $this->fmt($mm, 2),
            MeasurementRow::UNIT_CM => $this->fmt($mm / 10, 2),
            MeasurementRow::UNIT_M => $this->fmt($mm / 1000, 4),
            MeasurementRow::UNIT_IN_DEC => $this->fmt($totalInches, 4),
            MeasurementRow::UNIT_IN_FRAC => $this->inchFraction($totalInches),
            MeasurementRow::UNIT_FT_DEC => $this->fmt($totalInches / self::INCHES_PER_FOOT, 4),
            MeasurementRow::UNIT_FT_IN_DEC => $feet."' ".$this->fmt($inchRemainder, 4).'"',
            MeasurementRow::UNIT_FT_IN_FRAC => $feet."' ".$this->inchFraction($inchRemainder).'"',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rowPayload(MeasurementRow $row): array
    {
        $formats = $this->formatsFromMillimetres((float) $row->base_mm);

        return [
            'id' => $row->id,
            'label' => $row->label ?? '',
            'source_unit' => $row->source_unit,
            'source_value' => $row->source_value,
            'formats' => $formats,
            'created_label' => $row->created_at?->format('M j, Y g:i A'),
        ];
    }

    private function parseDecimal(string $value): float
    {
        $clean = str_replace([',', ' '], '', $value);
        if (! is_numeric($clean)) {
            throw new InvalidArgumentException('Enter a valid number.');
        }

        return (float) $clean;
    }

    private function parseInchFraction(string $input): float
    {
        $s = strtolower(trim($input));
        $s = str_replace(['inches', 'inch', 'in', '"', '″'], '', $s);
        $s = trim($s);

        if (preg_match('/^(-?\d+)\s+(\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            $sign = (int) $m[1] < 0 ? -1 : 1;
            $whole = abs((int) $m[1]);
            $num = (int) $m[2];
            $den = (int) $m[3];
            if ($den === 0) {
                throw new InvalidArgumentException('Invalid fraction.');
            }

            return $sign * ($whole + $num / $den);
        }

        if (preg_match('/^(-?\d+)\s*\/\s*(\d+)$/', $s, $m)) {
            $den = (int) $m[2];
            if ($den === 0) {
                throw new InvalidArgumentException('Invalid fraction.');
            }

            return (int) $m[1] / $den;
        }

        return $this->parseDecimal($s);
    }

    private function parseFeetInchDecimal(string $input): float
    {
        $s = trim($input);
        $s = str_replace(['feet', 'foot', 'ft'], "'", $s);
        $s = str_replace(['inches', 'inch', 'in'], '"', $s);

        if (preg_match("/^(-?\d+)\s*['′]\s*([\d.]+)\s*[\"″]?$/u", $s, $m)) {
            return (int) $m[1] * self::INCHES_PER_FOOT + $this->parseDecimal($m[2]);
        }

        if (preg_match("/^(-?\d+)\s*[-]\s*([\d.]+)$/u", $s, $m)) {
            return (int) $m[1] * self::INCHES_PER_FOOT + $this->parseDecimal($m[2]);
        }

        throw new InvalidArgumentException('Use format like 3\' 11.25" or 3-11.25.');
    }

    private function parseFeetInchFraction(string $input): float
    {
        $s = trim($input);
        $s = str_replace(['feet', 'foot', 'ft'], "'", $s);
        $s = preg_replace('/["″]\s*$/u', '', $s) ?? $s;

        if (preg_match("/^(-?\d+)\s*['′]\s*(.+)$/u", $s, $m)) {
            return (int) $m[1] * self::INCHES_PER_FOOT + $this->parseInchFraction(trim($m[2]));
        }

        throw new InvalidArgumentException('Use format like 3\' 11 1/4" or 3\' 11 1/4.');
    }

    private function inchFraction(float $inches): string
    {
        $sign = $inches < 0 ? '-' : '';
        $v = abs($inches);
        $whole = (int) floor($v);
        $sixteenths = (int) round(($v - $whole) * 16);

        if ($sixteenths === 16) {
            $whole++;
            $sixteenths = 0;
        }

        if ($sixteenths === 0) {
            return $sign.(string) $whole;
        }

        $n = $sixteenths;
        $d = 16;
        while ($n % 2 === 0 && $d % 2 === 0) {
            $n /= 2;
            $d /= 2;
        }

        $frac = $n.'/'.$d;

        return $sign.($whole > 0 ? $whole.' '.$frac : $frac);
    }

    private function fmt(float $value, int $maxDecimals): string
    {
        $rounded = round($value, $maxDecimals);
        $formatted = number_format($rounded, $maxDecimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
