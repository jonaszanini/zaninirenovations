<?php

namespace App\Support;

/**
 * Shared unit codes for basement / shed / trimming price configuration.
 */
final class QuotationMaterialUnits
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'each' => 'Each',
            'lf' => 'Linear ft',
            'sf' => 'Sq ft',
            'sheet' => 'Sheet',
            'panel' => 'Panel',
            'bundle' => 'Bundle',
            'bag' => 'Bag',
            'box' => 'Box',
            'roll' => 'Roll',
            'pail' => 'Pail',
            'gallon' => 'Gallon',
            'can' => 'Can',
            'quart' => 'Quart',
            'tube' => 'Tube',
            'cy' => 'Cubic yard',
            'opening' => 'Opening',
            'ls' => 'Lump',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function optionsWith(string $current): array
    {
        $options = self::options();
        $current = trim($current);
        if ($current !== '' && ! array_key_exists($current, $options)) {
            $options = [$current => $current] + $options;
        }

        return $options;
    }
}
