<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class AdminCalendarEvent extends Model
{
    /** Standalone (personal / holiday) events — always red on the calendar. */
    public const CALENDAR_COLOR = '#dc2626';

    protected $fillable = [
        'title',
        'description',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'start_time' => 'string',
        'end_time' => 'string',
    ];

    /** H:i for &lt;input type="time"&gt; (MySQL TIME may return H:i:s). */
    public function startTimeForInput(): ?string
    {
        return self::timeColumnForInput($this->start_time);
    }

    public function endTimeForInput(): ?string
    {
        return self::timeColumnForInput($this->end_time);
    }

    /**
     * Display label such as "9:00 AM–11:00 AM", or null when no start time.
     */
    public function timeRangeDisplayLabel(): ?string
    {
        if (! filled($this->start_time)) {
            return null;
        }

        $start = self::formatTime12Hour($this->start_time);
        if (filled($this->end_time)) {
            return $start.'–'.self::formatTime12Hour($this->end_time);
        }

        return $start;
    }

    /** Payload for FullCalendar (all-day or timed, inclusive end stored in DB for all-day). */
    public function toFullCalendarEvent(): array
    {
        $timeLabel = $this->timeRangeDisplayLabel();

        if (! filled($this->start_time)) {
            return [
                'id' => 'personal-'.$this->id,
                'title' => $this->title,
                'start' => $this->start_date->toDateString(),
                'end' => Carbon::parse($this->end_date)->addDay()->toDateString(),
                'allDay' => true,
                'backgroundColor' => self::CALENDAR_COLOR,
                'borderColor' => self::CALENDAR_COLOR,
                'textColor' => '#ffffff',
                'extendedProps' => [
                    'kind' => 'personal',
                    'description' => $this->description ?? '',
                    'eventId' => $this->id,
                    'baseTitle' => $this->title,
                    'startTime' => null,
                    'endTime' => null,
                    'timeLabel' => null,
                ],
            ];
        }

        $startDateTime = self::combineDateAndTime($this->start_date, $this->start_time);

        $payload = [
            'id' => 'personal-'.$this->id,
            'title' => $this->title,
            'start' => $startDateTime->format('Y-m-d\TH:i:s'),
            'allDay' => false,
            'backgroundColor' => self::CALENDAR_COLOR,
            'borderColor' => self::CALENDAR_COLOR,
            'textColor' => '#ffffff',
            'extendedProps' => [
                'kind' => 'personal',
                'description' => $this->description ?? '',
                'eventId' => $this->id,
                'baseTitle' => $this->title,
                'startTime' => $this->startTimeForInput(),
                'endTime' => $this->endTimeForInput(),
                'timeLabel' => $timeLabel,
            ],
        ];

        if (filled($this->end_time)) {
            $payload['end'] = self::combineDateAndTime($this->end_date, $this->end_time)
                ->format('Y-m-d\TH:i:s');
        }

        return $payload;
    }

    private static function timeColumnForInput(?string $time): ?string
    {
        if (! filled($time)) {
            return null;
        }

        return substr((string) $time, 0, 5);
    }

    private static function formatTime12Hour(?string $time): string
    {
        $normalized = ClientProject::normalizeTimeToHi($time);
        if ($normalized === null) {
            return '';
        }
        [$hour, $minute] = array_map('intval', explode(':', $normalized));

        return Carbon::createFromTime($hour, $minute, 0)->format('g:i A');
    }

    private static function combineDateAndTime(Carbon|string $date, string $time): Carbon
    {
        $normalized = ClientProject::normalizeTimeToHi($time) ?? '00:00';
        [$hour, $minute] = array_map('intval', explode(':', $normalized));
        $base = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return $base->setTime($hour, $minute, 0);
    }
}
