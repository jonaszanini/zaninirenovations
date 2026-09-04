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
        return 'https://images.homedepot.ca/productimages/p_'.rawurlencode($sku).'.jpg?product-images=l';
    }

    public static function productUrlFromPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return 'https://www.homedepot.ca/'.ltrim($path, '/');
    }

    /**
     * True when the URL is the canonical placeholder pattern (no real product photo synced yet).
     */
    public static function isGenericImageUrl(?string $url, ?string $sku = null): bool
    {
        if (! filled($url)) {
            return true;
        }

        $url = (string) $url;

        if (preg_match('~^https?://(?:www\.)?homedepot\.ca/productimages/p_\d+\.jpg(?:\?|$)~i', $url)) {
            return true;
        }

        if ($sku !== null && self::isValidSku($sku)) {
            $generic = self::imageUrl($sku);
            $normalizedUrl = strtok($url, '?') ?: $url;
            $normalizedGeneric = strtok($generic, '?') ?: $generic;

            if (strcasecmp($normalizedUrl, $normalizedGeneric) === 0) {
                return true;
            }
        }

        return false;
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

    /**
     * True when the URL host is Home Depot Canada (not a substring spoof).
     */
    public static function hostIsHomeDepot(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://'.$url;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return false;
        }

        return $host === 'homedepot.ca'
            || $host === 'www.homedepot.ca'
            || $host === 'images.homedepot.ca'
            || str_ends_with($host, '.homedepot.ca');
    }

    public static function isValidSku(?string $sku): bool
    {
        return $sku !== null && preg_match('/^\d{6,}$/', $sku) === 1;
    }
}
