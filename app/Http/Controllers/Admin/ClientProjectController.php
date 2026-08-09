<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectScheduleDatesClient;
use App\Mail\ClientProjectServiceConfirmationClient;
use App\Mail\ClientProjectTimeTrackingSummaryClient;
use App\Models\Client;
use App\Models\ClientCommunicationLog;
use App\Models\AdminCalendarEvent;
use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use App\Services\ClientProjectCalendarService;
use App\Services\ClientProjectClientMailer;
use App\Services\ClientProjectPhotoStorage;
use App\Services\ClientProjectStatusService;
use App\Services\ClientProjectContactService;
use App\Services\ClientProjectLinkedUtilitiesService;
use App\Support\AdminPaginatedList;
use App\Support\EasternStandardTimeDisplay;
use App\Services\GoogleCalendarLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD for ClientProject (full lifecycle management). The model's show view
 * is the "detail page" and hosts Calendar, Financials, Time Tracking and
 * History tabs.
 */
class ClientProjectController extends Controller
{
  /** @var list<int> */
    public const LIST_PER_PAGE_OPTIONS = [25, 35, 50];

    public function __construct(protected ClientProjectCalendarService $calendarService, protected ClientProjectClientMailer $clientMailer)
    {
    }

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
            /** `ongoing` (default): hide completed & rejected unless a specific status is chosen. `all`: show every status when status filter is empty. */
            'list' => in_array((string) $request->query('list', 'ongoing'), ['ongoing', 'all'], true)
                ? (string) $request->query('list', 'ongoing')
                : 'ongoing',
        ];

        $referenceId = $filters['q'] !== ''
            ? ClientProject::idFromReferenceSearch($filters['q'])
            : null;

        $query = ClientProject::query()
            ->withCount('installments')
            ->withSum('installments as paid_amount_sum', 'amount')
            // Pull hours so hourly-billed projects can display rate × hours
            // in the TOTAL column without N+1-ing timeEntries per row.
            ->withSum(
                ['timeEntries as hours_logged' => fn ($q) => $q->whereNotNull('hours')],
                'hours'
            )
            // Soonest relevant date first: job start when set, otherwise visit
            // date (Visit scheduled). Rows with neither fall to the bottom.
            ->orderByRaw('CASE WHEN start_date IS NULL AND visit_date IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(start_date, visit_date) ASC')
            ->orderByDesc('id');

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        } elseif ($filters['list'] === 'ongoing' && $filters['q'] === '') {
            // Emphasise active pipeline: exclude terminal states unless viewing "All"
            // or searching (which implies looking for any project matching the query).
            $query->whereNotIn('status', ClientProject::statusesHiddenFromOngoingList());
        }

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($sub) use ($q, $referenceId) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('client_email', 'like', "%{$q}%")
                    ->orWhere('client_phone', 'like', "%{$q}%");

                if ($referenceId !== null) {
                    $sub->orWhere('id', $referenceId);
                }
            });
        }

        if ($filters['from'] !== '') {
            try {
                $from = \Illuminate\Support\Carbon::parse($filters['from'])->startOfDay();
                $query->where(function ($sub) use ($from) {
                    $sub->where('start_date', '>=', $from)
                        ->orWhere('end_date', '>=', $from)
                        ->orWhere('visit_date', '>=', $from)
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
                        ->orWhere('visit_date', '<=', $to)
                        ->orWhere('created_at', '<=', $to);
                });
            } catch (\Throwable $e) {
                // ignore invalid date filter
            }
        }

        $perPage = AdminPaginatedList::perPageFrom(
            $request,
            self::LIST_PER_PAGE_OPTIONS,
            self::LIST_PER_PAGE_OPTIONS[0],
        );

        $projects = $query->paginate($perPage)->withQueryString();

        $statusLabels = ClientProject::statusLabels();
        $statusColors = ClientProject::statusColors();

        return view('admin.client_projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'statusLabels' => $statusLabels,
            'statusColors' => $statusColors,
            'perPage' => $perPage,
            'perPageOptions' => self::LIST_PER_PAGE_OPTIONS,
        ]);
    }

    public function create()
    {
        return view('admin.client_projects.create', [
            'clientsSearchUrl' => route('admin.clients.search'),
            'initialClientForPicker' => $this->initialClientForPicker(
                (int) old('client_id', 0) ?: null
            ),
        ]);
    }

    public function store(Request $request, ClientProjectStatusService $service, ClientProjectPhotoStorage $photoStorage, ClientProjectContactService $contactService)
    {
        $this->hydrateClientContactFromDirectory($request);
        $this->normalisePhoneOnRequest($request);
        $this->normaliseClientAddressGeoOnRequest($request);
        $this->normaliseContactedViaOnRequest($request);
        $this->normaliseConversationOnOnRequest($request);

        $contactedViaKeys = array_keys(ClientProject::contactedViaOptions());

        $data = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'contacted_via' => ['required', Rule::in($contactedViaKeys)],
            'conversation_on' => ['nullable', Rule::in($contactedViaKeys)],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_email_secondary' => ['nullable', 'email', 'max:255', 'different:client_email'],
            'client_phone' => ['nullable', 'digits:10'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'client_address_place_id' => ['nullable', 'string', 'max:255'],
            'client_address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'client_address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'invoice_billing_name' => ['nullable', 'string', 'max:255'],
            'invoice_billing_address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'billing_type' => ['required', \Illuminate\Validation\Rule::in([
                ClientProject::BILLING_FIXED,
                ClientProject::BILLING_HOURLY,
            ])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0.01', 'required_if:billing_type,'.ClientProject::BILLING_HOURLY],
            'send_client_email' => ['nullable', 'boolean'],
            'sync_client_directory' => ['nullable', 'boolean'],
            'visit_required' => ['nullable', 'boolean'],
            'visit_date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ], ClientProjectPhotoStorage::uploadValidationRules()), $this->clientProjectValidationMessages());

        // Normalise hourly_rate so fixed projects don't carry a stray rate.
        if ($data['billing_type'] !== ClientProject::BILLING_HOURLY) {
            $data['hourly_rate'] = null;
        }

        // Pulled out before array_merge below because it isn't a column
        // on the projects table — it controls the onCreated side-effect.
        $sendClientEmail = (bool) ($data['send_client_email'] ?? true);
        $syncDirectory = (bool) ($data['sync_client_directory'] ?? true);
        unset($data['send_client_email'], $data['sync_client_directory'], $data['photos']);

        $contactService->applyForCreate($data, $syncDirectory);

        foreach (['invoice_billing_name', 'invoice_billing_address', 'conversation_on'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        $isHourly = $data['billing_type'] === ClientProject::BILLING_HOURLY;
        $visitRequired = ! $isHourly && (bool) ($data['visit_required'] ?? false);

        if ($visitRequired) {
            $data['visit_required'] = true;
            $data['visit_done'] = false;
        } else {
            $data['visit_required'] = false;
            $data['visit_date'] = null;
            $data['visit_done'] = false;
        }

        // Hourly projects imply the price has already been agreed verbally,
        // so we skip the pre-quote stages (visit / quote pending) and land
        // directly at Proposal Accepted. Fixed-price projects start at
        // Visit required, Visit scheduled, or Quote pending.
        $initialStatus = ClientProject::initialStatusForCreate(
            $data['billing_type'],
            $visitRequired,
            $data['visit_date'] ?? null
        );

        $project = DB::transaction(function () use ($data, $initialStatus, $request, $photoStorage) {
            $project = ClientProject::create(array_merge($data, [
                'status' => $initialStatus,
                'status_changed_at' => now(),
            ]));

            if ($request->hasFile('photos')) {
                $photoStorage->storeUploads($project, (array) $request->file('photos'));
            }

            return $project;
        });

        $service->onCreated($project, $sendClientEmail);

        if ($isHourly) {
            $successMessage = 'Project created. Status set to Proposal Accepted — no email sent to the client yet.';
        } elseif ($initialStatus === ClientProject::STATUS_VISIT_REQUIRED) {
            if ($sendClientEmail && filled($project->client_email)) {
                $successMessage = 'Project created. Status set to Visit required. Acknowledgement emailed to the client.';
            } else {
                $successMessage = 'Project created. Status set to Visit required. No client email sent.';
            }
        } elseif ($initialStatus === ClientProject::STATUS_VISIT_SCHEDULED) {
            if ($sendClientEmail && filled($project->client_email)) {
                $successMessage = 'Project created. Status set to Visit scheduled. Acknowledgement emailed to the client.';
            } else {
                $successMessage = 'Project created. Status set to Visit scheduled. No client email sent.';
            }
        } elseif ($sendClientEmail && filled($project->client_email)) {
            $successMessage = 'Project created. Status set to Quote pending. Acknowledgement emailed to the client.';
        } else {
            $successMessage = 'Project created. Status set to Quote pending. No client email sent.';
        }

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
            'helpers',
            'helperTimeEntries.helper',
            'helperTimeEntries.payment',
            'helperTimeEntries.payment.splits',
            'helperPayments.helper',
            'helperPayments.timeEntries',
            'statusHistory.user',
            'contract',
            'quotations',
            'photos',
            // invoices.timeEntries is used by the hourly invoice tab to
            // show "N time entries snapshotted" under each invoice row.
            'invoices.timeEntries',
        ]);

        $calendarLink = GoogleCalendarLink::forProject($client_project);

        // Other-project events shown inside the date-picker modal on the
        // Calendar tab so the admin can see what days are already taken
        // before choosing a start/end. Excludes the current project so its
        // own dates don't show up as "conflicts".
        $scheduleEvents = $this->calendarService->schedulePickerEvents($client_project);

        $linkedUtilities = app(ClientProjectLinkedUtilitiesService::class)->forProject($client_project);

        return view('admin.client_projects.show', [
            'project' => $client_project,
            'calendarLink' => $calendarLink,
            'scheduleEvents' => $scheduleEvents,
            'activeHelpers' => \App\Models\Helper::query()->active()->orderBy('name')->get(),
            'linkedUtilities' => $linkedUtilities,
        ]);
    }

    public function storePhotos(Request $request, ClientProject $client_project, ClientProjectPhotoStorage $photoStorage)
    {
        $existingCount = (int) $client_project->photos()->count();
        $rules = ClientProjectPhotoStorage::uploadValidationRules($existingCount);
        $rules['photos'] = ['required', 'array', 'min:1', 'max:'.max(1, ClientProjectPhotoStorage::MAX_PHOTOS_PER_PROJECT - $existingCount)];

        $request->validate($rules, [
            'photos.required' => 'Choose at least one file to upload.',
        ]);

        try {
            $added = $photoStorage->storeUploads($client_project, (array) $request->file('photos'));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['photos' => $e->getMessage()])->withInput();
        }

        $count = count($added);
        $message = $count === 1 ? '1 file added.' : "{$count} files added.";

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->with('success', $message);
    }

    public function destroyPhoto(ClientProject $client_project, ClientProjectPhoto $photo, ClientProjectPhotoStorage $photoStorage)
    {
        if ((int) $photo->client_project_id !== (int) $client_project->id) {
            abort(404);
        }

        $photoStorage->deletePhoto($photo);

        return back()->with('success', 'File removed.');
    }

    public function edit(ClientProject $client_project)
    {
        $client_project->load(['client', 'photos']);

        return view('admin.client_projects.edit', [
            'project' => $client_project,
            'clientsSearchUrl' => route('admin.clients.search'),
            'initialClientForPicker' => $this->initialClientForPicker(
                (int) old('client_id', $client_project->client_id) ?: null
            ),
        ]);
    }

    public function update(Request $request, ClientProject $client_project, ClientProjectStatusService $service, ClientProjectContactService $contactService)
    {
        $this->hydrateClientContactFromDirectory($request);
        $this->normalisePhoneOnRequest($request);
        $this->normaliseClientAddressGeoOnRequest($request);
        $this->normaliseContactedViaOnRequest($request);
        $this->normaliseConversationOnOnRequest($request);

        $contactedViaKeys = array_keys(ClientProject::contactedViaOptions());

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'contacted_via' => ['sometimes', 'nullable', Rule::in($contactedViaKeys)],
            'conversation_on' => ['sometimes', 'nullable', Rule::in($contactedViaKeys)],
            'client_id' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_email_secondary' => ['nullable', 'email', 'max:255', 'different:client_email'],
            'client_phone' => ['nullable', 'digits:10'],
            'client_address' => ['nullable', 'string', 'max:500'],
            'client_address_place_id' => ['nullable', 'string', 'max:255'],
            'client_address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'client_address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'invoice_billing_name' => ['nullable', 'string', 'max:255'],
            'invoice_billing_address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visit_required' => ['sometimes', 'boolean'],
            'visit_date' => ['nullable', 'date'],
            'visit_done' => ['sometimes', 'boolean'],
            'billing_type' => ['nullable', \Illuminate\Validation\Rule::in([
                ClientProject::BILLING_FIXED,
                ClientProject::BILLING_HOURLY,
            ])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0.01', 'required_if:billing_type,'.ClientProject::BILLING_HOURLY],
            'sync_client_directory' => ['sometimes', 'boolean'],
        ];

        if ($client_project->isCompleted()) {
            $rules['client_id'] = ['sometimes', 'nullable', 'integer', 'exists:clients,id'];
            foreach (ClientProject::clientContactFieldKeys() as $key) {
                $rules[$key] = ['sometimes', 'nullable', 'string', 'max:2000'];
            }
            $rules['client_email'] = ['sometimes', 'nullable', 'email', 'max:255'];
            $rules['client_email_secondary'] = ['sometimes', 'nullable', 'email', 'max:255'];
            $rules['client_phone'] = ['sometimes', 'nullable', 'digits:10'];
            $rules['client_address'] = ['sometimes', 'nullable', 'string', 'max:500'];
            $rules['client_address_latitude'] = ['sometimes', 'nullable', 'numeric', 'between:-90,90'];
            $rules['client_address_longitude'] = ['sometimes', 'nullable', 'numeric', 'between:-180,180'];
            unset($rules['sync_client_directory']);
        }

        $validator = Validator::make($request->all(), $rules, $this->clientProjectValidationMessages());

        if ($validator->fails()) {
            if ($request->has('billing_type')) {
                return redirect()
                    ->route('admin.client-projects.edit', $client_project)
                    ->withErrors($validator)
                    ->withInput();
            }

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('schedule')
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        foreach (['invoice_billing_name', 'invoice_billing_address', 'conversation_on'] as $k) {
            if (array_key_exists($k, $data) && $data[$k] === '') {
                $data[$k] = null;
            }
        }

        // billing_type is optional on update — some narrower forms (like the
        // schedule-only partial on the show page) don't post it. Only update
        // billing fields if billing_type was actually present in the request,
        // otherwise leave the existing billing config untouched.
        if (!$request->has('billing_type')) {
            unset($data['billing_type'], $data['hourly_rate']);
        } elseif ($data['billing_type'] !== ClientProject::BILLING_HOURLY) {
            $data['hourly_rate'] = null;
        }

        // Visit fields: only apply when this request includes them (Calendar /
        // Edit forms). Other partial updates must not wipe visit settings.
        if (array_key_exists('visit_required', $data)) {
            if (!$data['visit_required']) {
                $data['visit_date'] = null;
            }
        } else {
            unset($data['visit_required'], $data['visit_date']);
        }

        $previousStart = $client_project->start_date ? $client_project->start_date->toDateString() : null;
        $previousEnd = $client_project->end_date ? $client_project->end_date->toDateString() : null;

        $syncDirectory = $request->boolean('sync_client_directory', true);
        unset($data['sync_client_directory']);
        $contactService->applyForUpdate($client_project, $data, $syncDirectory);

        $client_project->fill($data)->save();
        $client_project = $service->syncVisitStatusFromFields($client_project->fresh());

        $newStartAfterSave = $client_project->start_date ? $client_project->start_date->toDateString() : null;
        $newEndAfterSave = $client_project->end_date ? $client_project->end_date->toDateString() : null;
        $scheduleFieldsChanged = ($newStartAfterSave !== $previousStart) || ($newEndAfterSave !== $previousEnd);
        if ($scheduleFieldsChanged) {
            if ($newStartAfterSave !== $previousStart) {
                $client_project->day_before_start_sent_for_date = null;
            }
            $client_project->fixed_schedule_confirmation_sent_at = null;
            $client_project->saveQuietly();
        }

        // Dates are the only field that triggers an admin calendar email,
        // and only when the "first installment paid" gate is also satisfied
        // (maybeSendCalendarDatesEmail encodes that rule — see service).
        $newStart = $client_project->start_date ? $client_project->start_date->toDateString() : null;
        $newEnd = $client_project->end_date ? $client_project->end_date->toDateString() : null;
        $datesChanged = $newStart && $newEnd && ($newStart !== $previousStart || $newEnd !== $previousEnd);

        $successMessage = 'Project updated.';
        if ($datesChanged) {
            $result = $service->maybeSendCalendarDatesEmail($client_project);
            if ($result === 'sent') {
                $successMessage = 'Project updated. Calendar notification emailed.';
            } elseif ($result === 'failed') {
                $successMessage = 'Project updated, but the calendar notification email failed. Check logs and retry.';
            }
            // 'waiting-payment' is silent here — the user is still editing
            // dates ahead of the 1st payment; the email will fire once
            // they mark the first installment paid.
        }

        $scheduleClientWarning = null;
        $fixedSched = $service->maybeSendFixedScheduleConfirmationToClient($client_project->fresh());
        if ($fixedSched === 'sent') {
            $successMessage .= ' Schedule confirmation emailed to the client.';
        } elseif ($fixedSched === 'failed') {
            $scheduleClientWarning = 'The client schedule confirmation email failed to send. Check logs.';
        }

        $redirect = redirect()
            ->route('admin.client-projects.show', $client_project)
            ->with('success', $successMessage);

        if ($scheduleClientWarning) {
            $redirect->with('warning', $scheduleClientWarning);
        }

        return $redirect;
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
            'end_date.after_or_equal' => 'The end date must be the same day or after the start date. Fix the dates and try again.',
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

    /** Clear geocoding fields when the service address is blank. */
    protected function normaliseClientAddressGeoOnRequest(Request $request): void
    {
        if (! $request->has('client_address')) {
            return;
        }

        if (filled(trim((string) $request->input('client_address')))) {
            return;
        }

        $request->merge([
            'client_address' => null,
            'client_address_place_id' => null,
            'client_address_latitude' => null,
            'client_address_longitude' => null,
        ]);
    }

    /** Empty string → null so `nullable|in:…` passes on partial update forms. */
    protected function normaliseContactedViaOnRequest(Request $request): void
    {
        if (!$request->has('contacted_via')) {
            return;
        }

        $v = $request->input('contacted_via');
        if ($v === '' || $v === null) {
            $request->merge(['contacted_via' => null]);
        }
    }

    /** Empty string → null for optional follow-up channel. */
    protected function normaliseConversationOnOnRequest(Request $request): void
    {
        if (! $request->has('conversation_on')) {
            return;
        }

        $v = $request->input('conversation_on');
        if ($v === '' || $v === null) {
            $request->merge(['conversation_on' => null]);
        }
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

        $confirmationSubject = 'Service confirmation from Zanini Renovations';
        $confirmationDescription = 'Service booking confirmed — '
            .EasternStandardTimeDisplay::formatCalendarMediumDate($client_project->start_date)
            .' to '.EasternStandardTimeDisplay::formatCalendarMediumDate($client_project->end_date);

        $failure = $this->clientMailer->send(
            $client_project,
            new ClientProjectServiceConfirmationClient($client_project),
            ClientCommunicationLog::REASON_SERVICE_CONFIRMATION,
            $confirmationSubject,
            $confirmationDescription,
            'service confirmation',
        );

        if ($failure !== null) {
            return back()->with('error', 'Failed to send the service confirmation: '.$failure);
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
     * Manual on-demand "here are your dates" email — sendable any time from
     * the Calendar tab regardless of billing type. On success, promotes the
     * project to Scheduled when it is not already past that stage (re-sends
     * while In Progress / Completed etc. only log a history event).
     */
    public function sendScheduleDates(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        if (!$client_project->start_date || !$client_project->end_date) {
            return back()->with('error', 'Please set both the start and end dates before emailing the schedule.');
        }

        if (!$client_project->hasClientContactInfo()) {
            $missing = implode(', ', $client_project->missingClientContactFields());

            return back()->with('error', "Cannot send the schedule — missing client info: {$missing}.");
        }

        $subject = 'Your project dates with Zanini Renovations';
        $description = 'Schedule dates emailed manually — '
            .EasternStandardTimeDisplay::formatCalendarMediumDate($client_project->start_date)
            .' to '.EasternStandardTimeDisplay::formatCalendarMediumDate($client_project->end_date);

        $failure = $this->clientMailer->send(
            $client_project,
            new ClientProjectScheduleDatesClient($client_project),
            ClientCommunicationLog::REASON_SCHEDULE_DATES_MANUAL,
            $subject,
            $description,
            'manual schedule dates',
        );

        if ($failure !== null) {
            return back()->with('error', 'Failed to send the schedule email: '.$failure);
        }

        $sendNote = "Schedule dates emailed to {$client_project->clientEmailRecipientsLabel()}.";

        // Sending dates to the client means the job is booked — mark Scheduled.
        // Do not regress projects already in active work or finished.
        $statusesPastScheduled = [
            ClientProject::STATUS_IN_PROGRESS,
            ClientProject::STATUS_AWAITING_FINAL_PAYMENT,
            ClientProject::STATUS_COMPLETED,
            ClientProject::STATUS_CANCELLED,
            ClientProject::STATUS_REJECTED,
        ];

        try {
            if ($client_project->status === ClientProject::STATUS_SCHEDULED) {
                $statusService->recordEvent($client_project, $sendNote);
            } elseif (! in_array($client_project->status, $statusesPastScheduled, true)) {
                $statusService->transition(
                    $client_project,
                    ClientProject::STATUS_SCHEDULED,
                    $sendNote
                );
            } else {
                $statusService->recordEvent($client_project, $sendNote);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to update project status after schedule-dates email', [
                'project_id' => $client_project->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('schedule')
            ->with('success', $sendNote);
    }

    /**
     * Email the client a table of every closed time entry (date, start/end,
     * hours, notes) plus total hours and labour subtotal / HST / total with
     * HST. Does not change project status.
     */
    public function sendTimeTrackingSummary(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        if (!$client_project->isHourly()) {
            return back()->with('error', 'The time summary email is only available for hourly-billing projects.');
        }

        $client_project->load('timeEntries');

        $closedCount = $client_project->timeEntries->filter(fn ($e) => $e->hours !== null)->count();
        if ($closedCount === 0) {
            return back()
                ->withFragment('time')
                ->with('error', 'Add at least one completed time entry (with hours) before sending a summary to the client.');
        }

        if (!filled($client_project->client_email)) {
            return back()
                ->withFragment('time')
                ->with('error', 'Cannot send the time summary — the client email is missing. Edit the project to add it.');
        }

        if (!filled($client_project->client_name)) {
            return back()
                ->withFragment('time')
                ->with('error', 'Cannot send the time summary — the client name is missing. Edit the project to add it.');
        }

        $timeSummarySubject = 'Your time tracking summary — Zanini Renovations';
        $timeSummaryDescription = 'Time tracking summary — '.$closedCount.' '
            .Str::plural('entry', $closedCount).', '
            .number_format($client_project->totalHours(), 2).' hours total';

        $failure = $this->clientMailer->send(
            $client_project,
            new ClientProjectTimeTrackingSummaryClient($client_project),
            ClientCommunicationLog::REASON_TIME_TRACKING_SUMMARY,
            $timeSummarySubject,
            $timeSummaryDescription,
            'time tracking summary',
        );

        if ($failure !== null) {
            return back()
                ->withFragment('time')
                ->with('error', 'Failed to send the time summary: '.$failure);
        }

        $sendNote = "Time tracking summary emailed to {$client_project->clientEmailRecipientsLabel()}.";

        try {
            $statusService->recordEvent($client_project, $sendNote);
        } catch (\Throwable $e) {
            Log::warning('Failed to record time-tracking-summary history entry', [
                'project_id' => $client_project->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('time')
            ->with('success', $sendNote);
    }

    /**
     * Full-screen calendar view: job spans (start + end) and on-site visit
     * days (visit_date). Rejected projects are hidden.
     */
    public function calendar()
    {
        return view('admin.client_projects.calendar', [
            'events' => $this->calendarService->fullCalendarEvents(),
            'statusLabels' => ClientProject::statusLabels(),
            'statusColors' => ClientProject::statusColors(),
            'personalEventColor' => AdminCalendarEvent::CALENDAR_COLOR,
            'personalEventStoreUrl' => route('admin.calendar.personal-events.store'),
            'personalEventUpdateUrlTemplate' => route('admin.calendar.personal-events.update', ['admin_calendar_event' => '__ID__']),
            'personalEventDestroyUrlTemplate' => route('admin.calendar.personal-events.destroy', ['admin_calendar_event' => '__ID__']),
        ]);
    }

    /**
     * When a directory client is selected but contact fields were left blank
     * (e.g. the Tom Select autofill JS did not run), copy them from the client
     * record before validation so create/update can proceed.
     */
    private function hydrateClientContactFromDirectory(Request $request): void
    {
        $clientId = (int) $request->input('client_id', 0);
        if ($clientId <= 0) {
            return;
        }

        $client = Client::query()->find($clientId);
        if (! $client) {
            return;
        }

        $merge = [];
        if (! filled(trim((string) $request->input('client_name', '')))) {
            $merge['client_name'] = $client->name;
        }
        if (! filled(trim((string) $request->input('client_email', ''))) && filled($client->email)) {
            $merge['client_email'] = $client->email;
        }
        if (! filled(trim((string) $request->input('client_email_secondary', ''))) && filled($client->email_secondary)) {
            $merge['client_email_secondary'] = $client->email_secondary;
        }
        if (! filled(trim((string) $request->input('client_phone', ''))) && filled($client->phone)) {
            $merge['client_phone'] = $client->phone;
        }
        if (! filled(trim((string) $request->input('client_address', ''))) && filled($client->address)) {
            $merge['client_address'] = $client->address;
            $merge['client_address_place_id'] = $client->address_place_id;
            $merge['client_address_latitude'] = $client->address_latitude;
            $merge['client_address_longitude'] = $client->address_longitude;
        }

        if ($merge !== []) {
            $request->merge($merge);
        }
    }

    /** @return array<string, mixed>|null */
    private function initialClientForPicker(?int $clientId): ?array
    {
        if ($clientId === null || $clientId <= 0) {
            return null;
        }

        $client = Client::query()->find($clientId);

        return $client?->toPickerOption();
    }
}
