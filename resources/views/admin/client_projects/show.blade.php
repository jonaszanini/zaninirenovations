@extends('admin.layouts.app')

@section('heading', $project->name)

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
        <h2>{{ $project->name }}</h2>
        <div class="cp-header-meta">
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

    @unless ($isRejected)
      <form action="{{ route('admin.client-projects.status.update', $project) }}" method="post" class="cp-status-form">
        @csrf
        <label for="cp-status-select">Change status</label>
        <select id="cp-status-select" name="status">
          @foreach ($statusLabels as $key => $label)
            <option value="{{ $key }}" @selected($project->status === $key)>{{ $label }}</option>
          @endforeach
        </select>
        <input type="text" name="note" placeholder="Optional note…" maxlength="2000" class="cp-status-note">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save status</button>
        <span class="cp-hint" title="Workflow: Quote Pending → Quote Sent → Proposal Accepted → Scheduled (contract signed + 1st installment paid, waiting for start date) → In Progress → Completed. Financial & time tracking tabs unlock from Proposal Accepted onward.">?</span>
      </form>
    @else
      <p class="cp-final-note"><i class="fa fa-ban"></i> This project is marked as <strong>Rejected</strong>. No further status changes are possible.</p>
    @endunless

    <div class="cp-header-actions">
      <a href="{{ route('admin.client-projects.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit details
      </a>
      @if ($calendarLink)
        <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-calendar"></i> Add to Google Calendar
        </a>
      @endif
    </div>
  </div>

  {{-- TABS ---------------------------------------------------------------- --}}
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
    <button type="button" class="cp-tab" data-tab="quotation" role="tab">Quotation</button>
    <button type="button" class="cp-tab" data-tab="contract" role="tab">Contract</button>
    <button type="button" class="cp-tab" data-tab="invoice" role="tab">Invoice</button>
    <button type="button" class="cp-tab" data-tab="history" role="tab">History</button>
  </div>

  {{-- OVERVIEW TAB --------------------------------------------------------- --}}
  <section class="cp-tab-panel is-active" data-panel="overview">
    @if ($isCompleted)
      @include('admin.client_projects._final_summary', ['project' => $project])
    @endif

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
      </dl>

      @if ($project->notes)
        <h3 style="margin-top:24px;">Notes</h3>
        <div class="cp-notes-box">{{ $project->notes }}</div>
      @endif
    </div>
  </section>

  {{-- SCHEDULE / CALENDAR TAB ---------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="schedule">
    <div class="admin-card">
      <h3>Start &amp; End dates</h3>
      <p class="admin-form-help">Saving both dates schedules the project on the Project Calendar and emails you a Google Calendar link.</p>
      <div id="cp-schedule-date-warning" class="admin-flash admin-flash--warning" style="display:none;margin-bottom:14px;" role="alert"></div>
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

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start">Start date</label>
            <input type="date" id="cp-start" name="start_date" value="{{ old('start_date', optional($project->start_date)->toDateString()) }}">
            @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group">
            <label for="cp-end">End date</label>
            <input type="date" id="cp-end" name="end_date" value="{{ old('end_date', optional($project->end_date)->toDateString()) }}">
            @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">Same-day start &amp; end is allowed.</p>
          </div>
        </div>

        <button type="submit" class="admin-btn admin-btn--primary">Save dates</button>
      </form>

      @if ($project->start_date && $project->end_date)
        <div class="cp-schedule-summary">
          <strong>Scheduled:</strong>
          {{ \App\Models\ClientProject::formatDate($project->start_date) }}
          →
          {{ \App\Models\ClientProject::formatDate($project->end_date) }}
          @if ($calendarLink)
            ·
            <a href="{{ $calendarLink }}" target="_blank" rel="noopener noreferrer">Add to Google Calendar</a>
          @endif
        </div>
      @endif
    </div>

    {{-- Hourly-only: manual "service confirmation" email. The rate was
         agreed verbally up front, so the client doesn't get the automatic
         quote_pending emails — instead Jonas sends this confirmation once
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
  </section>

  {{-- FINANCIALS TAB ------------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="financials">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Financials are locked</h3>
        <p>Financial details become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._financials', ['project' => $project])
    @endif
  </section>

  {{-- TIME TRACKING TAB --------------------------------------------------- --}}
  <section class="cp-tab-panel" data-panel="time">
    @if (!$isAccepted)
      <div class="admin-card cp-locked">
        <h3>Time tracking is locked</h3>
        <p>Time entries become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    @else
      @include('admin.client_projects._time', ['project' => $project])
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
      if (!start || !end || !warn) return;

      function checkScheduleDates() {
        if (!start.value || !end.value) {
          warn.style.display = 'none';
          warn.textContent = '';
          return true;
        }
        if (start.value > end.value) {
          warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
          warn.style.display = 'block';
          return false;
        }
        warn.style.display = 'none';
        warn.textContent = '';
        return true;
      }

      start.addEventListener('change', checkScheduleDates);
      end.addEventListener('change', checkScheduleDates);
      form.addEventListener('submit', function (e) {
        if (!checkScheduleDates()) {
          e.preventDefault();
          warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
@endsection
