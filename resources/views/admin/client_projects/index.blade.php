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
    $quickStatusKeys = [
        \App\Models\ClientProject::STATUS_QUOTE_PENDING,
        \App\Models\ClientProject::STATUS_SCHEDULED,
        \App\Models\ClientProject::STATUS_QUOTE_SENT,
        \App\Models\ClientProject::STATUS_IN_PROGRESS,
    ];
    $quickStatusQuery = function (string $status) use ($filters): array {
        $base = \Illuminate\Support\Arr::except(request()->query(), ['page']);
        if (($filters['status'] ?? '') === $status) {
            unset($base['status']);
            return $base;
        }

        return array_merge($base, ['status' => $status]);
    };
    $perPagePreservedParams = \Illuminate\Support\Arr::only(
        request()->query(),
        ['list', 'q', 'status', 'from', 'to']
    );
  @endphp

  {{-- Subheader Scope Tabs & New Project CTA --}}
  <div class="cp-projects-scope-bar">
    <div class="cp-projects-scope-head">
      <div class="cp-projects-scope-start">
        <div class="cp-projects-scope-toggles" role="navigation" aria-label="Project list scope">
          <a href="{{ route('admin.client-projects.index', $toggleQueryBase('ongoing')) }}"
             class="cp-projects-scope-chip {{ $isOngoingList ? 'cp-projects-scope-chip--active' : '' }}"
             aria-current="{{ $isOngoingList ? 'page' : 'false' }}"
             title="Hide Completed and Rejected (focus on work in motion).">
            <i class="fa fa-filter" aria-hidden="true"></i>
            <span>Ongoing pipeline</span>
          </a>
          <a href="{{ route('admin.client-projects.index', $toggleQueryBase('all')) }}"
             class="cp-projects-scope-chip {{ ! $isOngoingList ? 'cp-projects-scope-chip--active' : '' }}"
             aria-current="{{ ! $isOngoingList ? 'page' : 'false' }}"
             title="Include Completed and Rejected with every other status.">
            <i class="fa fa-th-large" aria-hidden="true"></i>
            <span>All projects</span>
          </a>
        </div>
      </div>
      <a href="{{ route('admin.client-projects.create') }}" class="admin-btn admin-btn--primary cp-projects-scope-create">
        <i class="fa fa-plus-circle" aria-hidden="true"></i>
        <span>New project</span>
      </a>
    </div>
  </div>

  {{-- Unified Filters & Search + Quick Filter Card --}}
  <div class="admin-card cp-projects-filter-card">
    <details class="cp-filter-accordion"
             id="admin-filters-client-projects"
             data-admin-filters-accordion
             data-storage-key="admin-filters:client-projects"
             data-active-count="{{ $activeFilterCount }}"
             @if ($activeFilterCount > 0) open @endif>
      <summary class="cp-filter-accordion__summary" aria-controls="admin-filters-client-projects-panel">
        <span class="cp-filter-accordion__heading">
          <span class="cp-filter-accordion__icon" aria-hidden="true"><i class="fa fa-sliders"></i></span>
          <span class="admin-filters-accordion__title cp-filter-accordion__title">
            Filters &amp; Search
            <x-admin.info-tip label="Filters">By default Completed and Rejected are hidden. Use All projects or the Status filter below to surface them.</x-admin.info-tip>
          </span>
          @if ($activeFilterCount > 0)
            <span class="admin-filters-accordion__badge">{{ $activeFilterCount }} active</span>
          @endif
        </span>
        <span class="cp-filter-accordion__toggle">
          <span class="cp-filter-accordion__toggle-text">
            <span class="cp-filter-accordion__show-label">Show filters</span>
            <span class="cp-filter-accordion__hide-label">Hide filters</span>
          </span>
          <i class="fa fa-chevron-down cp-filter-accordion__caret" aria-hidden="true"></i>
        </span>
      </summary>
      <div class="cp-filter-accordion__body" id="admin-filters-client-projects-panel">
        <form method="get" action="{{ route('admin.client-projects.index') }}" class="cp-filters cp-filters--modern">
          <input type="hidden" name="list" value="{{ $filters['list'] ?? 'ongoing' }}">
          <input type="hidden" name="per_page" value="{{ $perPage }}">
          <div class="cp-filter cp-filter--search">
            <label for="cp-filter-q">Search</label>
            <input class="admin-input" type="text" id="cp-filter-q" name="q" value="{{ $filters['q'] }}" placeholder="Project #, name, or client">
          </div>
          <div class="cp-filter cp-filter--select">
            <label for="cp-filter-status">Status</label>
            <select class="admin-input" id="cp-filter-status" name="status">
              <option value="">All statuses</option>
              @foreach ($statusLabels as $statusOptionKey => $statusOptionLabel)
                <option value="{{ $statusOptionKey }}" @selected($filters['status'] === $statusOptionKey)>{{ $statusOptionLabel }}</option>
              @endforeach
            </select>
          </div>
          <div class="cp-filter cp-filter--date">
            <label for="cp-filter-from">From Date</label>
            <input class="admin-input" type="date" id="cp-filter-from" name="from" value="{{ $filters['from'] }}">
          </div>
          <div class="cp-filter cp-filter--date">
            <label for="cp-filter-to">To Date</label>
            <input class="admin-input" type="date" id="cp-filter-to" name="to" value="{{ $filters['to'] }}">
          </div>
          <div class="cp-filter cp-filter--actions">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm cp-filter-apply-btn">Apply</button>
            <a href="{{ route('admin.client-projects.index', ['list' => $filters['list'] ?? 'ongoing']) }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-filter-reset-btn">Reset</a>
          </div>
        </form>
      </div>
    </details>

    <div class="cp-projects-quick-status" role="toolbar" aria-label="Quick status filters">
      <span class="cp-projects-quick-status__label">Quick Filter:</span>
      @foreach ($quickStatusKeys as $statusKey)
        @php
          $statusColor = $statusColors[$statusKey] ?? '#6b7280';
          $isQuickStatusActive = ($filters['status'] ?? '') === $statusKey;
        @endphp
        <a href="{{ route('admin.client-projects.index', $quickStatusQuery($statusKey)) }}"
           class="cp-badge cp-projects-quick-status-btn @if ($isQuickStatusActive) cp-projects-quick-status-btn--active @endif"
           style="--cp-badge-color: {{ $statusColor }}; background: {{ $statusColor }}20; color: {{ $statusColor }}; border-color: {{ $statusColor }}4d;"
           @if ($isQuickStatusActive) aria-current="true" @endif>
          {{ $statusLabels[$statusKey] }}
        </a>
      @endforeach
    </div>
  </div>

  {{-- Interactive Projects Table Card --}}
  <section class="admin-card cp-projects-list-card" aria-label="Project list">
    <div class="cp-projects-card-header">
      <div class="cp-projects-card-header__lead">
        <div class="cp-projects-card-header__icon" aria-hidden="true">
          <i class="fa fa-list-ul"></i>
        </div>
        <div>
          <h2 class="cp-projects-card-header__title">Project List</h2>
          <p class="cp-projects-card-header__sub">
            Showing {{ $projects->total() }} {{ $projects->total() === 1 ? 'project entry' : 'project entries' }}
          </p>
        </div>
      </div>

      <div class="cp-projects-card-header__controls">
        <form method="get" action="{{ route('admin.client-projects.index') }}" class="cp-per-page-form">
          @foreach ($perPagePreservedParams as $paramKey => $paramVal)
            @if ($paramVal !== '' && $paramVal !== null)
              <input type="hidden" name="{{ $paramKey }}" value="{{ $paramVal }}">
            @endif
          @endforeach
          <label for="cp-filter-per-page">Rows per page:</label>
          <select class="admin-input cp-per-page-select" id="cp-filter-per-page" name="per_page" aria-label="Rows per page" onchange="this.form.submit()">
            @foreach ($perPageOptions as $option)
              <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }}</option>
            @endforeach
          </select>
        </form>
      </div>
    </div>

    @if ($projects->isEmpty())
      <div class="cp-projects-empty-wrap">
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
      <div class="admin-table-scroll">
        <table class="admin-table cp-table cp-projects-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Project</th>
              <th>Status</th>
              <th>Dates</th>
              <th class="cp-col-amount">Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($projects as $project)
              @php
                $url = route('admin.client-projects.show', $project);
                $rowStatusColor = $statusColors[$project->status] ?? '#6b7280';
              @endphp
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
                      $telHref = $hasPhone ? \App\Models\ClientProject::telHref($project->client_phone) : null;
                      $hasEmail = filled($project->client_email);
                      $gmailHref = $hasEmail
                        ? 'https://mail.google.com/mail/?authuser=zaninirenovations@gmail.com&view=cm&fs=1&to='.rawurlencode($project->client_email)
                        : null;
                      $mapsUrl = $project->googleMapsSearchUrl();
                      $hasAddress = filled($project->client_address) && filled($mapsUrl);
                    @endphp

                    <div class="cp-client-actions cp-client-mobile-actions" role="group" aria-label="Client contact actions">
                      {{-- Phone: click copies to clipboard --}}
                      @if ($hasPhone)
                        <button type="button"
                                class="cp-client-action-btn cp-client-action-btn--phone cp-client-action-btn--active cp-copy-btn cp-hold-copy-btn"
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
                                class="cp-client-action-btn cp-client-action-btn--email cp-client-action-btn--active cp-copy-btn cp-hold-copy-btn"
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
                           class="cp-client-action-btn cp-client-action-btn--address cp-client-action-btn--active"
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

                    <div class="cp-client-mobile-fields">
                      @if ($telHref && $formattedPhone)
                        <a href="{{ $telHref }}"
                           class="cp-client-mobile-field cp-table-phone"
                           data-cp-hold-copy="{{ $formattedPhone }}"
                           draggable="false"
                           title="Call {{ $formattedPhone }}. Hold to copy."
                           aria-label="Call {{ $formattedPhone }}. Hold to copy.">{{ $formattedPhone }}</a>
                      @endif
                      @if ($gmailHref)
                        <a href="{{ $gmailHref }}"
                           class="cp-client-mobile-field"
                           target="_blank"
                           rel="noopener noreferrer"
                           data-cp-hold-copy="{{ $project->client_email }}"
                           draggable="false"
                           title="Email {{ $project->client_email }} in Gmail. Hold to copy."
                           aria-label="Email {{ $project->client_email }} in Gmail. Hold to copy.">{{ $project->client_email }}</a>
                      @endif
                      @if ($hasAddress)
                        <a href="{{ $mapsUrl }}"
                           class="cp-client-mobile-field cp-table-street"
                           target="_blank"
                           rel="noopener noreferrer"
                           data-cp-hold-copy="{{ $project->client_address }}"
                           draggable="false"
                           title="Open this address in Google Maps. Hold to copy."
                           aria-label="Open this address in Google Maps. Hold to copy.">{{ $project->client_address }}</a>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  <div class="cp-table-project-cell">
                    <div class="cp-table-project-head">
                      <span class="cp-table-project-ref">{{ $project->referenceCode() }}</span>
                      <strong class="cp-table-project-name">{{ $project->name }}</strong>
                    </div>
                    @php
                      $hasStatusNote = filled($project->status_note);
                      $isRowClosed = $project->isClosed();
                    @endphp
                    <div class="cp-inline-note"
                         data-cp-inline-note
                         data-project-id="{{ $project->id }}"
                         data-status-url="{{ route('admin.client-projects.status.update', $project) }}"
                         data-current-status="{{ $project->status }}"
                         data-closed="{{ $isRowClosed ? '1' : '0' }}">
                      <div class="cp-table-status-note cp-inline-note__display {{ $isRowClosed ? 'cp-inline-note__display--locked' : '' }}"
                           @unless ($isRowClosed) role="button" tabindex="0" @endunless
                           title="{{ $isRowClosed ? ($hasStatusNote ? $project->status_note : 'Project is closed') : ($hasStatusNote ? $project->status_note : 'Click to add a quick note') }}">
                        <span class="cp-inline-note__text {{ $hasStatusNote ? '' : 'cp-inline-note__text--empty' }}">{{ $hasStatusNote ? $project->status_note : 'No notes' }}</span>
                        @unless ($isRowClosed)
                          <i class="fa fa-pencil cp-inline-note__icon" aria-hidden="true"></i>
                        @endunless
                      </div>
                      @unless ($isRowClosed)
                        <div class="cp-inline-note__editor" hidden>
                          <input type="text"
                                 class="admin-input cp-inline-note__input"
                                 maxlength="2000"
                                 value="{{ $project->status_note }}"
                                 placeholder="Quick note for this status…"
                                 aria-label="Quick note for {{ $project->name }}">
                          <button type="button"
                                  class="cp-inline-note__btn cp-inline-note__btn--save"
                                  data-cp-note-save
                                  title="Save note"
                                  aria-label="Save note">
                            <i class="fa fa-check" aria-hidden="true"></i>
                          </button>
                          <button type="button"
                                  class="cp-inline-note__btn cp-inline-note__btn--cancel"
                                  data-cp-note-cancel
                                  title="Cancel"
                                  aria-label="Cancel">
                            <i class="fa fa-times" aria-hidden="true"></i>
                          </button>
                        </div>
                      @endunless
                    </div>
                  </div>
                </td>
                <td>
                  <div class="cp-inline-status"
                       data-cp-inline-status
                       data-project-id="{{ $project->id }}"
                       data-status-url="{{ route('admin.client-projects.status.update', $project) }}"
                       data-reopen-url="{{ route('admin.client-projects.reopen', $project) }}"
                       data-current-status="{{ $project->status }}"
                       data-cancelled="{{ $project->isCancelled() ? '1' : '0' }}"
                       data-rejected="{{ $project->isRejected() ? '1' : '0' }}">
                    <button type="button"
                            class="cp-badge cp-status-pill cp-status-pill--interactive"
                            data-cp-status-trigger
                            aria-haspopup="listbox"
                            aria-expanded="false"
                            style="--cp-badge-color: {{ $rowStatusColor }}; background: {{ $rowStatusColor }}20; color: {{ $rowStatusColor }}; border-color: {{ $rowStatusColor }}45;"
                            @if ($project->isCancelled())
                              disabled
                              title="Cancelled projects are final"
                            @else
                              title="Click to change status"
                            @endif>
                      <span class="cp-status-pill__dot" aria-hidden="true"></span>
                      <span class="cp-status-pill__label">{{ $statusLabels[$project->status] ?? $project->status }}</span>
                      @unless ($project->isCancelled())
                        <i class="fa fa-caret-down cp-status-pill__caret" aria-hidden="true"></i>
                      @endunless
                    </button>
                    @unless ($project->isCancelled())
                      <div class="cp-status-menu" data-cp-status-menu role="listbox" aria-label="Change status for {{ $project->name }}" hidden>
                        @foreach ($statusLabels as $statusMenuKey => $statusMenuLabel)
                          @php
                            $statusMenuColor = $statusColors[$statusMenuKey] ?? '#6b7280';
                            $isReopenRestricted = $project->isRejected() && in_array($statusMenuKey, \App\Models\ClientProject::terminalStatuses(), true);
                          @endphp
                          <button type="button"
                                  class="cp-status-menu__option {{ $project->status === $statusMenuKey ? 'is-active' : '' }}"
                                  role="option"
                                  aria-selected="{{ $project->status === $statusMenuKey ? 'true' : 'false' }}"
                                  data-status-value="{{ $statusMenuKey }}"
                                  data-status-label="{{ $statusMenuLabel }}"
                                  data-status-color="{{ $statusMenuColor }}"
                                  @if ($isReopenRestricted) hidden @endif>
                            <span class="cp-status-pill__dot" style="background: {{ $statusMenuColor }};" aria-hidden="true"></span>
                            <span class="cp-status-menu__option-label">{{ $statusMenuLabel }}</span>
                            <i class="fa fa-check cp-status-menu__check" aria-hidden="true"></i>
                          </button>
                        @endforeach
                      </div>
                    @endunless
                  </div>
                </td>
                <td>
                  @if ($project->showsVisitLayoutInProjectList())
                    @if ($project->showsVisitRequiredInProjectList())
                      <div class="cp-subtle">Visit required</div>
                    @else
                      <div class="cp-dates-main">{{ $project->visitDateTimeDisplay() ?: 'Not scheduled' }}</div>
                      <div class="cp-subtle">Visit</div>
                    @endif
                  @elseif ($project->start_date && $project->end_date)
                    <div class="cp-dates-range">{{ \App\Models\ClientProject::formatDate($project->start_date) }} to {{ \App\Models\ClientProject::formatDate($project->end_date) }}</div>
                  @elseif ($project->start_date)
                    <div class="cp-dates-range">{{ \App\Models\ClientProject::formatDate($project->start_date) }}</div>
                  @elseif ($project->end_date)
                    <div class="cp-dates-range">Until {{ \App\Models\ClientProject::formatDate($project->end_date) }}</div>
                  @else
                    <span class="cp-subtle">Not scheduled</span>
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
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="cp-projects-card-footer">
        <span class="cp-projects-pagination-info">
          Showing {{ $projects->firstItem() ?? 0 }} to {{ $projects->lastItem() ?? 0 }} of {{ $projects->total() }} {{ $projects->total() === 1 ? 'entry' : 'entries' }}
        </span>
        <div class="admin-pagination cp-projects-pagination">
          @if ($projects->hasPages())
            {{ $projects->links('pagination.admin') }}
          @else
            <nav class="admin-pagination-nav" role="navigation" aria-label="Pagination">
              <span class="is-disabled">Previous</span>
              <span aria-current="page">1</span>
              <span class="is-disabled">Next</span>
            </nav>
          @endif
        </div>
      </div>
    @endif
  </section>

  <div id="cp-copy-toast" class="cp-copy-toast" role="status" aria-live="polite" aria-hidden="true">
    <i class="fa fa-check-circle" aria-hidden="true"></i>
    <span id="cp-copy-toast-msg">Copied to clipboard</span>
  </div>

  <div id="cp-index-cancel-modal" class="cp-status-modal" hidden>
    <div class="cp-status-modal-backdrop" data-cp-index-cancel-dismiss tabindex="-1" aria-hidden="true"></div>
    <div class="cp-status-modal-panel" role="dialog" aria-modal="true" aria-labelledby="cp-index-cancel-modal-title">
      <h2 id="cp-index-cancel-modal-title" class="cp-status-modal-title">Cancel project</h2>
      <p class="cp-status-modal-hint">This will mark the project as <strong>Cancelled</strong>. Please provide a reason — it will be saved in the project history.</p>
      <label for="cp-index-cancel-reason" class="cp-status-modal-label">Reason for cancelling</label>
      <textarea id="cp-index-cancel-reason" class="admin-input cp-status-modal-textarea" rows="4" maxlength="2000" required placeholder="e.g. Client went with another contractor"></textarea>
      <div class="cp-status-modal-actions">
        <button type="button" class="admin-btn admin-btn--ghost" data-cp-index-cancel-dismiss>Keep project active</button>
        <button type="button" class="admin-btn admin-btn--primary" id="cp-index-cancel-confirm">Confirm cancellation</button>
      </div>
    </div>
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
        // (links, buttons, inputs, selects, action buttons, inline note/status).
        return !!target.closest('a, button, input, select, textarea, label, .cp-client-action-btn, .cp-client-actions, .cp-client-mobile-fields, .cp-inline-note, .cp-inline-status, .cp-status-menu');
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

    // Shared toast helper + contact action buttons + inline quick note & status editor
    (function () {
      var toastEl = document.getElementById('cp-copy-toast');
      var toastMsgEl = document.getElementById('cp-copy-toast-msg');
      var toastTimer = null;

      function showToast(msg, isError) {
        if (!toastEl || !toastMsgEl) return;
        toastMsgEl.textContent = msg;
        toastEl.classList.toggle('cp-copy-toast--error', !!isError);
        toastEl.classList.add('cp-copy-toast--visible');
        toastEl.removeAttribute('aria-hidden');
        if (toastTimer) clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
          toastEl.classList.remove('cp-copy-toast--visible');
          toastEl.classList.remove('cp-copy-toast--error');
          toastEl.setAttribute('aria-hidden', 'true');
        }, 2500);
      }

      function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
      }

      function postJson(url, payload) {
        return fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: JSON.stringify(payload)
        }).then(function (res) {
          return res.json().catch(function () {
            return {};
          }).then(function (data) {
            if (!res.ok || data.ok === false) {
              var errMsg = (data && data.message) ? data.message : 'Unable to save changes.';
              if (data && data.errors) {
                var firstKey = Object.keys(data.errors)[0];
                if (firstKey && data.errors[firstKey] && data.errors[firstKey][0]) {
                  errMsg = data.errors[firstKey][0];
                }
              }
              throw new Error(errMsg);
            }
            return data;
          });
        });
      }

      function applyNoteDisplay(noteWrap, noteText, isClosed) {
        if (!noteWrap) return;
        var display = noteWrap.querySelector('.cp-inline-note__display');
        var textSpan = noteWrap.querySelector('.cp-inline-note__text');
        var input = noteWrap.querySelector('.cp-inline-note__input');
        var pencil = noteWrap.querySelector('.cp-inline-note__icon');
        var clean = noteText ? String(noteText).trim() : '';

        if (typeof isClosed === 'boolean') {
          noteWrap.setAttribute('data-closed', isClosed ? '1' : '0');
          if (display) {
            display.classList.toggle('cp-inline-note__display--locked', isClosed);
            if (isClosed) {
              display.removeAttribute('role');
              display.removeAttribute('tabindex');
            } else {
              display.setAttribute('role', 'button');
              display.setAttribute('tabindex', '0');
            }
          }
          if (pencil) {
            pencil.hidden = isClosed;
          }
        }

        if (textSpan) {
          if (clean !== '') {
            textSpan.textContent = clean;
            textSpan.classList.remove('cp-inline-note__text--empty');
          } else {
            textSpan.textContent = 'No notes';
            textSpan.classList.add('cp-inline-note__text--empty');
          }
        }
        if (display) {
          var closedState = noteWrap.getAttribute('data-closed') === '1';
          display.setAttribute(
            'title',
            closedState ? (clean || 'Project is closed') : (clean || 'Click to add a quick note')
          );
        }
        if (input) {
          input.value = clean;
        }
      }

      // ─── Inline Quick Note Editing (one-click) ────────────────────────────
      document.querySelectorAll('[data-cp-inline-note]').forEach(function (noteWrap) {
        var display = noteWrap.querySelector('.cp-inline-note__display');
        var editor = noteWrap.querySelector('.cp-inline-note__editor');
        var input = noteWrap.querySelector('.cp-inline-note__input');
        var saveBtn = noteWrap.querySelector('[data-cp-note-save]');
        var cancelBtn = noteWrap.querySelector('[data-cp-note-cancel]');
        if (!display || !editor || !input) return;

        var saving = false;
        var cancelling = false;
        var savingViaBtn = false;
        var initialValue = input.value;

        function openEditor() {
          if (noteWrap.getAttribute('data-closed') === '1' || saving) return;
          initialValue = input.value;
          display.hidden = true;
          editor.hidden = false;
          input.focus();
          input.select();
        }

        function closeEditor(restore) {
          if (restore) {
            input.value = initialValue;
          }
          editor.hidden = true;
          display.hidden = false;
        }

        function commitNote() {
          if (saving || noteWrap.getAttribute('data-closed') === '1') return;
          var nextVal = String(input.value || '').trim();
          var prevVal = String(initialValue || '').trim();
          if (nextVal === prevVal) {
            closeEditor(true);
            return;
          }

          saving = true;
          input.disabled = true;
          if (saveBtn) saveBtn.disabled = true;
          if (cancelBtn) cancelBtn.disabled = true;

          var url = noteWrap.getAttribute('data-status-url');
          var currentStatus = noteWrap.getAttribute('data-current-status');

          postJson(url, {
            status: currentStatus,
            note: nextVal
          }).then(function (data) {
            var savedNote = data.status_note || '';
            initialValue = savedNote;
            applyNoteDisplay(noteWrap, savedNote);
            closeEditor(false);
            showToast(data.message || (savedNote ? 'Status note saved.' : 'Status note cleared.'));
          }).catch(function (err) {
            showToast(err.message || 'Could not save quick note.', true);
            input.focus();
          }).finally(function () {
            saving = false;
            input.disabled = false;
            if (saveBtn) saveBtn.disabled = false;
            if (cancelBtn) cancelBtn.disabled = false;
          });
        }

        display.addEventListener('click', function (ev) {
          ev.stopPropagation();
          openEditor();
        });

        display.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            ev.stopPropagation();
            openEditor();
          }
        });

        input.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter') {
            ev.preventDefault();
            commitNote();
          } else if (ev.key === 'Escape') {
            ev.preventDefault();
            cancelling = true;
            closeEditor(true);
            display.focus();
            setTimeout(function () { cancelling = false; }, 0);
          }
        });

        if (saveBtn) {
          saveBtn.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
            savingViaBtn = true;
          });
          saveBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            savingViaBtn = false;
            commitNote();
          });
        }

        if (cancelBtn) {
          cancelBtn.addEventListener('mousedown', function (ev) {
            ev.preventDefault();
            cancelling = true;
          });
          cancelBtn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            closeEditor(true);
            cancelling = false;
          });
        }

        input.addEventListener('blur', function () {
          if (cancelling || savingViaBtn || saving) return;
          commitNote();
        });
      });

      // ─── Inline Status Dropdown (click pill to change status) ────────────
      var activeStatusMenu = null;
      var activeStatusWrap = null;
      var activeStatusTrigger = null;

      function positionStatusMenu(trigger, menu) {
        var rect = trigger.getBoundingClientRect();
        var menuWidth = Math.max(menu.offsetWidth || 216, 216);
        var menuHeight = Math.min(menu.scrollHeight || 320, 320);

        var left = rect.left;
        if (left + menuWidth > window.innerWidth - 8) {
          left = window.innerWidth - menuWidth - 8;
        }
        if (left < 8) left = 8;

        var top = rect.bottom + 6;
        if (top + menuHeight > window.innerHeight - 8 && rect.top - 6 - menuHeight >= 8) {
          top = rect.top - 6 - menuHeight;
        } else if (top + menuHeight > window.innerHeight - 8) {
          top = Math.max(8, window.innerHeight - menuHeight - 8);
        }

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
      }

      function closeActiveStatusMenu() {
        if (!activeStatusMenu) return;
        activeStatusMenu.hidden = true;
        activeStatusMenu.style.left = '';
        activeStatusMenu.style.top = '';
        if (activeStatusWrap && activeStatusMenu.parentNode !== activeStatusWrap) {
          activeStatusWrap.appendChild(activeStatusMenu);
        }
        if (activeStatusTrigger) {
          activeStatusTrigger.setAttribute('aria-expanded', 'false');
        }
        activeStatusMenu = null;
        activeStatusWrap = null;
        activeStatusTrigger = null;
      }

      function updateRowStatusUI(wrap, data) {
        var trigger = wrap.querySelector('[data-cp-status-trigger]');
        var labelEl = wrap.querySelector('.cp-status-pill__label');
        var caretEl = wrap.querySelector('.cp-status-pill__caret');
        var menu = wrap.querySelector('[data-cp-status-menu]') || (activeStatusWrap === wrap ? activeStatusMenu : null);
        var row = wrap.closest('tr');
        var noteWrap = row ? row.querySelector('[data-cp-inline-note]') : null;

        var newStatus = data.status;
        var newColor = data.status_color || '#6b7280';
        var newLabel = data.status_label || newStatus;

        wrap.setAttribute('data-current-status', newStatus);
        wrap.setAttribute('data-cancelled', data.is_cancelled ? '1' : '0');
        wrap.setAttribute('data-rejected', data.is_rejected ? '1' : '0');

        if (trigger) {
          trigger.style.setProperty('--cp-badge-color', newColor);
          trigger.style.background = newColor + '20';
          trigger.style.color = newColor;
          trigger.style.borderColor = newColor + '45';
          if (labelEl) labelEl.textContent = newLabel;
          if (data.is_cancelled) {
            trigger.disabled = true;
            trigger.setAttribute('title', 'Cancelled projects are final');
            if (caretEl) caretEl.hidden = true;
          } else {
            trigger.disabled = false;
            trigger.setAttribute('title', 'Click to change status');
            if (caretEl) caretEl.hidden = false;
          }
        }

        if (menu) {
          menu.querySelectorAll('.cp-status-menu__option').forEach(function (opt) {
            var val = opt.getAttribute('data-status-value');
            var isSelected = val === newStatus;
            opt.classList.toggle('is-active', isSelected);
            opt.setAttribute('aria-selected', isSelected ? 'true' : 'false');
            if (data.is_rejected) {
              opt.hidden = (val === 'cancelled' || val === 'rejected');
            } else {
              opt.hidden = false;
            }
          });
        }

        if (noteWrap) {
          noteWrap.setAttribute('data-current-status', newStatus);
          applyNoteDisplay(noteWrap, data.status_note || '', !!data.is_closed);
        }
      }

      var cancelModal = document.getElementById('cp-index-cancel-modal');
      var cancelReasonInput = document.getElementById('cp-index-cancel-reason');
      var cancelConfirmBtn = document.getElementById('cp-index-cancel-confirm');
      var pendingCancelWrap = null;

      function openCancelModalForWrap(wrap) {
        if (!cancelModal || !cancelReasonInput) return;
        pendingCancelWrap = wrap;
        cancelReasonInput.value = '';
        cancelModal.hidden = false;
        document.documentElement.classList.add('cp-cancel-modal-open');
        cancelReasonInput.focus();
      }

      function closeIndexCancelModal() {
        if (!cancelModal) return;
        cancelModal.hidden = true;
        document.documentElement.classList.remove('cp-cancel-modal-open');
        pendingCancelWrap = null;
      }

      function submitStatusChange(wrap, newStatus, noteText) {
        var trigger = wrap.querySelector('[data-cp-status-trigger]');
        var isRejected = wrap.getAttribute('data-rejected') === '1';
        var url = isRejected ? wrap.getAttribute('data-reopen-url') : wrap.getAttribute('data-status-url');
        if (!url) return;

        if (trigger) trigger.disabled = true;

        var payload = { status: newStatus };
        if (typeof noteText === 'string' && noteText.trim() !== '') {
          payload.note = noteText.trim();
        } else if (!isRejected) {
          payload.note = '';
        }

        postJson(url, payload).then(function (data) {
          updateRowStatusUI(wrap, data);
          showToast(data.message || 'Status updated.');
        }).catch(function (err) {
          if (trigger && wrap.getAttribute('data-cancelled') !== '1') {
            trigger.disabled = false;
          }
          showToast(err.message || 'Could not update status.', true);
        });
      }

      if (cancelConfirmBtn) {
        cancelConfirmBtn.addEventListener('click', function () {
          if (!cancelReasonInput || !pendingCancelWrap) return;
          var reason = cancelReasonInput.value.trim();
          if (!reason) {
            cancelReasonInput.setCustomValidity('Please enter a reason for cancelling.');
            cancelReasonInput.reportValidity();
            cancelReasonInput.setCustomValidity('');
            return;
          }
          var targetWrap = pendingCancelWrap;
          closeIndexCancelModal();
          submitStatusChange(targetWrap, 'cancelled', reason);
        });
      }

      document.querySelectorAll('[data-cp-index-cancel-dismiss]').forEach(function (el) {
        el.addEventListener('click', function () {
          closeIndexCancelModal();
        });
      });

      document.querySelectorAll('[data-cp-inline-status]').forEach(function (wrap) {
        var trigger = wrap.querySelector('[data-cp-status-trigger]');
        var menu = wrap.querySelector('[data-cp-status-menu]');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', function (ev) {
          ev.preventDefault();
          ev.stopPropagation();
          if (trigger.disabled) return;

          var wasOpen = activeStatusMenu === menu;
          closeActiveStatusMenu();
          if (wasOpen) return;

          document.body.appendChild(menu);
          menu.hidden = false;
          trigger.setAttribute('aria-expanded', 'true');
          positionStatusMenu(trigger, menu);
          activeStatusMenu = menu;
          activeStatusWrap = wrap;
          activeStatusTrigger = trigger;
        });

        menu.querySelectorAll('.cp-status-menu__option').forEach(function (opt) {
          opt.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();

            var newStatus = opt.getAttribute('data-status-value');
            var currentStatus = wrap.getAttribute('data-current-status');
            closeActiveStatusMenu();

            if (!newStatus || newStatus === currentStatus) return;

            if (newStatus === 'cancelled') {
              openCancelModalForWrap(wrap);
              return;
            }

            submitStatusChange(wrap, newStatus);
          });
        });
      });

      document.addEventListener('click', function (ev) {
        if (activeStatusMenu && !ev.target.closest('.cp-status-menu') && !ev.target.closest('[data-cp-status-trigger]')) {
          closeActiveStatusMenu();
        }
      });

      document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
          if (cancelModal && !cancelModal.hidden) {
            closeIndexCancelModal();
            return;
          }
          if (activeStatusMenu) {
            var triggerToFocus = activeStatusTrigger;
            closeActiveStatusMenu();
            if (triggerToFocus) triggerToFocus.focus();
          }
        }
      });

      window.addEventListener('resize', closeActiveStatusMenu);
      window.addEventListener('scroll', function (ev) {
        if (activeStatusMenu && ev.target && activeStatusMenu.contains && activeStatusMenu.contains(ev.target)) {
          return;
        }
        closeActiveStatusMenu();
      }, true);

      // ─── Client contact action buttons ────────────────────────────────────
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
            showToast('Unable to copy', true);
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

@push('scripts')
  <script src="{{ asset('js/admin-contact-hold.js') }}?v={{ @filemtime(public_path('js/admin-contact-hold.js')) ?: time() }}"></script>
@endpush
