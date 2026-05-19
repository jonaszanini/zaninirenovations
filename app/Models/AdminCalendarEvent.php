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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /** Payload for FullCalendar (all-day, inclusive end stored in DB). */
    public function toFullCalendarEvent(): array
    {
        return [
            'id' => 'personal-'.$this->id,
            'title' => $this->title,
            'start' => $this->start_date->toDateString(),
            'end' => Carbon::parse($this->end_date)->addDay()->toDateString(),
            'allDay' => true,
            'backgroundColor' => self::CALENDAR_COLOR,
            'borderColor' => self::CALENDAR_COLOR,
            'extendedProps' => [
                'kind' => 'personal',
                'description' => $this->description ?? '',
                'eventId' => $this->id,
            ],
        ];
    }
}
