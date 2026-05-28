<?php

namespace App\Models;

use App\Support\EasternStandardTimeDisplay;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectTimeEntry extends Model
{
    protected $table = 'client_project_time_entries';

    protected $fillable = [
        'client_project_id',
        'client_project_invoice_id',
        'occurred_on',
        'start_time',
        'end_time',
        'hours',
        'note',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'hours' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /**
     * The invoice this time entry was billed on, if any.
     *
     * NULL = "unbilled" — the entry is available to be pulled onto the
     * next hourly invoice draft.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientProjectInvoice::class, 'client_project_invoice_id');
    }

    /** True once the entry has been snapshotted onto some invoice. */
    public function isBilled(): bool
    {
        return !empty($this->client_project_invoice_id);
    }

    /** Scope: entries that have not yet been attached to any invoice. */
    public function scopeUnbilled($query)
    {
        return $query->whereNull('client_project_invoice_id');
    }

    /** Scope: entries with a computed duration (open entries excluded). */
    public function scopeClosed($query)
    {
        return $query->whereNotNull('hours');
    }

    /**
     * An "open" entry has a start time but not yet an end time — the user
     * is still working and will close it later. Hours stays NULL until then.
     */
    public function isOpen(): bool
    {
        return !empty($this->start_time) && empty($this->end_time);
    }

    /**
     * Compute decimal hours from "HH:MM" start_time → end_time (same calendar day).
     * Returns null if either side is missing, unparsable, or end is not after start.
     */
    public static function computeHoursFromRange(?string $start, ?string $end): ?float
    {
        $startMin = self::parseHhMm($start);
        $endMin = self::parseHhMm($end);
        if ($startMin === null || $endMin === null) {
            return null;
        }
        if ($endMin <= $startMin) {
            return null;
        }

        return round(($endMin - $startMin) / 60, 2);
    }

    /**
     * Validate and normalize start/end clock times for create/update forms.
     *
     * @return array{0: ?string, 1: ?string} normalized HH:MM values
     */
    public static function assertValidTimeEntryTimes(?string $start, ?string $end): array
    {
        $normalizedStart = self::normalizeClockTimeOrFail($start, 'start_time');
        $normalizedEnd = self::normalizeClockTimeOrFail($end, 'end_time');

        if ($normalizedEnd && ! $normalizedStart) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => 'Pick a start time before setting an end time.',
            ]);
        }

        if ($normalizedStart && $normalizedEnd) {
            $startMin = self::parseHhMm($normalizedStart);
            $endMin = self::parseHhMm($normalizedEnd);
            if ($endMin === null || $startMin === null || $endMin <= $startMin) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'end_time' => 'End time must be after start time.',
                ]);
            }
        }

        return [$normalizedStart, $normalizedEnd];
    }

    private static function normalizeClockTimeOrFail(?string $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $minutes = self::parseHhMm($value);
        if ($minutes === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'Enter a valid time (hours 0–23, minutes 0–59).',
            ]);
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Format a stored "HH:MM" (24h) value for display as 24-hour clock
     * (e.g. 9:00, 13:00). Returns "—" when empty.
     */
    public static function formatTime(?string $value): string
    {
        $minutes = self::parseHhMm($value);
        if ($minutes === null) {
            return '—';
        }
        $h24 = intdiv($minutes, 60);
        $m = $minutes % 60;

        return sprintf('%d:%02d', $h24, $m);
    }

    /**
     * Same as {@see formatTime}, interpreted on {@see $occurredOn} in Eastern local time.
     */
    public static function formatTimeForDisplay(?string $value, $occurredOn = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if ($occurredOn === null || $occurredOn === '') {
            return self::formatTime($value);
        }

        return EasternStandardTimeDisplay::formatLocalTimeOnDateAsEst($value, $occurredOn);
    }

    private static function parseHhMm(?string $value): ?int
    {
        if (!$value) {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $mm = (int) $m[2];
        if ($h < 0 || $h > 23 || $mm < 0 || $mm > 59) {
            return null;
        }

        return $h * 60 + $mm;
    }
}
