@extends('admin.layouts.app')

@section('heading', 'Blog tags')

@section('content')
  <div class="admin-toolbar">
    <a href="{{ route('admin.blog.tags.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New tag
    </a>
  </div>
  <div class="admin-card admin-card--narrow" style="margin-bottom:1rem;">
    <form method="post" action="{{ route('admin.blog.tags.bulk') }}">
      @csrf
      <div class="admin-form-group">
        <label for="bt-bulk">Bulk add tags</label>
        <textarea id="bt-bulk" name="names" rows="8" class="admin-input" placeholder="Paste one tag per line…">{{ old('names') }}</textarea>
        <p class="admin-form-help">One tag per line. Existing tags (matched by name, case-insensitive) and duplicate lines are skipped automatically.</p>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Add tags</button>
      </div>
    </form>
  </div>
  <div class="admin-card">
    <table class="admin-table">
      <thead>
        <tr><th>Name</th><th>Slug</th><th>Posts</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($tags as $tag)
          <tr>
            <td><strong>{{ $tag->name }}</strong></td>
            <td>{{ $tag->slug }}</td>
            <td>{{ $tag->posts_count }}</td>
            <td>
              <a href="{{ route('admin.blog.tags.edit', $tag) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form action="{{ route('admin.blog.tags.destroy', $tag) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this tag?');">
                @csrf @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="admin-form-help">No tags yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
