@extends('admin.layouts.app')

@section('heading', 'Fence Configuration')

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
        <h3 style="margin:0;">Materials catalogue</h3>
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
        <strong>Sync fence prices</strong> updates linked catalog materials via SerpApi ({{ number_format($linkedSyncCount) }} linked with a product URL).
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
                <strong>{{ $m->name }}</strong>
                <div class="admin-form-help" style="font-size:11px;">key: <code>{{ $m->key }}</code></div>
                <input type="hidden" name="materials[{{ $i }}][id]" value="{{ $m->id }}">
              </td>
              <td>
                <select name="materials[{{ $i }}][material_id]"
                        class="admin-input fq-material-select"
                        data-preview-target="fq-preview-{{ $i }}"
                        aria-label="Catalog material for {{ $m->name }}">
                  <option value="">— Select material —</option>
                  @foreach ($catalogMaterials as $catalog)
                    <option value="{{ $catalog->id }}"
                            @selected((int) old('materials.'.$i.'.material_id', $m->material_id) === (int) $catalog->id)
                            data-name="{{ e($catalog->name) }}"
                            data-sku="{{ e($catalog->store_sku ?? '') }}"
                            data-model="{{ e($catalog->model ?? '') }}"
                            data-price="{{ number_format((float) $catalog->price_cad, 2, '.', '') }}"
                            data-url="{{ e($catalog->product_url ?? '') }}"
                            data-vendor="{{ e($catalog->vendor ?? '') }}">
                      {{ $catalog->name }}
                      @if ($catalog->store_sku)
                        · {{ $catalog->store_sku }}
                      @endif
                      · {{ \App\Models\ClientProject::formatMoney((float) $catalog->price_cad) }}
                    </option>
                  @endforeach
                </select>
              </td>
              <td>
                <div id="fq-preview-{{ $i }}" class="fq-material-preview">
                  @if ($m->material_id && $m->material)
                    <span class="fq-material-preview__name">{{ $m->material->name }}</span>
                    @if ($m->material->store_sku)
                      <span class="admin-form-help">SKU {{ $m->material->store_sku }}</span>
                    @endif
                    @if ($m->material->model)
                      <span class="admin-form-help">Model {{ $m->material->model }}</span>
                    @endif
                    @if ($m->material->product_url)
                      <a href="{{ $m->material->product_url }}" target="_blank" rel="noopener" class="admin-text-link fq-material-preview__link">Product page</a>
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
                  @elseif ($m->effectivePriceCad() > 0)
                    {{ \App\Models\ClientProject::formatMoney($m->effectivePriceCad()) }}
                  @else
                    —
                  @endif
                </span>
              </td>
              <td>
                @if ($m->key === \App\Models\FenceMaterial::KEY_NAILS)
                  <input type="number"
                         min="1"
                         step="1"
                         name="materials[{{ $i }}][quantity_per_box]"
                         class="admin-input"
                         value="{{ old('materials.'.$i.'.quantity_per_box', $m->quantity_per_box) }}">
                  <p class="admin-form-help">Nails per box (for per-nail pricing).</p>
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
        <h3 style="margin:0;">Formula parameters</h3>
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
  <script>
    (function () {
      function formatMoney(amount) {
        var n = parseFloat(amount);
        if (isNaN(n)) return '—';
        return '$' + n.toFixed(2);
      }

      function updatePreview(select) {
        var targetId = select.getAttribute('data-preview-target');
        var previewEl = targetId ? document.getElementById(targetId) : null;
        var row = select.closest('tr');
        var priceEl = row ? row.querySelector('.fq-material-preview__price') : null;
        var option = select.options[select.selectedIndex];

        if (!previewEl) return;

        if (!option || !option.value) {
          previewEl.innerHTML = '<span class="admin-form-help">Select a catalog material.</span>';
          if (priceEl) priceEl.textContent = '—';
          return;
        }

        var name = option.getAttribute('data-name') || '';
        var sku = option.getAttribute('data-sku') || '';
        var model = option.getAttribute('data-model') || '';
        var url = option.getAttribute('data-url') || '';
        var price = option.getAttribute('data-price') || '';

        var html = '<span class="fq-material-preview__name">' + name + '</span>';
        if (sku) {
          html += '<span class="admin-form-help">SKU ' + sku + '</span>';
        }
        if (model) {
          html += '<span class="admin-form-help">Model ' + model + '</span>';
        }
        if (url) {
          html += '<a href="' + url + '" target="_blank" rel="noopener" class="admin-text-link fq-material-preview__link">Product page</a>';
        }
        previewEl.innerHTML = html;
        if (priceEl) {
          priceEl.textContent = formatMoney(price);
        }
      }

      document.querySelectorAll('.fq-material-select').forEach(function (select) {
        select.addEventListener('change', function () {
          updatePreview(select);
        });
      });
    })();
  </script>
@endpush
