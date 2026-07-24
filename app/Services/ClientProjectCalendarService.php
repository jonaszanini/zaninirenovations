<?php

namespace App\Services;

use App\Models\AdminCalendarEvent;
use App\Models\ClientProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds FullCalendar-compatible event arrays for ClientProject job spans
 * and on-site visit days, shared by the schedule date-picker modal
 * (ClientProjectController::show) and the full-screen calendar page
 * (ClientProjectController::calendar).
 */
class ClientProjectCalendarService
{
    /**
     * Events for every active project EXCEPT the given one, so the admin can
     * see what days are already taken before choosing a start/end for the
     * project they're currently editing.
     *
     * @return list<array<string, mixed>>
     */
    public function schedulePickerEvents(ClientProject $current): array
    {
        $projects = ClientProject::query()
            ->where('id', '!=', $current->id)
            ->whereNotIn('status', ClientProject::terminalStatuses())
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('start_date')->whereNotNull('end_date');
                })->orWhereNotNull('visit_date');
            })
            ->get();

        $events = $this->projectEvents($projects, includeUrl: false);

        // Personal / holiday events block the schedule too, so surface them in
        // the date picker (and its conflict warning) the same as project jobs.
        foreach (AdminCalendarEvent::query()->whereNotNull('start_date')->whereNotNull('end_date')->get() as $event) {
            $payload = $event->toFullCalendarEvent();
            $payload['extendedProps']['status'] = 'Personal event';
            $events[] = $payload;
        }

        return $events;
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
                    'extendedProps' => [
                        'kind' => 'job',
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
                $event = [
                    'id' => 'visit-'.$p->id,
                    'title' => ($visitDone ? 'Visit (done): ' : 'Visit: ').$p->name.' — '.$p->client_name,
                    'start' => $p->visit_date->toDateString(),
                    'end' => Carbon::parse($p->visit_date)->addDay()->toDateString(),
                    'allDay' => true,
                    'backgroundColor' => $visitColor,
                    'borderColor' => $visitColor,
                    'extendedProps' => [
                        'kind' => 'visit',
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
}
