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
  <div class="admin-form-group">
    <span class="admin-label-with-tip">
  <label for="br-headers">Headers / beams (lin. ft)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['header_lf'], 'tipLabel' => 'Headers / beams (lin. ft)'])
</span>
    @php
      $legacyHeaderQty = (!\Illuminate\Support\Arr::has($inputs ?? [], 'header_lf') && (int) ($brVal('header_qty', 0)) > 0)
        ? (int) $brVal('header_qty', 0)
        : 0;
    @endphp
    @if ($legacyHeaderQty > 0)
      <input type="hidden" name="header_qty" value="{{ $legacyHeaderQty }}">
    @endif
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-headers" name="header_lf"
           value="{{ $brVal('header_lf', 0) }}"
           @if ($legacyHeaderQty > 0) placeholder="{{ $legacyHeaderQty }} opening(s) saved" @endif>
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
