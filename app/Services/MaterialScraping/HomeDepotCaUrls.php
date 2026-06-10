<?php

namespace App\Services\MaterialScraping;

/**
 * Canonical Home Depot CA product and image URLs from a store SKU.
 */
class HomeDepotCaUrls
{
    public static function productUrl(string $sku): string
    {
        return 'https://www.homedepot.ca/product/'.rawurlencode($sku);
    }

    public static function imageUrl(string $sku): string
    {
        return 'https://images.homedepot.ca/productimages/p_'.rawurlencode($sku).'.jpg';
    }

    /**
     * Normalize a pasted line (raw SKU or full product URL) to a store SKU.
     */
    public static function normalizeSku(string $line): ?string
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('~homedepot\.ca~i', $line)) {
            $sku = HomeDepotCaLocalizedPriceFetcher::skuFromUrl($line);

            return self::isValidSku($sku) ? $sku : null;
        }

        if (preg_match('/^(\d{6,})$/', $line, $match)) {
            return $match[1];
        }

        return null;
    }

    public static function isValidSku(?string $sku): bool
    {
        return $sku !== null && preg_match('/^\d{6,}$/', $sku) === 1;
    }
}
