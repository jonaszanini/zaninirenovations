@extends('admin.layouts.app')

@section('heading', 'Blog posts')

@section('content')
  <div class="bp-list">
    <div class="bp-list-tabs" role="tablist" aria-label="Post status">
      @foreach (['all' => 'All', 'draft' => 'Drafts', 'scheduled' => 'Scheduled', 'published' => 'Published'] as $key => $label)
        <a href="{{ route('admin.blog.posts.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $q ?: null, 'category' => $categoryId ?: null, 'sort' => $sort !== 'updated' ? $sort : null])) }}"
           class="bp-list-tab {{ $status === $key ? 'is-active' : '' }}"
           role="tab"
           aria-selected="{{ $status === $key ? 'true' : 'false' }}">
          {{ $label }}
          <span class="bp-list-tab__count">{{ number_format($counts[$key] ?? 0) }}</span>
        </a>
      @endforeach
    </div>

    <form method="get" action="{{ route('admin.blog.posts.index') }}" class="cp-filters cp-filters--5col bp-list-filters">
      @if ($status !== 'all')
        <input type="hidden" name="status" value="{{ $status }}">
      @endif
      <div class="cp-filter">
        <label for="bp-list-q">Search</label>
        <input type="search" id="bp-list-q" name="q" value="{{ $q }}" placeholder="Title, slug, content…" class="admin-input">
      </div>
      <div class="cp-filter">
        <label for="bp-list-cat">Category</label>
        <select id="bp-list-cat" name="category" class="admin-input">
          <option value="">All categories</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->id }}" @selected($categoryId === (int) $cat->id)>{{ $cat->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="cp-filter">
        <label for="bp-list-sort">Sort</label>
        <select id="bp-list-sort" name="sort" class="admin-input">
          <option value="updated" @selected($sort === 'updated')>Updated</option>
          <option value="published" @selected($sort === 'published')>Published</option>
          <option value="title" @selected($sort === 'title')>Title</option>
          <option value="views" @selected($sort === 'views')>Views</option>
        </select>
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Apply</button>
        <a href="{{ route('admin.blog.posts.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
      <div class="cp-filter cp-filter--actions">
        <a href="{{ route('admin.blog.posts.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
          <i class="fa fa-plus"></i> New post
        </a>
      </div>
    </form>

    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--stack bp-list-table">
          <thead>
            <tr>
              <th class="bp-list-table__thumb">Post</th>
              <th>Category</th>
              <th>Status</th>
              <th>SEO</th>
              <th>Published</th>
              <th>Views</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($posts as $post)
              <tr>
                <td data-label="Post" class="bp-list-post">
                  <span class="bp-list-thumb" aria-hidden="true">
                    @if ($post->featuredImageUrl())
                      <img src="{{ $post->featuredImageUrl() }}" alt="">
                    @else
                      <span class="bp-list-thumb__empty"></span>
                    @endif
                  </span>
                  <span class="bp-list-post__text">
                    <a href="{{ route('admin.blog.posts.edit', $post) }}" class="bp-list-post__title">{{ $post->title }}</a>
                    @if ($post->pending_comments_count)
                      <span class="bp-seo-badge bp-seo-badge--warn">{{ $post->pending_comments_count }} pending comments</span>
                    @endif
                  </span>
                </td>
                <td data-label="Category">{{ $post->categories->pluck('name')->implode(', ') ?: '—' }}</td>
                <td data-label="Status">
                  @if ($post->isScheduled())
                    <span class="blog-post-status--scheduled">Scheduled</span>
                  @elseif ($post->status === \App\Models\BlogPost::STATUS_PUBLISHED)
                    <span class="blog-post-status--published">Published</span>
                  @else
                    <span class="blog-post-status--draft">Draft</span>
                  @endif
                </td>
                <td data-label="SEO" class="bp-list-seo">
                  @php $seoKeys = $post->seoIssueKeys(); @endphp
                  @if ($seoKeys === [])
                    <span class="bp-seo-badge bp-seo-badge--ok">OK</span>
                  @else
                    @foreach ($seoKeys as $key)
                      <span class="bp-seo-badge bp-seo-badge--warn">{{ ['meta' => 'Meta', 'seo' => 'SEO title', 'image' => 'Image', 'alt' => 'Alt'][$key] ?? $key }}</span>
                    @endforeach
                  @endif
                </td>
                <td data-label="Published">{{ $post->published_at ? $post->displayDate() : '—' }}</td>
                <td data-label="Views">{{ number_format($post->views_count) }}</td>
                <td class="admin-table__actions" data-label="">
                  <div class="bp-list-actions">
                    @if ($post->isPublishedNow())
                      <a href="{{ $post->publicUrl() }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener" aria-label="View on site">
                        <i class="fa fa-external-link"></i>
                      </a>
                    @else
                      <a href="{{ route('admin.blog.posts.preview', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener" aria-label="Preview">
                        <i class="fa fa-eye"></i>
                      </a>
                    @endif
                    <a href="{{ route('admin.blog.posts.edit', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                    <form action="{{ route('admin.blog.posts.duplicate', $post) }}" method="post" class="bp-inline-form">
                      @csrf
                      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Duplicate</button>
                    </form>
                    <form action="{{ route('admin.blog.posts.destroy', $post) }}" method="post" class="bp-inline-form"
                          data-confirm="Delete “{{ $post->title }}”? This cannot be undone."
                          data-confirm-title="Delete post"
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
                <td colspan="7" class="admin-form-help">No posts match these filters.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($posts->hasPages())
        <div class="bp-list-pager">{{ $posts->links('pagination.admin') }}</div>
      @endif
    </div>
  </div>
@endsection
