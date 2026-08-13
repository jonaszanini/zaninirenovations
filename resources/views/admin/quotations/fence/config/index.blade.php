@extends('admin.layouts.app')

@section('heading', 'Fence Configuration')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-materials.css') }}?v={{ @filemtime(public_path('css/admin-materials.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $amazonVendorLinkTemplate = route('admin.materials.vendor-link.amazon', ['asin' => 'PLACEHOLDER']);
  @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to fence quotations
      </a>
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.fence.config.materials.update') }}" class="admin-card fq-config-card">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 class="cp-form-help-flush">Materials catalogue</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Link each fence line item to a row in the <a href="{{ route('admin.materials.index') }}" class="admin-text-link">materials catalog</a>.
          Prices and product details are read live from the linked material.
        </p>
      </div>
      <div class="fq-config-header__actions">
        <button type="button"
                id="fq-sync-prices-btn"
                class="admin-btn admin-btn--ghost"
                data-start-url="{{ route('admin.quotations.fence.config.materials.sync.start') }}"
                data-step-url="{{ route('admin.quotations.fence.config.materials.sync.step') }}"
                @disabled($linkedSyncCount === 0)>
          <i class="fa fa-refresh"></i> Sync fence prices
        </button>
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save links
        </button>
      </div>
    </div>

    <p class="admin-form-help fq-sync-hint">
      <strong>Sync fence prices</strong> refreshes every linked catalog material listed below
      @if ($usesSerpApi)
        via SerpApi — Home Depot (product URL) or Amazon Canada (ASIN in SKU)
      @else
        via the store price API
      @endif
      ({{ number_format($linkedSyncCount) }} syncable).
      <a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a>
      @if ($usesSerpApi)
        · <a href="https://serpapi.com/dashboard" target="_blank" rel="noopener noreferrer" class="admin-text-link">SerpApi dashboard</a>
      @else
        · On production, set <code>MATERIALS_SCRAPER_SERPAPI_KEY</code> for online sync.
      @endif
    </p>

    <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>
    <div class="admin-table-scroll">
      <table class="admin-table fq-materials-table">
        <thead>
          <tr>
            <th>Fence item</th>
            <th style="min-width:220px;">Catalog material</th>
            <th style="min-width:200px;">Product details</th>
            <th style="width:130px;">Price (CAD$)</th>
            <th style="width:130px;">Quantity / box</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($materials as $i => $m)
            <tr class="fq-material-row" data-row="{{ $i }}">
              <td>
                <strong>{{ $m->effectiveName() }}</strong>
                <div class="admin-form-help" style="font-size:11px;">key: <code>{{ $m->key }}</code></div>
                <input type="hidden" name="materials[{{ $i }}][id]" value="{{ $m->id }}">
              </td>
              <td>
                @php
                  $storeUrl = $m->material?->storeProductUrl();
                  $linkVendor = $m->material?->storeLinkVendor();
                  if (! filled($storeUrl) && filled($m->effectiveStoreSku())) {
                    $fallbackAsin = \App\Services\MaterialScraping\AmazonCaUrls::normalizeAsin($m->effectiveStoreSku());
                    if ($fallbackAsin !== null) {
                      $storeUrl = \App\Services\MaterialScraping\AmazonCaUrls::productUrl($fallbackAsin);
                      $linkVendor = 'amazon';
                    } elseif (\App\Services\MaterialScraping\HomeDepotCaUrls::isValidSku($m->effectiveStoreSku())) {
                      $storeUrl = \App\Services\MaterialScraping\HomeDepotCaUrls::productUrl($m->effectiveStoreSku());
                      $linkVendor = 'home_depot';
                    }
                  }
                  $storeAsin = \App\Services\MaterialScraping\AmazonCaUrls::normalizeAsin(
                    (string) ($m->material?->store_sku ?? $m->effectiveStoreSku() ?? '')
                  );
                  $pickerUrl = $storeUrl;
                  if ($linkVendor === 'amazon' && filled($storeAsin)) {
                      $pickerUrl = route('admin.materials.vendor-link.amazon', ['asin' => $storeAsin]);
                  }
                @endphp
                @php
                  $fqPickerValue = (int) old('materials.'.$i.'.material_id', $m->material_id);
                  $fqPickerAttrs = [
                    'data-preview-target' => 'fq-preview-'.$i,
                    'data-price-target' => 'fq-price-'.$i,
                    'data-url-target' => 'fq-link-'.$i,
                  ];
                  if ($m->key === \App\Models\FenceMaterial::KEY_NAILS) {
                      $fqPickerAttrs['data-nails-per-box-target'] = 'fq-nails-per-box-'.$i;
                  }
                  if (filled($pickerUrl)) {
                      $fqPickerAttrs['data-store-url'] = $pickerUrl;
                      $fqPickerAttrs['data-link-vendor'] = $linkVendor;
                  }
                  if ($storeAsin) {
                      $fqPickerAttrs['data-store-asin'] = $storeAsin;
                  }
                @endphp
                <div class="fq-catalog-picker-wrap fq-catalog-picker">
                  <a href="{{ $pickerUrl ?: '#' }}"
                     class="admin-btn admin-btn--ghost admin-btn--sm fq-catalog-picker__open @if ($linkVendor) fq-catalog-picker__open--{{ $linkVendor }} @endif @if (! filled($pickerUrl)) fq-catalog-picker__open--disabled @endif"
                     id="fq-link-{{ $i }}"
                     target="_blank" rel="noopener"
                     title="{{ $linkVendor === 'amazon' ? 'Open on Amazon Canada' : ($linkVendor === 'home_depot' ? 'Open on Home Depot' : 'Open product page') }}"
                     aria-label="Open product page"
                     @if (filled($pickerUrl)) data-store-url="{{ $pickerUrl }}" data-link-vendor="{{ $linkVendor }}" @endif>
                    <i class="fa fa-external-link" aria-hidden="true"></i>
                  </a>
                  @include('admin.partials.search_select', [
                    'id' => 'fq-catalog-picker-'.$i,
                    'name' => 'materials['.$i.'][material_id]',
                    'value' => $fqPickerValue > 0 ? $fqPickerValue : '',
                    'hideLabel' => true,
                    'placeholder' => 'Search name or SKU…',
                    'ariaLabel' => 'Catalog material for '.$m->effectiveName(),
                    'optionsSource' => 'admin-ss-catalog-options',
                    'searchFields' => ['label', 'name', 'sku', 'model', 'vendor'],
                    'metaKeys' => ['sku', 'price_label'],
                    'emptyMessage' => 'No matching materials',
                    'maxOptions' => 80,
                    'listParent' => 'body',
                    'attrs' => $fqPickerAttrs,
                  ])
                </div>
              </td>
              <td>
                <div id="fq-preview-{{ $i }}" class="fq-material-preview" data-store-link-target="fq-preview-link-{{ $i }}">
                  @if ($m->material_id && $m->material)
                    <span class="fq-material-preview__name">{{ $m->material->displayName() }}</span>
                    @if ($m->material->store_sku)
                      <span class="admin-form-help fq-material-preview__sku-row">SKU {{ $m->material->store_sku }}</span>
                    @endif
                  @elseif ($m->effectiveLongName() || $m->effectiveStoreSku())
                    <span class="fq-material-preview__name">{{ $m->effectiveLongName() }}</span>
                    @if ($m->effectiveStoreSku())
                      <span class="admin-form-help">SKU {{ $m->effectiveStoreSku() }}</span>
                    @endif
                  @else
                    <span class="admin-form-help">Select a catalog material.</span>
                  @endif
                </div>
              </td>
              <td>
                <span class="fq-material-preview__price" id="fq-price-{{ $i }}">
                  @if ($m->material_id && $m->material)
                    {{ $m->material->formattedPrice() }}
                  @elseif ($m->effectivePriceCad()> 0)
                    {{ \App\Models\ClientProject::formatMoney($m->effectivePriceCad()) }}
                  @else
                    —
                  @endif
                </span>
              </td>
              <td>
                @if ($m->key === \App\Models\FenceMaterial::KEY_NAILS)
                  <strong id="fq-nails-per-box-{{ $i }}" class="fq-nails-per-box">
                    {{ $m->effectiveQuantityPerBox() ?? '—' }}
                  </strong>
                @else
                  <span class="admin-form-help">—</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </form>

  <div id="fq-sync-progress" class="admin-card admin-card--narrow mat-bulk-progress mat-sync-all-progress" hidden aria-live="polite" style="margin-top:16px;">
    <div class="mat-bulk-progress__head">
      <h3 class="mat-bulk-progress__title" id="fq-sync-phase-label">Sync in progress</h3>
      <span class="mat-bulk-progress__counter" id="fq-sync-counter">0 / 0</span>
    </div>
    <div class="mat-bulk-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="fq-sync-progressbar">
      <div class="mat-bulk-progress__bar" id="fq-sync-bar" style="width:0%"></div>
    </div>
    <p class="mat-bulk-progress__message" id="fq-sync-message">Preparing…</p>
    <dl class="mat-bulk-progress__stats" id="fq-sync-stats">
      <div><dt>Synced</dt><dd id="fq-sync-ok">0</dd></div>
      <div><dt>Failed</dt><dd id="fq-sync-failed">0</dd></div>
      <div><dt>Skipped</dt><dd id="fq-sync-skipped">0</dd></div>
      <div><dt>Remaining</dt><dd id="fq-sync-remaining">0</dd></div>
    </dl>
    <ul class="mat-bulk-progress__log" id="fq-sync-log"></ul>
    <div class="mat-bulk-progress__done" id="fq-sync-done" hidden>
      <p class="mat-bulk-progress__summary" id="fq-sync-summary"></p>
      <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="fq-sync-reload">Reload page</button>
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.fence.config.formula.update') }}" class="admin-card fq-config-card" style="margin-top:16px;">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 class="cp-form-help-flush">Formula parameters</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Structural assumptions used by the calculator. Labour rates below are defaults for new fence quotations (editable per quote).
        </p>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save formula
      </button>
    </div>

    <div class="fq-formula-grid">
      <div class="admin-form-group">
        <label for="fq-labour-6">Labour rate 6′ fence (CAD$ / linear ft)</label>
        <input id="fq-labour-6" type="text" name="labour_rate_per_ft"
               class="admin-input cp-money-input" inputmode="decimal" autocomplete="off"
               value="{{ old('labour_rate_per_ft', number_format((float) $settings->labour_rate_per_ft, 2, '.', '')) }}">
        <p class="admin-form-help">Default start price for new 6′ fence quotations.</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-labour-8">Labour rate 8′ fence (CAD$ / linear ft)</label>
        <input id="fq-labour-8" type="text" name="labour_rate_8ft_per_ft"
               class="admin-input cp-money-input" inputmode="decimal" autocomplete="off"
               value="{{ old('labour_rate_8ft_per_ft', number_format((float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft), 2, '.', '')) }}">
        <p class="admin-form-help">Default start price for new 8′ fence quotations.</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-labour-additional-gate">Labour per additional gate (CAD$)</label>
        <input id="fq-labour-additional-gate" type="text" name="labour_per_additional_gate"
               class="admin-input cp-money-input" inputmode="decimal" autocomplete="off"
               value="{{ old('labour_per_additional_gate', number_format((float) ($settings->labour_per_additional_gate ?? 0), 2, '.', '')) }}">
        <p class="admin-form-help">Added to labour for gate #2 and each gate after that.</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-gate-rails">Extra rails per gate (2x4x8')</label>
        <input id="fq-gate-rails" type="number" min="0" step="1" name="gate_rails"
               class="admin-input"
               value="{{ old('gate_rails', $settings->gate_rails) }}">
        <p class="admin-form-help">Frame + diagonal brace (typically 5).</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-concrete-6">Concrete bags / post — 6' fence</label>
        <input id="fq-concrete-6" type="number" min="0" step="0.01" name="concrete_per_post_6ft"
               class="admin-input"
               value="{{ old('concrete_per_post_6ft', (float) $settings->concrete_per_post_6ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-concrete-8">Concrete bags / post — 8' fence</label>
        <input id="fq-concrete-8" type="number" min="0" step="0.01" name="concrete_per_post_8ft"
               class="admin-input"
               value="{{ old('concrete_per_post_8ft', (float) $settings->concrete_per_post_8ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-rails-6">Rails per section — 6' fence</label>
        <input id="fq-rails-6" type="number" min="1" step="1" name="rails_per_section_6ft"
               class="admin-input"
               value="{{ old('rails_per_section_6ft', $settings->rails_per_section_6ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-rails-8">Rails per section — 8' fence</label>
        <input id="fq-rails-8" type="number" min="1" step="1" name="rails_per_section_8ft"
               class="admin-input"
               value="{{ old('rails_per_section_8ft', $settings->rails_per_section_8ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-nails">Nails per picket</label>
        <input id="fq-nails" type="number" min="1" step="1" name="nails_per_picket"
               class="admin-input"
               value="{{ old('nails_per_picket', $settings->nails_per_picket) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-spacing">Max post-to-post spacing (ft)</label>
        <input id="fq-spacing" type="number" min="1" step="0.01" name="max_post_spacing_ft"
               class="admin-input"
               value="{{ old('max_post_spacing_ft', (float) $settings->max_post_spacing_ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="fq-width">Board width (inches, actual)</label>
        <input id="fq-width" type="number" min="1" step="0.001" name="board_width_in"
               class="admin-input"
               value="{{ old('board_width_in', (float) $settings->board_width_in) }}">
        <p class="admin-form-help">Nominal 1x6 lumber is typically 5.5" actual.</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-overlap">Overlapping (inches)</label>
        <input id="fq-overlap" type="number" min="0" step="0.001" name="overlapping_in"
               class="admin-input"
               value="{{ old('overlapping_in', (float) $settings->overlapping_in) }}">
        <p class="admin-form-help">Only used in "overlapping" board style.</p>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script>
    (function () {
      var catalogOptions = [];
      try {
        var catalogJson = document.getElementById('admin-ss-catalog-options');
        catalogOptions = catalogJson ? JSON.parse(catalogJson.textContent || '[]') : [];
      } catch (e) {
        catalogOptions = [];
      }
      var amazonVendorLinkTemplate = @json($amazonVendorLinkTemplate);

      function escapeHtml(text) {
        return String(text)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function amazonPickerUrl(asin) {
        return amazonVendorLinkTemplate.replace('PLACEHOLDER', encodeURIComponent(asin));
      }

      function setStoreLinkIcon(linkEl, vendor) {
        linkEl.classList.remove('fq-catalog-picker__open--amazon', 'fq-catalog-picker__open--home_depot');
        if (vendor === 'amazon') {
          linkEl.classList.add('fq-catalog-picker__open--amazon');
          linkEl.setAttribute('title', 'Open on Amazon Canada');
        } else if (vendor === 'home_depot') {
          linkEl.classList.add('fq-catalog-picker__open--home_depot');
          linkEl.setAttribute('title', 'Open on Home Depot');
        } else {
          linkEl.setAttribute('title', 'Open product page');
        }
        linkEl.setAttribute('aria-label', 'Open product page');
        linkEl.innerHTML = '<i class="fa fa-external-link" aria-hidden="true"></i>';
      }

      function normalizeAsin(value) {
        var candidate = String(value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (!/^[A-Z0-9]{10}$/.test(candidate)) return '';
        if (!/[A-Z]/.test(candidate)) return '';
        return candidate;
      }

      function selectStoreUrl(selectEl) {
        if (!selectEl) return '';
        return String(selectEl.getAttribute('data-store-url') || '').trim();
      }

      function selectLinkVendor(selectEl) {
        if (!selectEl) return null;
        var vendor = String(selectEl.getAttribute('data-link-vendor') || '').trim();
        return vendor || null;
      }

      function resolveItemStoreUrl(item, selectEl) {
        var asin = normalizeAsin(item && item.sku);
        if (!asin && selectEl) {
          asin = normalizeAsin(selectEl.getAttribute('data-store-asin') || '');
        }
        if (asin) {
          return amazonPickerUrl(asin);
        }

        var url = item && item.url ? String(item.url).trim() : '';
        if (!url) {
          url = selectStoreUrl(selectEl);
        }
        return url;
      }

      function resolveLinkVendor(item, url, selectEl) {
        if (normalizeAsin(item && item.sku) || (selectEl && normalizeAsin(selectEl.getAttribute('data-store-asin') || ''))) {
          return 'amazon';
        }
        if (item && item.link_vendor) return item.link_vendor;
        var fromSelect = selectLinkVendor(selectEl);
        if (fromSelect) return fromSelect;
        var lower = String(url || '').toLowerCase();
        if (lower.indexOf('amazon.') !== -1) return 'amazon';
        if (lower.indexOf('homedepot.ca') !== -1) return 'home_depot';
        return null;
      }

      function setProductLink(linkEl, url, vendor) {
        if (!linkEl) return;

        if (!url) {
          linkEl.setAttribute('href', '#');
          linkEl.classList.add('fq-catalog-picker__open--disabled');
          linkEl.removeAttribute('data-store-url');
          linkEl.removeAttribute('data-link-vendor');
          linkEl.removeAttribute('hidden');
          return;
        }

        linkEl.setAttribute('href', url);
        linkEl.classList.remove('fq-catalog-picker__open--disabled');
        linkEl.removeAttribute('hidden');
        linkEl.setAttribute('data-store-url', url);
        if (vendor) {
          linkEl.setAttribute('data-link-vendor', vendor);
        } else {
          linkEl.removeAttribute('data-link-vendor');
        }
        setStoreLinkIcon(linkEl, vendor);
      }

      function syncSelectStoreMeta(selectEl, item, url, vendor) {
        if (!selectEl) return;
        var asin = normalizeAsin(item && item.sku);
        if (asin) {
          selectEl.setAttribute('data-store-asin', asin);
        } else {
          selectEl.removeAttribute('data-store-asin');
        }
        if (url) {
          selectEl.setAttribute('data-store-url', url);
          if (vendor) {
            selectEl.setAttribute('data-link-vendor', vendor);
          } else {
            selectEl.removeAttribute('data-link-vendor');
          }
        } else {
          selectEl.removeAttribute('data-store-url');
          selectEl.removeAttribute('data-link-vendor');
        }
      }

      function applyProductLinkIfPresent(selectEl, value, optionMap) {
        var linkEl = document.getElementById(selectEl.getAttribute('data-url-target') || '');
        if (!linkEl) return;

        if (!value) {
          syncSelectStoreMeta(selectEl, null, '', null);
          setProductLink(linkEl, '', null);
          return;
        }

        var item = optionMap[String(value)] || null;
        var url = resolveItemStoreUrl(item, selectEl);
        var vendor = resolveLinkVendor(item, url, selectEl);
        syncSelectStoreMeta(selectEl, item, url, vendor);
        if (!url) {
          setProductLink(linkEl, '', null);
          return;
        }

        setProductLink(linkEl, url, vendor);
      }

      function updateNailsPerBox(selectEl, value, optionMap) {
        var nailsPerBoxEl = document.getElementById(selectEl.getAttribute('data-nails-per-box-target') || '');
        if (!nailsPerBoxEl) return;

        if (!value) {
          nailsPerBoxEl.textContent = '—';
          return;
        }

        var item = optionMap[String(value)] || null;
        var qty = item && item.quantity_per_package ? parseInt(item.quantity_per_package, 10) : 0;
        nailsPerBoxEl.textContent = qty> 0 ? String(qty) : '—';
      }

      function updatePreview(selectEl, value, optionMap) {
        var previewEl = document.getElementById(selectEl.getAttribute('data-preview-target') || '');
        var priceEl = document.getElementById(selectEl.getAttribute('data-price-target') || '');

        if (!previewEl) return;

        if (!value) {
          previewEl.innerHTML = '<span class="admin-form-help">Select a catalog material.</span>';
          if (priceEl) priceEl.textContent = '—';
          updateNailsPerBox(selectEl, '', optionMap);
          return;
        }

        var item = optionMap[String(value)] || null;
        if (!item) {
          updateNailsPerBox(selectEl, value, optionMap);
          return;
        }

        var html = '<span class="fq-material-preview__name">' + escapeHtml(item.name) + '</span>';
        if (item.sku) {
          html += '<span class="admin-form-help fq-material-preview__sku-row">SKU ' + escapeHtml(item.sku) + '</span>';
        }
        previewEl.innerHTML = html;
        if (priceEl) {
          priceEl.textContent = item.price_label || '—';
        }
        updateNailsPerBox(selectEl, value, optionMap);
      }

      (function initFencePriceSync() {
        var syncBtn = document.getElementById('fq-sync-prices-btn');
        if (!syncBtn) return;

        var STEP_DELAY_MS = {{ (int) config('materials.scraper.admin_step_delay_ms', 800) }};
        var MAX_RETRIES = 5;
        var progressEl = document.getElementById('fq-sync-progress');
        var phaseLabel = document.getElementById('fq-sync-phase-label');
        var counterEl = document.getElementById('fq-sync-counter');
        var barEl = document.getElementById('fq-sync-bar');
        var progressbarEl = document.getElementById('fq-sync-progressbar');
        var messageEl = document.getElementById('fq-sync-message');
        var logEl = document.getElementById('fq-sync-log');
        var doneEl = document.getElementById('fq-sync-done');
        var summaryEl = document.getElementById('fq-sync-summary');
        var reloadBtn = document.getElementById('fq-sync-reload');
        var okEl = document.getElementById('fq-sync-ok');
        var failedEl = document.getElementById('fq-sync-failed');
        var skippedEl = document.getElementById('fq-sync-skipped');
        var remainingEl = document.getElementById('fq-sync-remaining');

        function csrfToken() {
          var meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? meta.getAttribute('content') : '';
        }

        function sleep(ms) {
          return new Promise(function (resolve) { setTimeout(resolve, ms); });
        }

        function postJson(url, body, attempt) {
          attempt = attempt || 0;
          return fetch(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken()
            },
            body: JSON.stringify(body || {})
          }).then(function (res) {
            return res.text().then(function (text) {
              var data = null;
              var trimmed = (text || '').trim();
              if (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[') {
                try { data = JSON.parse(trimmed); } catch (e) { data = null; }
              }
              if (!data) {
                var err = new Error(
                  res.status === 503 || res.status === 502 || res.status === 429
                    ? 'Server busy (HTTP ' + res.status + '). Retrying…'
                    : 'Server returned a non-JSON response (HTTP ' + res.status + ').'
                );
                err.status = res.status;
                err.retryable = res.status === 503 || res.status === 502 || res.status === 429 || res.status === 408;
                throw err;
              }
              if (!res.ok) {
                var fail = new Error(data.message || ('Request failed (' + res.status + ')'));
                fail.status = res.status;
                fail.retryable = res.status === 503 || res.status === 502 || res.status === 429;
                throw fail;
              }
              return data;
            });
          }).catch(function (err) {
            if (!err.status && err.name === 'TypeError') {
              err.retryable = true;
            }
            if (err.retryable && attempt < MAX_RETRIES) {
              return sleep(Math.min(30000, 1500 * Math.pow(2, attempt))).then(function () {
                return postJson(url, body, attempt + 1);
              });
            }
            throw err;
          });
        }

        function updateProgress(data) {
          var total = data.phase_total || 1;
          var current = data.current || 0;
          var pct = total > 0 ? Math.round((current / total) * 100) : 0;

          if (phaseLabel) phaseLabel.textContent = data.phase_label || 'Syncing…';
          if (counterEl) {
            counterEl.textContent = data.phase === 'done'
              ? (current + ' / ' + total + ' complete')
              : (current + ' / ' + total + ' · ' + (data.remaining ?? (total - current)) + ' remaining');
          }
          if (barEl) barEl.style.width = pct + '%';
          if (progressbarEl) progressbarEl.setAttribute('aria-valuenow', String(pct));
          if (messageEl) {
            messageEl.textContent = data.message || '';
            messageEl.classList.remove('mat-bulk-progress__message--error');
          }
          if (data.stats) {
            if (okEl) okEl.textContent = String(data.stats.ok || 0);
            if (failedEl) failedEl.textContent = String(data.stats.failed || 0);
            if (skippedEl) skippedEl.textContent = String(data.stats.skipped || 0);
            if (remainingEl) remainingEl.textContent = String(data.remaining ?? Math.max(0, total - current));
          }
          if (logEl && Array.isArray(data.log)) {
            logEl.innerHTML = '';
            data.log.slice().reverse().forEach(function (line) {
              var li = document.createElement('li');
              li.textContent = line;
              logEl.appendChild(li);
            });
          }
        }

        function setRunning(running) {
          syncBtn.disabled = running || syncBtn.getAttribute('data-empty') === '1';
          syncBtn.innerHTML = running
            ? '<i class="fa fa-refresh"></i> Syncing…'
            : '<i class="fa fa-refresh"></i> Sync fence prices';
        }

        if (syncBtn.disabled) {
          syncBtn.setAttribute('data-empty', '1');
        }

        syncBtn.addEventListener('click', function () {
          var startUrl = syncBtn.getAttribute('data-start-url');
          var stepUrl = syncBtn.getAttribute('data-step-url');
          if (!startUrl || !stepUrl || !progressEl) return;

          progressEl.hidden = false;
          if (doneEl) doneEl.hidden = true;
          if (logEl) logEl.innerHTML = '';
          setRunning(true);

          postJson(startUrl, {})
            .then(function (startData) {
              updateProgress(startData);

              function runStep(token) {
                return postJson(stepUrl, { token: token }).then(function (stepData) {
                  updateProgress(stepData);
                  if (stepData.done) {
                    setRunning(false);
                    if (doneEl) doneEl.hidden = false;
                    if (summaryEl) summaryEl.textContent = stepData.summary || stepData.message || 'Done.';
                    return stepData;
                  }
                  return sleep(STEP_DELAY_MS).then(function () {
                    return runStep(token);
                  });
                });
              }

              return runStep(startData.token);
            })
            .catch(function (err) {
              setRunning(false);
              if (messageEl) {
                messageEl.textContent = err.message || 'Sync failed.';
                messageEl.classList.add('mat-bulk-progress__message--error');
              }
            });
        });

        if (reloadBtn) {
          reloadBtn.addEventListener('click', function () {
            window.location.reload();
          });
        }
      })();

      var optionMap = {};
      catalogOptions.forEach(function (item) {
        optionMap[String(item.id)] = item;
      });

      document.querySelectorAll('.fq-catalog-picker-wrap [data-admin-search-select]').forEach(function (root) {
        var initial = window.AdminSearchSelect ? AdminSearchSelect.getValue(root) : '';
        updatePreview(root, initial && initial !== '0' ? initial : '', optionMap);
        applyProductLinkIfPresent(root, initial && initial !== '0' ? initial : '', optionMap);
        root.addEventListener('admin-search-select:change', function (e) {
          var resolved = (e.detail && e.detail.id) || '';
          updatePreview(root, resolved, optionMap);
          applyProductLinkIfPresent(root, resolved, optionMap);
        });
      });
    })();
  </script>
@endpush
