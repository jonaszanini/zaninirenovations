{{--
  Shared-work quantity field: info icon on the label, trailing gear icon to Shared prices, live rate beside the input.

  @param string      $id
  @param string      $name
  @param string      $label
  @param mixed       $value
  @param string      $info
  @param string      $rate
  @param string|null $workKey
  @param string|null $gearUrl
  @param string      $gearLabel
  @param string      $inputClass
  @param string      $type          number (default)
  @param string      $step          HTML5 step; integer parent $step is ignored
  @param string      $min
  @param string|null $placeholder
  @param bool        $required
  @param string|null $warningId
  @param string|null $warning
--}}
@php
  $id = $id ?? $name;
  $inputClass = trim('admin-input '.($inputClass ?? ''));
  $gearUrl = $gearUrl ?? \App\Services\SharedWorkCatalog::sharedPricesUrl($workKey ?? null);
  $gearLabel = $gearLabel ?? 'Edit '.($label ?? 'work').' rates';
  $type = $type ?? 'number';
  // Parent wizards use $step as a section counter (int 1, 2, … 10). Blade includes
  // inherit that, so `$step ?? '0.01'` would paint step="10" and browsers reject
  // imported LF values like 87 or 12.5. Only honor an explicit HTML5 step string.
  $qtyStep = $qtyStep ?? null;
  if ($qtyStep === null && isset($step) && (is_string($step) || is_float($step))) {
      $qtyStep = $step;
  }
  $qtyStep = ($qtyStep !== null && $qtyStep !== '') ? (string) $qtyStep : '0.01';
  $min = $min ?? '0';
  $required = ! empty($required);
@endphp
<div class="sq-qty">
  <span class="admin-label-with-tip">
  <label class="sq-qty__label" for="{{ $id }}">@if (! empty($clientFilled))
      @include('admin.quotations.basement._client_filled', ['force' => true])
    @endif
    {{ $label }}</label>
  @include('admin.partials.info_tip', ['tipText' => $info ?? null, 'tipLabel' => $label])
</span>
  <div class="sq-qty__row">
    <div class="sq-qty__control">
      <input type="{{ $type }}" step="{{ $qtyStep }}" min="{{ $min }}" class="{{ $inputClass }}" id="{{ $id }}" name="{{ $name }}"
             value="{{ $value }}"
             @if (! empty($placeholder)) placeholder="{{ $placeholder }}" @endif
             @if ($required) required @endif>
      <a href="{{ $gearUrl }}" class="sq-qty__gear" aria-label="{{ $gearLabel }}" title="{{ $gearLabel }}">
        <i class="fa fa-cog" aria-hidden="true"></i>
      </a>
    </div>
    @if (filled($rate ?? null))
      <span class="sq-qty__rate">{{ $rate }}</span>
    @endif
  </div>
  @if (filled($warning ?? null))
    <p class="fl-field-warning" id="{{ $warningId }}" hidden>
      <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
      {{ $warning }}
    </p>
  @endif
</div>
