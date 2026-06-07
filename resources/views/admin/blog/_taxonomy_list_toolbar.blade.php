<div class="admin-toolbar">
  <form method="get" action="{{ $indexRoute }}" class="cp-clients-toolbar-search admin-list-filters">
    <input type="search"
           name="q"
           value="{{ $q }}"
           placeholder="{{ $searchPlaceholder }}"
           class="admin-input cp-clients-toolbar-search__input">
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
  <a href="{{ $createRoute }}" class="admin-btn admin-btn--primary admin-btn--sm">
    <i class="fa fa-plus"></i> {{ $createLabel }}
  </a>
</div>
