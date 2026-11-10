<div id="br-framing-fields" class="br-framing-fields admin-form-grid-4">
  <div id="br-framing-primary-slot" class="br-framing-primary-slot">
    @unless(($scopePreset ?? '') === 'wall')
      @include('admin.quotations.basement._wall_primary_fields')
    @endunless
  </div>
  <div class="admin-form-group">
    <span class="admin-label-with-tip">
  <label for="br-bulkhead">Duct boxing (lin. ft)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['bulkhead_lf'], 'tipLabel' => 'Duct boxing (lin. ft)'])
</span>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-bulkhead" name="bulkhead_lf"
           value="{{ $brVal('bulkhead_lf', 0) }}">
  </div>
  @php
    $storedHeaderInputs = (isset($quotation) && is_array($quotation->inputs ?? null))
      ? $quotation->inputs
      : (is_array($inputs ?? null) ? $inputs : []);
    $legacyHeaderQty = (! array_key_exists('header_lf', $storedHeaderInputs) && (int) ($storedHeaderInputs['header_qty'] ?? 0) > 0)
      ? (int) $storedHeaderInputs['header_qty']
      : 0;
  @endphp
  <div class="admin-form-group">
    <span class="admin-label-with-tip">
  <label for="br-headers">Headers / beams (lin. ft)</label>
  @include('admin.partials.info_tip', [
      'tipText' => $legacyHeaderQty > 0
          ? $legacyHeaderQty.' openings saved from a previous save. Enter linear feet here to switch to lin. ft pricing, or leave blank to keep the saved opening count. '.$brInfo['header_lf']
          : $brInfo['header_lf'],
      'tipLabel' => 'Headers / beams (lin. ft)',
  ])
</span>
    @if ($legacyHeaderQty > 0)
      <input type="hidden" name="header_qty" value="{{ $legacyHeaderQty }}">
    @endif
    @php
      $headerLfValue = old('header_lf');
      if ($headerLfValue === null) {
          $headerLfValue = array_key_exists('header_lf', $storedHeaderInputs)
            ? $storedHeaderInputs['header_lf']
            : ($legacyHeaderQty > 0 ? '' : 0);
      }
    @endphp
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-headers" name="header_lf"
           value="{{ $headerLfValue }}"
           @if ($legacyHeaderQty > 0) placeholder="{{ $legacyHeaderQty }} openings saved; enter lin. ft to replace" data-br-header-legacy="{{ $legacyHeaderQty }}" @endif>
  </div>
  <div class="admin-form-group br-field--phone-wide">
    <span class="admin-label-with-tip">
  <label for="br-stud-spacing">Stud spacing</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['stud_spacing_in'] ?? 'On-centre stud spacing. 16″ o.c. is the default.', 'tipLabel' => 'Stud spacing'])
</span>
    <select class="admin-input br-preview" id="br-stud-spacing" name="stud_spacing_in">
      @foreach (\App\Services\BasementQuotationCalculator::STUD_SPACINGS as $oc)
        <option value="{{ $oc }}" @selected((int) $brVal('stud_spacing_in', 16) === $oc)>{{ $oc }}" o.c.</option>
      @endforeach
    </select>
  </div>
</div>
