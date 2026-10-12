@extends('admin.layouts.app')

@section('heading')
  Latest Projects Photos
  <x-admin.info-tip label="Latest Projects Photos">
    @php
      $portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
    @endphp
    Each project is a public page at /portfolio/… as soon as you save it. Photos are stored as WebP at {{ $portfolioWebp['quality'] }}% quality, max edge {{ $portfolioWebp['max_edge_px'] }}px, about {{ (int) round($portfolioWebp['max_bytes'] / 1024) }}&nbsp;KB each; up to 15 per project.
  </x-admin.info-tip>
@endsection

@section('content')
  <div class="admin-toolbar">
    <div class="admin-toolbar-actions">
      <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" title="Manage portfolio categories">
        <i class="fa fa-cog"></i> Categories
      </a>
      <a href="{{ route('admin.portfolio.create') }}" class="admin-btn admin-btn--primary">
        <i class="fa fa-plus"></i> New portfolio project
      </a>
    </div>
  </div>

  @php
    $activeFilterCount = \App\Support\AdminListFilters::activeCount([
        'q' => $filters['q'] ?? '',
        'category' => $filters['category'] ?? '',
    ]);
  @endphp
  @component('admin.partials.filters_accordion', [
    'id' => 'admin-filters-portfolio',
    'storageKey' => 'admin-filters:portfolio',
    'activeCount' => $activeFilterCount,
  ])
    <form method="get" action="{{ route('admin.portfolio.index') }}" class="cp-filters cp-filters--3col portfolio-admin-filters">
      <div class="cp-filter">
        <label for="portfolio-filter-q">Search</label>
        <input class="admin-input" type="search" id="portfolio-filter-q" name="q" value="{{ $filters['q'] }}" placeholder="Project title or description">
      </div>
      <div class="cp-filter">
        <label for="portfolio-filter-category">Category</label>
        <select class="admin-input" id="portfolio-filter-category" name="category">
          <option value="">All categories</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat->slug }}" @selected($filters['category'] === $cat->slug)>{{ $cat->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
        <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
    </form>
  @endcomponent

  @if ($projects->isEmpty())
    <div class="admin-card">
      <div class="admin-empty">
        <p>{{ ($filters['q'] !== '' || $filters['category'] !== '')
          ? 'No portfolio projects match these filters.'
          : 'No portfolio projects yet.' }}</p>
        @if ($filters['q'] === '' && $filters['category'] === '')
          <a href="{{ route('admin.portfolio.create') }}" class="admin-btn admin-btn--primary">Add your first project</a>
        @else
          <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost">Clear filters</a>
        @endif
      </div>
    </div>
  @else
    <div class="portfolio-admin-grid">
      @foreach ($projects as $project)
        @php
          $cover = $project->coverPhoto;
        @endphp
        <article class="portfolio-admin-card">
          <a href="{{ route('admin.portfolio.edit', $project) }}" class="portfolio-admin-card__media" title="Edit {{ $project->title }}">
            @if ($cover)
              <img src="{{ $cover->url() }}" alt="" loading="lazy" decoding="async">
            @else
              <span class="portfolio-admin-card__placeholder" aria-hidden="true">
                <i class="fa fa-image"></i>
                <span>No photo</span>
              </span>
            @endif
          </a>
          <div class="portfolio-admin-card__body">
            <h3 class="portfolio-admin-card__title">
              <a href="{{ route('admin.portfolio.edit', $project) }}">{{ $project->title }}</a>
            </h3>
            @if ($project->categories->isNotEmpty())
              <div class="portfolio-admin-card__tags">
                @foreach ($project->categories->sortBy(fn ($c) => [$c->sort_order, strtolower($c->name)]) as $cat)
                  <span class="admin-badge">{{ $cat->name }}</span>
                @endforeach
              </div>
            @endif
            <p class="portfolio-admin-card__meta">
              @if ($project->townLabel() !== '')
                {{ $project->townLabel() }} ·
              @endif
              {{ $project->photos_count }} / 15 photos
              @if ($project->completed_on)
                · {{ \App\Support\EasternStandardTimeDisplay::formatCalendarMediumDate($project->completed_on) }}
              @endif
            </p>
            <div class="portfolio-admin-card__actions">
              @if ($project->slug)
                <a href="{{ $project->publicUrl() }}" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener">View page</a>
              @endif
              <a href="{{ route('admin.portfolio.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
              <form action="{{ route('admin.portfolio.destroy', $project) }}" method="post" onsubmit="return confirm('Delete this project and all photos?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </div>
          </div>
        </article>
      @endforeach
    </div>
    <div class="admin-pagination">
      {{ $projects->links('pagination.admin') }}
    </div>
  @endif
@endsection
