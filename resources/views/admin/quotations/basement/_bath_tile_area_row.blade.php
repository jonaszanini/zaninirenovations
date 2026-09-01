{{--
  One bathroom tile zone (floor, shower, accent wall, …).

  @param int|string $i
  @param array       $area  label, sqft, mode, length_ft, width_ft
--}}
@php
  $i = $i ?? '__I__';
  $area = is_array($area ?? null) ? $area : [];
  $label = $area['label'] ?? '';
  $sqft = $area['sqft'] ?? '';
  $mode = \App\Services\BasementQuotationCalculator::normalizeAreaMode($area['mode'] ?? 'sqft');
  $length = $area['length_ft'] ?? ($area['length'] ?? '');
  $width = $area['width_ft'] ?? ($area['width'] ?? '');
  $computed = ($mode === 'dimensions' && $length !== '' && $width !== '' && (float) $length > 0 && (float) $width > 0)
      ? rtrim(rtrim(number_format((float) $length * (float) $width, 2, '.', ''), '0'), '.').' sq ft'
      : 'Enter length and width';
@endphp
<div class="br-tile-row" data-br-tile-row data-br-lw>
  <div class="br-tile-row__main">
    <div class="admin-form-group br-tile-row__sqft">
      <label for="br-tile-{{ $i }}">Square feet</label>
      <div class="br-tile-row__sqft-controls">
        <div class="br-lw__sqft" data-br-lw-sqft @if($mode === 'dimensions') hidden @endif>
          <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-tile-{{ $i }}"
                 name="bath_tile_areas[{{ $i }}][sqft]" data-br-lw-qty
                 value="{{ $sqft }}" placeholder="0 if none">
        </div>
        <div class="br-lw-segment" role="radiogroup" aria-label="Square feet entry">
          <label class="br-lw-segment__item">
            <input type="radio" class="br-preview" name="bath_tile_areas[{{ $i }}][mode]" value="sqft"
                   data-br-lw-mode @checked($mode === 'sqft')>
            <span>Total sq ft</span>
          </label>
          <label class="br-lw-segment__item">
            <input type="radio" class="br-preview" name="bath_tile_areas[{{ $i }}][mode]" value="dimensions"
                   data-br-lw-mode @checked($mode === 'dimensions')>
            <span>Length × width</span>
          </label>
        </div>
      </div>
    </div>
    <div class="admin-form-group br-tile-row__name">
      <label for="br-tile-label-{{ $i }}">Area name</label>
      <input type="text" class="admin-input br-preview" id="br-tile-label-{{ $i }}"
             name="bath_tile_areas[{{ $i }}][label]" maxlength="80"
             value="{{ $label }}" placeholder="e.g. Floor, Shower">
    </div>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm br-tile-row__remove" data-br-tile-remove aria-label="Remove tile area">
      <i class="fa fa-trash" aria-hidden="true"></i>
    </button>
  </div>
  <div class="br-lw__dims br-tile-row__dims" data-br-lw-dims @unless($mode === 'dimensions') hidden @endunless>
    <div class="admin-form-group br-lw__dim">
      <label for="br-tile-{{ $i }}-length">Length (ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-tile-{{ $i }}-length"
             name="bath_tile_areas[{{ $i }}][length_ft]" data-br-lw-length
             value="{{ $length }}" placeholder="e.g. 12">
    </div>
    <div class="admin-form-group br-lw__dim">
      <label for="br-tile-{{ $i }}-width">Width (ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-tile-{{ $i }}-width"
             name="bath_tile_areas[{{ $i }}][width_ft]" data-br-lw-width
             value="{{ $width }}" placeholder="e.g. 10">
    </div>
  </div>
  <p class="admin-form-help br-lw__computed" data-br-lw-computed @if($mode !== 'dimensions') hidden @endif>{{ $computed }}</p>
</div>
