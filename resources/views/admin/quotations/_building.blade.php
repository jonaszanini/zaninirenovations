{{--
  Building labour surcharge. Checkbox stays collapsed; checking it reveals
  the percent field (default 30%). Same reveal pattern as Include a helper.

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
  <h3 class="q-building-card__title"><i class="fa fa-building" aria-hidden="true"></i> Building</h3>
  <p class="admin-form-help q-building-card__intro">For condominiums and multi-unit residential or commercial buildings.</p>

  @include('admin.partials.checkbox', [
    'name' => 'include_building',
    'id' => 'q-building-include',
    'hiddenFallback' => true,
    'checked' => $checked,
    'label' => 'Add extra labour for this building',
    'info' => 'Adds a labour surcharge for elevator, parking, and loading-bay access. Hidden from the client PDF — folded into labour.',
    'inputClass' => trim('admin-checkbox__input '.$previewClass),
    'attrs' => ['data-reveal' => 'q-building-percent-wrap'],
  ])

  <div class="admin-form-group q-building-percent-wrap" id="q-building-percent-wrap" @unless($checked) hidden @endunless>
    <label for="q-building-percent">Additional labour</label>
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
    <p class="admin-form-help">Default 30%. Applies to labour only — not materials.</p>
    @error('building_labor_percent')
      <p class="admin-form-error">{{ $message }}</p>
    @enderror
  </div>
</div>

<style>
  .q-building-card { margin-bottom: 16px; }
  .q-building-card__title {
    margin: 0 0 6px;
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--admin-heading);
  }
  .q-building-card__intro { margin: 0 0 12px; }
  .q-building-percent-wrap { margin: 12px 0 0; }
  .q-building-percent-row {
    display: flex;
    align-items: center;
    gap: 8px;
    max-width: 200px;
  }
  .q-building-percent-row .admin-input { min-width: 0; flex: 1; }
  .q-building-percent-suffix {
    font-weight: 600;
    color: var(--admin-muted);
  }
</style>
