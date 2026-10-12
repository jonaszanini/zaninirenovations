@extends('admin.layouts.app')

@section('heading', 'Blog comments')

@section('content')
  <div class="bp-list">
    <div class="bp-list-tabs" role="tablist" aria-label="Comment status">
      @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'all' => 'All'] as $key => $label)
        <a href="{{ route('admin.blog.comments.index', array_filter(['status' => $key, 'q' => $q ?: null])) }}"
           class="bp-list-tab {{ $status === $key ? 'is-active' : '' }}"
           role="tab"
           aria-selected="{{ $status === $key ? 'true' : 'false' }}">
          {{ $label }}
          <span class="bp-list-tab__count">{{ number_format($counts[$key] ?? 0) }}</span>
        </a>
      @endforeach
    </div>

    @php
      $activeFilterCount = \App\Support\AdminListFilters::activeCount([
          'q' => $q ?? '',
      ]);
    @endphp
    @component('admin.partials.filters_accordion', [
      'id' => 'admin-filters-blog-comments',
      'storageKey' => 'admin-filters:blog-comments',
      'activeCount' => $activeFilterCount,
    ])
      <form method="get" action="{{ route('admin.blog.comments.index') }}" class="cp-filters cp-filters--3col bp-list-filters">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="cp-filter">
          <label for="bp-comments-q">Search</label>
          <input type="search" id="bp-comments-q" name="q" value="{{ $q }}" placeholder="Author, email, comment, post…" class="admin-input">
        </div>
        <div class="cp-filter cp-filter--actions">
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Apply</button>
          <a href="{{ route('admin.blog.comments.index', ['status' => $status]) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
        </div>
      </form>
    @endcomponent

    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--stack">
          <thead>
            <tr>
              <th>Post</th>
              <th>Author</th>
              <th>Comment</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($comments as $comment)
              <tr>
                <td data-label="Post">
                  @if ($comment->post)
                    <a href="{{ route('admin.blog.posts.edit', $comment->post) }}">{{ $comment->post->title }}</a>
                  @else
                    —
                  @endif
                </td>
                <td data-label="Author">
                  {{ $comment->author_name }}
                  <div class="admin-form-help">{{ $comment->author_email }}</div>
                </td>
                <td data-label="Comment">{{ Str::limit($comment->body, 140) }}</td>
                <td data-label="Date">{{ $comment->created_at->format('M j, Y') }}</td>
                <td class="admin-table__actions" data-label="">
                  <div class="bp-list-actions">
                    @if (! $comment->is_approved)
                      <form action="{{ route('admin.blog.comments.approve', $comment) }}" method="post" class="bp-inline-form">
                        @csrf
                        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Approve</button>
                      </form>
                    @endif
                    <form action="{{ route('admin.blog.comments.destroy', $comment) }}" method="post" class="bp-inline-form"
                          data-confirm="Delete this comment?"
                          data-confirm-title="Delete comment"
                          data-confirm-danger>
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="admin-form-help">No comments.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($comments->hasPages())
        <div class="bp-list-pager">{{ $comments->links('pagination.admin') }}</div>
      @endif
    </div>
  </div>
@endsection
