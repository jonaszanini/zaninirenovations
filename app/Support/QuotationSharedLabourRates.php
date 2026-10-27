<?php

namespace App\Support;

use App\Services\SharedWorkCatalog;

/**
 * Labour rate defaults for new quotation forms — owner hourly comes from Shared prices crew rates.
 */
final class QuotationSharedLabourRates
{
    public static function ownerHourlyForNewQuotation(): float
    {
        return SharedWorkCatalog::ownerHourlyRate();
    }

    public static function ownerHourlyFormattedForNewQuotation(): string
    {
        return number_format(self::ownerHourlyForNewQuotation(), 2, '.', '');
    }
}
