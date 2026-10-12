{{--
  Building labour surcharge. Same toggle + expandable pattern as "Visit required"
  (client_projects/_visit_required_toggle): switching it on reveals the percent
  field (default 30%).

  @param object|null  $quotation
  @param array|null   $inputs
  @param string       $previewClass   e.g. fl-preview-trigger / tm-preview / mw-preview / pt-recalc / tl-live-trigger
--}}
@php
  $quotation = $quotation ?? null;
  $inputs = is_array($inputs ?? null)
    ? $inputs
    : (is_array($quotation->inputs ?? null) ? $quotation->inputs : []);
  $previewClass = $previewClass ?? '';

  if (old('include_building') !== null && old('include_building') !== '') {
      $checked = filter_var(old('include_building'), FILTER_VALIDATE_BOOLEAN);
  } elseif (array_key_exists('include_building', $inputs)) {
      $checked = filter_var($inputs['include_building'], FILTER_VALIDATE_BOOLEAN);
  } elseif ($quotation && array_key_exists('include_building', $quotation->getAttributes())) {
      $checked = (bool) ($quotation->include_building ?? false);
  } else {
      $checked = false;
  }

  $percentRaw = old('building_labor_percent');
  if ($percentRaw === null || $percentRaw === '') {
      if (isset($inputs['building_labor_percent']) && $inputs['building_labor_percent'] !== '' && $inputs['building_labor_percent'] !== null) {
          $percentRaw = $inputs['building_labor_percent'];
      } elseif ($quotation && array_key_exists('building_labor_percent', $quotation->getAttributes()) && $quotation->building_labor_percent !== null) {
          $percentRaw = $quotation->building_labor_percent;
      } else {
          $percentRaw = \App\Support\QuotationBuildingLabor::DEFAULT_PERCENT;
      }
  }
  $percentDisplay = rtrim(rtrim(number_format((float) $percentRaw, 2, '.', ''), '0'), '.');
  if ($percentDisplay === '') {
      $percentDisplay = '30';
  }
@endphp

<div class="admin-card q-building-card" id="q-building">
  <input type="hidden" name="include_building" value="0">
  <span class="admin-label-with-tip">
    <label class="cp-visit-toggle" for="q-building-include">
      <input type="checkbox"
             name="include_building"
             value="1"
             id="q-building-include"
             class="{{ $previewClass }}"
             aria-controls="q-building-expandable"
             @checked($checked)>
      <span class="cp-visit-toggle__control" aria-hidden="true"></span>
      <span class="cp-visit-toggle__label">Building extra labour</span>
    </label>
    @include('admin.partials.info_tip', [
      'tipText' => 'For condominiums and multi-unit residential or commercial buildings. Adds a labour surcharge for elevator, parking, and loading-bay access. Hidden from the client PDF — folded into labour.',
      'tipLabel' => 'Building extra labour',
    ])
  </span>

  <div id="q-building-expandable"
       class="cp-visit-expandable @if ($checked) is-open @endif"
       aria-hidden="{{ $checked ? 'false' : 'true' }}"
       @unless($checked) inert @endunless>
    <div class="cp-visit-expandable__inner">
      <div class="q-building-percent-wrap" id="q-building-percent-wrap">
        <div class="admin-form-group q-building-field">
          <span class="admin-label-with-tip">
  <label for="q-building-percent">Additional labour</label>
  @include('admin.partials.info_tip', ['tipText' => 'Default 30%. Applies to labour only — not materials.', 'tipLabel' => 'Additional labour'])
</span>
          <div class="q-building-percent-row">
            <input type="number"
                   id="q-building-percent"
                   name="building_labor_percent"
                   class="admin-input {{ $previewClass }}"
                   min="0"
                   max="{{ \App\Support\QuotationBuildingLabor::MAX_PERCENT }}"
                   step="0.5"
                   inputmode="decimal"
                   autocomplete="off"
                   value="{{ $percentDisplay }}"
                   aria-label="Additional labour percent for buildings">
            <span class="q-building-percent-suffix">%</span>
          </div>
          @error('building_labor_percent')
            <p class="admin-form-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="q-building-added" id="q-building-added" aria-live="polite">
          <span class="q-building-added__label">Added to this quotation</span>
          <strong class="q-building-added__amount" id="q-building-added-amount">CA$0.00</strong>
          <p class="admin-form-help q-building-added__detail" id="q-building-added-detail">30% of labour</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .q-building-card { margin-bottom: 16px; }
  .q-building-card .cp-visit-toggle { min-height: 44px; }
  .q-building-card .cp-visit-expandable.is-open.is-settled .cp-visit-expandable__inner { overflow: visible; }
  .q-building-percent-wrap {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 10px;
    padding-top: 12px;
  }
  .q-building-field { margin: 0; }
  .q-building-field > label { margin-bottom: 0.25rem; }
  .q-building-percent-row {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .q-building-percent-row .admin-input,
  .q-building-field input[type="number"].admin-input {
    width: 5.5rem;
    max-width: 5.5rem;
    flex: 0 0 auto;
  }
  .q-building-percent-suffix {
    font-weight: 600;
    color: var(--admin-muted);
  }
  .q-building-added {
    padding: 8px 12px;
    border: 1px solid var(--admin-border);
    border-radius: 8px;
    background: var(--admin-surface-hover);
  }
  .q-building-added__label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--admin-muted);
  }
  .q-building-added__amount {
    display: block;
    margin-top: 2px;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--admin-heading);
    font-variant-numeric: tabular-nums;
  }
  .q-building-added__detail { margin: 2px 0 0; }
  @media (min-width: 701px) {
    .q-building-percent-wrap {
      flex-direction: row;
      flex-wrap: wrap;
      align-items: center;
      gap: 16px 24px;
    }
    .q-building-field { flex: 0 0 auto; }
    .q-building-added {
      display: flex;
      flex-wrap: wrap;
      align-items: baseline;
      gap: 2px 14px;
      flex: 0 1 auto;
      width: max-content;
      max-width: 100%;
      padding: 8px 14px;
    }
    .q-building-added__label,
    .q-building-added__amount,
    .q-building-added__detail {
      display: inline;
      margin: 0;
    }
  }
  @media (min-width: 900px) {
    .q-building-added { margin-left: auto; }
  }
</style>
<script src="{{ asset('js/admin-quotation-building.js') }}?v={{ @filemtime(public_path('js/admin-quotation-building.js')) ?: time() }}"></script>
