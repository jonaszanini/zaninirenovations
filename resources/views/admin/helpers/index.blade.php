@extends('admin.layouts.app')

@section('heading')
  Helpers
  <x-admin.info-tip label="Helpers">Register helpers, their hourly rates, and contact details. Assign them to projects on the Time tracking tab.</x-admin.info-tip>
@endsection

@section('content')
  <div class="admin-toolbar">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      @if ($returnProjectId)
        <a href="{{ route('admin.client-projects.show', $returnProjectId) }}#time" class="admin-btn admin-btn--ghost">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      @endif
      <a href="{{ route('admin.helpers.create', $returnProjectId ? ['return_project' => $returnProjectId] : []) }}" class="admin-btn admin-btn--primary">
        <i class="fa fa-plus"></i> New helper
      </a>
    </div>
  </div>

  @if ($helpers->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>No helpers yet.</p>
        <a href="{{ route('admin.helpers.create', $returnProjectId ? ['return_project' => $returnProjectId] : []) }}" class="admin-btn admin-btn--primary">Add a helper</a>
      </div>
    </div>
  @else
    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Rate / h</th>
              <th>Projects</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($helpers as $helper)
              <tr>
                <td><strong>{{ $helper->name }}</strong></td>
                <td>{{ $helper->email }}</td>
                <td>{{ $helper->displayPhone() ?? '—' }}</td>
                <td>{{ \App\Models\ClientProject::formatMoney((float) $helper->hourly_rate) }}</td>
                <td>{{ $helper->projects_count }}</td>
                <td>{{ $helper->is_active ? 'Active' : 'Inactive' }}</td>
                <td style="white-space:nowrap;">
                  <a href="{{ route('admin.helpers.edit', array_filter(['helper' => $helper, 'return_project' => $returnProjectId])) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                  <form action="{{ route('admin.helpers.destroy', $helper) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this helper?');">
                    @csrf
                    @method('DELETE')
                    @if ($returnProjectId)
                      <input type="hidden" name="return_project" value="{{ $returnProjectId }}">
                    @endif
                    <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" @if ($helper->time_entries_count> 0) disabled title="Has time entries — deactivate instead" @endif>Delete</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@endsection
