@extends('admin.layouts.app')

@section('heading', 'Projects')

@section('content')
  @php
    $toggleQueryBase = fn (string $listMode) => array_merge(
        \Illuminate\Support\Arr::except(request()->query(), ['page']),
        ['list' => $listMode],
    );
    $isOngoingList = (($filters['list'] ?? 'ongoing') === 'ongoing');
    $activeFilterCount = \App\Support\AdminListFilters::activeCount([
        'q' => $filters['q'] ?? '',
        'status' => $filters['status'] ?? '',
        'from' => $filters['from'] ?? '',
        'to' => $filters['to'] ?? '',
    ], [], [
        'dateRange' => ['from', 'to'],
    ]);
  @endphp

  <div class="cp-projects-scope-bar">
    <div class="cp-projects-scope-head">
      <div class="cp-projects-scope-start">
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
      <a href="{{ route('admin.client-projects.create') }}" class="admin-btn admin-btn--primary cp-projects-scope-create">
        <i class="fa fa-plus"></i> New project
      </a>
    </div>
  </div>

  @component('admin.partials.filters_accordion', [
    'id' => 'admin-filters-client-projects',
    'storageKey' => 'admin-filters:client-projects',
    'activeCount' => $activeFilterCount,
    'hint' => 'By default Completed and Rejected are hidden. Use All projects or the Status filter below to surface them.',
  ])
    <form method="get" action="{{ route('admin.client-projects.index') }}" class="cp-filters">
      <input type="hidden" name="list" value="{{ $filters['list'] ?? 'ongoing' }}">
      <div class="cp-filter">
        <label for="cp-filter-q">Search</label>
        <input class="admin-input" type="text" id="cp-filter-q" name="q" value="{{ $filters['q'] }}" placeholder="Project #, name, or client">
      </div>
      <div class="cp-filter">
        <label for="cp-filter-status">Status</label>
        <select class="admin-input" id="cp-filter-status" name="status">
          <option value="">All statuses</option>
          @foreach ($statusLabels as $key => $statusLabel)
            <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $statusLabel }}</option>
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
      <div class="cp-filter">
        <label for="cp-filter-per-page">Show</label>
        <select class="admin-input" id="cp-filter-per-page" name="per_page" aria-label="Rows per page" onchange="this.form.submit()">
          @foreach ($perPageOptions as $option)
            <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }}</option>
          @endforeach
        </select>
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
        <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
    </form>
  @endcomponent

  @php
    $quickStatusKeys = [
      \App\Models\ClientProject::STATUS_QUOTE_PENDING,
      \App\Models\ClientProject::STATUS_SCHEDULED,
      \App\Models\ClientProject::STATUS_QUOTE_SENT,
    ];
    $quickStatusQuery = fn (string $status) => array_merge(
        \Illuminate\Support\Arr::except(request()->query(), ['page']),
        ['status' => $status],
    );
  @endphp
  <div class="cp-projects-quick-status" role="toolbar" aria-label="Quick status filters">
    @foreach ($quickStatusKeys as $statusKey)
      @php
        $statusColor = $statusColors[$statusKey] ?? '#6b7280';
        $isQuickStatusActive = ($filters['status'] ?? '') === $statusKey;
      @endphp
      <a href="{{ route('admin.client-projects.index', $quickStatusQuery($statusKey)) }}"
         class="cp-badge cp-projects-quick-status-btn @if ($isQuickStatusActive) cp-projects-quick-status-btn--active @endif"
         style="background: {{ $statusColor }}20; color: {{ $statusColor }};"
         @if ($isQuickStatusActive) aria-current="true" @endif>
        {{ $statusLabels[$statusKey] }}
      </a>
    @endforeach
  </div>

  @if ($projects->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>{{ (($filters['list'] ?? 'ongoing') === 'ongoing') && ($filters['status'] ?? '') === ''
          ? 'No ongoing pipeline projects match these filters. Try All projects or adjust search and dates.'
          : 'No projects match these filters.' }}</p>
        <p class="admin-form-help" style="margin-top:12px;">
          @if (($filters['list'] ?? 'ongoing') === 'ongoing' && ($filters['status'] ?? '') === '')
            Completed, cancelled, and rejected are hidden in the default view.&nbsp;<a href="{{ route('admin.client-projects.index', $toggleQueryBase('all')) }}">Show all projects</a>
            &nbsp;
          @endif
          <a href="{{ route('admin.client-projects.create') }}">Create a new project</a>
        </p>
      </div>
    </div>
  @else
    <div class="admin-card">
      <div class="admin-table-scroll">
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
                  <div class="cp-client-cell">
                    <strong class="cp-client-name">@include('admin.partials.client_directory_link', [
                      'project' => $project,
                      'label' => $project->clientNameForProjectList(),
                    ])</strong>

                    @php
                      $hasPhone = filled($project->client_phone);
                      $formattedPhone = $hasPhone ? \App\Models\ClientProject::formatPhone($project->client_phone) : null;
                      $hasEmail = filled($project->client_email);
                      $mapsUrl = $project->googleMapsSearchUrl();
                      $hasAddress = filled($project->client_address) && filled($mapsUrl);
                    @endphp

                    <div class="cp-client-actions cp-client-mobile-actions" role="group" aria-label="Client contact actions">
                      {{-- Phone: click copies to clipboard --}}
                      @if ($hasPhone)
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--phone cp-copy-btn cp-hold-copy-btn"
                                data-copy="{{ $project->client_phone }}"
                                data-copy-label="Phone number"
                                title="Copy phone: {{ $formattedPhone }}"
                                aria-label="Copy phone {{ $formattedPhone }}">
                          <i class="fa fa-phone" aria-hidden="true"></i>
                        </button>
                      @else
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--phone cp-client-action-btn--disabled"
                                disabled
                                aria-disabled="true"
                                tabindex="-1"
                                title="No phone number"
                                aria-label="No phone number">
                          <i class="fa fa-phone" aria-hidden="true"></i>
                        </button>
                      @endif

                      {{-- Email: click copies to clipboard --}}
                      @if ($hasEmail)
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--email cp-copy-btn cp-hold-copy-btn"
                                data-copy="{{ $project->client_email }}"
                                data-copy-label="Email"
                                title="Copy email: {{ $project->client_email }}"
                                aria-label="Copy email {{ $project->client_email }}">
                          <i class="fa fa-envelope" aria-hidden="true"></i>
                        </button>
                      @else
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--email cp-client-action-btn--disabled"
                                disabled
                                aria-disabled="true"
                                tabindex="-1"
                                title="No email address"
                                aria-label="No email address">
                          <i class="fa fa-envelope" aria-hidden="true"></i>
                        </button>
                      @endif

                      {{-- Address: click opens Google Maps in a new tab --}}
                      @if ($hasAddress)
                        <a href="{{ $mapsUrl }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="cp-client-action-btn cp-client-action-btn--address"
                           title="Open in Google Maps: {{ $project->client_address }}"
                           aria-label="Open {{ $project->client_address }} in Google Maps">
                          <i class="fa fa-map-marker" aria-hidden="true"></i>
                        </a>
                      @else
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--address cp-client-action-btn--disabled"
                                disabled
                                aria-disabled="true"
                                tabindex="-1"
                                title="No address provided"
                                aria-label="No address provided">
                          <i class="fa fa-map-marker" aria-hidden="true"></i>
                        </button>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  <div class="cp-table-project-cell">
                    <div class="cp-table-project-ref">{{ $project->referenceCode() }}</div>
                    <strong class="cp-table-project-name">{{ $project->name }}</strong>
                    @if (filled($project->status_note))
                      <div class="cp-table-status-note" title="{{ $project->status_note }}">
                        {{ $project->status_note }}
                      </div>
                    @endif
                  </div>
                </td>
                <td>
                  <span class="cp-badge" style="background: {{ $statusColors[$project->status] ?? '#6b7280' }}20; color: {{ $statusColors[$project->status] ?? '#6b7280' }};"@if (filled($project->status_note)) title="{{ $project->status_note }}"@endif>
                    {{ $statusLabels[$project->status] ?? $project->status }}
                  </span>
                </td>
                <td>
                  @if ($project->showsVisitLayoutInProjectList())
                    @if ($project->showsVisitRequiredInProjectList())
                      <div class="cp-subtle">Visit required</div>
                    @else
                      <div>{{ $project->visitDateTimeDisplay() }}</div>
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
    </div>
    <div class="admin-pagination">
      {{ $projects->links('pagination.admin') }}
    </div>
  @endif

  <div id="cp-copy-toast" class="cp-copy-toast" role="status" aria-live="polite" aria-hidden="true">
    <i class="fa fa-check-circle" aria-hidden="true"></i>
    <span id="cp-copy-toast-msg">Copied to clipboard</span>
  </div>

  {{-- Row-as-link behaviour. <tr> can't legally wrap an <a>, so we navigate
       in JS. Plain clicks open in the same tab; Ctrl/Cmd-click and middle-click
       open in a new tab to match standard link behaviour. --}}
  <script>
    (function () {
      var rows = document.querySelectorAll('.cp-row-link[data-href]');
      if (!rows.length) return;

      function shouldIgnore(target) {
        // Don't hijack clicks on real interactive elements inside the row
        // (links, buttons, inputs, selects, action buttons).
        return !!target.closest('a, button, input, select, textarea, label, .cp-client-action-btn, .cp-client-actions');
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

    // Client contact action buttons: Click to copy for phone & email; open maps for address
    (function () {
      var toastEl = document.getElementById('cp-copy-toast');
      var toastMsgEl = document.getElementById('cp-copy-toast-msg');
      var toastTimer = null;

      function showToast(msg) {
        if (!toastEl || !toastMsgEl) return;
        toastMsgEl.textContent = msg;
        toastEl.classList.add('cp-copy-toast--visible');
        toastEl.removeAttribute('aria-hidden');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
          toastEl.classList.remove('cp-copy-toast--visible');
          toastEl.setAttribute('aria-hidden', 'true');
        }, 2200);
      }

      function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
          return navigator.clipboard.writeText(text).catch(function () {
            return fallbackCopy(text);
          });
        }
        return fallbackCopy(text);
      }

      function fallbackCopy(text) {
        return new Promise(function (resolve, reject) {
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.top = '0';
          ta.style.left = '0';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          try {
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) resolve(); else reject();
          } catch (e) {
            document.body.removeChild(ta);
            reject(e);
          }
        });
      }

      // Handle copy buttons (phone & email)
      var copyBtns = document.querySelectorAll('.cp-copy-btn');
      copyBtns.forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();

          var textToCopy = btn.getAttribute('data-copy') || '';
          var label = btn.getAttribute('data-copy-label') || 'Copied';
          if (!textToCopy) return;

          copyText(textToCopy).then(function () {
            showToast(label + ' copied to clipboard: ' + textToCopy);
            if (navigator.vibrate) {
              try { navigator.vibrate(50); } catch (e) {}
            }
            btn.classList.add('cp-client-action-btn--copied');
            setTimeout(function () {
              btn.classList.remove('cp-client-action-btn--copied');
            }, 750);
          }).catch(function () {
            showToast('Unable to copy');
          });
        });
      });

      // Prevent row navigation when clicking address link
      var addressLinks = document.querySelectorAll('.cp-client-action-btn--address');
      addressLinks.forEach(function (el) {
        el.addEventListener('click', function (ev) {
          ev.stopPropagation();
        });
      });

      // Prevent row navigation or action when clicking disabled buttons
      var disabledBtns = document.querySelectorAll('.cp-client-action-btn--disabled');
      disabledBtns.forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
        });
      });
    })();
  </script>
@endsection
