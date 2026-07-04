@extends('admin.layouts.app')

@section('heading', 'Suppliers')

@push('head')
  <style>
    .ss-tag-chip {
      display: inline-block;
      margin: 0 4px 4px 0;
      padding: 2px 8px;
      border-radius: 999px;
      background: #e8f4f8;
      color: #024768;
      font-size: 12px;
      white-space: nowrap;
    }
    .ss-tag-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 8px; }
    .ss-notes-preview { max-width: 220px; color: #6b7280; font-size: 13px; }
    .ss-favorite { color: #d97706; }
  </style>
@endpush

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.supplier-sites.index') }}" class="cp-clients-toolbar-search admin-list-filters" style="flex:1;">
      <input type="search"
             name="q"
             value="{{ $q }}"
             placeholder="Search name, URL, notes, phone, or tags…"
             class="admin-input cp-clients-toolbar-search__input">
      <label class="admin-list-filters__per-page">
        <span class="admin-list-filters__per-page-label">Tag</span>
        <select name="tag" class="admin-input admin-list-filters__per-page-select" aria-label="Filter by tag" onchange="this.form.submit()">
          <option value="">All tags</option>
          @foreach ($filterTags as $tag)
            <option value="{{ $tag->slug }}" @selected($tagSlug === $tag->slug)>{{ $tag->name }}</option>
          @endforeach
        </select>
      </label>
      <label class="admin-list-filters__per-page">
        <span class="admin-list-filters__per-page-label">Show</span>
        <select name="per_page" class="admin-input admin-list-filters__per-page-select" aria-label="Rows per page" onchange="this.form.submit()">
          @foreach (\App\Support\AdminPaginatedList::PER_PAGE_OPTIONS as $option)
            <option value="{{ $option }}" @selected((string) $perPage === (string) $option)>
              {{ $option === 'all' ? 'All' : $option }}
            </option>
          @endforeach
        </select>
      </label>
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <a href="{{ route('admin.supplier-sites.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> Add supplier
    </a>
  </div>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th style="width:36px;"></th>
            <th>Name</th>
            <th>Website</th>
            <th>Tags</th>
            <th>Phone</th>
            <th>Notes</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($supplierSites as $site)
            <tr>
              <td>
                @if ($site->is_favorite)
                  <i class="fa fa-star ss-favorite" title="Favorite" aria-hidden="true"></i>
                @endif
              </td>
              <td><strong>{{ $site->name }}</strong></td>
              <td>
                <a href="{{ $site->url }}" target="_blank" rel="noopener noreferrer" class="admin-text-link">
                  {{ $site->url }}
                  <i class="fa fa-external-link" aria-hidden="true" style="font-size:11px;margin-left:4px;"></i>
                </a>
              </td>
              <td>
                @forelse ($site->tags as $tag)
                  <span class="ss-tag-chip">{{ $tag->name }}</span>
                @empty
                  <span class="admin-form-help">—</span>
                @endforelse
              </td>
              <td>{{ filled($site->phone) ? $site->phone : '—' }}</td>
              <td>
                @if (filled($site->notes))
                  <span class="ss-notes-preview">{{ \Illuminate\Support\Str::limit($site->notes, 80) }}</span>
                @else
                  —
                @endif
              </td>
              <td style="white-space:nowrap;">
                <a href="{{ route('admin.supplier-sites.edit', $site) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="{{ route('admin.supplier-sites.destroy', $site) }}" method="post" style="display:inline;" onsubmit="return confirm('Remove this supplier?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="admin-form-help">
                @if ($q !== '' || $tagSlug !== '')
                  No suppliers match your search.
                @else
                  No suppliers yet. Add your first landscaping, rental, or material supplier bookmark.
                @endif
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($supplierSites instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $supplierSites->hasPages())
      <div style="padding:12px 16px;">
        {{ $supplierSites->withQueryString()->links() }}
      </div>
    @endif
  </div>
@endsection
