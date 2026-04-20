<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectServiceConfirmationClient;
use App\Models\ClientProject;
use App\Services\ClientProjectStatusService;
use App\Services\GoogleCalendarLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $this->normalisePhoneOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_email_secondary' => ['nullable', 'email', 'max:255', 'different:client_email'],
            'client_phone' => ['nullable', 'digits:10'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'billing_type' => ['required', \Illuminate\Validation\Rule::in([
                ClientProject::BILLING_FIXED,
                ClientProject::BILLING_HOURLY,
            ])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0.01', 'required_if:billing_type,'.ClientProject::BILLING_HOURLY],
        ], $this->clientProjectValidationMessages());

        // Normalise hourly_rate so fixed projects don't carry a stray rate.
        if ($data['billing_type'] !== ClientProject::BILLING_HOURLY) {
            $data['hourly_rate'] = null;
        }

        // Hourly projects imply the price has already been agreed verbally,
        // so we skip Quote Pending (which would trigger daily admin reminders
        // and the "we received your request" client email) and land directly
        // at Proposal Accepted. The service confirmation email is sent
        // manually from the project page once dates are scheduled.
        $isHourly = $data['billing_type'] === ClientProject::BILLING_HOURLY;
        $initialStatus = $isHourly
            ? ClientProject::STATUS_PROPOSAL_ACCEPTED
            : ClientProject::STATUS_QUOTE_PENDING;

        $project = DB::transaction(function () use ($data, $initialStatus) {
            $project = ClientProject::create(array_merge($data, [
                'status' => $initialStatus,
                'status_changed_at' => now(),
            ]));

            return $project;
        });

        $service->onCreated($project);

        $successMessage = $isHourly
            ? 'Project created. Status set to Proposal Accepted — no email sent to the client yet.'
            : 'Project created. Status set to Quote Pending.';

        return redirect()
            ->route('admin.client-projects.show', $project)
            ->with('success', $successMessage);
    }

    public function show(ClientProject $client_project)
    {
        $client_project->load([
            'installments',
            'expenses',
            'returns',
            'timeEntries',
            'statusHistory.user',
            'contract',
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
        $this->normalisePhoneOnRequest($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_email_secondary' => ['nullable', 'email', 'max:255', 'different:client_email'],
            'client_phone' => ['nullable', 'digits:10'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'billing_type' => ['nullable', \Illuminate\Validation\Rule::in([
                ClientProject::BILLING_FIXED,
                ClientProject::BILLING_HOURLY,
            ])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0.01', 'required_if:billing_type,'.ClientProject::BILLING_HOURLY],
        ], $this->clientProjectValidationMessages());

        // billing_type is optional on update — some narrower forms (like the
        // schedule-only partial on the show page) don't post it. Only update
        // billing fields if billing_type was actually present in the request,
        // otherwise leave the existing billing config untouched.
        if (!$request->has('billing_type')) {
            unset($data['billing_type'], $data['hourly_rate']);
        } elseif ($data['billing_type'] !== ClientProject::BILLING_HOURLY) {
            $data['hourly_rate'] = null;
        }

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

    /**
     * Strip every non-digit from `client_phone` on an incoming request
     * before validation runs. Users submit the masked value "(647)
     * 834-7570" from the form; we validate against the normalised
     * 10-digit string "6478347570" so the `digits:10` rule is reliable
     * regardless of how the field is formatted client-side.
     *
     * Empty / whitespace-only input is converted to null so the
     * "nullable" rule keeps the field truly optional.
     */
    /**
     * User-facing validation messages. Laravel falls back to
     * "validation.digits" (the raw translation key) when no message
     * file is published, which looks broken in the UI — provide
     * friendly strings for the rules we actually use.
     */
    protected function clientProjectValidationMessages(): array
    {
        return [
            'client_phone.digits' => 'Phone number must have exactly 10 digits (e.g. (123) 456-7890).',
            'client_email.email' => 'Please enter a valid email address.',
            'client_email_secondary.email' => 'Please enter a valid secondary email address.',
            'client_email_secondary.different' => 'The secondary email must be different from the primary email.',
            'end_date.after_or_equal' => 'End date must be the same as or after the start date.',
            'hourly_rate.required_if' => 'Hourly rate is required when billing type is "Hourly rate".',
            'hourly_rate.min' => 'Hourly rate must be at least $0.01.',
        ];
    }

    protected function normalisePhoneOnRequest(Request $request): void
    {
        if (!$request->has('client_phone')) {
            return;
        }

        $raw = (string) $request->input('client_phone');
        $digits = preg_replace('/\D/', '', $raw);

        $request->merge([
            'client_phone' => $digits === '' ? null : $digits,
        ]);
    }

    public function destroy(ClientProject $client_project)
    {
        $client_project->delete();

        return redirect()
            ->route('admin.client-projects.index')
            ->with('success', 'Project deleted.');
    }

    /**
     * Send the hourly-billing "service confirmation" email to the client.
     *
     * Only available for hourly projects that have both start_date and
     * end_date set. The rate/price was already agreed verbally, so the
     * email essentially says "the calendar slot is locked in". If the
     * project is still in Proposal Accepted we also transition it to
     * In Progress, mirroring the Quote Sent auto-transition used by the
     * quotation flow.
     */
    public function sendServiceConfirmation(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        if ($client_project->billing_type !== ClientProject::BILLING_HOURLY) {
            return back()->with('error', 'Service confirmation is only available for hourly-billing projects.');
        }

        if (!$client_project->start_date || !$client_project->end_date) {
            return back()->with('error', 'Please set both the start and end dates before sending the service confirmation.');
        }

        if (!$client_project->hasClientContactInfo()) {
            $missing = implode(', ', $client_project->missingClientContactFields());

            return back()->with('error', "Cannot send the service confirmation — missing client info: {$missing}.");
        }

        try {
            $mailer = Mail::to($client_project->client_email, $client_project->client_name);
            if ($cc = $client_project->secondaryClientEmail()) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectServiceConfirmationClient($client_project));
        } catch (\Throwable $e) {
            Log::error('Failed to email service confirmation', [
                'project_id' => $client_project->id,
                'message' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to send the service confirmation: '.$e->getMessage());
        }

        $client_project->service_confirmation_sent_at = now();
        $client_project->save();

        $sendNote = "Service confirmation emailed to {$client_project->clientEmailRecipientsLabel()}.";

        // Confirming the booking with the client effectively means the
        // date is locked in and we're waiting for it to arrive, which is
        // what "Scheduled" captures. Only promote from Proposal Accepted
        // so subsequent re-sends (already past Scheduled, e.g. In
        // Progress or Completed) don't regress the status.
        if ($client_project->status === ClientProject::STATUS_PROPOSAL_ACCEPTED) {
            try {
                $statusService->transition(
                    $client_project,
                    ClientProject::STATUS_SCHEDULED,
                    $sendNote
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to auto-transition project to Scheduled after service confirmation', [
                    'project_id' => $client_project->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        } else {
            // Re-send (status already past Proposal Accepted). Record an
            // event-only row so the history still shows the send.
            try {
                $statusService->recordEvent($client_project, $sendNote);
            } catch (\Throwable $e) {
                Log::warning('Failed to record service-confirmation history entry', [
                    'project_id' => $client_project->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('schedule')
            ->with('success', "Service confirmation emailed to {$client_project->clientEmailRecipientsLabel()}.");
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
