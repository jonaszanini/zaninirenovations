@extends('admin.layouts.app')

@section('heading', 'Portfolio categories')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">These labels appear on the Latest projects section of the public site. Slugs are for internal use and stable links.</p>
    <div class="admin-toolbar-actions">
      <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Latest Projects Photos
      </a>
      <a href="{{ route('admin.categories.create') }}" class="admin-btn admin-btn--primary">
        <i class="fa fa-plus"></i> New category
      </a>
    </div>
  </div>

  @if ($categories->isEmpty())
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No categories yet.</p>
        <a href="{{ route('admin.categories.create') }}" class="admin-btn admin-btn--primary">Add a category</a>
      </div>
    </div>
  @else
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Projects</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($categories as $category)
            <tr>
              <td>{{ $category->sort_order }}</td>
              <td><strong>{{ $category->name }}</strong></td>
              <td><code class="admin-code">{{ $category->slug }}</code></td>
              <td>{{ $category->projects_count }}</td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.categories.edit', $category) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="{{ route('admin.categories.destroy', $category) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this category? This cannot be undone.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" @if ($category->projects_count> 0) disabled title="Reassign projects first" @endif>Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection
