@extends('admin.layouts.app')

@section('heading', 'Materials — Config')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar mat-config-toolbar">
    <p class="admin-toolbar-lede mat-config-toolbar__lede">
      Bulk import Home Depot SKUs and copy local sync commands for your PC.
    </p>
    <div class="mat-config-toolbar__actions">
      <a href="https://serpapi.com/dashboard" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener noreferrer">
        <i class="fa fa-external-link"></i> SerpApi dashboard
      </a>
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to materials
      </a>
    </div>
  </div>

  @if ($usesSerpApi)
    <p class="admin-form-help mat-serpapi-hint">
      Admin sync on this server uses SerpApi.
      <a href="https://serpapi.com/dashboard" target="_blank" rel="noopener noreferrer" class="admin-text-link">Open the SerpApi dashboard</a>
      to see search usage, remaining credits, and recent requests.
    </p>
  @endif

  <div class="mat-admin-panels">
    <details class="admin-accordion admin-card admin-card--narrow mat-bulk-import" @if(old('skus')) open @endif>
      <summary class="admin-accordion__summary">
        <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
        <span class="admin-accordion__title">Bulk import Home Depot SKUs</span>
      </summary>
      <div class="admin-accordion__panel">
        <form id="mat-bulk-form" method="post" action="{{ route('admin.materials.bulk-import') }}"
              data-start-url="{{ route('admin.materials.bulk-import.start') }}"
              data-step-url="{{ route('admin.materials.bulk-import.step') }}">
          @csrf
          <div class="admin-form-group">
            <label for="mat-bulk-skus">SKUs or product URLs</label>
            <textarea id="mat-bulk-skus" name="skus" rows="8" class="admin-input @error('skus') is-invalid @enderror" aria-label="SKU list, one per line" placeholder="Paste one SKU or product URL per line…">{{ old('skus') }}</textarea>
            @error('skus') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help">
              One SKU (e.g. <code>1000108771</code>) or full Home Depot product URL per line.
              Blank lines are ignored. If the same SKU appears more than once in your paste, only the first is imported; the rest are skipped. SKUs already in the catalog are updated.
            </p>
          </div>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="mat-bulk-category">Category</label>
              <input type="text" id="mat-bulk-category" name="category" value="{{ old('category') }}" class="admin-input" list="mat-bulk-category-list" placeholder="e.g. Lumber">
              @if ($categories->isNotEmpty())
                <datalist id="mat-bulk-category-list">
                  @foreach ($categories as $cat)
                    <option value="{{ $cat }}">
                  @endforeach
                </datalist>
              @endif
            </div>
            <div class="admin-form-group">
              <label for="mat-bulk-vendor">Vendor</label>
              <input type="text" id="mat-bulk-vendor" name="vendor" value="{{ old('vendor', 'Home Depot') }}" class="admin-input">
            </div>
          </div>
          <div class="admin-form-group">
            @include('admin.partials.checkbox', [
              'name' => 'sync_prices',
              'id' => 'mat-bulk-sync',
              'checked' => old('sync_prices'),
              'label' => 'Sync prices after import',
            ])
            <p class="admin-form-help">
              When checked, fetches name and price via SerpApi (online admin) or the local store API for each imported item (~2&nbsp;s each).
              For large lists (30+ SKUs), leave unchecked and use <strong>Sync all</strong> on the materials list afterward.
            </p>
          </div>
          <div id="mat-bulk-progress" class="mat-bulk-progress" hidden aria-live="polite">
            <div class="mat-bulk-progress__head">
              <h3 class="mat-bulk-progress__title" id="mat-bulk-phase-label">Import in progress</h3>
              <span class="mat-bulk-progress__counter" id="mat-bulk-counter">0 / 0</span>
            </div>
            <div class="mat-bulk-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="mat-bulk-progressbar">
              <div class="mat-bulk-progress__bar" id="mat-bulk-bar" style="width:0%"></div>
            </div>
            <p class="mat-bulk-progress__message" id="mat-bulk-message">Preparing…</p>
            <dl class="mat-bulk-progress__stats" id="mat-bulk-stats">
              <div><dt>Created</dt><dd id="mat-stat-created">0</dd></div>
              <div><dt>Updated</dt><dd id="mat-stat-updated">0</dd></div>
              <div><dt>Synced</dt><dd id="mat-stat-sync-ok">0</dd></div>
              <div><dt>Sync failed</dt><dd id="mat-stat-sync-failed">0</dd></div>
            </dl>
            <ul class="mat-bulk-progress__log" id="mat-bulk-log"></ul>
            <div class="mat-bulk-progress__done" id="mat-bulk-done" hidden>
              <p class="mat-bulk-progress__summary" id="mat-bulk-summary"></p>
              <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="mat-bulk-reload">Reload page</button>
            </div>
          </div>
          <div class="admin-form-actions">
            <button type="submit" class="admin-btn admin-btn--primary" id="mat-bulk-submit">Import SKUs</button>
          </div>
        </form>
      </div>
    </details>

    <details class="admin-accordion admin-card admin-card--narrow mat-local-sync" open>
      <summary class="admin-accordion__summary">
        <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
        <span class="admin-accordion__title">Local price sync (your PC)</span>
      </summary>
      <div class="admin-accordion__panel">
        <p class="admin-form-help mat-local-sync__lede">
          Run from your project root in PowerShell or Terminal.
          @if ($usesSerpApi)
            On your PC, leave <code>MATERIALS_SCRAPER_SERPAPI_KEY</code> unset so sync uses the free Home Depot store API instead of SerpApi credits.
          @else
            Uses the Home Depot store API (Carleton Place store <code>7263</code> when configured).
          @endif
          @if ($needsSyncCount > 0)
            <strong>{{ number_format($needsSyncCount) }}</strong> material{{ $needsSyncCount === 1 ? '' : 's' }} never synced or last sync failed.
          @endif
        </p>
        <ul class="mat-sync-commands__list">
          <li class="mat-sync-commands__row">
            <div class="mat-sync-commands__cmd-wrap">
              <code class="mat-sync-commands__cmd" id="mat-sync-cmd-new-dry">php artisan materials:sync-new-prices --dry-run</code>
              <span class="admin-form-help mat-sync-commands__label">New / failed only — preview</span>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-copy-target="mat-sync-cmd-new-dry">
              <i class="fa fa-copy"></i> Copy
            </button>
          </li>
          <li class="mat-sync-commands__row">
            <div class="mat-sync-commands__cmd-wrap">
              <code class="mat-sync-commands__cmd" id="mat-sync-cmd-new">php artisan materials:sync-new-prices</code>
              <span class="admin-form-help mat-sync-commands__label">New / failed only — update database</span>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-copy-target="mat-sync-cmd-new">
              <i class="fa fa-copy"></i> Copy
            </button>
          </li>
          <li class="mat-sync-commands__row">
            <div class="mat-sync-commands__cmd-wrap">
              <code class="mat-sync-commands__cmd" id="mat-sync-cmd-dry">php artisan materials:sync-prices --dry-run</code>
              <span class="admin-form-help mat-sync-commands__label">All materials — preview</span>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-copy-target="mat-sync-cmd-dry">
              <i class="fa fa-copy"></i> Copy
            </button>
          </li>
          <li class="mat-sync-commands__row">
            <div class="mat-sync-commands__cmd-wrap">
              <code class="mat-sync-commands__cmd" id="mat-sync-cmd-live">php artisan materials:sync-prices</code>
              <span class="admin-form-help mat-sync-commands__label">All materials — update database</span>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-copy-target="mat-sync-cmd-live">
              <i class="fa fa-copy"></i> Copy
            </button>
          </li>
        </ul>
      </div>
    </details>
  </div>

  <p class="admin-form-help mat-config-footnote">
    {{ number_format($totalMaterials) }} {{ $totalMaterials === 1 ? 'material' : 'materials' }} in database
    @if ($syncableCount > 0)
      · {{ number_format($syncableCount) }} syncable via admin
    @endif
  </p>
@endsection

@push('scripts')
  @include('admin.materials._config-scripts')
@endpush
