@php
  $workIndex = $workIndex ?? 0;
  $work = is_array($work ?? null) ? $work : [];
  $workCount = (int) ($workCount ?? 1);
  $isOpen = (bool) ($isOpen ?? true);
  $title = old('works.'.$workIndex.'.title', $work['title'] ?? '');
  $matRows = old('works.'.$workIndex.'.materials', $work['materials'] ?? null);
  if (!is_array($matRows) || count($matRows) === 0) {
      $matRows = [['short_name' => '', 'description' => '', 'url' => '', 'image_url' => '', 'unit_price' => '', 'quantity' => '1', 'apply_hst' => true, 'hst_includes_unit' => false, 'catalog_material_id' => null, 'delivery_cad' => 0]];
  }
  $labourMode = old('works.'.$workIndex.'.labour_mode', $work['labour_mode'] ?? 'hours');
  $labourQty = old('works.'.$workIndex.'.labour_quantity', isset($work['labour_quantity']) && $work['labour_quantity'] !== null && $work['labour_quantity'] !== '' ? (string) $work['labour_quantity'] : '');
  $labourRateRaw = old('works.'.$workIndex.'.labour_hourly_rate', $work['labour_hourly_rate'] ?? '65.00');
  $labourRate = $labourRateRaw === '' || $labourRateRaw === null
      ? '65.00'
      : (is_numeric($labourRateRaw) ? number_format((float) $labourRateRaw, 2, '.', '') : (string) $labourRateRaw);
  $labourUseFixed = filter_var(old('works.'.$workIndex.'.labour_use_fixed', $work['labour_use_fixed'] ?? false), FILTER_VALIDATE_BOOLEAN);
  $labourFixedAmount = old('works.'.$workIndex.'.labour_fixed_amount');
  if ($labourFixedAmount === null && array_key_exists('labour_fixed_amount', $work) && $work['labour_fixed_amount'] !== null && $work['labour_fixed_amount'] !== '') {
      $labourFixedAmount = number_format((float) $work['labour_fixed_amount'], 2, '.', '');
  }
  if ($labourFixedAmount === null) {
      $labourFixedAmount = '';
  }
  $labourFixedHstInc = filter_var(old('works.'.$workIndex.'.labour_fixed_hst_included', $work['labour_fixed_hst_included'] ?? false), FILTER_VALIDATE_BOOLEAN);
  $labourQtyLabel = $labourMode === 'days' ? 'Estimated days' : 'Estimated hours';
  $labourEquiv = '';
  if (! $labourUseFixed && $labourQty !== '' && is_numeric($labourQty) && (float) $labourQty > 0) {
      if ($labourMode === 'days') {
          $hours = round((float) $labourQty * 8, 2);
          $labourEquiv = '≈ '.rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.').' hours';
      } else {
          $days = round((float) $labourQty / 8, 3);
          $labourEquiv = '≈ '.rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.').' days (8 h/day)';
      }
  }
  $jobNumber = is_numeric($workIndex) ? ((int) $workIndex + 1) : 1;
  $idBase = 'sq-w'.$workIndex;
  $includeContingency = (bool) ($includeContingency ?? false);
  $contingencyEnabled = (bool) ($contingencyEnabled ?? true);
  $contingencyPercent = ($contingencyPercent ?? '') !== '' ? $contingencyPercent : '10';
@endphp
<article class="admin-card sq-work-card{{ $isOpen ? ' is-open' : '' }}" data-work-index="{{ $workIndex }}">
  <header class="sq-work-card__header">
    <button type="button" class="sq-work-toggle" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-label="Expand or collapse this job">
      <i class="fa fa-chevron-down" aria-hidden="true"></i>
    </button>
    <span class="sq-work-number">Job <span class="sq-work-number-val">{{ $jobNumber }}</span></span>
    <div class="admin-form-group sq-work-title-group">
      <label class="visually-hidden" for="{{ $idBase }}-title">Job name</label>
      <input type="text" id="{{ $idBase }}-title" name="works[{{ $workIndex }}][title]"
             class="admin-input sq-work-title @error('works.'.$workIndex.'.title') is-invalid @enderror"
             maxlength="160"
             placeholder="{{ $workCount >= 2 ? 'e.g. Replace vanity' : 'Job name (optional)' }}"
             value="{{ $title }}">
      @error('works.'.$workIndex.'.title') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>
    <span class="sq-work-subtotal" aria-label="Job subtotal before HST">CA$0.00</span>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm sq-remove-work" aria-label="Remove this job" title="Remove this job" @if ($workCount < 2) hidden @endif>
      <i class="fa fa-trash"></i>
    </button>
  </header>

  <div class="sq-work-card__body">
    <div class="sq-layout">
      <div class="sq-panel sq-panel--nested">
        <div class="sq-panel-head">
          <h3 class="cp-form-help-flush">Materials</h3>
        </div>
        <p class="admin-form-help sq-materials-intro">
          Each row: <strong>Type manually</strong>, <strong>From Materials DB</strong> (<a href="{{ route('admin.materials.index') }}" class="admin-text-link">catalog</a>),
          or <strong>Electrical work</strong> (its own line on the client PDF).
          Unit price is before tax unless you check <strong>Price includes HST</strong>.
        </p>

        <div class="sq-materials-list">
          @foreach ($matRows as $i => $row)
            @include('admin.quotations.standard._material_row', ['workIndex' => $workIndex, 'i' => $i, 'row' => $row])
          @endforeach
        </div>

        <div class="sq-materials-end">
          <div class="sq-materials-footer-start">
            @if ($includeContingency)
              <div class="sq-contingency-inline" id="sq-contingency-block">
                <div class="sq-contingency-controls">
                  <input type="hidden" name="contingency_enabled" value="0">
                  <label class="admin-checkbox sq-checkbox-label sq-contingency-toggle">
                    <input type="checkbox" id="sq-contingency-enabled" name="contingency_enabled" value="1" class="admin-checkbox__input" @checked($contingencyEnabled)>
                    <span class="admin-checkbox__mark" aria-hidden="true"></span>
                    <span class="admin-checkbox__text">Contingency allowance</span>
                  </label>
                  <div class="sq-contingency-pct-field">
                    <input type="text" inputmode="decimal" autocomplete="off" id="sq-contingency-percent"
                           name="contingency_percent" class="admin-input sq-contingency-percent"
                           maxlength="6" placeholder="10" value="{{ $contingencyPercent }}"
                           aria-label="Contingency allowance percent"
                           @disabled(!$contingencyEnabled)>
                    <span class="sq-contingency-pct-suffix">%</span>
                  </div>
                </div>
                @error('contingency_percent') <p class="admin-form-error">{{ $message }}</p> @enderror
              </div>
            @endif
            <div class="sq-add-material-wrap">
              <button type="button" class="admin-btn admin-btn--ghost sq-add-material">
                <i class="fa fa-plus"></i> Add material
              </button>
            </div>
          </div>

          <div class="cp-quotation-totals sq-materials-totals">
            <div class="cp-quotation-totals-row">
              <span>Total materials (before HST)</span>
              <span class="sq-job-mat-subtotal">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row">
              <span>Total materials with HST</span>
              <span class="sq-job-mat-with-hst">CA$0.00</span>
            </div>
          </div>
        </div>
      </div>

      <div class="sq-panel sq-panel--nested sq-labour-panel">
        <div class="sq-labour-head">
          <h3 class="sq-labour-title">
            Labour
            @include('admin.partials.info_tip', ['text' => 'Labour includes HST (13%) unless you turn it off in Totals. A flat fee is one dollar amount. Otherwise use hourly rate × hours or days (8 h = 1 day).'])
          </h3>
          <input type="hidden" name="works[{{ $workIndex }}][labour_use_fixed]" value="0">
          <label class="admin-checkbox sq-checkbox-label sq-labour-fixed-toggle">
            <input type="checkbox" name="works[{{ $workIndex }}][labour_use_fixed]" value="1" class="admin-checkbox__input sq-labour-use-fixed-cb" @checked($labourUseFixed)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Flat labour fee</span>
          </label>
        </div>

        <div class="sq-labour-fixed-fields" @unless($labourUseFixed) hidden @endunless>
          <div class="admin-form-group">
            <label for="{{ $idBase }}-labour-fixed-amount">Amount (CAD)</label>
            <input type="text" inputmode="decimal" autocomplete="off" id="{{ $idBase }}-labour-fixed-amount" name="works[{{ $workIndex }}][labour_fixed_amount]"
                   class="admin-input cp-money-input sq-labour-fixed-amount" placeholder="0.00" value="{{ $labourFixedAmount }}">
            @error('works.'.$workIndex.'.labour_fixed_amount') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="sq-labour-hst-field">
            <input type="hidden" name="works[{{ $workIndex }}][labour_fixed_hst_included]" value="0">
            <label class="admin-checkbox sq-checkbox-label">
              <input type="checkbox" name="works[{{ $workIndex }}][labour_fixed_hst_included]" value="1" class="admin-checkbox__input sq-labour-fixed-hst-inc-cb" @checked($labourFixedHstInc)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">HST included</span>
            </label>
            @include('admin.partials.info_tip', ['text' => 'When checked, the amount is the total with tax. Before-tax labour is calculated the same way as a material with an HST-included price.'])
          </div>
        </div>

        <div class="sq-labour-rate-fields" @if($labourUseFixed) hidden @endif>
          <div class="admin-form-group sq-labour-rate-group">
            <label for="{{ $idBase }}-labour-rate">Hourly rate (CAD) @unless($labourUseFixed)<span class="cp-required">*</span>@endunless</label>
            <input type="text" inputmode="decimal" id="{{ $idBase }}-labour-rate" name="works[{{ $workIndex }}][labour_hourly_rate]" class="admin-input cp-money-input sq-labour-rate"
                   @unless($labourUseFixed) required @endunless placeholder="65.00" value="{{ $labourRate }}">
            @error('works.'.$workIndex.'.labour_hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>

          <fieldset class="sq-labour-mode-fieldset">
            <legend>Estimate by</legend>
            <label class="cp-checkbox-row">
              <input type="radio" name="works[{{ $workIndex }}][labour_mode]" value="hours" class="sq-labour-mode" @checked($labourMode === 'hours')>
              <span>Hours</span>
            </label>
            <label class="cp-checkbox-row">
              <input type="radio" name="works[{{ $workIndex }}][labour_mode]" value="days" class="sq-labour-mode" @checked($labourMode === 'days')>
              <span>Days <span class="admin-form-help">× 8 h</span></span>
            </label>
          </fieldset>

          <div class="admin-form-group sq-labour-qty-group">
            <label for="{{ $idBase }}-labour-qty" class="sq-labour-qty-label">{{ $labourQtyLabel }}</label>
            <input type="number" id="{{ $idBase }}-labour-qty" name="works[{{ $workIndex }}][labour_quantity]" class="admin-input sq-labour-qty" min="0" step="0.01"
                   value="{{ $labourQty }}" placeholder="0">
            @error('works.'.$workIndex.'.labour_quantity') <p class="admin-form-error">{{ $message }}</p> @enderror
            @error('works.'.$workIndex.'.labour_mode') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help sq-labour-equiv">{{ $labourEquiv }}</p>
          </div>
        </div>

        <div class="cp-quotation-totals sq-labour-totals">
          <div class="cp-quotation-totals-row">
            <span>Total labour (before HST)</span>
            <span class="sq-job-labour-total">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row sq-job-labour-hst-row">
            <span>HST on labour (13%)</span>
            <span class="sq-job-labour-hst">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span class="sq-job-labour-with-hst-label">Total labour with HST</span>
            <span class="sq-job-labour-with-hst">CA$0.00</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>
