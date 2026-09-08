@extends('admin.layouts.app')

@section('heading', 'Email / SMS log')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Audit trail of every email and SMS sent by the system. Newest first.
      Each row records the recipient, what was sent, and whether the
      delivery attempt succeeded.
    </p>
  </div>

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
              @endphp
              <tr @class(['admin-input', 'cp-comm-row-failed' => $isFailed])>
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
                <td>
                  @if ($log->subject)
                    <div><strong>{{ $log->subject }}</strong></div>
                  @endif
                  <div>{{ $log->description }}</div>
                  @if ($log->error)
                    <div class="cp-subtle" style="color:#b45309;">
                      <i class="fa fa-exclamation-triangle"></i> {{ $log->error }}
                    </div>
                  @endif
                </td>
                <td>
                  <div>{{ $log->recipient }}</div>
                  @if ($log->cc)
                    <div class="cp-subtle">cc: {{ $log->cc }}</div>
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
@endsection
