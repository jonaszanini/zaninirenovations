<?php

namespace App\Services;

use App\Models\AdminCalendarEvent;
use App\Models\ClientProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Builds FullCalendar-compatible event arrays for ClientProject job spans
 * and on-site visit days, shared by the schedule date-picker modal
 * (ClientProjectController::schedulePickerEvents) and the full-screen calendar page
 * (ClientProjectController::calendar).
 */
class ClientProjectCalendarService
{
    /**
     * Events for every active project except `$current` (when set), so the
     * admin can see booked days before choosing a start/end.
     *
     * @return list<array<string, mixed>>
     */
    public function schedulePickerEvents(?ClientProject $current = null): array
    {
        $projects = ClientProject::query()
            ->when($current, fn ($q) => $q->where('id', '!=', $current->id))
            ->whereNotIn('status', ClientProject::terminalStatuses())
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('start_date')->whereNotNull('end_date');
                })->orWhereNotNull('visit_date');
            })
            ->get();

        $events = $this->projectEvents($projects, includeUrl: false);

        foreach (AdminCalendarEvent::query()->whereNotNull('start_date')->whereNotNull('end_date')->get() as $event) {
            $payload = $event->toFullCalendarEvent();
            $payload['extendedProps']['status'] = 'Personal event';
            $events[] = $payload;
        }

        return array_map(
            fn (array $event) => $this->eventForSchedulePicker($event),
            $events
        );
    }

    /**
     * Full-screen calendar view: job spans + on-site visit days for every
     * active project, plus personal/holiday events. Rejected/terminal
     * projects are hidden.
     */
    public function fullCalendarEvents(): Collection
    {
        $projects = ClientProject::query()
            ->whereNotIn('status', ClientProject::terminalStatuses())
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('start_date')->whereNotNull('end_date');
                })->orWhereNotNull('visit_date');
            })
            ->orderByRaw('COALESCE(start_date, visit_date)')
            ->orderByDesc('id')
            ->get();

        $events = collect($this->projectEvents($projects, includeUrl: true));

        foreach (AdminCalendarEvent::query()->orderBy('start_date')->orderBy('id')->get() as $personal) {
            $events->push($personal->toFullCalendarEvent());
        }

        return $events->values();
    }

    /**
     * Persist a drag or resize from the project calendar.
     *
     * Job spans stay all-day (`end` is exclusive, stored end_date is inclusive).
     * Visits update visit_date and, when timed, visit_time. Personal events
     * follow the same date/time rules as the personal-event modal.
     *
     * @return array{event: array<string, mixed>, schedule_changed: bool, project: ?ClientProject}
     */
    public function moveEvent(string $kind, int $id, string $start, ?string $end, bool $allDay): array
    {
        return match ($kind) {
            'job' => $this->moveJob($id, $start, $end, $allDay),
            'visit' => $this->moveVisit($id, $start, $allDay),
            'personal' => $this->movePersonal($id, $start, $end, $allDay),
            default => throw ValidationException::withMessages([
                'kind' => ['Choose a job, visit, or personal event.'],
            ]),
        };
    }

    /**
     * @param  Collection<int, ClientProject>  $projects
     * @return list<array<string, mixed>>
     */
    private function projectEvents(Collection $projects, bool $includeUrl): array
    {
        $events = [];

        foreach ($projects as $p) {
            if ($p->start_date && $p->end_date) {
                $event = [
                    'id' => 'job-'.$p->id,
                    'title' => $p->name.' — '.$p->client_name,
                    'start' => $p->start_date->toDateString(),
                    // FullCalendar treats `end` as exclusive for all-day events.
                    'end' => Carbon::parse($p->end_date)->addDay()->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $p->statusColor(),
                    'borderColor' => $p->statusColor(),
                    'textColor' => '#ffffff',
                    'startEditable' => true,
                    'durationEditable' => true,
                    'extendedProps' => [
                        'kind' => 'job',
                        'recordId' => $p->id,
                        'status' => $p->statusLabel(),
                    ],
                ];
                if ($includeUrl) {
                    $event['url'] = route('admin.client-projects.show', $p);
                }
                $events[] = $event;
            }

            if ($p->visit_date) {
                $visitDone = (bool) $p->visit_done;
                $visitColor = $visitDone ? '#94a3b8' : $p->statusColor();

                $hasTime = filled($p->visit_time);
                if ($hasTime) {
                    $timeParts = explode(':', $p->visit_time);
                    $hour = (int) ($timeParts[0] ?? 0);
                    $minute = (int) ($timeParts[1] ?? 0);
                    $startDateTime = Carbon::parse($p->visit_date)->setTime($hour, $minute, 0);
                    $endDateTime = (clone $startDateTime)->addHour();
                    // Use floating local format (no timezone offset) so FullCalendar
                    // displays the exact wall-clock time without browser TZ conversion.
                    $start = $startDateTime->format('Y-m-d\TH:i:s');
                    $end = $endDateTime->format('Y-m-d\TH:i:s');
                    $allDay = false;
                } else {
                    $start = $p->visit_date->toDateString();
                    $end = Carbon::parse($p->visit_date)->addDay()->toDateString();
                    $allDay = true;
                }

                $event = [
                    'id' => 'visit-'.$p->id,
                    'title' => ($visitDone ? 'Visit (done): ' : 'Visit: ').$p->name.' — '.$p->client_name,
                    'start' => $start,
                    'end' => $end,
                    'allDay' => $allDay,
                    'backgroundColor' => $visitColor,
                    'borderColor' => $visitColor,
                    'textColor' => '#ffffff',
                    'startEditable' => true,
                    'durationEditable' => false,
                    'extendedProps' => [
                        'kind' => 'visit',
                        'recordId' => $p->id,
                        'status' => $visitDone ? 'Visit (completed)' : 'Visit — '.$p->statusLabel(),
                    ],
                ];
                if ($includeUrl) {
                    $event['url'] = route('admin.client-projects.show', $p);
                }
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Schedule picker overlap logic expects YYYY-MM-DD start/end (all-day blocks).
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function eventForSchedulePicker(array $event): array
    {
        $start = $this->extractDatePart($event['start'] ?? null);
        if ($start === null) {
            return $event;
        }

        $inclusiveEnd = $start;
        if (! empty($event['allDay']) && ! empty($event['end'])) {
            $exclusiveEnd = $this->extractDatePart($event['end']);
            if ($exclusiveEnd !== null) {
                $inclusiveEnd = Carbon::parse($exclusiveEnd)->subDay()->toDateString();
                if ($inclusiveEnd < $start) {
                    $inclusiveEnd = $start;
                }
            }
        }

        return array_merge($event, [
            'start' => $start,
            'end' => Carbon::parse($inclusiveEnd)->addDay()->toDateString(),
            'allDay' => true,
        ]);
    }

    /**
     * @return array{event: array<string, mixed>, schedule_changed: bool, project: ?ClientProject}
     */
    private function moveJob(int $id, string $start, ?string $end, bool $allDay): array
    {
        $project = $this->movableProject($id);
        if (! $allDay) {
            throw ValidationException::withMessages([
                'all_day' => ['Job spans stay all-day events.'],
            ]);
        }

        $startDate = $this->requireDate($start, 'start');
        $endDate = $this->inclusiveAllDayEnd($startDate, $end);
        $previousStart = $project->start_date?->toDateString();
        $previousEnd = $project->end_date?->toDateString();

        $project->start_date = $startDate;
        $project->end_date = $endDate;
        $project->save();

        $scheduleChanged = $previousStart !== $startDate || $previousEnd !== $endDate;
        if ($scheduleChanged) {
            if ($previousStart !== $startDate) {
                $project->day_before_start_sent_for_date = null;
            }
            $project->fixed_schedule_confirmation_sent_at = null;
            $project->saveQuietly();
        }

        return [
            'event' => $this->singleProjectEvent($project->fresh(), 'job'),
            'schedule_changed' => $scheduleChanged,
            'project' => $project->fresh(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, schedule_changed: bool, project: ?ClientProject}
     */
    private function moveVisit(int $id, string $start, bool $allDay): array
    {
        $project = $this->movableProject($id);
        $instant = $this->splitInstant($start, 'start');
        $project->visit_date = $instant['date'];
        $project->visit_time = $allDay ? null : $instant['time'];
        if (! $allDay && $project->visit_time === null) {
            throw ValidationException::withMessages([
                'start' => ['A timed visit needs a start time.'],
            ]);
        }
        $project->save();

        return [
            'event' => $this->singleProjectEvent($project->fresh(), 'visit'),
            'schedule_changed' => false,
            'project' => $project->fresh(),
        ];
    }

    /**
     * @return array{event: array<string, mixed>, schedule_changed: bool, project: ?ClientProject}
     */
    private function movePersonal(int $id, string $start, ?string $end, bool $allDay): array
    {
        $event = AdminCalendarEvent::query()->find($id);
        if ($event === null) {
            throw ValidationException::withMessages([
                'id' => ['That personal event was not found.'],
            ]);
        }

        $startInstant = $this->splitInstant($start, 'start');
        if ($allDay) {
            $event->start_date = $startInstant['date'];
            $event->end_date = $this->inclusiveAllDayEnd($startInstant['date'], $end);
            $event->start_time = null;
            $event->end_time = null;
        } else {
            if ($startInstant['time'] === null) {
                throw ValidationException::withMessages([
                    'start' => ['A timed personal event needs a start time.'],
                ]);
            }
            $endInstant = $end !== null && $end !== ''
                ? $this->splitInstant($end, 'end')
                : ['date' => $startInstant['date'], 'time' => null];
            if ($endInstant['date'] !== $startInstant['date']) {
                throw ValidationException::withMessages([
                    'end' => ['Timed personal events must use the same start and end date.'],
                ]);
            }
            if ($endInstant['time'] !== null && $endInstant['time'] <= $startInstant['time']) {
                throw ValidationException::withMessages([
                    'end' => ['End time must be after the start time.'],
                ]);
            }
            $event->start_date = $startInstant['date'];
            $event->end_date = $startInstant['date'];
            $event->start_time = $startInstant['time'];
            $event->end_time = $endInstant['time'];
        }
        $event->save();

        return [
            'event' => $event->fresh()->toFullCalendarEvent(),
            'schedule_changed' => false,
            'project' => null,
        ];
    }

    private function movableProject(int $id): ClientProject
    {
        $project = ClientProject::query()->find($id);
        if ($project === null || in_array($project->status, ClientProject::terminalStatuses(), true)) {
            throw ValidationException::withMessages([
                'id' => ['This project cannot be moved on the calendar.'],
            ]);
        }

        return $project;
    }

    /**
     * @return array<string, mixed>
     */
    private function singleProjectEvent(ClientProject $project, string $kind): array
    {
        $events = $this->projectEvents(collect([$project]), includeUrl: true);
        foreach ($events as $event) {
            if (($event['extendedProps']['kind'] ?? null) === $kind) {
                return $event;
            }
        }

        throw ValidationException::withMessages([
            'id' => ['This project has no '.$kind.' event to update.'],
        ]);
    }

    /**
     * @return array{date: string, time: ?string}
     */
    private function splitInstant(string $value, string $field): array
    {
        $value = trim($value);
        if (! preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}):(\d{2}))?/', $value, $matches)) {
            throw ValidationException::withMessages([
                $field => ['Enter a valid date.'],
            ]);
        }

        return [
            'date' => $matches[1],
            'time' => isset($matches[2]) ? $matches[2].':'.$matches[3] : null,
        ];
    }

    private function requireDate(string $value, string $field): string
    {
        return $this->splitInstant($value, $field)['date'];
    }

    private function inclusiveAllDayEnd(string $startDate, ?string $end): string
    {
        if ($end === null || trim($end) === '') {
            return $startDate;
        }

        $exclusive = $this->requireDate($end, 'end');
        $inclusive = Carbon::parse($exclusive)->subDay()->toDateString();
        if ($inclusive < $startDate) {
            return $startDate;
        }

        return $inclusive;
    }

    private function extractDatePart(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
