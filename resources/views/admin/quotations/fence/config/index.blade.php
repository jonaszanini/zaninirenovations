@extends('admin.layouts.app')

@section('heading', 'Fence Configuration')

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

@section('content')
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
        @if ($usesSerpApi)
          <button type="submit"
                  form="fq-sync-materials-form"
                  class="admin-btn admin-btn--ghost"
                  @disabled($linkedSyncCount === 0)>
            <i class="fa fa-refresh"></i> Sync fence prices
          </button>
        @endif
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save links
        </button>
      </div>
    </div>

    @if ($usesSerpApi)
      <p class="admin-form-help fq-sync-hint">
        <strong>Sync fence prices</strong> updates linked catalog materials via SerpApi — Home Depot (product URL) or Amazon Canada (ASIN in SKU) ({{ number_format($linkedSyncCount) }} syncable).
        <a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a>
        · <a href="https://serpapi.com/dashboard" target="_blank" rel="noopener noreferrer" class="admin-text-link">SerpApi dashboard</a>
      </p>
    @else
      <p class="admin-form-help fq-sync-hint">
        Online sync requires SerpApi on the server. Update linked materials from your PC with
        <code>php artisan materials:sync-prices</code> (see <a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a>).
      </p>
    @endif

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
                @endphp
                <div class="fq-catalog-picker-wrap">
                  <a href="{{ $storeUrl ?: '#' }}"
                     class="admin-btn admin-btn--ghost admin-btn--sm fq-catalog-picker__open @if ($linkVendor) fq-catalog-picker__open--{{ $linkVendor }} @endif"
                     id="fq-link-{{ $i }}"
                     target="_blank" rel="noopener"
                     title="{{ $linkVendor === 'amazon' ? 'Open on Amazon Canada' : ($linkVendor === 'home_depot' ? 'Open on Home Depot' : 'Open product page') }}"
                     aria-label="{{ $linkVendor === 'amazon' ? 'Open on Amazon Canada' : ($linkVendor === 'home_depot' ? 'Open on Home Depot' : 'Open product page') }}"
                     @if (! filled($storeUrl)) hidden @endif>
                    @if ($linkVendor === 'amazon')
                      <i class="fa fa-amazon" aria-hidden="true"></i>
                    @else
                      <i class="fa fa-external-link" aria-hidden="true"></i>
                    @endif
                  </a>
                  <div class="fq-catalog-picker">
                    <select name="materials[{{ $i }}][material_id]"
                            id="fq-catalog-picker-{{ $i }}"
                            class="admin-input fq-catalog-picker__select"
                            data-preview-target="fq-preview-{{ $i }}"
                            data-price-target="fq-price-{{ $i }}"
                            data-url-target="fq-link-{{ $i }}"
                            @if ($m->key === \App\Models\FenceMaterial::KEY_NAILS)
                              data-nails-per-box-target="fq-nails-per-box-{{ $i }}"
                            @endif
                            data-initial="{{ (int) old('materials.'.$i.'.material_id', $m->material_id) }}"
                            aria-label="Catalog material for {{ $m->effectiveName() }}">
                      <option value="">— Search name or SKU —</option>
                    </select>
                  </div>
                </div>
              </td>
              <td>
                <div id="fq-preview-{{ $i }}" class="fq-material-preview">
                  @if ($m->material_id && $m->material)
                    <span class="fq-material-preview__name">{{ $m->material->displayName() }}</span>
                    @if ($m->material->store_sku)
                      <span class="admin-form-help">SKU {{ $m->material->store_sku }}</span>
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
                  <p class="admin-form-help">Nails per box (from catalog qty per package).</p>
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

  @if ($usesSerpApi)
    <form id="fq-sync-materials-form" method="post" action="{{ route('admin.quotations.fence.config.materials.sync') }}" class="fq-sync-form">
      @csrf
    </form>
  @endif

  <form method="post" action="{{ route('admin.quotations.fence.config.formula.update') }}" class="admin-card fq-config-card" style="margin-top:16px;">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 class="cp-form-help-flush">Formula parameters</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Structural assumptions used by the calculator. Per-height labour rates are set on each fence quotation.
        </p>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save formula
      </button>
    </div>

    <div class="fq-formula-grid">
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
        <label for="fq-gap">Standard gap between boards (inches)</label>
        <input id="fq-gap" type="number" min="0" step="0.001" name="standard_gap_in"
               class="admin-input"
               value="{{ old('standard_gap_in', (float) $settings->standard_gap_in) }}">
        <p class="admin-form-help">Only used in "standard" board style.</p>
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <script>
    (function () {
      var catalogOptions = @json($catalogPickerOptions);

      function escapeHtml(text) {
        return String(text)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;');
      }

      function setStoreLinkIcon(linkEl, vendor) {
        linkEl.classList.remove('fq-catalog-picker__open--amazon', 'fq-catalog-picker__open--home_depot');
        if (vendor === 'amazon') {
          linkEl.classList.add('fq-catalog-picker__open--amazon');
          linkEl.innerHTML = '<i class="fa fa-amazon" aria-hidden="true"></i>';
          linkEl.setAttribute('title', 'Open on Amazon Canada');
          linkEl.setAttribute('aria-label', 'Open on Amazon Canada');
        } else {
          if (vendor === 'home_depot') {
            linkEl.classList.add('fq-catalog-picker__open--home_depot');
          }
          linkEl.innerHTML = '<i class="fa fa-external-link" aria-hidden="true"></i>';
          linkEl.setAttribute('title', vendor === 'home_depot' ? 'Open on Home Depot' : 'Open product page');
          linkEl.setAttribute('aria-label', vendor === 'home_depot' ? 'Open on Home Depot' : 'Open product page');
        }
      }

      function resolveItemStoreUrl(item) {
        if (!item) return '';
        if (item.url) return String(item.url);
        var sku = String(item.sku || '').trim().toUpperCase().replace(/\s+/g, '');
        if (/^[A-Z0-9]{10}$/.test(sku)) {
          return 'https://www.amazon.ca/dp/' + encodeURIComponent(sku);
        }
        return '';
      }

      function resolveLinkVendor(item, url) {
        if (item && item.link_vendor) return item.link_vendor;
        var lower = String(url || '').toLowerCase();
        if (lower.indexOf('amazon.') !== -1) return 'amazon';
        if (lower.indexOf('homedepot.ca') !== -1) return 'home_depot';
        if (item && item.sku && /^[A-Z0-9]{10}$/.test(String(item.sku).trim().toUpperCase())) {
          return 'amazon';
        }
        return null;
      }

      function updateProductLink(selectEl, value, optionMap) {
        var linkEl = document.getElementById(selectEl.getAttribute('data-url-target') || '');
        if (!linkEl) return;

        var resolvedValue = value ? String(value) : '';
        if (!resolvedValue && selectEl.tomselect) {
          resolvedValue = String(selectEl.tomselect.getValue() || '');
        }

        if (!resolvedValue) {
          linkEl.hidden = true;
          linkEl.setAttribute('href', '#');
          setStoreLinkIcon(linkEl, null);
          return;
        }

        var item = optionMap[resolvedValue] || null;
        var url = resolveItemStoreUrl(item);
        if (url) {
          linkEl.setAttribute('href', url);
          linkEl.hidden = false;
          setStoreLinkIcon(linkEl, resolveLinkVendor(item, url));
        } else {
          linkEl.hidden = true;
          linkEl.setAttribute('href', '#');
          setStoreLinkIcon(linkEl, null);
        }
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
          updateProductLink(selectEl, value, optionMap);
          updateNailsPerBox(selectEl, value, optionMap);
          return;
        }

        var item = optionMap[String(value)] || null;
        if (!item) {
          previewEl.innerHTML = '<span class="admin-form-help">Select a catalog material.</span>';
          if (priceEl) priceEl.textContent = '—';
          updateProductLink(selectEl, value, optionMap);
          updateNailsPerBox(selectEl, value, optionMap);
          return;
        }

        var html = '<span class="fq-material-preview__name">' + escapeHtml(item.name) + '</span>';
        if (item.sku) {
          html += '<span class="admin-form-help">SKU ' + escapeHtml(item.sku) + '</span>';
        }
        previewEl.innerHTML = html;
        if (priceEl) {
          priceEl.textContent = item.price_label || '—';
        }
        updateProductLink(selectEl, value, optionMap);
        updateNailsPerBox(selectEl, value, optionMap);
      }

      if (typeof TomSelect === 'undefined') return;

      var optionMap = {};
      catalogOptions.forEach(function (item) {
        optionMap[String(item.id)] = item;
      });

      document.querySelectorAll('.fq-catalog-picker__select').forEach(function (selectEl) {
        var initial = selectEl.getAttribute('data-initial') || '';
        var ts = new TomSelect(selectEl, {
          plugins: ['clear_button'],
          options: catalogOptions,
          valueField: 'id',
          labelField: 'label',
          searchField: ['label', 'name', 'sku', 'model', 'vendor'],
          maxOptions: 80,
          placeholder: 'Type to search name or SKU…',
          allowEmptyOption: true,
          closeAfterSelect: true,
          render: {
            option: function (data, escape) {
              return '<div class="fq-catalog-picker__option">' +
                '<span class="fq-catalog-picker__option-name">' + escape(data.name) + '</span>' +
                (data.sku ? '<span class="fq-catalog-picker__option-meta">SKU ' + escape(data.sku) + '</span>' : '') +
                '<span class="fq-catalog-picker__option-price">' + escape(data.price_label || '') + '</span>' +
                '</div>';
            },
            item: function (data, escape) {
              return '<span class="fq-catalog-picker__item">' + escape(data.label) + '</span>';
            }
          },
          onChange: function (value) {
            var resolved = value || this.getValue() || '';
            updatePreview(selectEl, resolved, optionMap);
          }
        });

        if (initial && initial !== '0') {
          ts.setValue(initial, true);
        }
        updateProductLink(selectEl, ts.getValue() || initial, optionMap);
      });
    })();
  </script>
@endpush
