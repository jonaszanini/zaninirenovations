@extends('admin.layouts.app')

@section('heading', 'Blog tags')

@section('content')
  @include('admin.blog._taxonomy_list_toolbar', [
    'indexRoute' => route('admin.blog.tags.index'),
    'q' => $q,
    'perPage' => $perPage,
    'searchPlaceholder' => 'Search name or slug…',
    'createRoute' => route('admin.blog.tags.create'),
    'createLabel' => 'New tag',
  ])
  <details class="admin-accordion admin-card" @if(old('names')) open @endif>
    <summary class="admin-accordion__summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="admin-accordion__title">
        Bulk add tags
        <x-admin.info-tip label="Bulk add tags">One tag per line. Existing tags (matched by name, case-insensitive) and duplicate lines are skipped automatically.</x-admin.info-tip>
      </span>
    </summary>
    <div class="admin-accordion__panel">
      <form method="post" action="{{ route('admin.blog.tags.bulk') }}">
        @csrf
        <div class="admin-form-group">
          <textarea id="bt-bulk" name="names" rows="8" class="admin-input" aria-label="Tag list, one per line" placeholder="Paste one tag per line…">{{ old('names') }}</textarea>
        </div>
        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary">Add tags</button>
        </div>
      </form>
    </div>
  </details>
  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table admin-table--stack">
      <thead>
        <tr><th>Name</th><th>Slug</th><th style="width:60px;">Posts</th><th style="width:140px;"></th></tr>
      </thead>
      <tbody>
        @forelse ($tags as $tag)
          <tr>
            <td data-label="Name"><strong>{{ $tag->name }}</strong></td>
            <td data-label="Slug">{{ $tag->slug }}</td>
            <td data-label="Posts">{{ $tag->posts_count }}</td>
            <td class="admin-table__actions" data-label="">
              <a href="{{ route('admin.blog.tags.edit', $tag) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form action="{{ route('admin.blog.tags.destroy', $tag) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this tag?');">
                @csrf @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="4" class="admin-form-help">
              @if ($q !== '')
                No tags match your search.
              @else
                No tags yet.
              @endif
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
    </div>
    @include('admin.blog._taxonomy_list_footer', ['items' => $tags])
  </div>
@endsection
