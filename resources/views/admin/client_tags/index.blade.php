@extends('admin.layouts.app')

@section('heading', 'Client tags')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Segment the client directory (e.g. VIP, Repeat customer, Referral). Assign tags from each client's edit page.</p>
    <div class="admin-toolbar-actions">
      <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Clients
      </a>
      <a href="{{ route('admin.client-tags.create') }}" class="admin-btn admin-btn--primary">
        <i class="fa fa-plus"></i> New tag
      </a>
    </div>
  </div>

  @if ($tags->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>No tags yet.</p>
        <a href="{{ route('admin.client-tags.create') }}" class="admin-btn admin-btn--primary">Add a tag</a>
      </div>
    </div>
  @else
    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Tag</th>
              <th>Clients</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($tags as $tag)
              <tr>
                <td><span class="admin-badge" style="background:{{ $tag->color }}1a;color:{{ $tag->color }};border:1px solid {{ $tag->color }}55;">{{ $tag->name }}</span></td>
                <td>{{ $tag->clients_count }}</td>
                <td style="white-space:nowrap;">
                  <a href="{{ route('admin.client-tags.edit', $tag) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                  <form action="{{ route('admin.client-tags.destroy', $tag) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this tag? It will be removed from all clients.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
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
