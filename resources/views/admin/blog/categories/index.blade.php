@extends('admin.layouts.app')

@section('heading', 'Blog categories')

@section('content')
  <div class="admin-toolbar">
    <a href="{{ route('admin.blog.categories.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New category
    </a>
  </div>
  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($categories as $category)
          <tr>
            <td><strong>{{ $category->name }}</strong></td>
            <td>{{ $category->slug }}</td>
            <td>{{ $category->posts_count }}</td>
            <td>
              <a href="{{ route('admin.blog.categories.edit', $category) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form action="{{ route('admin.blog.categories.destroy', $category) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this category?');">
                @csrf @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="admin-form-help">No categories yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
