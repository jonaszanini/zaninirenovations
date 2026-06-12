<?php

namespace App\Services\MaterialScraping;

/**
 * Canonical Amazon CA product URLs from an ASIN.
 */
class AmazonCaUrls
{
    public static function productUrl(string $asin): string
    {
        return 'https://www.amazon.ca/dp/'.rawurlencode(self::normalizeAsin($asin) ?? $asin);
    }

    public static function isAmazonCaUrl(string $url): bool
    {
        return (bool) preg_match('~^https?://(?:www\.)?amazon\.ca/~i', $url);
    }

    public static function isValidAsin(?string $asin): bool
    {
        if (! filled($asin)) {
            return false;
        }

        $normalized = strtoupper(trim($asin));

        // Home Depot SKUs are numeric-only; real Amazon ASINs include a letter.
        return (bool) preg_match('/^(?=.*[A-Z])[A-Z0-9]{10}$/', $normalized);
    }

    public static function normalizeAsin(string $line): ?string
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (preg_match('~amazon\.ca~i', $line)) {
            return self::asinFromUrl($line);
        }

        if (preg_match('~^https?://(?:www\.)?amazon\.com/~i', $line)) {
            return self::asinFromUrl($line);
        }

        $candidate = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $line) ?? $line);

        return self::isValidAsin($candidate) ? $candidate : null;
    }

    public static function asinFromUrl(string $url): ?string
    {
        if (preg_match('~/(?:dp|gp/product|product)/([A-Z0-9]{10})(?:[/?]|$)~i', $url, $match)) {
            $asin = strtoupper($match[1]);

            return self::isValidAsin($asin) ? $asin : null;
        }

        if (preg_match('~[?&]asin=([A-Z0-9]{10})(?:&|$)~i', $url, $match)) {
            $asin = strtoupper($match[1]);

            return self::isValidAsin($asin) ? $asin : null;
        }

        return null;
    }
}
