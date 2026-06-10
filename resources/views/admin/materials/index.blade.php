@extends('admin.layouts.app')

@section('heading', 'Materials')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <form method="get" action="{{ route('admin.materials.index') }}" class="mat-toolbar-search">
      <input type="search" name="q" value="{{ $q }}" placeholder="Search name, SKU, vendor…" class="admin-input mat-toolbar-search__input">
      @if ($categories->isNotEmpty())
        <select name="category" class="admin-input mat-toolbar-search__category">
          <option value="">All categories</option>
          @foreach ($categories as $cat)
            <option value="{{ $cat }}" @selected($category === $cat)>{{ $cat }}</option>
          @endforeach
        </select>
      @endif
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <div class="mat-toolbar-actions">
      <a href="{{ route('admin.materials.create') }}" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-plus"></i> Add material
      </a>
      <p class="admin-form-help mat-toolbar-total">
        {{ number_format($totalMaterials) }} {{ $totalMaterials === 1 ? 'material' : 'materials' }} in database
      </p>
    </div>
  </div>

  <p class="admin-form-help mat-sync-hint">
    <i class="fa fa-info-circle"></i>
    Refresh retail prices locally with
    <code>php artisan materials:sync-prices --dry-run</code> (requires Ollama/Browsershot on your PC — see project docs).
  </p>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table mat-table">
        <thead>
          <tr>
            <th class="mat-col-image" scope="col"><span class="admin-sr-only">Image</span></th>
            <th>Name</th>
            <th>Category</th>
            <th>Vendor</th>
            <th>SKU</th>
            <th class="mat-col-price">Price</th>
            <th>Last synced</th>
            <th style="width:90px;">Active</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse ($materials as $material)
            <tr class="{{ $material->is_active ? '' : 'mat-row--inactive' }}">
              <td class="mat-col-image">
                @if ($material->image_url)
                  <img src="{{ $material->image_url }}" alt="" class="mat-thumb" loading="lazy" width="48" height="48">
                @else
                  <span class="mat-thumb mat-thumb--empty" aria-hidden="true"><i class="fa fa-image"></i></span>
                @endif
              </td>
              <td>
                <strong>{{ $material->name }}</strong>
                @if ($material->product_url)
                  <br><a href="{{ $material->product_url }}" target="_blank" rel="noopener" class="admin-text-link mat-product-link">Product page</a>
                @endif
              </td>
              <td>{{ $material->category ?: '—' }}</td>
              <td>{{ $material->vendor ?: '—' }}</td>
              <td>{{ $material->store_sku ?: '—' }}</td>
              <td class="mat-col-price">{{ $material->formattedPrice() }}</td>
              <td>
                @if ($material->last_scraped_at)
                  <span class="mat-scrape-badge mat-scrape-badge--{{ $material->last_scrape_status ?? 'unknown' }}">
                    {{ $material->last_scrape_status }}
                  </span>
                  <br><small class="admin-form-help">{{ \App\Support\EasternStandardTimeDisplay::formatInstantCalendarDate($material->last_scraped_at) }}</small>
                @else
                  <span class="admin-form-help">—</span>
                @endif
              </td>
              <td>{{ $material->is_active ? 'Yes' : 'No' }}</td>
              <td>
                <a href="{{ route('admin.materials.edit', $material) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="admin-form-help">No materials yet. Add your first item or paste product URLs for local price sync.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($materials->hasPages())
      <div style="margin-top:16px;">{{ $materials->links('pagination.admin') }}</div>
    @endif
  </div>
@endsection
