@extends('admin.layouts.app')

@section('heading', 'Projects')

@section('content')
  @php
    $toggleQueryBase = fn (string $listMode) => array_merge(
        \Illuminate\Support\Arr::except(request()->query(), ['page']),
        ['list' => $listMode],
    );
    $isOngoingList = (($filters['list'] ?? 'ongoing') === 'ongoing');
  @endphp

  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage every project from quote request to completion. Status changes and emails are logged automatically.</p>
    <a href="{{ route('admin.client-projects.create') }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  <div class="cp-projects-scope-bar">
    <div class="cp-projects-scope-head">
      <span class="cp-projects-scope-label">Show</span>
      <div class="cp-projects-scope-toggles">
        <a href="{{ route('admin.client-projects.index', $toggleQueryBase('ongoing')) }}"
           class="cp-projects-scope-chip {{ $isOngoingList ? 'cp-projects-scope-chip--active' : '' }}"
           aria-current="{{ $isOngoingList ? 'page' : 'false' }}"
           title="Hide Completed and Rejected (focus on work in motion).">
          <i class="fa fa-spinner"></i> Ongoing pipeline
        </a>
        <a href="{{ route('admin.client-projects.index', $toggleQueryBase('all')) }}"
           class="cp-projects-scope-chip {{ ! $isOngoingList ? 'cp-projects-scope-chip--active' : '' }}"
           aria-current="{{ ! $isOngoingList ? 'page' : 'false' }}"
           title="Include Completed and Rejected with every other status.">
          <i class="fa fa-th-large"></i> All projects
        </a>
      </div>
    </div>
    <p class="cp-projects-scope-hint">
      <i class="fa fa-filter"></i> By default Completed and Rejected are hidden. Use All projects or the Status filter below to surface them.
    </p>
  </div>

  <form method="get" action="{{ route('admin.client-projects.index') }}" class="cp-filters">
    <input type="hidden" name="list" value="{{ $filters['list'] ?? 'ongoing' }}">
    <div class="cp-filter">
      <label for="cp-filter-q">Search</label>
      <input class="admin-input" type="text" id="cp-filter-q" name="q" value="{{ $filters['q'] }}" placeholder="Project or client name">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-status">Status</label>
      <select class="admin-input" id="cp-filter-status" name="status">
        <option value="">All statuses</option>
        @foreach ($statusLabels as $key => $label)
          <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="cp-filter">
      <label for="cp-filter-from">From</label>
      <input class="admin-input" type="date" id="cp-filter-from" name="from" value="{{ $filters['from'] }}">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-to">To</label>
      <input class="admin-input" type="date" id="cp-filter-to" name="to" value="{{ $filters['to'] }}">
    </div>
    <div class="cp-filter cp-filter--actions">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
      <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
    </div>
  </form>

  @if ($projects->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>{{ (($filters['list'] ?? 'ongoing') === 'ongoing') && ($filters['status'] ?? '') === ''
          ? 'No ongoing pipeline projects match these filters. Try All projects or adjust search and dates.'
          : 'No projects match these filters.' }}</p>
        <p class="admin-form-help" style="margin-top:12px;">
          @if (($filters['list'] ?? 'ongoing') === 'ongoing' && ($filters['status'] ?? '') === '')
            Completed and rejected are hidden in the default view.&nbsp;<a href="{{ route('admin.client-projects.index', $toggleQueryBase('all')) }}">Show all projects</a>
            &nbsp;
          @endif
          <a href="{{ route('admin.client-projects.create') }}">Create a new project</a>
        </p>
      </div>
    </div>
  @else
    <div class="admin-table-wrap">
      <table class="admin-table cp-table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Project</th>
            <th>Status</th>
            <th>Dates</th>
            <th class="cp-col-amount">Total</th>
            <th class="cp-col-amount">Paid</th>
            <th>Last updated</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($projects as $project)
            @php $url = route('admin.client-projects.show', $project); @endphp
            <tr class="cp-row-link" data-href="{{ $url }}" tabindex="0" role="link" aria-label="Open project {{ $project->name }}">
              <td>
                <strong>{{ $project->client_name }}</strong>
                @if ($project->client_email)
                  <div class="cp-subtle">{{ $project->client_email }}</div>
                @endif
              </td>
              <td>
                {{ $project->name }}
                @include('admin.client_projects._contacted_via_display', ['project' => $project])
              </td>
              <td>
                <span class="cp-badge" style="background: {{ $statusColors[$project->status] ?? '#6b7280' }}20; color: {{ $statusColors[$project->status] ?? '#6b7280' }};">
                  {{ $statusLabels[$project->status] ?? $project->status }}
                </span>
              </td>
              <td>
                @if ($project->showsVisitLayoutInProjectList())
                  @if ($project->showsVisitRequiredInProjectList())
                    <div class="cp-subtle">Visit required</div>
                  @else
                    <div>{{ \App\Models\ClientProject::formatDate($project->visit_date) }}</div>
                    <div class="cp-subtle">Visit</div>
                  @endif
                @else
                  <div>{{ \App\Models\ClientProject::formatDate($project->start_date) }}</div>
                  <div class="cp-subtle">→ {{ \App\Models\ClientProject::formatDate($project->end_date) }}</div>
                @endif
              </td>
              <td class="cp-col-amount">
                @if ($project->isHourly())
                  @php
                    $hourlyTotal = round((float) ($project->hours_logged ?? 0) * (float) ($project->hourly_rate ?? 0), 2);
                  @endphp
                  {{ \App\Models\ClientProject::formatMoney($hourlyTotal) }}
                  <div class="cp-subtle">{{ number_format((float) ($project->hours_logged ?? 0), 2) }} h @ {{ \App\Models\ClientProject::formatMoney((float) ($project->hourly_rate ?? 0)) }}/h</div>
                @else
                  {{ \App\Models\ClientProject::formatMoney($project->total_amount) }}
                @endif
              </td>
              <td class="cp-col-amount">{{ number_format($project->percentPaid(), 0) }}%</td>
              <td>{{ \App\Models\ClientProject::formatDateTime($project->updated_at) }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="admin-pagination">
      {{ $projects->links('pagination.admin') }}
    </div>
  @endif

  {{-- Row-as-link behaviour. <tr> can't legally wrap an <a>, so we navigate
       in JS. Plain clicks open in the same tab; Ctrl/Cmd-click and middle-click
       open in a new tab to match standard link behaviour. --}}
  <script>
    (function () {
      var rows = document.querySelectorAll('.cp-row-link[data-href]');
      if (!rows.length) return;

      function shouldIgnore(target) {
        // Don't hijack clicks on real interactive elements inside the row
        // (links, buttons, inputs, selects). Today there are none, but this
        // keeps the row safe if any get added later.
        return !!target.closest('a, button, input, select, textarea, label');
      }

      rows.forEach(function (row) {
        var url = row.getAttribute('data-href');

        row.addEventListener('click', function (ev) {
          if (shouldIgnore(ev.target)) return;
          if (ev.ctrlKey || ev.metaKey || ev.shiftKey) {
            window.open(url, '_blank');
          } else {
            window.location.href = url;
          }
        });

        // Middle-click (auxclick, button === 1) → open in new tab.
        row.addEventListener('auxclick', function (ev) {
          if (ev.button !== 1 || shouldIgnore(ev.target)) return;
          ev.preventDefault();
          window.open(url, '_blank');
        });

        // Keyboard: Enter / Space activates the row when it has focus.
        row.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter' && ev.key !== ' ') return;
          if (shouldIgnore(ev.target)) return;
          ev.preventDefault();
          window.location.href = url;
        });
      });
    })();
  </script>
@endsection
