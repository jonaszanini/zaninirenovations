@php
  $unitValue = (string) ($value ?? '');
  $unitOptions = \App\Support\QuotationMaterialUnits::optionsWith($unitValue);
  $derivedPlate = (bool) ($derivedPlate ?? false);
  $unitName = (string) ($name ?? 'unit');
  $unitId = $id ?? null;
  $unitAria = $ariaLabel ?? 'Unit';
@endphp
<select class="admin-input" name="{{ $unitName }}"
        @if ($unitId) id="{{ $unitId }}" @endif
        aria-label="{{ $unitAria }}"
        @if ($derivedPlate) disabled aria-readonly="true" @endif>
  @foreach ($unitOptions as $unitCode => $unitLabel)
    <option value="{{ $unitCode }}" @selected($unitValue === $unitCode)>{{ $unitLabel }}</option>
  @endforeach
</select>
@if ($derivedPlate)
  <input type="hidden" name="{{ $unitName }}" value="{{ $unitValue }}">
@endif
