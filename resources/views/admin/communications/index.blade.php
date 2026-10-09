@extends('admin.layouts.app')

@section('heading', 'Email / SMS log')

@push('head')
<style>
.comm-desc {
  max-width: 280px;
}
.comm-desc__subject {
  font-weight: 500;
  color: var(--admin-heading);
  margin-bottom: 2px;
}
.comm-desc__body {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 0.85rem;
  color: var(--admin-muted);
  line-height: 1.4;
}
.comm-desc__body.is-expanded {
  -webkit-line-clamp: unset;
  overflow: visible;
}
.comm-desc__toggle {
  background: none;
  border: none;
  padding: 0;
  margin-top: 4px;
  font-size: 0.75rem;
  color: var(--admin-primary);
  cursor: pointer;
  text-decoration: underline;
}
.comm-desc__toggle:hover {
  color: var(--admin-primary-hover);
}
.comm-desc__error {
  margin-top: 4px;
  font-size: 0.8rem;
  color: #b45309;
}
.comm-row--compact td {
  padding-top: 0.5rem;
  padding-bottom: 0.5rem;
}
.comm-recipient {
  max-width: 180px;
  word-break: break-word;
}
.comm-recipient__cc {
  font-size: 0.8rem;
  color: var(--admin-muted);
}
</style>
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede" style="font-size:0.9rem;">
      Audit trail of every email and SMS sent by the system.
    </p>
  </div>

  @php
    $activeFilterCount = \App\Support\AdminListFilters::activeCount([
        'q' => $filters['q'] ?? '',
        'channel' => $filters['channel'] ?? '',
        'reason' => $filters['reason'] ?? '',
        'direction' => $filters['direction'] ?? '',
    ]);
  @endphp
  @component('admin.partials.filters_accordion', [
    'id' => 'admin-filters-communications',
    'storageKey' => 'admin-filters:communications',
    'activeCount' => $activeFilterCount,
  ])
    <form method="get" action="{{ route('admin.communications.index') }}" class="cp-filters cp-filters--5col">
      <div class="cp-filter">
        <label for="comm-filter-q">Search</label>
        <input class="admin-input" type="text" id="comm-filter-q" name="q" value="{{ $filters['q'] }}"
               placeholder="Client, project, recipient, subject…">
      </div>
      <div class="cp-filter">
        <label for="comm-filter-channel">Channel</label>
        <select class="admin-input" id="comm-filter-channel" name="channel">
          <option value="">All channels</option>
          <option value="email" @selected($filters['channel'] === 'email')>Email</option>
          <option value="sms" @selected($filters['channel'] === 'sms')>SMS</option>
        </select>
      </div>
      <div class="cp-filter">
        <label for="comm-filter-reason">Reason</label>
        <select class="admin-input" id="comm-filter-reason" name="reason">
          <option value="">All reasons</option>
          @foreach ($reasonOptions as $key => $label)
            <option value="{{ $key }}" @selected($filters['reason'] === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="cp-filter">
        <label for="comm-filter-direction">Audience</label>
        <select class="admin-input" id="comm-filter-direction" name="direction">
          <option value="">All</option>
          <option value="to_client" @selected($filters['direction'] === 'to_client')>To client</option>
          <option value="to_admin" @selected($filters['direction'] === 'to_admin')>To admin (internal)</option>
        </select>
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
        <a href="{{ route('admin.communications.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
    </form>
  @endcomponent

  @if ($logs->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>No communications match these filters yet.</p>
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
              <th>Type</th>
              <th>Reason</th>
              <th>Description</th>
              <th>Recipient</th>
              <th>Status</th>
              <th>Date &amp; time</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($logs as $log)
              @php
                $isSms = $log->channel === \App\Models\ClientCommunicationLog::CHANNEL_SMS;
                $isAdminBound = $log->direction === \App\Models\ClientCommunicationLog::DIRECTION_TO_ADMIN;
                $isFailed = $log->isFailed();
                $reasonColor = $log->reasonColor();
                $projectUrl = $log->client_project_id
                  ? route('admin.client-projects.show', $log->client_project_id)
                  : null;
                $hasLongDesc = strlen($log->description ?? '') > 80;
              @endphp
              <tr @class(['comm-row--compact', 'cp-comm-row-failed' => $isFailed])>
                <td>
                  @if ($isAdminBound)
                    <strong>{{ $log->clientNameLabel() }}</strong>
                    <div class="cp-subtle"><i class="fa fa-user-secret" aria-hidden="true"></i> Internal</div>
                  @else
                    <strong>@include('admin.partials.client_directory_link', [
                      'project' => $log->project,
                      'label' => $log->clientNameLabel(),
                    ])</strong>
                  @endif
                </td>
                <td>
                  @if ($projectUrl)
                    <a href="{{ $projectUrl }}">{{ $log->projectNameLabel() }}</a>
                  @else
                    {{ $log->projectNameLabel() }}
                  @endif
                </td>
                <td>
                  <span class="cp-badge" style="background: {{ $isSms ? '#10b98120' : '#3b82f620' }}; color: {{ $isSms ? '#10b981' : '#3b82f6' }};">
                    <i class="fa fa-{{ $isSms ? 'comment' : 'envelope' }}" aria-hidden="true"></i>
                    {{ $log->channelLabel() }}
                  </span>
                </td>
                <td>
                  <span class="cp-badge" style="background: {{ $reasonColor }}20; color: {{ $reasonColor }};">
                    {{ $log->reasonLabel() }}
                  </span>
                </td>
                <td class="comm-desc">
                  @if ($log->subject)
                    <div class="comm-desc__subject">{{ $log->subject }}</div>
                  @endif
                  <div class="comm-desc__body" data-full="{{ e($log->description) }}">{{ $log->description }}</div>
                  @if ($hasLongDesc)
                    <button type="button" class="comm-desc__toggle" onclick="toggleCommDesc(this)">Show more</button>
                  @endif
                  @if ($log->error)
                    <div class="comm-desc__error">
                      <i class="fa fa-exclamation-triangle"></i> {{ $log->error }}
                    </div>
                  @endif
                </td>
                <td class="comm-recipient">
                  <div>{{ $log->recipient }}</div>
                  @if ($log->cc)
                    <div class="comm-recipient__cc">cc: {{ $log->cc }}</div>
                  @endif
                </td>
                <td>
                  @if ($isFailed)
                    <span class="cp-badge" style="background:#ef444420; color:#ef4444;">
                      <i class="fa fa-times-circle"></i> Failed
                    </span>
                  @else
                    <span class="cp-badge" style="background:#22c55e20; color:#22c55e;">
                      <i class="fa fa-check-circle"></i> Sent
                    </span>
                  @endif
                </td>
                <td>
                  {{ \App\Models\ClientProject::formatDateTime($log->sent_at) }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
    <div class="admin-pagination">
      {{ $logs->links('pagination.admin') }}
    </div>
  @endif

  <script>
    function toggleCommDesc(btn) {
      var body = btn.previousElementSibling;
      while (body && !body.classList.contains('comm-desc__body')) {
        body = body.previousElementSibling;
      }
      if (!body) return;
      var expanded = body.classList.toggle('is-expanded');
      btn.textContent = expanded ? 'Show less' : 'Show more';
    }
  </script>
@endsection
