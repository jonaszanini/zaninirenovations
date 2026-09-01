@extends('admin.layouts.app')

@section('heading', 'Low Deck Configuration')

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
      @include('admin.partials.quotation_back_to_quote')
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
      <a href="{{ route('admin.quotations.low-deck.config.work-description') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-file-text-o"></i> Work description template
      </a>
    </div>
  </div>

  <form method="post" action="{{ route('admin.quotations.low-deck.config.materials.update') }}" class="admin-card fq-config-card">
    @csrf
    @method('PUT')
    @include('admin.partials.quotation_return_quote_hidden')
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

    <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>
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
                @php
                  $ldPickerValue = (int) old('materials.'.$i.'.material_id', $m->material_id);
                  $ldPickerAttrs = [
                    'data-preview-target' => 'ld-preview-'.$i,
                    'data-price-target' => 'ld-price-'.$i,
                    'data-url-target' => 'ld-link-'.$i,
                  ];
                  if ($isBox) {
                      $ldPickerAttrs['data-qty-per-box-target'] = 'ld-qty-per-box-'.$i;
                  }
                  if (filled($pickerUrl)) {
                      $ldPickerAttrs['data-store-url'] = $pickerUrl;
                      $ldPickerAttrs['data-link-vendor'] = $linkVendor;
                  }
                  if ($storeAsin) {
                      $ldPickerAttrs['data-store-asin'] = $storeAsin;
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
                  @include('admin.partials.search_select', [
                    'id' => 'ld-catalog-picker-'.$i,
                    'name' => 'materials['.$i.'][material_id]',
                    'value' => $ldPickerValue > 0 ? $ldPickerValue : '',
                    'hideLabel' => true,
                    'placeholder' => 'Search name or SKU…',
                    'ariaLabel' => 'Catalog material for '.$m->effectiveName(),
                    'optionsSource' => 'admin-ss-catalog-options',
                    'searchFields' => ['label', 'name', 'sku', 'model', 'vendor'],
                    'metaKeys' => ['sku', 'price_label', 'delivery_label'],
                    'emptyMessage' => 'No matching materials',
                    'maxOptions' => 80,
                    'listParent' => 'body',
                    'attrs' => $ldPickerAttrs,
                  ])
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
        <label>Field joist spacing</label>
        <p class="admin-form-help">Calculated automatically from deck width/length (uniform bays, max 15 in.). Edge joists are fixed 2¼ in. inside the perimeter rim.</p>
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
        <label for="ld-under-deck-gravel-coverage">Under-deck gravel coverage (sq ft / bag)</label>
        <input id="ld-under-deck-gravel-coverage" type="number" min="0.5" step="0.1"
               name="under_deck_gravel_sq_ft_per_bag" class="admin-input"
               value="{{ old('under_deck_gravel_sq_ft_per_bag', (float) ($settings->under_deck_gravel_sq_ft_per_bag ?? 8)) }}">
        <p class="admin-form-help">Deck area covered by one under-deck gravel bag when “Gravel under the deck” is enabled.</p>
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
        <input id="ld-labour-rate" type="text" name="labour_rate_per_sqft"
               class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" placeholder="0.00"
               data-cp-money-entry="direct"
               value="{{ old('labour_rate_per_sqft', number_format((float) $settings->labour_rate_per_sqft, 2, '.', '')) }}">
      </div>

      <div class="admin-form-group">
        <label for="ld-waste-fee">Waste dump fee (CA$ / project)</label>
        <input id="ld-waste-fee" type="text" name="waste_dump_fee_cad"
               class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" placeholder="0.00"
               data-cp-money-entry="direct"
               value="{{ old('waste_dump_fee_cad', number_format((float) $settings->waste_dump_fee_cad, 2, '.', '')) }}">
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

      var optionMap = {};
      catalogOptions.forEach(function (item) { optionMap[String(item.id)] = item; });

      document.querySelectorAll('.fq-catalog-picker-wrap [data-admin-search-select]').forEach(function (root) {
        var initial = window.AdminSearchSelect ? AdminSearchSelect.getValue(root) : '';
        updatePreview(root, initial && initial !== '0' ? initial : '', optionMap);
        root.addEventListener('admin-search-select:change', function (e) {
          updatePreview(root, (e.detail && e.detail.id) || '', optionMap);
        });
      });
    })();
  </script>
@endpush
