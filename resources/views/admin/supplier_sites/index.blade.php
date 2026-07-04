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
    .ss-toolbar-form {
      display: flex;
      flex-direction: column;
      gap: 12px;
      flex: 1;
      min-width: 0;
    }
    .ss-toolbar-search-row {
      display: flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 8px;
    }
    .ss-toolbar-search-row .admin-input {
      flex: 1 1 auto;
      min-width: 0;
      max-width: none;
    }
    .ss-toolbar-filters {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
    }
    .ss-notes-preview { max-width: 220px; color: #6b7280; font-size: 13px; }
    .ss-favorite { color: #d97706; }
  </style>
@endpush

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.supplier-sites.index') }}" class="ss-toolbar-form">
      <div class="ss-toolbar-search-row">
        <input type="search"
               name="q"
               value="{{ $q }}"
               placeholder="Search name, URL, email, address, notes, phone, or tags…"
               class="admin-input">
        <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-search"></i> Search
        </button>
      </div>
      <div class="ss-toolbar-filters admin-list-filters">
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
      </div>
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
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
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
              <td>
                @if (filled($site->email))
                  <a href="mailto:{{ $site->email }}" class="admin-text-link">{{ $site->email }}</a>
                @else
                  —
                @endif
              </td>
              <td>{{ filled($site->phone) ? \App\Models\ClientProject::formatPhone($site->phone) : '—' }}</td>
              <td>
                @if (filled($site->address))
                  <span class="ss-notes-preview">{{ \Illuminate\Support\Str::limit($site->address, 60) }}</span>
                @else
                  —
                @endif
              </td>
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
              <td colspan="9" class="admin-form-help">
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
