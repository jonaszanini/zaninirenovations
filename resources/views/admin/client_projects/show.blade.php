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
            <span><i class="fa fa-phone"></i> {{ $project->client_phone }}</span>
          @endif
          @if ($project->client_email)
            <span><i class="fa fa-envelope"></i> <a href="mailto:{{ $project->client_email }}">{{ $project->client_email }}</a></span>
          @endif
          @if ($project->client_address)
            <span><i class="fa fa-map-marker"></i> {{ $project->client_address }}</span>
          @endif
        </div>
      </div>
      <div class="cp-header-status">
        <span class="cp-badge cp-badge--lg" style="background: {{ $project->statusColor() }}20; color: {{ $project->statusColor() }};">
          {{ $project->statusLabel() }}
        </span>
        <div class="cp-updated">Updated {{ optional($project->updated_at)->format('d/m/Y H:i') }}</div>
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
        <span class="cp-hint" title="Proposal Accepted will automatically move the project to In Progress and unlock the financial & time tracking tabs.">?</span>
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
      <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
    </div>
  </div>

  {{-- TABS ---------------------------------------------------------------- --}}
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
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
        <dt>Phone</dt><dd>{{ $project->client_phone ?: '—' }}</dd>
        <dt>Address</dt><dd>{{ $project->client_address ?: '—' }}</dd>
        <dt>Created</dt><dd>{{ $project->created_at->format('d/m/Y H:i') }}</dd>
        <dt>Status changed</dt><dd>{{ $project->status_changed_at ? $project->status_changed_at->format('d/m/Y H:i') : '—' }}</dd>
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
      <form action="{{ route('admin.client-projects.update', $project) }}" method="post" class="cp-schedule-form">
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
            <input type="date" id="cp-start" name="start_date" value="{{ optional($project->start_date)->toDateString() }}">
          </div>
          <div class="admin-form-group">
            <label for="cp-end">End date</label>
            <input type="date" id="cp-end" name="end_date" value="{{ optional($project->end_date)->toDateString() }}">
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
              <th>From</th>
              <th>To</th>
              <th>Note</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($project->statusHistory as $entry)
              <tr>
                <td>{{ $entry->changed_at->format('d/m/Y H:i') }}</td>
                <td>{{ $entry->fromLabel() }}</td>
                <td><strong>{{ $entry->toLabel() }}</strong></td>
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

      try {
        var saved = sessionStorage.getItem('cpActiveTab-{{ $project->id }}');
        if (saved) show(saved);
      } catch (e) {}
    })();
  </script>
@endsection
