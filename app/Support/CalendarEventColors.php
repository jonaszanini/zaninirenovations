<?php

namespace App\Support;

/**
 * WCAG 2.x contrast helpers for FullCalendar 7 event colors (`color` / `contrastColor`).
 */
final class CalendarEventColors
{
    /** Normal text on event blocks (AA). */
    public const MIN_CONTRAST = 4.5;

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    public static function apply(array $event, string $color): array
    {
        $contrast = self::contrastColorFor($color);

        $event['color'] = $color;
        $event['contrastColor'] = $contrast;
        $event['backgroundColor'] = $color;
        $event['borderColor'] = $color;
        $event['textColor'] = $contrast;

        return $event;
    }

    public static function contrastColorFor(string $color): string
    {
        $rgb = self::parseHex($color);
        if ($rgb === null) {
            return '#111827';
        }

        $lum = self::relativeLuminance($rgb);
        $white = self::contrastRatio($lum, 1.0);
        $black = self::contrastRatio($lum, 0.0);

        if ($white >= self::MIN_CONTRAST) {
            return '#ffffff';
        }
        if ($black >= self::MIN_CONTRAST) {
            return '#111827';
        }

        return $white >= $black ? '#ffffff' : '#111827';
    }

    /**
     * @return array{r: float, g: float, b: float}|null
     */
    private static function parseHex(string $color): ?array
    {
        $color = trim($color);
        if (! preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches)) {
            return null;
        }

        $hex = $matches[1];

        return [
            'r' => hexdec(substr($hex, 0, 2)) / 255,
            'g' => hexdec(substr($hex, 2, 2)) / 255,
            'b' => hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    /**
     * @param  array{r: float, g: float, b: float}  $rgb
     */
    private static function relativeLuminance(array $rgb): float
    {
        $channels = [];
        foreach (['r', 'g', 'b'] as $key) {
            $c = $rgb[$key];
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function contrastRatio(float $lum1, float $lum2): float
    {
        $lighter = max($lum1, $lum2);
        $darker = min($lum1, $lum2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
