<?php

namespace App\Support;

use App\Services\SharedWorkCatalog;
use Illuminate\Http\Request;

/**
 * Labour rate defaults for new quotation forms — owner hourly comes from Shared prices crew rates.
 */
final class QuotationSharedLabourRates
{
    /** Legacy owner hourly when stored inputs omit owner_hourly_rate (pre–shared-prices behaviour). */
    public const LEGACY_OWNER_HOURLY = 85.0;

    public static function ownerHourlyForNewQuotation(): float
    {
        return SharedWorkCatalog::ownerHourlyRate();
    }

    public static function ownerHourlyFormattedForNewQuotation(): string
    {
        return number_format(self::ownerHourlyForNewQuotation(), 2, '.', '');
    }

    public static function legacyOwnerHourlyFallback(): float
    {
        return self::LEGACY_OWNER_HOURLY;
    }

    /**
     * Resolve owner hourly for save/preview: posted value, then stored inputs, then new vs legacy default.
     *
     * @param  array<string, mixed>|null  $storedInputs
     */
    public static function resolveOwnerHourlyFromRequest(
        Request $request,
        ?array $storedInputs = null,
        bool $isNewQuotation = false
    ): float {
        if ($request->has('owner_hourly_rate') && $request->input('owner_hourly_rate') !== '') {
            return max(0, (float) $request->input('owner_hourly_rate'));
        }

        if (is_array($storedInputs) && array_key_exists('owner_hourly_rate', $storedInputs)
            && $storedInputs['owner_hourly_rate'] !== '' && $storedInputs['owner_hourly_rate'] !== null) {
            return max(0, (float) $storedInputs['owner_hourly_rate']);
        }

        return $isNewQuotation
            ? self::ownerHourlyForNewQuotation()
            : self::legacyOwnerHourlyFallback();
    }
}
