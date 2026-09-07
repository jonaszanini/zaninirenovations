@php
  $pi = $pi ?? '__P__';
  $si = $si ?? '__S__';
  $set = $set ?? [];
  $unit = $set['unit'] ?? $unit ?? 'in';
@endphp
<div class="mw-set-row admin-form-grid-2" data-mw-set-row>
  <div class="admin-form-group">
    <label>Qty</label>
    <input type="number" min="0" step="1" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][qty]"
           value="{{ $set['qty'] ?? 1 }}">
  </div>
  <div class="admin-form-group">
    <label>Units</label>
    <select class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][unit]">
      <option value="in" @selected(($set['unit'] ?? $unit) === 'in')>Inches</option>
      <option value="ft" @selected(($set['unit'] ?? $unit) === 'ft')>Feet</option>
    </select>
  </div>
  <div class="admin-form-group" data-mw-set-field="length">
    <label>Length</label>
    <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][length]"
           value="{{ $set['length'] ?? '' }}">
  </div>
  <div class="admin-form-group" data-mw-set-field="width">
    <label>Width</label>
    <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][width]"
           value="{{ $set['width'] ?? '' }}">
  </div>
  <div class="admin-form-group" data-mw-set-field="depth">
    <label>Depth</label>
    <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][depth]"
           value="{{ $set['depth'] ?? '' }}">
  </div>
  <div class="admin-form-group" data-mw-set-field="height">
    <label>Height</label>
    <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][height]"
           value="{{ $set['height'] ?? '' }}">
  </div>
  <div class="admin-form-group" data-mw-set-field="thickness">
    <label>Thickness (outer)</label>
    <input type="number" min="0" step="0.01" class="admin-input mw-preview" name="pieces[{{ $pi }}][sets][{{ $si }}][thickness]"
           value="{{ $set['thickness'] ?? '' }}">
  </div>
  <div class="admin-form-group mw-set-row__remove">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-remove-set aria-label="Remove size set">
      <i class="fa fa-trash"></i> Remove set
    </button>
  </div>
</div>
