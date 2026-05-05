<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Human-facing instants use {@see easternTimezone()} (default America/Toronto)
 * local wall clock. The suffix is always {@see DISPLAY_TIMEZONE_LABEL} (“EST”)
 * for consistent copy even when daylight time is in effect; the hour/minute
 * match Toronto civil time. Storage and business logic are unchanged.
 *
 * Calendar-only fields (visit date, start/end dates, due dates) use
 * {@see formatCalendarDate()} / {@see formatCalendarLongDate()} — date in
 * Eastern with no time-of-day semantics, so midnight-backed date casts stay
 * on the correct civil day.
 */
final class EasternStandardTimeDisplay
{
    /** Suffix appended to {@see formatDateTime} / {@see formatDateTimeMedium}. */
    public const DISPLAY_TIMEZONE_LABEL = 'EST';

    public static function easternTimezone(): string
    {
        return (string) config('app.timezone', 'America/Toronto');
    }

    public static function formatDateTime($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        $c = $c->timezone(self::easternTimezone());

        return $c->format('d/m/Y H:i').' '.self::DISPLAY_TIMEZONE_LABEL;
    }

    /**
     * Civil calendar date in Eastern (no EST skew) — for date columns / bookings.
     */
    public static function formatCalendarDate($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('d/m/Y');
    }

    public static function formatCalendarDayMonth($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('d/m');
    }

    public static function formatCalendarLongDate($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('F j, Y');
    }

    /** Short month for inline copy (e.g. Jul 4, 2026). */
    public static function formatCalendarMediumDate($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('M j, Y');
    }

    public static function formatCalendarWeekdayShort($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('D');
    }

    public static function formatCalendarLongDateWithWeekday($date): string
    {
        if ($date === null || $date === '') {
            return '—';
        }
        $c = $date instanceof CarbonInterface
            ? $date->copy()
            : Carbon::parse((string) $date);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('l, F j, Y');
    }

    /**
     * Calendar day from an instant (e.g. updated_at) in Eastern local date.
     */
    public static function formatInstantCalendarDate($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        return $c->timezone(self::easternTimezone())->format('d/m/Y');
    }

    public static function formatInstantLongDate($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        return $c->timezone(self::easternTimezone())->format('F j, Y');
    }

    public static function todayLongDate(): string
    {
        return self::formatInstantLongDate(Carbon::now(self::easternTimezone()));
    }

    /** e.g. dashboard snapshot line */
    public static function formatDateTimeMedium($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);
        $c = $c->timezone(self::easternTimezone());

        return $c->format('l, M j, Y g:i A').' '.self::DISPLAY_TIMEZONE_LABEL;
    }

    /**
     * HH:MM entered as local Eastern (Toronto) civil time on {@see $calendarDate}.
     */
    public static function formatLocalTimeOnDateAsEst(?string $hhmm, $calendarDate): string
    {
        if (!$hhmm || $calendarDate === null || $calendarDate === '') {
            return '—';
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($hhmm), $m)) {
            return '—';
        }
        $h = (int) $m[1];
        $mm = (int) $m[2];
        if ($h < 0 || $h > 23 || $mm < 0 || $mm > 59) {
            return '—';
        }
        $day = $calendarDate instanceof CarbonInterface
            ? $calendarDate->copy()
            : Carbon::parse((string) $calendarDate);
        $day = $day->timezone(self::easternTimezone())->startOfDay();
        $base = $day->copy()->addHours($h)->addMinutes($mm);

        return $base->format('H:i');
    }
}
