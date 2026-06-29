@extends('admin.layouts.app')

@section('heading', 'Low Deck Configuration')

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.css">
@endpush

@section('content')
  @php
    $amazonVendorLinkTemplate = route('admin.materials.vendor-link.amazon', ['asin' => 'PLACEHOLDER']);
    $boxKeys = \App\Models\LowDeckMaterial::BOX_KEYS;
  @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.low-deck.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to low deck quotations
      </a>
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.low-deck.config.materials.update') }}" class="admin-card fq-config-card">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 class="cp-form-help-flush">Materials catalogue</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Link each low deck line item to a row in the <a href="{{ route('admin.materials.index') }}" class="admin-text-link">materials catalog</a>.
          Prices and product details are read live from the linked material.
        </p>
      </div>
      <div class="fq-config-header__actions">
        @if ($usesSerpApi)
          <button type="submit"
                  form="ld-sync-materials-form"
                  class="admin-btn admin-btn--ghost"
                  @disabled($linkedSyncCount === 0)>
            <i class="fa fa-refresh"></i> Sync low deck prices
          </button>
        @endif
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save links
        </button>
      </div>
    </div>

    @if ($usesSerpApi)
      <p class="admin-form-help fq-sync-hint">
        <strong>Sync low deck prices</strong> updates linked catalog materials via SerpApi — Home Depot (product URL) or Amazon Canada (ASIN in SKU) ({{ number_format($linkedSyncCount) }} syncable).
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
            <th>Low deck item</th>
            <th style="min-width:220px;">Catalog material</th>
            <th style="min-width:200px;">Product details</th>
            <th style="width:130px;">Price (CA$)</th>
            <th style="width:130px;">Quantity / box</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($materials as $i => $m)
            @php $isBox = in_array($m->key, $boxKeys, true); @endphp
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
                  $storeAsin = \App\Services\MaterialScraping\AmazonCaUrls::normalizeAsin(
                    (string) ($m->material?->store_sku ?? $m->effectiveStoreSku() ?? '')
                  );
                  $pickerUrl = $storeUrl;
                  if ($linkVendor === 'amazon' && filled($storeAsin)) {
                      $pickerUrl = route('admin.materials.vendor-link.amazon', ['asin' => $storeAsin]);
                  }
                @endphp
                <div class="fq-catalog-picker-wrap fq-catalog-picker">
                  <a href="{{ $pickerUrl ?: '#' }}"
                     class="admin-btn admin-btn--ghost admin-btn--sm fq-catalog-picker__open @if ($linkVendor) fq-catalog-picker__open--{{ $linkVendor }} @endif @if (! filled($pickerUrl)) fq-catalog-picker__open--disabled @endif"
                     id="ld-link-{{ $i }}"
                     target="_blank" rel="noopener"
                     title="Open product page"
                     aria-label="Open product page"
                     @if (filled($pickerUrl)) data-store-url="{{ $pickerUrl }}" data-link-vendor="{{ $linkVendor }}" @endif>
                    <i class="fa fa-external-link" aria-hidden="true"></i>
                  </a>
                  <select name="materials[{{ $i }}][material_id]"
                            id="ld-catalog-picker-{{ $i }}"
                            class="fq-catalog-picker__select"
                            data-preview-target="ld-preview-{{ $i }}"
                            data-price-target="ld-price-{{ $i }}"
                            data-url-target="ld-link-{{ $i }}"
                            @if ($isBox) data-qty-per-box-target="ld-qty-per-box-{{ $i }}" @endif
                            data-initial="{{ (int) old('materials.'.$i.'.material_id', $m->material_id) }}"
                            @if (filled($pickerUrl)) data-store-url="{{ $pickerUrl }}" data-link-vendor="{{ $linkVendor }}" @endif
                            @if ($storeAsin) data-store-asin="{{ $storeAsin }}" @endif
                            aria-label="Catalog material for {{ $m->effectiveName() }}">
                    <option value="">— Search name or SKU —</option>
                  </select>
                </div>
              </td>
              <td>
                <div id="ld-preview-{{ $i }}" class="fq-material-preview">
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
                <span class="fq-material-preview__price" id="ld-price-{{ $i }}">
                  @if ($m->material_id && $m->material)
                    {{ $m->material->formattedPrice() }}
                  @elseif ($m->effectivePriceCad() > 0)
                    {{ \App\Models\ClientProject::formatMoney($m->effectivePriceCad()) }}
                  @else
                    —
                  @endif
                </span>
              </td>
              <td>
                @if ($isBox)
                  <strong id="ld-qty-per-box-{{ $i }}" class="fq-nails-per-box">
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

  @if ($usesSerpApi)
    <form id="ld-sync-materials-form" method="post" action="{{ route('admin.quotations.low-deck.config.materials.sync') }}" class="fq-sync-form">
      @csrf
    </form>
  @endif

  <form method="post" action="{{ route('admin.quotations.low-deck.config.formula.update') }}" class="admin-card fq-config-card" style="margin-top:16px;">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 class="cp-form-help-flush">Formula parameters</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Structural and retail assumptions used by the calculator. The labour rate and dump fee are prefilled onto each quotation.
        </p>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save formula
      </button>
    </div>

    <div class="fq-formula-grid">
      <div class="admin-form-group">
        <label for="ld-max-area">Maximum area (sq ft)</label>
        <input id="ld-max-area" type="number" min="50" step="1" name="max_area_sq_ft"
               class="admin-input" value="{{ old('max_area_sq_ft', (float) $settings->max_area_sq_ft) }}">
        <p class="admin-form-help">OBC floating deck guardrail (592 sq ft = 55 m²).</p>
      </div>

      <div class="admin-form-group">
        <label for="ld-block-spacing">Block grid spacing (ft, max O.C.)</label>
        <input id="ld-block-spacing" type="number" min="1" step="0.5" name="block_spacing_ft"
               class="admin-input" value="{{ old('block_spacing_ft', (float) $settings->block_spacing_ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-default-spacing">Default joist spacing (in.)</label>
        <select id="ld-default-spacing" name="default_joist_spacing_in" class="admin-input">
          <option value="12" @selected((int) old('default_joist_spacing_in', $settings->default_joist_spacing_in) === 12)>12 in</option>
          <option value="16" @selected((int) old('default_joist_spacing_in', $settings->default_joist_spacing_in) === 16)>16 in</option>
        </select>
      </div>

      <div class="admin-form-group">
        <label for="ld-max-span">Max joist span (in.)</label>
        <input id="ld-max-span" type="number" min="24" step="1" name="max_joist_span_in"
               class="admin-input" value="{{ old('max_joist_span_in', (float) $settings->max_joist_span_in) }}">
        <p class="admin-form-help">112 in = 9' 4" for 2x6 at 12" O.C.</p>
      </div>

      <div class="admin-form-group">
        <label for="ld-board-width">Board width (in., actual)</label>
        <input id="ld-board-width" type="number" min="1" step="0.001" name="board_width_in"
               class="admin-input" value="{{ old('board_width_in', (float) $settings->board_width_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-board-gap">Board gap (in.)</label>
        <input id="ld-board-gap" type="number" min="0" step="0.001" name="board_gap_in"
               class="admin-input" value="{{ old('board_gap_in', (float) $settings->board_gap_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-post-length">Post length per block (ft)</label>
        <input id="ld-post-length" type="number" min="0.5" step="0.1" name="post_length_per_block_ft"
               class="admin-input" value="{{ old('post_length_per_block_ft', (float) $settings->post_length_per_block_ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-gravel-depth">Gravel depth (in.)</label>
        <input id="ld-gravel-depth" type="number" min="0" step="0.1" name="gravel_depth_in"
               class="admin-input" value="{{ old('gravel_depth_in', (float) $settings->gravel_depth_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-gravel-footprint">Gravel footprint (in. square)</label>
        <input id="ld-gravel-footprint" type="number" min="1" step="0.1" name="gravel_footprint_in"
               class="admin-input" value="{{ old('gravel_footprint_in', (float) $settings->gravel_footprint_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-gravel-bag">Gravel volume per bag (cu ft)</label>
        <input id="ld-gravel-bag" type="number" min="0.05" step="0.01" name="gravel_cubic_ft_per_bag"
               class="admin-input" value="{{ old('gravel_cubic_ft_per_bag', (float) $settings->gravel_cubic_ft_per_bag) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-struct-post">6" structural screws / standard post</label>
        <input id="ld-struct-post" type="number" min="0" step="1" name="structural_screws_per_post"
               class="admin-input" value="{{ old('structural_screws_per_post', $settings->structural_screws_per_post) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-struct-corner">6" structural screws / corner post</label>
        <input id="ld-struct-corner" type="number" min="0" step="1" name="structural_screws_per_corner"
               class="admin-input" value="{{ old('structural_screws_per_corner', $settings->structural_screws_per_corner) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-board-screws">2-1/2" deck screws / inch of board</label>
        <input id="ld-board-screws" type="number" min="0" step="0.0001" name="board_screws_per_inch"
               class="admin-input" value="{{ old('board_screws_per_inch', (float) $settings->board_screws_per_inch) }}">
        <p class="admin-form-help">0.2 = 2 screws per 10 in of board length.</p>
      </div>

      <div class="admin-form-group">
        <label for="ld-frame-t">3" frame screws / T-joint</label>
        <input id="ld-frame-t" type="number" min="0" step="1" name="frame_screws_per_t_joint"
               class="admin-input" value="{{ old('frame_screws_per_t_joint', $settings->frame_screws_per_t_joint) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-frame-cross">3" frame screws / + cross-joint</label>
        <input id="ld-frame-cross" type="number" min="0" step="1" name="frame_screws_per_cross_joint"
               class="admin-input" value="{{ old('frame_screws_per_cross_joint', $settings->frame_screws_per_cross_joint) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-tape-roll">Joist tape roll length (ft)</label>
        <input id="ld-tape-roll" type="number" min="1" step="1" name="joist_tape_roll_length_ft"
               class="admin-input" value="{{ old('joist_tape_roll_length_ft', (float) $settings->joist_tape_roll_length_ft) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-tape-overlap">Joist tape overlap (in. / member)</label>
        <input id="ld-tape-overlap" type="number" min="0" step="0.1" name="joist_tape_overlap_in"
               class="admin-input" value="{{ old('joist_tape_overlap_in', (float) $settings->joist_tape_overlap_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-kerf">Saw kerf (in.)</label>
        <input id="ld-kerf" type="number" min="0" step="0.001" name="kerf_in"
               class="admin-input" value="{{ old('kerf_in', (float) $settings->kerf_in) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-labour-rate">Labour rate (CA$ / sq ft)</label>
        <input id="ld-labour-rate" type="number" min="0" step="0.01" name="labour_rate_per_sqft"
               class="admin-input" value="{{ old('labour_rate_per_sqft', (float) $settings->labour_rate_per_sqft) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-waste-fee">Waste dump fee (CA$ / project)</label>
        <input id="ld-waste-fee" type="number" min="0" step="0.01" name="waste_dump_fee_cad"
               class="admin-input" value="{{ old('waste_dump_fee_cad', (float) $settings->waste_dump_fee_cad) }}">
      </div>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  <script>
    (function () {
      var catalogOptions = @json($catalogPickerOptions);
      var amazonVendorLinkTemplate = @json($amazonVendorLinkTemplate);

      function escapeHtml(text) {
        return String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
      }

      function amazonPickerUrl(asin) {
        return amazonVendorLinkTemplate.replace('PLACEHOLDER', encodeURIComponent(asin));
      }

      function normalizeAsin(value) {
        var candidate = String(value || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        if (!/^[A-Z0-9]{10}$/.test(candidate)) return '';
        if (!/[A-Z]/.test(candidate)) return '';
        return candidate;
      }

      function resolveItemStoreUrl(item, selectEl) {
        var asin = normalizeAsin(item && item.sku);
        if (!asin && selectEl) asin = normalizeAsin(selectEl.getAttribute('data-store-asin') || '');
        if (asin) return amazonPickerUrl(asin);
        var url = item && item.url ? String(item.url).trim() : '';
        if (!url && selectEl) url = String(selectEl.getAttribute('data-store-url') || '').trim();
        return url;
      }

      function setProductLink(linkEl, url) {
        if (!linkEl) return;
        if (!url) {
          linkEl.setAttribute('href', '#');
          linkEl.classList.add('fq-catalog-picker__open--disabled');
          return;
        }
        linkEl.setAttribute('href', url);
        linkEl.classList.remove('fq-catalog-picker__open--disabled');
      }

      function updateQtyPerBox(selectEl, value, optionMap) {
        var el = document.getElementById(selectEl.getAttribute('data-qty-per-box-target') || '');
        if (!el) return;
        if (!value) { el.textContent = '—'; return; }
        var item = optionMap[String(value)] || null;
        var qty = item && item.quantity_per_package ? parseInt(item.quantity_per_package, 10) : 0;
        el.textContent = qty > 0 ? String(qty) : '—';
      }

      function updatePreview(selectEl, value, optionMap) {
        var previewEl = document.getElementById(selectEl.getAttribute('data-preview-target') || '');
        var priceEl = document.getElementById(selectEl.getAttribute('data-price-target') || '');
        var linkEl = document.getElementById(selectEl.getAttribute('data-url-target') || '');
        if (!previewEl) return;

        if (!value) {
          previewEl.innerHTML = '<span class="admin-form-help">Select a catalog material.</span>';
          if (priceEl) priceEl.textContent = '—';
          setProductLink(linkEl, '');
          updateQtyPerBox(selectEl, '', optionMap);
          return;
        }

        var item = optionMap[String(value)] || null;
        if (item) {
          var html = '<span class="fq-material-preview__name">' + escapeHtml(item.name) + '</span>';
          if (item.sku) html += '<span class="admin-form-help fq-material-preview__sku-row">SKU ' + escapeHtml(item.sku) + '</span>';
          previewEl.innerHTML = html;
          if (priceEl) priceEl.textContent = item.price_label || '—';
          setProductLink(linkEl, resolveItemStoreUrl(item, selectEl));
        }
        updateQtyPerBox(selectEl, value, optionMap);
      }

      if (typeof TomSelect === 'undefined') return;

      var optionMap = {};
      catalogOptions.forEach(function (item) { optionMap[String(item.id)] = item; });

      document.querySelectorAll('.fq-catalog-picker__select').forEach(function (selectEl) {
        var initial = selectEl.getAttribute('data-initial') || '';
        var syncingInitial = true;
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
            if (syncingInitial) return;
            updatePreview(selectEl, value || this.getValue() || '', optionMap);
          }
        });

        if (initial && initial !== '0') ts.setValue(initial, true);
        updatePreview(selectEl, initial && initial !== '0' ? initial : '', optionMap);
        syncingInitial = false;
      });
    })();
  </script>
@endpush
