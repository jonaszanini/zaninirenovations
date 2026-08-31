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
@endphp
<div class="br-tile-row" data-br-tile-row>
  <div class="br-tile-row__bar">
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
  @include('admin.quotations.basement._qty_lw', [
    'name' => 'bath_tile_areas['.$i.'][sqft]',
    'id' => 'br-tile-'.$i,
    'label' => 'Square feet',
    'value' => $sqft,
    'modeName' => 'bath_tile_areas['.$i.'][mode]',
    'lengthName' => 'bath_tile_areas['.$i.'][length_ft]',
    'widthName' => 'bath_tile_areas['.$i.'][width_ft]',
    'mode' => $mode,
    'lengthValue' => $length,
    'widthValue' => $width,
    'placeholder' => '0 if none',
  ])
</div>
