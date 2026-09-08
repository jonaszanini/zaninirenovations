<?php

namespace App\Support;

class QuotationHelperLabor
{
    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function enabled(array $inputs): bool
    {
        return filter_var($inputs['include_helper'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function amount(float $hours, float $ownerRate, float $helperRate, bool $includeHelper): float
    {
        $hours = max(0, $hours);
        $cost = $hours * $ownerRate;
        if ($includeHelper) {
            $cost += $hours * $helperRate;
        }

        return round($cost, 2);
    }
}
