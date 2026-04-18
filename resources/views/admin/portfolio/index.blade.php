@extends('admin.layouts.app')

@section('heading', 'Latest Projects Photos')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage portfolio photos shown on the public home page (WebP, JPEG, or PNG — stored as WebP, max edge {{ (int) config('webp.max_edge_px', 1920) }}px; up to 15 per project).</p>
    <a href="{{ route('admin.portfolio.create') }}" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New portfolio project
    </a>
  </div>

  @if ($projects->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No portfolio projects yet.</p>
        <a href="{{ route('admin.portfolio.create') }}" class="admin-btn admin-btn--primary">Add your first project</a>
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
            <th>Completed on</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($projects as $project)
            <tr>
              <td><strong>{{ $project->title }}</strong></td>
              <td><span class="admin-badge">{{ $project->categoryLabel() }}</span></td>
              <td>{{ $project->photos_count }} / 15</td>
              <td>{{ optional($project->completed_on)->format('M j, Y') ?? '—' }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.portfolio.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="{{ route('admin.portfolio.destroy', $project) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this project and all photos?');">
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
