{{--
  Include-a-helper toggle + hourly rate.
  Rate field stays hidden until the checkbox is checked.

  @param bool         $checked
  @param string       $previewClass   e.g. mw-preview / br-preview / pq-calc-in / sh-preview / tm-preview
  @param string       $rateId
  @param string|float $rateValue
  @param string|null  $rateName        default helper_hourly_rate
  @param string|null  $checkboxId
--}}
@php
  $checked = (bool) ($checked ?? false);
  $previewClass = $previewClass ?? '';
  $rateId = $rateId ?? 'helper-rate';
  $rateName = $rateName ?? 'helper_hourly_rate';
  $checkboxId = $checkboxId ?? $rateId.'-include';
  $rateValue = $rateValue ?? '0.00';
@endphp
@include('admin.partials.checkbox', [
  'name' => 'include_helper',
  'id' => $checkboxId,
  'hiddenFallback' => true,
  'checked' => $checked,
  'label' => 'Include a helper',
  'info' => 'When checked, each labor hour is billed at the owner rate plus the helper rate.',
  'inputClass' => trim('admin-checkbox__input '.$previewClass),
  'attrs' => ['data-reveal' => $rateId.'-wrap'],
])
<div class="admin-form-group" id="{{ $rateId }}-wrap" @unless($checked) hidden @endunless>
  <label for="{{ $rateId }}">Helper hourly rate (CAD$)</label>
  <input type="text" inputmode="decimal" autocomplete="off"
         class="admin-input cp-money-input {{ $previewClass }}" id="{{ $rateId }}" name="{{ $rateName }}"
         value="{{ $rateValue }}">
</div>
