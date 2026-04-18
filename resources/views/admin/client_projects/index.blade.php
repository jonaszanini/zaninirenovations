@extends('admin.layouts.app')

@section('heading', 'Projects')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage every project from quote request to completion. Status changes and emails are logged automatically.</p>
    <a href="{{ route('admin.client-projects.create') }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  <form method="get" action="{{ route('admin.client-projects.index') }}" class="cp-filters">
    <div class="cp-filter">
      <label for="cp-filter-q">Search</label>
      <input type="text" id="cp-filter-q" name="q" value="{{ $filters['q'] }}" placeholder="Project or client name">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-status">Status</label>
      <select id="cp-filter-status" name="status">
        <option value="">All statuses</option>
        @foreach ($statusLabels as $key => $label)
          <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div class="cp-filter">
      <label for="cp-filter-from">From</label>
      <input type="date" id="cp-filter-from" name="from" value="{{ $filters['from'] }}">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-to">To</label>
      <input type="date" id="cp-filter-to" name="to" value="{{ $filters['to'] }}">
    </div>
    <div class="cp-filter cp-filter--actions">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
      <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
    </div>
  </form>

  @if ($projects->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No projects match these filters.</p>
        <a href="{{ route('admin.client-projects.create') }}" class="admin-btn admin-btn--primary">Create your first project</a>
      </div>
    </div>
  @else
    <div class="admin-table-wrap">
      <table class="admin-table cp-table">
        <thead>
          <tr>
            <th>Project</th>
            <th>Client</th>
            <th>Status</th>
            <th>Dates</th>
            <th class="cp-col-amount">Total</th>
            <th class="cp-col-amount">Paid</th>
            <th>Last updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($projects as $project)
            <tr>
              <td>
                <strong>{{ $project->name }}</strong>
              </td>
              <td>
                <div>{{ $project->client_name }}</div>
                @if ($project->client_email)
                  <div class="cp-subtle">{{ $project->client_email }}</div>
                @endif
              </td>
              <td>
                <span class="cp-badge" style="background: {{ $statusColors[$project->status] ?? '#6b7280' }}20; color: {{ $statusColors[$project->status] ?? '#6b7280' }};">
                  {{ $statusLabels[$project->status] ?? $project->status }}
                </span>
              </td>
              <td>
                <div>{{ \App\Models\ClientProject::formatDate($project->start_date) }}</div>
                <div class="cp-subtle">→ {{ \App\Models\ClientProject::formatDate($project->end_date) }}</div>
              </td>
              <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($project->total_amount) }}</td>
              <td class="cp-col-amount">{{ number_format($project->percentPaid(), 0) }}%</td>
              <td>{{ optional($project->updated_at)->format('d/m/Y H:i') }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.client-projects.show', $project) }}" class="admin-btn admin-btn--primary admin-btn--sm">Open</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="admin-pagination">
      {{ $projects->links('pagination.admin') }}
    </div>
  @endif
@endsection
