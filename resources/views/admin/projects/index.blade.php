@extends('admin.layouts.app')

@section('title', 'Latest projects')
@section('heading', 'Latest projects')

@section('content')
  <div class="admin-toolbar">
    <p style="margin:0;color:var(--admin-muted);">Manage portfolio projects and photos (WebP, up to 10 per project).</p>
    <a href="{{ route('admin.projects.create') }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  @if ($projects->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No projects yet.</p>
        <a href="{{ route('admin.projects.create') }}" class="admin-btn admin-btn--primary">Add your first project</a>
      </div>
    </div>
  @else
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Photos</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($projects as $project)
            <tr>
              <td><strong>{{ $project->title }}</strong></td>
              <td><span class="admin-badge">{{ $project->categoryLabel() }}</span></td>
              <td>{{ $project->photos_count }} / 10</td>
              <td>{{ $project->updated_at->format('M j, Y') }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.projects.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="{{ route('admin.projects.destroy', $project) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this project and all photos?');">
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
    <div class="admin-pagination">
      {{ $projects->links('pagination.admin') }}
    </div>
  @endif
@endsection
