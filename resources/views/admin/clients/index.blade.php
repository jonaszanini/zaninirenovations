@extends('admin.layouts.app')

@section('heading', 'Clients')

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.clients.index') }}" class="cp-clients-toolbar-search">
      <input type="search"
             name="q"
             value="{{ $q }}"
             placeholder="Search name, email, phone…"
             class="admin-input cp-clients-toolbar-search__input">
      @if ($tags->isNotEmpty())
        <select name="tag" class="admin-input" onchange="this.form.submit()" aria-label="Filter by tag">
          <option value="0">All tags</option>
          @foreach ($tags as $tag)
            <option value="{{ $tag->id }}" @selected($tagId === $tag->id)>{{ $tag->name }}</option>
          @endforeach
        </select>
      @endif
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <div class="cp-clients-toolbar-actions">
      <a href="{{ route('admin.client-tags.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" title="Manage client tags">
        <i class="fa fa-tags"></i> Tags
      </a>
      <a href="{{ route('admin.clients.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> New client
      </a>
      <p class="admin-form-help cp-clients-toolbar-total">
        {{ number_format($totalClients) }} {{ $totalClients === 1 ? 'client' : 'clients' }} in database
      </p>
    </div>
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
              <td>
                <strong>{{ $client->name }}</strong>
                @if ($client->tags->isNotEmpty())
                  <div class="cl-tag-picker" style="margin-top:4px;">
                    @foreach ($client->tags as $tag)
                      <span class="admin-badge" style="background:{{ $tag->color }}1a;color:{{ $tag->color }};border:1px solid {{ $tag->color }}55;">{{ $tag->name }}</span>
                    @endforeach
                  </div>
                @endif
              </td>
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
      <div style="margin-top:16px;">{{ $clients->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection
