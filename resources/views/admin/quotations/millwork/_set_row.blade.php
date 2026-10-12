@php
  $pi = $pi ?? '__P__';
  $si = $si ?? '__S__';
  $set = $set ?? [];
  $unit = $set['unit'] ?? $unit ?? 'in';
  $prefix = 'pieces['.$pi.'][sets]['.$si.']';
  $qtyErrorKey = 'pieces.'.$pi.'.sets.'.$si.'.qty';
  $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
@endphp
<div class="mw-set-row admin-form-grid-2" data-mw-set-row>
  <input type="hidden" name="{{ $prefix }}[unit]" value="{{ $unit }}">
  <div class="admin-form-group">
    <label>Qty</label>
    <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty{{ $errors->has($qtyErrorKey) ? ' is-invalid' : '' }}" name="{{ $prefix }}[qty]"
           value="{{ $set['qty'] ?? 1 }}"
           @if ($errors->has($qtyErrorKey)) aria-invalid="true" @endif>
    @error($qtyErrorKey)
      <p class="admin-form-error">{{ $message }}</p>
    @enderror
  </div>
  @include('admin.quotations.millwork._dim_field', [
    'namePrefix' => $prefix,
    'row' => $set,
    'fallbackUnit' => $unit,
    'dimName' => 'length',
    'dimLabel' => 'Length',
    'dimField' => 'set-length',
    'setField' => 'length',
    'dimShow' => null,
  ])
  @include('admin.quotations.millwork._dim_field', [
    'namePrefix' => $prefix,
    'row' => $set,
    'fallbackUnit' => $unit,
    'dimName' => 'width',
    'dimLabel' => 'Width',
    'dimField' => 'set-width',
    'setField' => 'width',
    'dimShow' => null,
  ])
  @include('admin.quotations.millwork._dim_field', [
    'namePrefix' => $prefix,
    'row' => $set,
    'fallbackUnit' => $unit,
    'dimName' => 'depth',
    'dimLabel' => 'Depth',
    'dimField' => 'set-depth',
    'setField' => 'depth',
    'dimShow' => null,
  ])
  @include('admin.quotations.millwork._dim_field', [
    'namePrefix' => $prefix,
    'row' => $set,
    'fallbackUnit' => $unit,
    'dimName' => 'height',
    'dimLabel' => 'Height',
    'dimField' => 'set-height',
    'setField' => 'height',
    'dimShow' => null,
  ])
  @include('admin.quotations.millwork._dim_field', [
    'namePrefix' => $prefix,
    'row' => $set,
    'fallbackUnit' => $unit,
    'dimName' => 'thickness',
    'dimLabel' => 'Thickness (outer)',
    'dimField' => 'set-thickness',
    'setField' => 'thickness',
    'dimShow' => null,
  ])
  <div class="admin-form-group mw-set-row__remove">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-remove-set aria-label="Remove size set">
      <i class="fa fa-trash"></i> Remove set
    </button>
  </div>
</div>
