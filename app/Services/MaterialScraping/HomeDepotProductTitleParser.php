<?php

namespace App\Services\MaterialScraping;

/**
 * Best-effort unit and package quantity hints from Home Depot product titles.
 */
class HomeDepotProductTitleParser
{
    /**
     * @return array{unit: ?string, quantity_per_package: ?int}
     */
    public static function parse(?string $title): array
    {
        if (! filled($title)) {
            return ['unit' => null, 'quantity_per_package' => null];
        }

        $title = trim($title);
        $quantity = self::parseQuantity($title);
        $unit = self::parseUnit($title, $quantity);

        return [
            'unit' => $unit,
            'quantity_per_package' => $quantity,
        ];
    }

    private static function parseQuantity(string $title): ?int
    {
        $patterns = [
            '/\b(?:set|pack|case|box|bundle)\s+of\s+(\d{1,4})\b/i',
            '/\((\d{1,4})\s*[-\s]?(?:pack|pk|count|ct|piece|pc|pcs)\)/i',
            '/\b(\d{1,4})\s*[-\s]?(?:pack|pk|count|ct|piece|pc|pcs)\b/i',
            '/\b(\d{1,4})\s*\/\s*(?:pkg|package|box|case)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $match)) {
                $qty = (int) $match[1];

                return $qty >= 2 ? $qty : null;
            }
        }

        return null;
    }

    private static function parseUnit(string $title, ?int $quantity): ?string
    {
        if (preg_match('/\b(?:set|pack|case|box|bundle)\s+of\s+\d{1,4}\b/i', $title)) {
            return 'package';
        }

        if ($quantity !== null) {
            return 'package';
        }

        if (preg_match('/\b\d+(?:\.\d+)?\s*(l|ml|kg|g|lb|oz|gal|quart|qt)\b/i', $title)) {
            if (preg_match('/\bpail\b/i', $title)) {
                return 'pail';
            }

            return 'each';
        }

        if (preg_match('/\bsheet\b/i', $title)) {
            return 'sheet';
        }

        if (preg_match('/\bboard\b|\blumber\b|\bplywood\b/i', $title)) {
            return 'each';
        }

        return null;
    }
}
