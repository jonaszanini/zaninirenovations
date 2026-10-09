@php
  $dimName = $dimName ?? 'width';
  $dimLabel = $dimLabel ?? 'W';
  $dimShow = $dimShow ?? null;
  $dimPlaceholder = $dimPlaceholder ?? '';
  $dimField = $dimField ?? $dimName;
  $row = $row ?? $piece ?? [];
  $prefix = $namePrefix ?? ('pieces['.$pi.']');
  $unit = (($row[$dimName.'_unit'] ?? $row['unit'] ?? $fallbackUnit ?? 'in') === 'ft') ? 'ft' : 'in';
  $setField = $setField ?? null;
@endphp
<div class="admin-form-group mw-field--{{ $dimField }} mw-dim-field"
     @if ($dimShow) data-mw-show="{{ $dimShow }}" @endif
     @if ($setField) data-mw-set-field="{{ $setField }}" @endif>
  <label>{{ $dimLabel }}</label>
  <div class="mw-dim">
    <input type="text" inputmode="decimal" autocomplete="off"
           class="admin-input mw-preview mw-input--dim"
           name="{{ $prefix }}[{{ $dimName }}]"
           placeholder="{{ $dimPlaceholder }}"
           value="{{ $row[$dimName] ?? '' }}">
    <select class="admin-input mw-preview mw-dim__unit" name="{{ $prefix }}[{{ $dimName }}_unit]" aria-label="{{ $dimLabel }} units">
      <option value="in" @selected($unit === 'in')>In</option>
      <option value="ft" @selected($unit === 'ft')>Ft</option>
    </select>
  </div>
</div>
