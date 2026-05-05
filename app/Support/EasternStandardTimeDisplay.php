<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Human-facing datetimes are shown as Eastern Standard Time (nominal UTC-5).
 * When {@see easternTimezone()} is in daylight time (EDT), the local wall clock
 * is moved back one hour for display only. Storage and business logic are unchanged.
 *
 * Calendar-only fields (visit date, start/end dates, due dates) use
 * {@see formatCalendarDate()} / {@see formatCalendarLongDate()} — no hour shift,
 * so midnight-backed date casts stay on the correct civil day.
 */
final class EasternStandardTimeDisplay
{
    public static function easternTimezone(): string
    {
        return (string) config('app.timezone', 'America/Toronto');
    }

    /**
     * Instant in app Eastern zone; subtract one hour while DST (EDT) is in effect.
     */
    public static function toEstWallClock(CarbonInterface $instant): Carbon
    {
        $c = $instant->copy()->timezone(self::easternTimezone());
        if ((int) $c->format('I') === 1) {
            $c = $c->copy()->subHour();
        }

        return $c;
    }

    public static function formatDateTime($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        $c = self::toEstWallClock($c);

        return $c->format('d/m/Y H:i').' EST';
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
     * Calendar day from an instant (e.g. updated_at) after EST display adjustment.
     */
    public static function formatInstantCalendarDate($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        return self::toEstWallClock($c)->format('d/m/Y');
    }

    public static function formatInstantLongDate($dateTime): string
    {
        if ($dateTime === null) {
            return '—';
        }
        $c = $dateTime instanceof CarbonInterface
            ? $dateTime->copy()
            : Carbon::parse((string) $dateTime);

        return self::toEstWallClock($c)->format('F j, Y');
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
        $c = self::toEstWallClock($c);

        return $c->format('l, M j, Y g:i A').' EST';
    }

    /**
     * HH:MM entered as local Eastern (Toronto) civil time on {@see $calendarDate},
     * converted to EST display (subtract one hour when that date is in EDT).
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
        $adj = self::toEstWallClock($base);

        return $adj->format('H:i');
    }
}
