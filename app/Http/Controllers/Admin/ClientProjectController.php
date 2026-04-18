<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use App\Services\GoogleCalendarLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CRUD for ClientProject (full lifecycle management). The model's show view
 * is the "detail page" and hosts Calendar, Financials, Time Tracking and
 * History tabs.
 */
class ClientProjectController extends Controller
{
    public function index(Request $request)
    {
        $validStatuses = ClientProject::statuses();

        $filters = [
            'status' => in_array((string) $request->query('status', ''), $validStatuses, true)
                ? (string) $request->query('status')
                : '',
            'q' => trim((string) $request->query('q', '')),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
        ];

        $query = ClientProject::query()
            ->withCount('installments')
            ->withSum('installments as paid_amount_sum', 'amount')
            ->orderByDesc('status_changed_at')
            ->orderByDesc('id');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('client_email', 'like', "%{$q}%")
                    ->orWhere('client_phone', 'like', "%{$q}%");
            });
        }

        if ($filters['from'] !== '') {
            try {
                $from = \Illuminate\Support\Carbon::parse($filters['from'])->startOfDay();
                $query->where(function ($sub) use ($from) {
                    $sub->where('start_date', '>=', $from)
                        ->orWhere('end_date', '>=', $from)
                        ->orWhere('created_at', '>=', $from);
                });
            } catch (\Throwable $e) {
                // ignore invalid date filter
            }
        }
        if ($filters['to'] !== '') {
            try {
                $to = \Illuminate\Support\Carbon::parse($filters['to'])->endOfDay();
                $query->where(function ($sub) use ($to) {
                    $sub->where('start_date', '<=', $to)
                        ->orWhere('end_date', '<=', $to)
                        ->orWhere('created_at', '<=', $to);
                });
            } catch (\Throwable $e) {
                // ignore invalid date filter
            }
        }

        $projects = $query->paginate(15)->withQueryString();

        $statusLabels = ClientProject::statusLabels();
        $statusColors = ClientProject::statusColors();

        return view('admin.client_projects.index', compact('projects', 'filters', 'statusLabels', 'statusColors'));
    }

    public function create()
    {
        return view('admin.client_projects.create');
    }

    public function store(Request $request, ClientProjectStatusService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $project = DB::transaction(function () use ($data) {
            $project = ClientProject::create(array_merge($data, [
                'status' => ClientProject::STATUS_QUOTE_PENDING,
                'status_changed_at' => now(),
            ]));

            return $project;
        });

        $service->onCreated($project);

        return redirect()
            ->route('admin.client-projects.show', $project)
            ->with('success', 'Project created. Status set to Quote Pending.');
    }

    public function show(ClientProject $client_project)
    {
        $client_project->load([
            'installments',
            'expenses',
            'returns',
            'timeEntries',
            'statusHistory.user',
        ]);

        $calendarLink = GoogleCalendarLink::forProject($client_project);

        return view('admin.client_projects.show', [
            'project' => $client_project,
            'calendarLink' => $calendarLink,
        ]);
    }

    public function edit(ClientProject $client_project)
    {
        return view('admin.client_projects.edit', [
            'project' => $client_project,
        ]);
    }

    public function update(Request $request, ClientProject $client_project, ClientProjectStatusService $service)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:50'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $previousStart = $client_project->start_date ? $client_project->start_date->toDateString() : null;
        $previousEnd = $client_project->end_date ? $client_project->end_date->toDateString() : null;

        $client_project->fill($data)->save();

        // If both dates are now present AND either of them changed, send the admin calendar email.
        $newStart = $client_project->start_date ? $client_project->start_date->toDateString() : null;
        $newEnd = $client_project->end_date ? $client_project->end_date->toDateString() : null;
        $datesChanged = $newStart && $newEnd && ($newStart !== $previousStart || $newEnd !== $previousEnd);

        if ($datesChanged) {
            $service->notifyCalendarDatesSaved($client_project);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->with('success', 'Project updated.');
    }

    public function destroy(ClientProject $client_project)
    {
        $client_project->delete();

        return redirect()
            ->route('admin.client-projects.index')
            ->with('success', 'Project deleted.');
    }

    /**
     * Full-screen calendar view: all projects that have both dates set and
     * that are not rejected, color-coded by status.
     */
    public function calendar()
    {
        $projects = ClientProject::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->where('status', '!=', ClientProject::STATUS_REJECTED)
            ->orderBy('start_date')
            ->get();

        $events = $projects->map(function (ClientProject $p) {
            // FullCalendar all-day events have exclusive end dates.
            $endExclusive = $p->end_date
                ? \Illuminate\Support\Carbon::parse($p->end_date)->addDay()->toDateString()
                : null;

            return [
                'id' => $p->id,
                'title' => $p->name.' — '.$p->client_name,
                'start' => optional($p->start_date)->toDateString(),
                'end' => $endExclusive,
                'allDay' => true,
                'url' => route('admin.client-projects.show', $p),
                'backgroundColor' => $p->statusColor(),
                'borderColor' => $p->statusColor(),
                'extendedProps' => [
                    'status' => $p->statusLabel(),
                ],
            ];
        })->values();

        return view('admin.client_projects.calendar', [
            'events' => $events,
            'statusLabels' => ClientProject::statusLabels(),
            'statusColors' => ClientProject::statusColors(),
        ]);
    }
}
