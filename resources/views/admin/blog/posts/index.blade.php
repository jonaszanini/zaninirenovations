@extends('admin.layouts.app')

@section('heading', 'Blog posts')

@section('content')
  @php
    $activeFilterCount = \App\Support\AdminListFilters::activeCount([
        'q' => $q ?? '',
        'category' => $categoryId ?? 0,
        'sort' => $sort ?? 'updated',
    ], [
        'category' => 0,
        'sort' => 'updated',
    ]);
  @endphp
  <div class="bp-list">
    <div class="bp-list-toolbar">
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
      <a href="{{ route('admin.blog.posts.create') }}" class="admin-btn admin-btn--primary admin-btn--sm bp-list-new">
        <i class="fa fa-plus" aria-hidden="true"></i> New post
      </a>
    </div>

    @component('admin.partials.filters_accordion', [
      'id' => 'admin-filters-blog-posts',
      'storageKey' => 'admin-filters:blog-posts',
      'activeCount' => $activeFilterCount,
    ])
      <form method="get" action="{{ route('admin.blog.posts.index') }}" class="bp-list-filters">
        @if ($status !== 'all')
          <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div class="cp-filter cp-filter--search">
          <label for="bp-list-q">Search</label>
          <input type="search" id="bp-list-q" name="q" value="{{ $q }}" placeholder="Search posts" class="admin-input">
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
      </form>
    @endcomponent

    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--compact admin-table--stack bp-list-table">
          <thead>
            <tr>
              <th class="bp-list-col bp-list-col--post" scope="col">Post</th>
              <th class="bp-list-col bp-list-col--cat" scope="col">Category</th>
              <th class="bp-list-col bp-list-col--status" scope="col">Status</th>
              <th class="bp-list-col bp-list-col--date" scope="col">Published</th>
              <th class="bp-list-col bp-list-col--views" scope="col">Views</th>
              <th class="bp-list-col bp-list-col--actions" scope="col" aria-label="Actions"></th>
            </tr>
          </thead>
          <tbody>
            @forelse ($posts as $post)
              @php
                $seoKeys = $post->seoIssueKeys();
                if ($post->isScheduled()) {
                  $statusLabel = 'Scheduled';
                  $statusClass = 'blog-post-status--scheduled';
                } elseif ($post->status === \App\Models\BlogPost::STATUS_PUBLISHED) {
                  $statusLabel = 'Published';
                  $statusClass = 'blog-post-status--published';
                } else {
                  $statusLabel = 'Draft';
                  $statusClass = 'blog-post-status--draft';
                }
                $categoryNames = $post->categories->pluck('name')->filter()->values();
                $firstCategory = $categoryNames->first();
                $extraCategoryCount = max(0, $categoryNames->count() - 1);
                $categoryTitle = $categoryNames->implode(', ');
                $publishedLabel = $post->published_at ? $post->displayDate() : '—';
                $viewsLabel = number_format($post->views_count);
              @endphp
              <tr>
                <td class="bp-list-col bp-list-col--post">
                  <div class="bp-list-post">
                    @if ($post->featuredImageListSrc())
                      <span class="bp-list-thumb">
                        <img src="{{ $post->featuredImageListSrc() }}" alt="" onerror="var t=this.closest('.bp-list-thumb'); if (t) t.setAttribute('hidden','hidden');">
                      </span>
                    @endif
                    <span class="bp-list-post__text">
                      <a href="{{ route('admin.blog.posts.edit', $post) }}" class="bp-list-post__title">{{ $post->title }}</a>
                      @if ($post->pending_comments_count)
                        <span class="bp-seo-badge bp-seo-badge--warn">{{ $post->pending_comments_count }} pending</span>
                      @endif
                      <span class="bp-list-seo bp-list-seo--card">
                        @if ($seoKeys === [])
                          <span class="bp-seo-badge bp-seo-badge--ok">OK</span>
                        @else
                          @foreach ($seoKeys as $key)
                            <span class="bp-seo-badge bp-seo-badge--warn">{{ ['meta' => 'Meta', 'seo' => 'SEO title', 'image' => 'Image', 'alt' => 'Alt'][$key] ?? $key }}</span>
                          @endforeach
                        @endif
                      </span>
                      <span class="bp-list-post__meta">
                        <span class="bp-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                        <span class="bp-list-post__meta-cats">
                          @if ($firstCategory)
                            <span class="bp-list-cat" title="{{ $categoryTitle }}">
                              <span class="bp-list-cat__name">{{ $firstCategory }}</span>
                              @if ($extraCategoryCount > 0)
                                <span class="bp-list-cat__more">+{{ $extraCategoryCount }}</span>
                              @endif
                            </span>
                          @else
                            —
                          @endif
                        </span>
                        <span>{{ $publishedLabel }}</span>
                        <span>{{ $viewsLabel }} views</span>
                      </span>
                    </span>
                  </div>
                </td>
                <td class="bp-list-col bp-list-col--cat" data-label="Category" @if ($categoryTitle !== '') title="{{ $categoryTitle }}" @endif>
                  @if ($firstCategory)
                    <span class="bp-list-cat">
                      <span class="bp-list-cat__name">{{ $firstCategory }}</span>
                      @if ($extraCategoryCount > 0)
                        <span class="bp-list-cat__more">+{{ $extraCategoryCount }}</span>
                      @endif
                    </span>
                  @else
                    —
                  @endif
                </td>
                <td class="bp-list-col bp-list-col--status" data-label="Status">
                  <span class="bp-status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </td>
                <td class="bp-list-col bp-list-col--date" data-label="Published">{{ $publishedLabel }}</td>
                <td class="bp-list-col bp-list-col--views" data-label="Views">{{ $viewsLabel }}</td>
                <td class="admin-table__actions bp-list-col bp-list-col--actions">
                  <div class="bp-list-actions">
                    @if ($post->isPublishedNow())
                      <a href="{{ $post->publicUrl() }}" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" target="_blank" rel="noopener" aria-label="View on site" title="View">
                        <i class="fa fa-external-link" aria-hidden="true"></i>
                      </a>
                    @else
                      <a href="{{ route('admin.blog.posts.preview', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" target="_blank" rel="noopener" aria-label="Preview" title="Preview">
                        <i class="fa fa-eye" aria-hidden="true"></i>
                      </a>
                    @endif
                    <a href="{{ route('admin.blog.posts.edit', $post) }}" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn" aria-label="Edit" title="Edit">
                      <i class="fa fa-pencil" aria-hidden="true"></i>
                    </a>
                    <div class="admin-actions-dropdown">
                      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm bp-list-icon-btn admin-actions-dropdown__toggle" aria-label="More actions">
                        <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                      </button>
                      <div class="admin-actions-dropdown__menu">
                        <form action="{{ route('admin.blog.posts.duplicate', $post) }}" method="post" class="admin-actions-dropdown__form">
                          @csrf
                          <button type="submit" class="admin-actions-dropdown__item">
                            <i class="fa fa-copy" aria-hidden="true"></i> Duplicate
                          </button>
                        </form>
                        <form action="{{ route('admin.blog.posts.destroy', $post) }}" method="post" class="admin-actions-dropdown__form"
                              data-confirm="Delete “{{ $post->title }}”? This cannot be undone."
                              data-confirm-title="Delete post"
                              data-confirm-danger>
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="admin-actions-dropdown__item admin-actions-dropdown__item--danger">
                            <i class="fa fa-trash" aria-hidden="true"></i> Delete
                          </button>
                        </form>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="admin-form-help">No posts match these filters.</td>
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
