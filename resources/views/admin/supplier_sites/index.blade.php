@extends('admin.layouts.app')

@section('heading', 'Suppliers')

@push('head')
  <style>
    .ss-tag-chip {
      display: inline-block;
      margin: 0 4px 4px 0;
      padding: 2px 8px;
      border-radius: 999px;
      background: var(--admin-elevated);
      color: var(--admin-heading);
      border: 1px solid var(--admin-border);
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
    .ss-notes-preview { max-width: 220px; color: var(--admin-muted); font-size: 13px; }
    .ss-favorite { color: #d97706; }
    .ss-distance-cell { white-space: nowrap; font-size: 13px; }
    .ss-distance-refresh { min-width: 32px; padding-left: 8px; padding-right: 8px; }
    .ss-th-sort a {
      color: inherit;
      text-decoration: none;
      white-space: nowrap;
    }
    .ss-th-sort a:hover { text-decoration: underline; }
    .ss-th-sort__head {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      flex-wrap: nowrap;
    }
    .ss-distance-error {
      color: var(--admin-muted);
      font-size: 12px;
    }
    .ss-url-actions {
      display: flex;
      flex-wrap: nowrap;
      align-items: center;
      gap: 4px;
    }
    .ss-url-action {
      min-width: 32px;
      padding-left: 8px;
      padding-right: 8px;
    }
    .ss-logo-thumb {
      display: block;
      width: 32px;
      height: 32px;
      object-fit: contain;
      border-radius: 4px;
      background: var(--admin-elevated);
      border: 1px solid var(--admin-border);
    }
    .ss-name-cell {
      display: flex;
      align-items: center;
      gap: 8px;
      min-width: 0;
    }
    .ss-name-cell strong {
      min-width: 0;
    }
  </style>
@endpush

@php
  $sortQuery = static function (string $column) use ($q, $tagSlug, $perPage, $sort, $sortDir) {
      $nextDir = ($sort === $column && $sortDir === 'asc') ? 'desc' : 'asc';
      return route('admin.supplier-sites.index', array_filter([
          'q' => $q !== '' ? $q : null,
          'tag' => $tagSlug !== '' ? $tagSlug : null,
          'per_page' => $perPage,
          'sort' => $column,
          'dir' => $nextDir,
      ]));
  };
@endphp

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
          <span class="admin-list-filters__per-page-label">Sort</span>
          <select name="sort" class="admin-input admin-list-filters__per-page-select" aria-label="Sort suppliers" onchange="this.form.submit()">
            <option value="">Default</option>
            <option value="distance" @selected(($sort ?? '') === 'distance')>Distance</option>
          </select>
        </label>
        @if (($sort ?? '') === 'distance')
          <input type="hidden" name="dir" value="{{ $sortDir }}">
        @endif
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
      <table class="admin-table admin-table--stack ss-table">
        <thead>
          <tr>
            <th style="width:30px;"></th>
            <th>Name</th>
            <th style="width:70px;">Website</th>
            <th style="width:120px;">Tags</th>
            <th>Email</th>
            <th style="width:110px;">Phone</th>
            <th style="width:140px;">Address</th>
            <th class="ss-th-sort" style="width:120px;">
              <span class="ss-th-sort__head">
                @include('admin.partials.tipped_label', [
                  'label' => 'Distance',
                  'tipText' => 'One-way driving distance from your home address (set on the Vehicles page). Refresh updates the value.',
                ])
                <a href="{{ $sortQuery('distance') }}" class="ss-th-sort-link" aria-label="Sort by distance">
                  @if (($sort ?? '') === 'distance')
                    <i class="fa fa-sort-{{ $sortDir === 'desc' ? 'down' : 'up' }}" aria-hidden="true"></i>
                  @else
                    <i class="fa fa-sort" aria-hidden="true"></i>
                  @endif
                </a>
              </span>
            </th>
            <th style="width:140px;">Notes</th>
            <th style="width:130px;"></th>
          </tr>
        </thead>
        <tbody>
          @forelse ($supplierSites as $site)
            <tr>
              <td data-label="">
                @if ($site->is_favorite)
                  <i class="fa fa-star ss-favorite" title="Favorite" aria-hidden="true"></i>
                @endif
              </td>
              <td data-label="Name">
                <div class="ss-name-cell">
                  @if (filled($site->logo_url))
                    <img src="{{ $site->logo_url }}"
                         alt=""
                         class="ss-logo-thumb"
                         width="32"
                         height="32"
                         loading="lazy"
                         onerror="this.style.display='none'">
                  @endif
                  <strong>{{ $site->name }}</strong>
                </div>
              </td>
              <td data-label="Website">
                @if (filled($site->url))
                  <div class="ss-url-actions">
                    <a href="{{ $site->url }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="admin-btn admin-btn--ghost admin-btn--sm ss-url-action"
                       title="Open website: {{ $site->url }}"
                       aria-label="Open {{ $site->name }} website">
                      <i class="fa fa-external-link" aria-hidden="true"></i>
                    </a>
                    <button type="button"
                            class="admin-btn admin-btn--ghost admin-btn--sm ss-url-action ss-url-copy"
                            title="Copy link: {{ $site->url }}"
                            aria-label="Copy {{ $site->name }} website link"
                            data-copy-url="{{ $site->url }}">
                      <i class="fa fa-copy" aria-hidden="true"></i>
                    </button>
                  </div>
                @else
                  —
                @endif
              </td>
              <td data-label="Tags">
                @forelse ($site->tags as $tag)
                  <span class="ss-tag-chip">{{ $tag->name }}</span>
                @empty
                  <span class="admin-form-help">—</span>
                @endforelse
              </td>
              <td data-label="Email">
                @if (filled($site->email))
                  <a href="mailto:{{ $site->email }}" class="admin-text-link">{{ $site->email }}</a>
                @else
                  —
                @endif
              </td>
              <td data-label="Phone">{{ filled($site->phone) ? \App\Models\ClientProject::formatPhone($site->phone) : '—' }}</td>
              <td data-label="Address">
                @if (filled($site->address))
                  <span class="ss-notes-preview" title="{{ $site->address }}">{{ \Illuminate\Support\Str::limit($site->address, 40) }}</span>
                @else
                  —
                @endif
              </td>
              <td data-label="Distance" class="ss-distance-cell">
                @php
                  $distanceSortUrl = $sortQuery('distance');
                @endphp
                <span class="admin-sr-only"><a href="{{ $distanceSortUrl }}">Sort by distance</a></span>
                <span data-ss-distance-label="{{ $site->id }}" @class(['ss-distance-error' => filled($site->distance_error) && $site->distance_km === null])>{{ $site->distanceDisplayLabel() }}</span>
                @if ($site->distanceErrorForTip())
                  @include('admin.partials.info_tip', [
                    'tipText' => $site->distanceErrorForTip(),
                    'tipLabel' => 'Distance error',
                  ])
                @endif
                @if (filled($site->address))
                  <button type="button"
                          class="admin-btn admin-btn--ghost admin-btn--sm ss-distance-refresh"
                          title="Refresh driving distance"
                          aria-label="Refresh driving distance for {{ $site->name }}"
                          data-refresh-url="{{ route('admin.supplier-sites.refresh-distance', $site) }}"
                          data-site-id="{{ $site->id }}">
                    <i class="fa fa-refresh" aria-hidden="true"></i>
                  </button>
                @endif
              </td>
              <td data-label="Notes">
                @if (filled($site->notes))
                  <span class="ss-notes-preview" title="{{ $site->notes }}">{{ \Illuminate\Support\Str::limit($site->notes, 50) }}</span>
                @else
                  —
                @endif
              </td>
              <td class="admin-table__actions" data-label="">
                <a href="{{ route('admin.supplier-sites.edit', $site) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="{{ route('admin.supplier-sites.destroy', $site) }}" method="post" style="display:inline;" onsubmit="return confirm('Remove this supplier?');">
                  @csrf @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="10" class="admin-form-help">
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

@push('scripts')
  <script>
    (function () {
      document.querySelectorAll('.ss-url-copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var url = btn.getAttribute('data-copy-url');
          if (!url) return;

          function showCopied() {
            btn.classList.add('is-copied');
            var icon = btn.querySelector('i');
            var prevClass = icon ? icon.className : '';
            if (icon) icon.className = 'fa fa-check';
            window.setTimeout(function () {
              btn.classList.remove('is-copied');
              if (icon) icon.className = prevClass;
            }, 1600);
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(showCopied).catch(function () {
              window.prompt('Copy link:', url);
            });
          } else {
            window.prompt('Copy link:', url);
          }
        });
      });

      document.querySelectorAll('.ss-distance-refresh').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var url = btn.getAttribute('data-refresh-url');
          var siteId = btn.getAttribute('data-site-id');
          if (!url || !siteId) return;
          btn.disabled = true;
          fetch(url, {
            method: 'POST',
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
          })
            .then(function (res) { return res.json(); })
            .then(function (payload) {
              var label = document.querySelector('[data-ss-distance-label="' + siteId + '"]');
              if (label && payload.distance_label) {
                label.textContent = payload.distance_label;
              }
            })
            .finally(function () {
              btn.disabled = false;
            });
        });
      });
    })();
  </script>
@endpush
