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
     * Run 0 is never connected. Saved rows store null/missing as off
     * (legacy). New form runs set the flag to on explicitly.
     */
    public static function connectedToPrevious(mixed $value, int $index): bool
    {
        if ($index <= 0) {
            return false;
        }

        return self::isOn($value);
    }

    /**
     * @param  array<int|string, mixed>  $sides
     * @return list<array<string, mixed>>
     */
    public static function normaliseSides(array $sides): array
    {
        $out = [];
        foreach ($sides as $side) {
            if (! is_array($side)) {
                continue;
            }
            $out[] = self::normaliseSide($side);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    public static function normaliseSide(array $side): array
    {
        $side['already_have_post_left'] = self::isOn($side['already_have_post_left'] ?? false);
        $side['already_have_post_right'] = self::isOn($side['already_have_post_right'] ?? false);
        $side['gate_goes_here'] = self::isOn($side['gate_goes_here'] ?? false);
        // Missing/null on a saved row is off (legacy). New runs set the key to true.
        $side['connected_to_previous'] = self::isOn($side['connected_to_previous'] ?? null);

        return $side;
    }
}
