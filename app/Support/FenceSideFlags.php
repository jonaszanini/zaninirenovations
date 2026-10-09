<?php

namespace App\Support;

/**
 * HTML checkboxes post "0" / "1". JS !!"0" is true and PHP (bool) "0" is
 * true, so restore and calculate must treat only real on-values as ticked.
 */
final class FenceSideFlags
{
    public const FLAG_KEYS = [
        'already_have_post_left',
        'already_have_post_right',
        'gate_goes_here',
        'connected_to_previous',
    ];

    public static function isOn(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === 1.0 || $value === '1';
    }

    /**
     * Run 0 is never connected. A missing key matches JS
     * connectedOn(undefined) = on (pre-#50 saved sides). An explicit null
     * is off. Posted "0" / false is off; "1" / true is on.
     */
    public static function connectedToPrevious(mixed $value, int $index, bool $keyPresent = true): bool
    {
        if ($index <= 0) {
            return false;
        }
        if (! $keyPresent) {
            return true;
        }
        if ($value === null) {
            return false;
        }

        return self::isOn($value);
    }

    /**
     * @param  array<string, mixed>  $side
     */
    public static function connectedToPreviousFromSide(array $side, int $index): bool
    {
        return self::connectedToPrevious(
            $side['connected_to_previous'] ?? null,
            $index,
            array_key_exists('connected_to_previous', $side)
        );
    }

    /**
     * @param  array<int|string, mixed>  $sides
     * @return list<array<string, mixed>>
     */
    public static function normaliseSides(array $sides): array
    {
        $out = [];
        $index = 0;
        foreach ($sides as $side) {
            if (! is_array($side)) {
                continue;
            }
            $out[] = self::normaliseSide($side, $index);
            $index++;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    public static function normaliseSide(array $side, int $index = 0): array
    {
        $side['already_have_post_left'] = self::isOn($side['already_have_post_left'] ?? false);
        $side['already_have_post_right'] = self::isOn($side['already_have_post_right'] ?? false);
        $side['gate_goes_here'] = self::isOn($side['gate_goes_here'] ?? false);
        $side['connected_to_previous'] = self::connectedToPreviousFromSide($side, $index);

        return $side;
    }
}
