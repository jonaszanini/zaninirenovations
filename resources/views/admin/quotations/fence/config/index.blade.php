@extends('admin.layouts.app')

@section('heading', 'Fence Configuration')

@section('content')
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.fence.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to fence quotations
      </a>
    </div>
  </div>

  {{-- ==============================================================
       Materials catalogue
       Fixed list, seeded at migration time. Admins edit product
       details, price, URL, and (for nails) quantity-per-box.
       ============================================================== --}}
  <form method="post" action="{{ route('admin.quotations.fence.config.materials.update') }}" class="admin-card fq-config-card">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 style="margin:0;">Materials catalogue</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Enter store product details, the current price (before tax, in CAD$), and optionally a link to the product page.
          The calculator pulls these values live when generating an estimate.
        </p>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save catalogue
      </button>
    </div>

    <div class="admin-table-scroll">
      <table class="admin-table fq-materials-table">
        <thead>
          <tr>
            <th>Material</th>
            <th style="min-width:200px;">Long name</th>
            <th style="min-width:120px;">Model</th>
            <th style="min-width:120px;">Store SKU</th>
            <th style="width:170px;">Price (CAD$, pre-tax)</th>
            <th style="width:130px;">Quantity / box</th>
            <th style="min-width:180px;">Product URL</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($materials as $i => $m)
            <tr>
              <td>
                <strong>{{ $m->name }}</strong>
                <div class="admin-form-help" style="font-size:11px;">key: <code>{{ $m->key }}</code></div>
                <input type="hidden" name="materials[{{ $i }}][id]" value="{{ $m->id }}">
              </td>
              <td>
                <input type="text"
                       name="materials[{{ $i }}][long_name]"
                       class="admin-input"
                       maxlength="500"
                       placeholder="Full product title…"
                       value="{{ old('materials.'.$i.'.long_name', $m->long_name) }}">
              </td>
              <td>
                <input type="text"
                       name="materials[{{ $i }}][model]"
                       class="admin-input"
                       maxlength="160"
                       placeholder="Model #"
                       value="{{ old('materials.'.$i.'.model', $m->model) }}">
              </td>
              <td>
                <input type="text"
                       name="materials[{{ $i }}][store_sku]"
                       class="admin-input"
                       maxlength="160"
                       placeholder="SKU"
                       value="{{ old('materials.'.$i.'.store_sku', $m->store_sku) }}">
              </td>
              <td>
                <input type="text"
                       name="materials[{{ $i }}][price_cad]"
                       class="admin-input cp-money-input"
                       inputmode="decimal"
                       autocomplete="off"
                       value="{{ old('materials.'.$i.'.price_cad', number_format((float) $m->price_cad, 2, '.', '')) }}">
              </td>
              <td>
                @if ($m->key === \App\Models\FenceMaterial::KEY_NAILS)
                  <input type="number"
                         min="1"
                         step="1"
                         name="materials[{{ $i }}][quantity_per_box]"
                         class="admin-input"
                         value="{{ old('materials.'.$i.'.quantity_per_box', $m->quantity_per_box) }}">
                @else
                  <span class="admin-form-help">—</span>
                @endif
              </td>
              <td>
                <div class="fq-url-field">
                  <input type="url"
                         name="materials[{{ $i }}][url]"
                         class="admin-input"
                         placeholder="https://..."
                         value="{{ old('materials.'.$i.'.url', $m->url) }}">
                  @if ($m->url)
                    <a href="{{ $m->url }}"
                       target="_blank"
                       rel="noopener"
                       class="fq-url-open"
                       aria-label="Open product URL in new tab"
                       title="Open product URL">
                      <i class="fa fa-external-link"></i>
                    </a>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </form>

  {{-- ==============================================================
       Formula parameters
       ============================================================== --}}
  <form method="post" action="{{ route('admin.quotations.fence.config.formula.update') }}" class="admin-card fq-config-card" style="margin-top:16px;">
    @csrf
    @method('PUT')
    <div class="fq-config-header">
      <div>
        <h3 style="margin:0;">Formula parameters</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Structural assumptions and per-height labour rates used by the calculator.
        </p>
      </div>
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save formula
      </button>
    </div>

    <div class="fq-formula-grid">
      <div class="admin-form-group">
        <label for="fq-labour-rate-6">Labour rate 6&prime; fence (CAD$ / linear ft)</label>
        <input id="fq-labour-rate-6" type="text" name="labour_rate_per_ft"
               class="admin-input cp-money-input" inputmode="decimal"
               value="{{ old('labour_rate_per_ft', number_format((float) $settings->labour_rate_per_ft, 2, '.', '')) }}">
        <p class="admin-form-help">Used when fence height is 6&prime;. HST is not applied to labour.</p>
      </div>

      <div class="admin-form-group">
        <label for="fq-labour-rate-8">Labour rate 8&prime; fence (CAD$ / linear ft)</label>
        <input id="fq-labour-rate-8" type="text" name="labour_rate_8ft_per_ft"
               class="admin-input cp-money-input" inputmode="decimal"
               value="{{ old('labour_rate_8ft_per_ft', number_format((float) ($settings->labour_rate_8ft_per_ft ?? $settings->labour_rate_per_ft), 2, '.', '')) }}">
        <p class="admin-form-help">Used when fence height is 8&prime;. HST is not applied to labour.</p>
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
        <label for="fq-gap">Standard gap between boards (inches)</label>
        <input id="fq-gap" type="number" min="0" step="0.001" name="standard_gap_in"
               class="admin-input"
               value="{{ old('standard_gap_in', (float) $settings->standard_gap_in) }}">
        <p class="admin-form-help">Only used in "standard" board style.</p>
      </div>
    </div>
  </form>
@endsection

