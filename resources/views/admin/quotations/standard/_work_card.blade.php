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
  $jobNumber = is_numeric($workIndex) ? ((int) $workIndex + 1) : 1;
  $idBase = 'sq-w'.$workIndex;
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

        <div class="sq-add-material-wrap">
          <button type="button" class="admin-btn admin-btn--ghost sq-add-material-wide sq-add-material">
            <i class="fa fa-plus"></i> Add material
          </button>
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

      <div class="sq-panel sq-panel--nested">
        <h3 style="margin:0 0 8px;">Labour</h3>
        <p class="admin-form-help">Labour is <strong>HST-taxable</strong> at 13%. Use a <strong>flat labour fee</strong> for a fixed dollar amount, or estimate by <strong>rate × hours/days</strong> (<span title="1 day = 8 hours">8 h = 1 day</span>).</p>

        <div class="admin-form-group sq-labour-fixed-block">
          <input type="hidden" name="works[{{ $workIndex }}][labour_use_fixed]" value="0">
          <label class="admin-checkbox sq-checkbox-label cp-geometry-hint">
            <input type="checkbox" name="works[{{ $workIndex }}][labour_use_fixed]" value="1" class="admin-checkbox__input sq-labour-use-fixed-cb" @checked($labourUseFixed)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text"><strong>Flat labour fee</strong> (skip rate &amp; hours)</span>
          </label>
          <div class="sq-labour-fixed-fields">
            <label for="{{ $idBase }}-labour-fixed-amount">Amount (CAD)</label>
            <input type="text" inputmode="decimal" autocomplete="off" id="{{ $idBase }}-labour-fixed-amount" name="works[{{ $workIndex }}][labour_fixed_amount]"
                   class="admin-input cp-money-input sq-labour-fixed-amount" placeholder="0.00" value="{{ $labourFixedAmount }}">
            <div class="cp-form-group-spaced">
              <input type="hidden" name="works[{{ $workIndex }}][labour_fixed_hst_included]" value="0">
              <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label" style="display:inline-flex;">
                <input type="checkbox" name="works[{{ $workIndex }}][labour_fixed_hst_included]" value="1" class="admin-checkbox__input sq-labour-fixed-hst-inc-cb" @checked($labourFixedHstInc)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">HST included</span>
              </label>
            </div>
            <p class="admin-form-help" style="margin-top:8px;">When <strong>HST included</strong> is checked, the amount is the total with tax; before-tax labour is calculated the same way as materials with <strong>HST-incl. price</strong>.</p>
          </div>
          @error('works.'.$workIndex.'.labour_fixed_amount') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>

        <div class="sq-labour-rate-fields">
          <div class="admin-form-group">
            <label for="{{ $idBase }}-labour-rate">Hourly rate (CAD) @unless($labourUseFixed)<span class="cp-required">*</span>@endunless</label>
            <input type="text" inputmode="decimal" id="{{ $idBase }}-labour-rate" name="works[{{ $workIndex }}][labour_hourly_rate]" class="admin-input cp-money-input sq-labour-rate"
                   @unless($labourUseFixed) required @endunless placeholder="65.00" value="{{ $labourRate }}">
            @error('works.'.$workIndex.'.labour_hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>

          <fieldset class="sq-labour-mode-fieldset">
            <legend class="admin-form-label">Estimate by</legend>
            <label class="cp-checkbox-row">
              <input type="radio" name="works[{{ $workIndex }}][labour_mode]" value="hours" class="sq-labour-mode" @checked($labourMode === 'hours')>
              <span>Hours</span>
            </label>
            <label class="cp-checkbox-row">
              <input type="radio" name="works[{{ $workIndex }}][labour_mode]" value="days" class="sq-labour-mode" @checked($labourMode === 'days')>
              <span>Days</span> <span class="admin-form-help">(× 8 h)</span>
            </label>
          </fieldset>

          <div class="admin-form-group">
            <label for="{{ $idBase }}-labour-qty" class="sq-labour-qty-label">Estimated hours</label>
            <input type="number" id="{{ $idBase }}-labour-qty" name="works[{{ $workIndex }}][labour_quantity]" class="admin-input sq-labour-qty" min="0" step="0.01"
                   value="{{ $labourQty }}" placeholder="0">
            @error('works.'.$workIndex.'.labour_quantity') <p class="admin-form-error">{{ $message }}</p> @enderror
            @error('works.'.$workIndex.'.labour_mode') <p class="admin-form-error">{{ $message }}</p> @enderror
            <p class="admin-form-help sq-labour-equiv" style="margin-top:6px;"></p>
          </div>
        </div>

        <div class="cp-quotation-totals sq-labour-totals">
          <div class="cp-quotation-totals-row">
            <span>Total labour (before HST)</span>
            <span class="sq-job-labour-total">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>HST on labour (13%)</span>
            <span class="sq-job-labour-hst">CA$0.00</span>
          </div>
          <div class="cp-quotation-totals-row">
            <span>Total labour with HST</span>
            <span class="sq-job-labour-with-hst">CA$0.00</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</article>
