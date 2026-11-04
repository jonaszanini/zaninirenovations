<?php

namespace App\Support;

use App\Services\SharedWorkCatalog;
use App\Models\StandardQuotation;
use Illuminate\Http\Request;

/**
 * Labour rate defaults for new quotation forms — owner hourly comes from Shared prices crew rates.
 */
final class QuotationSharedLabourRates
{
    /** Legacy owner hourly when stored inputs omit owner_hourly_rate (pre–shared-prices behaviour). */
    public const LEGACY_OWNER_HOURLY = 85.0;

    public const LEGACY_HOURS_PER_DAY = 8.0;

    public static function ownerHourlyForNewQuotation(): float
    {
        return SharedWorkCatalog::ownerHourlyRate();
    }

    public static function hoursPerDayForNewQuotation(): float
    {
        return SharedWorkCatalog::hoursPerDay();
    }

    public static function formatHoursPerDayLabel(float $hoursPerDay): string
    {
        $formatted = number_format(max(0.0, $hoursPerDay), 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    /**
     * Frozen days→hours factor for an existing quote, or shared crew default for new.
     */
    public static function resolveHoursPerDay(?StandardQuotation $existing): float
    {
        if ($existing !== null && $existing->getAttribute('labour_hours_per_day') !== null) {
            return max(0.5, (float) $existing->labour_hours_per_day);
        }

        return self::hoursPerDayForNewQuotation();
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
