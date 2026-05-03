@extends('admin.layouts.app')

@section('heading', 'Clients')

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.clients.index') }}" class="cp-clients-toolbar-search">
      <input type="search"
             name="q"
             value="{{ $q }}"
             placeholder="Search name, email, phone…"
             class="admin-input"
             style="max-width:320px;">
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <a href="{{ route('admin.clients.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New client
    </a>
  </div>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th style="width:100px;">Projects</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($clients as $client)
            <tr>
              <td><strong>{{ $client->name }}</strong></td>
              <td>{{ $client->email ?: '—' }}</td>
              <td>{{ $client->phone ? \App\Models\ClientProject::formatPhone($client->phone) : '—' }}</td>
              <td>{{ $client->projects_count }}</td>
              <td>
                <a href="{{ route('admin.clients.edit', $client) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" class="admin-form-help">No clients yet. Add one or create a project — existing project contacts can be imported via migration.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($clients->hasPages())
      <div style="margin-top:16px;">{{ $clients->links() }}</div>
    @endif
  </div>
@endsection
