<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Helpers for admin list-page filter bars (active-count badges, etc.).
 */
class AdminListFilters
{
    /**
     * Count how many filter values the list actually applies.
     *
     * Empty string and null are treated as "not set". A From/To pair
     * listed in $options['dateRange'] counts as one active filter when
     * either side is a valid date. $options['minLength'] skips values
     * shorter than the controller's threshold. $options['allowed']
     * skips values the controller would ignore.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $defaults
     * @param  array{
     *     dateRange?: list<string>,
     *     minLength?: array<string, int>,
     *     allowed?: array<string, list<string>>
     * }  $options
     */
    public static function activeCount(array $values, array $defaults = [], array $options = []): int
    {
        $count = 0;
        $dateRangeKeys = $options['dateRange'] ?? [];
        $minLength = $options['minLength'] ?? [];
        $allowed = $options['allowed'] ?? [];

        if ($dateRangeKeys !== []) {
            $hasDate = false;
            foreach ($dateRangeKeys as $key) {
                if (array_key_exists($key, $values) && self::isValidDate($values[$key])) {
                    $hasDate = true;
                }
                unset($values[$key]);
            }
            if ($hasDate) {
                $count++;
            }
        }

        foreach ($values as $key => $value) {
            $default = $defaults[$key] ?? '';

            if (is_string($value)) {
                $value = trim($value);
            }
            if (is_string($default)) {
                $default = trim($default);
            }

            if (isset($minLength[$key])) {
                $length = is_string($value) ? mb_strlen($value) : 0;
                if ($length < $minLength[$key]) {
                    continue;
                }
            }

            if (isset($allowed[$key]) && ! in_array((string) $value, $allowed[$key], true)) {
                continue;
            }

            if (self::isBlank($value) && self::isBlank($default)) {
                continue;
            }

            if ((string) $value !== (string) $default) {
                $count++;
            }
        }

        return $count;
    }

    private static function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private static function isValidDate(mixed $value): bool
    {
        if (! is_string($value)) {
            $value = $value === null ? '' : (string) $value;
        }

        $value = trim($value);
        if ($value === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
