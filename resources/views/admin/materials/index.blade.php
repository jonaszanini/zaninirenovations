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

  <div class="admin-card admin-card--narrow mat-sync-commands" id="mat-local-sync">
    <h3 class="mat-sync-commands__title">Local price sync</h3>
    <p class="admin-form-help mat-sync-commands__lede">
      Run these from your project root on your PC. Set <code>MATERIALS_SCRAPER_HD_STORE_ID</code> in your local <code>.env</code> for correct shelf prices (no Ollama or Browsershot needed).
    </p>
    <ul class="mat-sync-commands__list">
      <li class="mat-sync-commands__row">
        <div class="mat-sync-commands__cmd-wrap">
          <code class="mat-sync-commands__cmd" id="mat-sync-cmd-dry">php artisan materials:sync-prices --dry-run</code>
          <span class="admin-form-help mat-sync-commands__label">Preview changes</span>
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-mat-copy="mat-sync-cmd-dry" data-mat-copy-feedback="mat-sync-copy-dry">
          <i class="fa fa-copy"></i> Copy
        </button>
        <span id="mat-sync-copy-dry" class="mat-sync-commands__feedback" aria-live="polite" hidden>Copied.</span>
      </li>
      <li class="mat-sync-commands__row">
        <div class="mat-sync-commands__cmd-wrap">
          <code class="mat-sync-commands__cmd" id="mat-sync-cmd-live">php artisan materials:sync-prices</code>
          <span class="admin-form-help mat-sync-commands__label">Apply updates</span>
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm mat-sync-commands__copy" data-mat-copy="mat-sync-cmd-live" data-mat-copy-feedback="mat-sync-copy-live">
          <i class="fa fa-copy"></i> Copy
        </button>
        <span id="mat-sync-copy-live" class="mat-sync-commands__feedback" aria-live="polite" hidden>Copied.</span>
      </li>
    </ul>
  </div>

  <details class="admin-accordion admin-card admin-card--narrow mat-bulk-import" @if(old('skus')) open @endif>
    <summary class="admin-accordion__summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="admin-accordion__title">Bulk import Home Depot SKUs</span>
    </summary>
    <div class="admin-accordion__panel">
      <form method="post" action="{{ route('admin.materials.bulk-import') }}">
        @csrf
        <div class="admin-form-group">
          <label for="mat-bulk-skus">SKUs or product URLs</label>
          <textarea id="mat-bulk-skus" name="skus" rows="8" class="admin-input" aria-label="SKU list, one per line" placeholder="Paste one SKU or product URL per line…">{{ old('skus') }}</textarea>
          <p class="admin-form-help">
            One SKU (e.g. <code>1000108771</code>) or full Home Depot product URL per line.
            Product and image URLs are built automatically. Duplicate lines and invalid entries are skipped.
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
            When checked, fetches name and price from Home Depot for each imported item (~2&nbsp;s each).
            For 30+ SKUs, leave unchecked and use the <a href="#mat-local-sync" class="admin-text-link">local sync commands</a> above.
          </p>
        </div>
        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn--primary">Import SKUs</button>
        </div>
      </form>
    </div>
  </details>

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
              <td colspan="9" class="admin-form-help">No materials yet. Add your first item or bulk-import Home Depot SKUs above.</td>
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

@push('scripts')
  <script>
    (function () {
      function copyText(text, feedbackEl) {
        function showOk() {
          if (!feedbackEl) return;
          feedbackEl.hidden = false;
          setTimeout(function () {
            feedbackEl.hidden = true;
          }, 2000);
        }

        function showErr() {
          window.alert('Could not copy to clipboard. Select the command text and copy manually (Ctrl+C).');
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(showOk).catch(function () {
            fallbackCopy(text) ? showOk() : showErr();
          });
        } else {
          fallbackCopy(text) ? showOk() : showErr();
        }
      }

      function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        var ok = false;
        try {
          ok = document.execCommand('copy');
        } catch (e) {
          ok = false;
        }
        document.body.removeChild(ta);
        return ok;
      }

      document.querySelectorAll('[data-mat-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var codeId = btn.getAttribute('data-mat-copy');
          var feedbackId = btn.getAttribute('data-mat-copy-feedback');
          var codeEl = codeId ? document.getElementById(codeId) : null;
          var feedbackEl = feedbackId ? document.getElementById(feedbackId) : null;
          if (!codeEl) return;
          copyText(codeEl.textContent.trim(), feedbackEl);
        });
      });
    })();
  </script>
@endpush
