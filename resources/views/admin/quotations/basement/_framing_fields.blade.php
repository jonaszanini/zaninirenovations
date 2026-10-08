<div id="br-framing-fields" class="br-framing-fields admin-form-grid-4">
  <div id="br-framing-primary-slot" class="br-framing-primary-slot">
    @unless(($scopePreset ?? '') === 'wall')
      @include('admin.quotations.basement._wall_primary_fields')
    @endunless
  </div>
  <div class="admin-form-group">
    <label for="br-bulkhead">@include('admin.partials.info_tip', ['text' => $brInfo['bulkhead_lf']]) Duct boxing (lin. ft)</label>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-bulkhead" name="bulkhead_lf"
           value="{{ $brVal('bulkhead_lf', 0) }}">
  </div>
  <div class="admin-form-group">
    <label for="br-headers">@include('admin.partials.info_tip', ['text' => $brInfo['header_qty']]) Headers / beams</label>
    <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-headers" name="header_qty"
           value="{{ $brVal('header_qty', 0) }}">
  </div>
  <div class="admin-form-group br-field--phone-wide">
    <label for="br-stud-spacing">@include('admin.partials.info_tip', ['text' => $brInfo['stud_spacing_in'] ?? 'On-centre stud spacing. 16″ o.c. is the default.']) Stud spacing</label>
    <select class="admin-input br-preview" id="br-stud-spacing" name="stud_spacing_in">
      @foreach (\App\Services\BasementQuotationCalculator::STUD_SPACINGS as $oc)
        <option value="{{ $oc }}" @selected((int) $brVal('stud_spacing_in', 16) === $oc)>{{ $oc }}" o.c.</option>
      @endforeach
    </select>
  </div>
</div>
