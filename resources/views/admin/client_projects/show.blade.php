@extends('admin.layouts.app')

@section('heading', $project->name)

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
  <link rel="stylesheet" href="{{ asset('css/admin-vehicles.css') }}?v={{ @filemtime(public_path('css/admin-vehicles.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $statusLabels = \App\Models\ClientProject::statusLabels();
    $statusColors = \App\Models\ClientProject::statusColors();
    $isCompleted = $project->isCompleted();
    $isRejected = $project->isRejected();
    $isAccepted = $project->isAccepted();
  @endphp

  {{-- HEADER / STATUS BAR -------------------------------------------------- --}}
  <div class="admin-card cp-header-card">
    <div class="cp-header-row">
      <div class="cp-header-title">
        <div class="cp-client">{{ $project->client_name }}</div>
        @if ($project->usesClientSnapshot())
          <p class="admin-form-help" style="margin:4px 0 0;">
            <i class="fa fa-lock" aria-hidden="true"></i>
            Client information as of completion
            @if ($project->clientSnapshotCapturedLabel())
              ({{ $project->clientSnapshotCapturedLabel() }})
            @endif
          </p>
        @endif
        <h2>{{ $project->name }}</h2>
        <div class="cp-header-contact-block">
          @if (filled($project->notes))
            @php
              $notesPreview = \Illuminate\Support\Str::limit(trim((string) $project->notes), 800);
            @endphp
            <span class="cp-notes-popover-wrap">
              <button type="button"
                      class="cp-notes-indicator cp-notes-indicator--btn"
                      id="cp-notes-toggle"
                      aria-expanded="false"
                      aria-controls="cp-notes-popover">
                <i class="fa fa-sticky-note-o" aria-hidden="true"></i> Notes
              </button>
              <div class="cp-notes-popover" id="cp-notes-popover" role="dialog" aria-label="Project notes preview" hidden>
                <div class="cp-notes-popover-body">{{ $notesPreview }}</div>
              </div>
            </span>
          @endif
          <div class="cp-header-contact-via-block">
            @include('admin.client_projects._contacted_via_display', ['project' => $project, 'lineClass' => 'cp-contacted-via-line'])
          </div>
        </div>
        <div class="cp-header-meta">
          <span class="cp-billing-type" title="How this project is billed">
            @if ($project->isHourly())
              <i class="fa fa-clock-o" aria-hidden="true"></i> Hourly rate
            @else
              <i class="fa fa-tag" aria-hidden="true"></i> Fixed price
            @endif
          </span>
          @if ($project->client_phone)
            <span><i class="fa fa-phone"></i> {{ \App\Models\ClientProject::formatPhone($project->client_phone) }}</span>
          @endif
          @if ($project->client_email)
            <span><i class="fa fa-envelope"></i> <a href="mailto:{{ $project->client_email }}">{{ $project->client_email }}</a></span>
          @endif
          @if ($project->secondaryClientEmail())
            <span title="Secondary email — CC'd on every client email"><i class="fa fa-envelope-o"></i> <a href="mailto:{{ $project->secondaryClientEmail() }}">{{ $project->secondaryClientEmail() }}</a></span>
          @endif
          @if ($project->client_address)
            <span>
              <i class="fa fa-map-marker"></i>
              <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($project->client_address) }}"
                 target="_blank"
                 rel="noopener noreferrer"
                 title="Open in Google Maps">{{ $project->client_address }}</a>
            </span>
          @endif
        </div>
      </div>
      <div class="cp-header-status">
        <span class="cp-badge cp-badge--lg" style="background: {{ $project->statusColor() }}20; color: {{ $project->statusColor() }};">
          {{ $project->statusLabel() }}
        </span>
        <div class="cp-updated">Updated {{ \App\Models\ClientProject::formatDateTime($project->updated_at) }}</div>
      </div>
    </div>

    @if ($project->photos->isNotEmpty())
      @include('admin.client_projects._project_photos_gallery', [
        'project' => $project,
        'galleryId' => 'cp-show-project-photos',
        'editable' => false,
      ])
    @endif

    @unless ($isRejected)
      {{-- Status form: label + help icon on one line, then a row of
           controls (select | save). The Save button remains in markup
           for no-JS fallback and is hidden when auto-submit is active. --}}
      <form action="{{ route('admin.client-projects.status.update', $project) }}" method="post" class="cp-status-form" id="cp-status-form" data-current-status="{{ $project->status }}">
        @csrf
        <div class="cp-status-form-head">
          <label for="cp-status-select" class="cp-status-form-label">Change status</label>
          <span class="cp-hint" title="Workflow: Visit required → Visit scheduled → Quote Pending → Quote Sent → Proposal Accepted → Pending First Payment → Scheduled → In Progress → Awaiting Final Payment → Completed. Time tracking, quotation, contract, invoice, and financials unlock from Proposal Accepted onward.">?</span>
          <span class="cp-status-form-help">Pick a status to save it instantly.</span>
        </div>
        <div class="cp-status-controls">
          <select id="cp-status-select" name="status" class="admin-input cp-status-select">
            @foreach ($statusLabels as $key => $label)
              <option value="{{ $key }}" @selected($project->status === $key)>{{ $label }}</option>
            @endforeach
          </select>
          {{-- The Save button stays for the no-JS fallback; the auto-submit
               script below adds .cp-status-form--auto to the form which
               hides it (see admin-client-projects.css). --}}
          <button type="submit" class="admin-btn admin-btn--primary cp-status-save">
            <i class="fa fa-save"></i> Save status
          </button>
        </div>
      </form>
      <script>
        (function () {
          var form = document.getElementById('cp-status-form');
          if (!form) return;
          var select = form.querySelector('#cp-status-select');
          if (!select) return;

          // Hide the Save button now that we know JS is on.
          form.classList.add('cp-status-form--auto');

          var submitting = false;
          select.addEventListener('change', function () {
            if (submitting) return;
            if (select.value === form.getAttribute('data-current-status')) return;

            submitting = true;
            select.disabled = true;
            // Re-add the disabled select's value so it still posts.
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'status';
            hidden.value = select.value;
            form.appendChild(hidden);
            form.submit();
          });
        })();
      </script>
      @if (filled($project->notes))
        <script>
          (function () {
            var toggle = document.getElementById('cp-notes-toggle');
            var popover = document.getElementById('cp-notes-popover');
            if (!toggle || !popover) return;

            function closePopover() {
              toggle.setAttribute('aria-expanded', 'false');
              popover.hidden = true;
            }

            function openPopover() {
              toggle.setAttribute('aria-expanded', 'true');
              popover.hidden = false;
            }

            toggle.addEventListener('click', function (e) {
              e.preventDefault();
              if (popover.hidden) {
                openPopover();
              } else {
                closePopover();
              }
            });

            document.addEventListener('click', function (e) {
              if (popover.hidden) return;
              if (toggle.contains(e.target) || popover.contains(e.target)) return;
              closePopover();
            });

            document.addEventListener('keydown', function (e) {
              if (e.key === 'Escape') closePopover();
            });
          })();
        </script>
      @endif
    @else
      <p class="cp-final-note"><i class="fa fa-ban"></i> This project is marked as <strong>Rejected</strong>. No further status changes are possible.</p>
    @endunless

    <div class="cp-header-actions">
      <a href="{{ route('admin.client-projects.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit details
      </a>
      @if ($calendarLink)
        <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="cp-calendar-link cp-calendar-link--btn">
          <i class="fa fa-calendar" aria-hidden="true"></i>
          <span>Add to Google Calendar</span>
        </a>
      @endif
    </div>
  </div>

  {{-- TABS ---------------------------------------------------------------- --}}
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
    <button type="button" class="cp-tab" data-tab="quotation" role="tab">Quotation</button>
    <button type="button" class="cp-tab" data-tab="contract" role="tab">Contract</button>
    <button type="button" class="cp-tab" data-tab="invoice" role="tab">Invoice</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
    <button type="button" class="cp-tab" data-tab="history" role="tab">History</button>
  </div>

  {{-- OVERVIEW TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel is-active" data-panel="overview">
    @if ($isCompleted)
      @include('admin.client_projects._final_summary', ['project' => $project])
    @endif

    @include('admin.client_projects._travel_cost', ['project' => $project])

    <div class="admin-card">
      <h3>Details</h3>
      <dl class="cp-dl">
        <dt>Project name</dt><dd>{{ $project->name }}</dd>
        <dt>Client</dt><dd>{{ $project->client_name }}</dd>
        <dt>Email</dt><dd>{{ $project->client_email ?: '—' }}</dd>
        <dt>Secondary email</dt><dd>{{ $project->secondaryClientEmail() ?: '—' }}</dd>
        <dt>Phone</dt><dd>{{ \App\Models\ClientProject::formatPhone($project->client_phone) ?: '—' }}</dd>
        <dt>Address</dt><dd>{{ $project->client_address ?: '—' }}</dd>
        <dt>Created</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->created_at) }}</dd>
        <dt>Status changed</dt><dd>{{ \App\Models\ClientProject::formatDateTime($project->status_changed_at) }}</dd>
        @if ($project->visit_required || $project->visit_date || $project->visit_done)
          <dt>Visit</dt>
          <dd>
            @if ($project->visit_done)
              Done
              @if ($project->visit_date)
                ({{ \App\Models\ClientProject::formatDate($project->visit_date) }})
              @endif
            @elseif ($project->visit_required)
              {{ $project->visit_date ? \App\Models\ClientProject::formatDate($project->visit_date) : 'Required — date not set yet' }}
            @elseif ($project->visit_date)
              {{ \App\Models\ClientProject::formatDate($project->visit_date) }}
            @endif
          </dd>
        @endif
      </dl>

      @if ($project->notes)
        <h3 style="margin-top:24px;">Notes</h3>
        <div class="cp-notes-box">{{ $project->notes }}</div>
      @endif
    </div>
  </section>

  {{-- SCHEDULE / CALENDAR TAB ---------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="schedule" id="schedule">
    @php
      $datesSet = $project->start_date && $project->end_date;
      $hasContact = $project->hasClientContactInfo();
      $canSendDates = !$isRejected && $datesSet && $hasContact;
      $lastSentDates = \App\Models\ClientCommunicationLog::query()
        ->where('client_project_id', $project->id)
        ->where('reason', \App\Models\ClientCommunicationLog::REASON_SCHEDULE_DATES_MANUAL)
        ->where('status', \App\Models\ClientCommunicationLog::STATUS_SENT)
        ->latest('sent_at')
        ->first();
      $lastSentRecipients = $lastSentDates
        ? trim($lastSentDates->recipient.($lastSentDates->cc ? ', '.$lastSentDates->cc : ''), ', ')
        : null;
      $sendDatesConfirm = $lastSentDates
        ? 'Re-send the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?'
        : 'Email the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?';
    @endphp

    <form id="cp-schedule-dates-form" action="{{ route('admin.client-projects.update', $project) }}" method="post" class="cp-schedule-form">
      @csrf
      @method('PUT')
      {{-- Include required fields (controller validates the full update payload). --}}
      <input type="hidden" name="name" value="{{ $project->name }}">
      <input type="hidden" name="client_name" value="{{ $project->client_name }}">
      <input type="hidden" name="client_email" value="{{ $project->client_email }}">
      <input type="hidden" name="client_phone" value="{{ $project->client_phone }}">
      <input type="hidden" name="client_address" value="{{ $project->client_address }}">
      <input type="hidden" name="notes" value="{{ $project->notes }}">

      @php
        $cpShowVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
        $cpShowVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
          || (bool) old('visit_done', $project->visit_done)
          || filled($cpShowVisitDateVal)
          || $errors->has('visit_date');
      @endphp
      <div class="admin-card cp-visit-card cp-visit-section">
        @include('admin.client_projects._visit_required_toggle', [
          'visitRequiredId' => 'cp-visit-required',
          'visitRequiredChecked' => old('visit_required', $project->visit_required),
          'expandableId' => 'cp-schedule-visit-expandable',
          'visitDateId' => 'cp-visit-date',
          'visitDateValue' => $cpShowVisitDateVal,
          'expandOpen' => $cpShowVisitExpandOpen,
          'showVisitDone' => true,
          'visitDoneId' => 'cp-visit-done',
          'visitDoneChecked' => old('visit_done', $project->visit_done),
        ])
      </div>

      <div class="admin-card">
        <h3>Start &amp; End dates</h3>
        <p class="admin-form-help">Saving both dates schedules the project on the Project Calendar and emails you a Google Calendar link.</p>
        <div id="cp-schedule-date-warning" class="admin-flash admin-flash--warning admin-flash--js-hidden" role="alert"></div>

        <div class="cp-schedule-pick-row">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="cp-open-date-picker">
            <i class="fa fa-calendar"></i> Pick on calendar
          </button>
          <span class="admin-form-help cp-schedule-pick-hint">See other projects' booked days and click two days (start, then end) to fill the dates below.</span>
        </div>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start">Start date</label>
            <input class="admin-input" type="date" id="cp-start" name="start_date" value="{{ old('start_date', optional($project->start_date)->toDateString()) }}">
            @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-end">End date</label>
            <input class="admin-input" type="date" id="cp-end" name="end_date" value="{{ old('end_date', optional($project->end_date)->toDateString()) }}">
            @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">Same-day start &amp; end is allowed.</p>
          </div>
        </div>

        @if ($project->start_date && $project->end_date)
          <div class="cp-schedule-summary cp-schedule-summary--with-action">
            <span class="cp-schedule-summary__dates">
              <strong>Scheduled:</strong>
              {{ \App\Models\ClientProject::formatDate($project->start_date) }}
              <span class="cp-schedule-summary__arrow" aria-hidden="true">→</span>
              {{ \App\Models\ClientProject::formatDate($project->end_date) }}
            </span>
            @if ($calendarLink)
              <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="cp-calendar-link">
                <i class="fa fa-calendar" aria-hidden="true"></i>
                <span>Add to Google Calendar</span>
              </a>
            @endif
          </div>
        @endif

        @unless ($isRejected)
          @if (!$datesSet)
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Save both dates first to enable <strong>Send dates to client</strong>.
            </p>
          @elseif (!$hasContact)
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Missing client contact info: {{ implode(', ', $project->missingClientContactFields()) }}.
              <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project details</a> to fix.
            </p>
          @endif

          @if ($lastSentDates)
            <p class="admin-form-help cp-send-dates-note">
              <i class="fa fa-check-circle" style="color:#10b981;"></i>
              Dates last emailed on
              <strong>{{ \App\Models\ClientProject::formatDateTime($lastSentDates->sent_at) }}</strong>
              to {{ $lastSentRecipients ?: $project->clientEmailRecipientsLabel() }}.
            </p>
          @endif
        @endunless

        <div class="cp-schedule-actions">
          <button type="submit" class="admin-btn admin-btn--primary">Save dates</button>
          @unless ($isRejected)
            {{-- This button lives in the dates card but submits the small
                 #cp-send-dates-form below via the HTML5 `form=` attribute. --}}
            <button type="submit"
                    form="cp-send-dates-form"
                    class="admin-btn admin-btn--primary cp-send-dates-btn"
                    @disabled(!$canSendDates)
                    title="Email {{ $project->clientEmailRecipientsLabel() ?: 'the client' }} the scheduled dates.">
              <i class="fa fa-paper-plane"></i>
              {{ $lastSentDates ? 'Re-send dates to client' : 'Send dates to client' }}
            </button>
          @endunless
        </div>
      </div>
    </form>

    {{-- Standalone form for the "Send dates to client" button placed inside
         the dates card above (referenced via `form="cp-send-dates-form"`).
         Kept separate so its POST doesn't piggyback on the project-update
         payload. --}}
    @unless ($isRejected)
      <form id="cp-send-dates-form"
            action="{{ route('admin.client-projects.schedule-dates.send', $project) }}"
            method="post"
            class="cp-send-dates-form"
            onsubmit="return confirm('{{ $sendDatesConfirm }}');">
        @csrf
      </form>
    @endunless

    {{-- Hourly-only: manual "service confirmation" email. The rate was
         agreed verbally up front, so the client doesn't get the automatic
         pre-quote acknowledgement — instead Jonas sends this confirmation once
         the dates are locked in. --}}
    @if ($project->billing_type === \App\Models\ClientProject::BILLING_HOURLY && !$isRejected)
      <div class="admin-card cp-service-confirmation-card">
        <h3>Service confirmation</h3>
        <p class="admin-form-help">
          Hourly-billing projects skip the quotation flow because the rate was agreed verbally.
          Once the dates are saved above, send the client a confirmation email with the scheduled
          {{ $project->start_date && $project->end_date && \Illuminate\Support\Carbon::parse($project->start_date)->isSameDay(\Illuminate\Support\Carbon::parse($project->end_date)) ? 'date' : 'dates' }},
          hourly rate, materials policy, and payment instructions.
        </p>

        @php
          $datesSet = $project->start_date && $project->end_date;
          $hasContact = $project->hasClientContactInfo();
          $alreadySent = (bool) $project->service_confirmation_sent_at;
          $canSend = $datesSet && $hasContact;
        @endphp

        @if (!$datesSet)
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Save both the start and end dates before you can send the confirmation.
          </p>
        @elseif (!$hasContact)
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Missing client contact info: {{ implode(', ', $project->missingClientContactFields()) }}.
            <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project details</a> to fix.
          </p>
        @endif

        @if ($alreadySent)
          <p class="admin-form-help">
            <i class="fa fa-check-circle" style="color:#10b981;"></i>
            Last sent on
            <strong>{{ \App\Models\ClientProject::formatDateTime($project->service_confirmation_sent_at) }}</strong>.
          </p>
        @endif

        <form action="{{ route('admin.client-projects.service-confirmation.send', $project) }}"
              method="post"
              class="cp-schedule-form"
              onsubmit="return confirm('{{ $alreadySent ? 'Re-send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?' : 'Send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?' }}');">
          @csrf
          <button type="submit"
                  class="admin-btn admin-btn--primary"
                  @disabled(!$canSend)>
            <i class="fa fa-paper-plane"></i>
            {{ $alreadySent ? 'Re-send service confirmation' : 'Send service confirmation' }}
          </button>
        </form>
      </div>
    @endif

    {{-- DATE-PICKER MODAL: shows other projects' booked days so the admin
         can pick a non-overlapping start/end. Lazy-initialised on first open. --}}
    <div class="cp-modal-backdrop cp-date-picker-backdrop" id="cp-date-picker-modal" hidden>
      <div class="cp-modal cp-date-picker-modal" role="dialog" aria-labelledby="cp-date-picker-title" aria-modal="true">
        <div class="cp-modal-header">
          <h3 id="cp-date-picker-title">Pick start &amp; end dates</h3>
          <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="cp-modal-body cp-date-picker-body">
          <div class="cp-date-picker-selection">
            <div>
              <span class="cp-date-picker-selection-label">Start</span>
              <strong id="cp-date-picker-start">—</strong>
            </div>
            <span class="cp-date-picker-selection-arrow" aria-hidden="true">→</span>
            <div>
              <span class="cp-date-picker-selection-label">End</span>
              <strong id="cp-date-picker-end">—</strong>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-date-picker-clear" id="cp-date-picker-clear">
              Clear
            </button>
          </div>
          <div id="cp-date-picker-conflict" class="admin-flash admin-flash--warning cp-date-picker-conflict admin-flash--js-hidden" role="alert"></div>
          <div id="cp-date-picker-calendar" class="cp-date-picker-calendar"></div>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-date-picker-confirm" disabled>
            <i class="fa fa-check"></i> Use these dates
          </button>
        </div>
      </div>
    </div>
  </section>

  {{-- TIME TRACKING TAB --------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="time" id="time">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Time tracking is locked</h3>
        <p>Time entries become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._time', ['project' => $project])
      @include('admin.client_projects._helper_time', ['project' => $project, 'activeHelpers' => $activeHelpers ?? collect()])
    @endif
  </section>

  {{-- QUOTATION TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="quotation" id="quotation">
    @include('admin.client_projects._quotation', ['project' => $project])
  </section>

  {{-- CONTRACT TAB -------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="contract" id="contract">
    @include('admin.client_projects._contract', ['project' => $project])
  </section>

  {{-- INVOICE TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="invoice" id="invoice">
    @include('admin.client_projects._invoice', ['project' => $project])
  </section>

  {{-- FINANCIALS TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="financials" id="financials">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Financials are locked</h3>
        <p>Financial details become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._financials', ['project' => $project])
    @endif
  </section>

  {{-- HISTORY TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="history">
    <div class="admin-card">
      <h3>Status history</h3>
      @if ($project->statusHistory->isEmpty())
        <p class="admin-form-help">No history yet.</p>
      @else
        <div class="admin-table-scroll">
        <table class="admin-table cp-history-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Event</th>
              <th>Note</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($project->statusHistory as $entry)
              <tr>
                <td>{{ $entry->changedAtDisplay() }}</td>
                <td>
                  @if ($entry->isEvent())
                    <span class="cp-badge" style="background:#e5e7eb;color:#4b5563;">Event</span>
                  @else
                    <span class="cp-badge" style="background:#dbeafe;color:#1e40af;">
                      {{ $entry->fromLabel() }} → {{ $entry->toLabel() }}
                    </span>
                  @endif
                </td>
                <td>{{ $entry->note ?: '—' }}</td>
                <td>{{ $entry->user?->email ?? 'system' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
        </div>
      @endif
    </div>
  </section>

  {{-- TAB SCRIPT ---------------------------------------------------------- --}}
  <script>
    (function () {
      var tabs = document.querySelectorAll('.cp-tab');
      var panels = document.querySelectorAll('.cp-tab-panel');
      if (!tabs.length) return;

      function show(key) {
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-tab') === key);
        });
        panels.forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        // remember last tab for a refresh within the same page session
        try { sessionStorage.setItem('cpActiveTab-{{ $project->id }}', key); } catch (e) {}
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
      });

      // Priority: explicit URL hash (e.g. /show#contract) wins over the
      // session-remembered tab. This lets the controller redirect a user
      // back to the right tab after a save/email action.
      var hashTab = (window.location.hash || '').replace('#', '');
      if (hashTab && document.querySelector('.cp-tab[data-tab="' + hashTab + '"]')) {
        show(hashTab);
      } else {
        try {
          var saved = sessionStorage.getItem('cpActiveTab-{{ $project->id }}');
          if (saved) show(saved);
        } catch (e) {}
      }
    })();
  </script>
  <script>
    (function () {
      var form = document.getElementById('cp-schedule-dates-form');
      if (!form) return;
      var start = form.querySelector('[name="start_date"]');
      var end = form.querySelector('[name="end_date"]');
      var warn = document.getElementById('cp-schedule-date-warning');
      var visitCb = document.getElementById('cp-visit-required');
      var visitDate = document.getElementById('cp-visit-date');
      var visitDoneCb = document.getElementById('cp-visit-done');
      var visitExpand = document.getElementById('cp-schedule-visit-expandable');

      function syncVisitExpandable() {
        if (!visitExpand) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        var open = required || done;
        visitExpand.classList.toggle('is-open', open);
        visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function syncVisitFields() {
        if (!visitDate) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        if (!required && !done) {
          visitDate.value = '';
        }
        visitDate.readOnly = !!done;
        visitDate.disabled = !done && !required;
        visitDate.classList.toggle('cp-visit-date-input--locked', !!done || !required);
        syncVisitExpandable();
      }

      function checkScheduleDates() {
        if (!start || !end || !warn) return true;
        if (!start.value || !end.value) {
          warn.classList.add('admin-flash--js-hidden');
          warn.textContent = '';
          return true;
        }
        if (start.value> end.value) {
          warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
          warn.classList.remove('admin-flash--js-hidden');
          return false;
        }
        warn.classList.add('admin-flash--js-hidden');
        warn.textContent = '';
        return true;
      }

      if (start && end && warn) {
        start.addEventListener('change', checkScheduleDates);
        end.addEventListener('change', checkScheduleDates);
      }
      if (visitCb) visitCb.addEventListener('change', syncVisitFields);
      if (visitDoneCb) visitDoneCb.addEventListener('change', syncVisitFields);
      if (visitDate) visitDate.addEventListener('change', syncVisitExpandable);
      if (visitDate) visitDate.addEventListener('input', syncVisitExpandable);
      syncVisitFields();

      form.addEventListener('submit', function (e) {
        if (visitDate && visitCb && !visitCb.checked && !(visitDoneCb && visitDoneCb.checked)) {
          visitDate.disabled = false;
          visitDate.readOnly = false;
          visitDate.value = '';
        }
        if (!checkScheduleDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>

  {{-- Date-picker modal (Calendar tab) ----------------------------------- --}}
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    (function () {
      var openBtn = document.getElementById('cp-open-date-picker');
      var modal = document.getElementById('cp-date-picker-modal');
      if (!openBtn || !modal) return;

      var calendarEl = document.getElementById('cp-date-picker-calendar');
      var startLabel = document.getElementById('cp-date-picker-start');
      var endLabel = document.getElementById('cp-date-picker-end');
      var clearBtn = document.getElementById('cp-date-picker-clear');
      var confirmBtn = document.getElementById('cp-date-picker-confirm');
      var conflictBox = document.getElementById('cp-date-picker-conflict');
      var startInput = document.getElementById('cp-start');
      var endInput = document.getElementById('cp-end');

      var BUSY_EVENTS = @json($scheduleEvents);
      var calendar = null;
      var selection = { start: null, end: null };

      function fmtIso(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
      }
      function parseIso(iso) {
        // Build a local-date (avoid UTC drift you'd get from `new Date('2026-06-10')`).
        var parts = iso.split('-');
        return new Date(+parts[0], +parts[1] - 1, +parts[2]);
      }
      function fmtDisplay(iso) {
        if (!iso) return '—';
        var d = parseIso(iso);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
      }
      function addDaysIso(iso, days) {
        var d = parseIso(iso);
        d.setDate(d.getDate() + days);
        return fmtIso(d);
      }

      function rangeOverlapsBusy(startIso, endIso) {
        if (!startIso || !endIso) return null;
        var startMs = parseIso(startIso).getTime();
        var endMs = parseIso(endIso).getTime();
        for (var i = 0; i < BUSY_EVENTS.length; i++) {
          var ev = BUSY_EVENTS[i];
          var evStart = parseIso(ev.start).getTime();
          // Stored end is exclusive (FullCalendar all-day), make it inclusive.
          var evEnd = parseIso(addDaysIso(ev.end, -1)).getTime();
          if (startMs <= evEnd && endMs>= evStart) {
            return ev;
          }
        }
        return null;
      }

      function refreshSelectionDisplay() {
        startLabel.textContent = fmtDisplay(selection.start);
        endLabel.textContent = fmtDisplay(selection.end);
        confirmBtn.disabled = !(selection.start && selection.end);

        if (calendar) {
          var sources = calendar.getEventSources();
          for (var i = 0; i < sources.length; i++) {
            if (sources[i].id === 'cp-selection') sources[i].remove();
          }
          if (selection.start && selection.end) {
            calendar.addEventSource({
              id: 'cp-selection',
              events: [{
                id: 'cp-selection',
                start: selection.start,
                end: addDaysIso(selection.end, 1),
                allDay: true,
                display: 'background',
                color: '#2563eb',
              }]
            });
          }
        }

        var conflict = rangeOverlapsBusy(selection.start, selection.end);
        if (conflict) {
          conflictBox.classList.remove('admin-flash--js-hidden');
          conflictBox.innerHTML = 'This range overlaps <strong>' +
            (conflict.title || 'another project') + '</strong>';
        } else {
          conflictBox.classList.add('admin-flash--js-hidden');
          conflictBox.textContent = '';
        }
      }

      function setSelection(startIso, endIso) {
        if (startIso && endIso && startIso> endIso) {
          var tmp = startIso; startIso = endIso; endIso = tmp;
        }
        selection.start = startIso || null;
        selection.end = endIso || null;
        refreshSelectionDisplay();
      }

      function handleDayClick(iso) {
        // No selection yet, or both dates already set → start fresh.
        if (!selection.start || (selection.start && selection.end)) {
          setSelection(iso, null);
          return;
        }
        // Have a start, no end → set the end (auto-swap if click is earlier).
        setSelection(selection.start, iso);
      }

      function initCalendar() {
        if (calendar || !calendarEl || typeof FullCalendar === 'undefined') return;

        // Seed initial date from the existing inputs (if any) so the calendar
        // opens on the right month.
        var initialDate = startInput && startInput.value ? startInput.value : new Date();

        calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          initialDate: initialDate,
          firstDay: 0,
          height: 'auto',
          headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
          selectable: true,
          unselectAuto: false,
          longPressDelay: 150,
          eventDisplay: 'block',
          events: BUSY_EVENTS,
          eventDidMount: function (info) {
            if (info.event.extendedProps && info.event.extendedProps.status) {
              info.el.setAttribute('title', info.event.title + ' — ' + info.event.extendedProps.status);
            }
          },
          // We intentionally do NOT use `dateClick` here: with `selectable: true`,
          // a single-day click ALSO fires `select`, and the two handlers race
          // (each overwriting the other's state). Routing everything through
          // `select` keeps a single source of truth.
          select: function (info) {
            // FullCalendar's `end` is exclusive for all-day selections.
            var startIso = fmtIso(info.start);
            var endIso = fmtIso(new Date(info.end.getTime() - 24 * 60 * 60 * 1000));
            if (startIso === endIso) {
              // Single-day click → behaves like the 2-step "click start, click end" flow.
              handleDayClick(startIso);
            } else {
              // Drag across multiple days → take the whole range at once.
              setSelection(startIso, endIso);
            }
            calendar.unselect();
          },
        });
        calendar.render();
      }

      function openModal() {
        // Pre-seed selection from current input values when the modal opens.
        if (startInput && startInput.value && endInput && endInput.value) {
          selection.start = startInput.value;
          selection.end = endInput.value;
        } else if (startInput && startInput.value) {
          selection.start = startInput.value;
          selection.end = null;
        } else {
          selection.start = null;
          selection.end = null;
        }

        modal.hidden = false;
        document.body.classList.add('cp-modal-open');

        // Defer init so the modal has real dimensions when the calendar lays out.
        setTimeout(function () {
          initCalendar();
          if (calendar) {
            if (selection.start) calendar.gotoDate(selection.start);
            calendar.updateSize();
          }
          refreshSelectionDisplay();
        }, 0);
      }

      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
      }

      openBtn.addEventListener('click', openModal);
      modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.matches && e.target.matches('[data-cp-modal-close]'))) {
          closeModal();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
      });

      clearBtn.addEventListener('click', function () {
        setSelection(null, null);
      });

      confirmBtn.addEventListener('click', function () {
        if (!selection.start || !selection.end) return;
        if (startInput) {
          startInput.value = selection.start;
          startInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (endInput) {
          endInput.value = selection.end;
          endInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeModal();
      });
    })();
  </script>
  <script src="{{ asset('js/admin-vehicles.js') }}?v={{ @filemtime(public_path('js/admin-vehicles.js')) ?: time() }}" defer></script>
@endsection
