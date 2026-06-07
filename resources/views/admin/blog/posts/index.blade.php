@extends('admin.layouts.app')

@section('heading', 'Blog posts')

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.blog.posts.index') }}" class="cp-clients-toolbar-search">
      <input type="search" name="q" value="{{ $q }}" placeholder="Search title, slug, content…" class="admin-input cp-clients-toolbar-search__input">
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm"><i class="fa fa-search"></i> Search</button>
    </form>
    <a href="{{ route('admin.blog.posts.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New post
    </a>
  </div>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Status</th>
            <th>Published</th>
            <th style="width:80px;">Views</th>
            <th style="width:160px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($posts as $post)
            <tr>
              <td>
                <strong>{{ $post->title }}</strong>
                @if ($post->featured_image_path)
                  <br><small class="admin-form-help">Has featured image</small>
                @endif
              </td>
              <td>{{ $post->categories->pluck('name')->implode(', ') ?: '—' }}</td>
              <td>
                @if ($post->isScheduled())
                  <span class="blog-post-status--scheduled">Scheduled</span>
                @elseif ($post->status === \App\Models\BlogPost::STATUS_PUBLISHED)
                  <span class="blog-post-status--published">Published</span>
                @else
                  <span class="blog-post-status--draft">Draft</span>
                @endif
              </td>
              <td>{{ $post->published_at ? $post->displayDate() : '—' }}</td>
              <td>{{ number_format($post->views_count) }}</td>
              <td>
                @if ($post->isPublishedNow())
                  <a href="{{ $post->publicUrl() }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener">
                    <i class="fa fa-external-link"></i>
                  </a>
                @endif
                <a href="{{ route('admin.blog.posts.edit', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="admin-form-help">No posts yet. Create your first post.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($posts->hasPages())
      <div style="margin-top:16px;">{{ $posts->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection
