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
  @param string      $step
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
  $step = $step ?? '0.01';
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
      <input type="{{ $type }}" step="{{ $step }}" min="{{ $min }}" class="{{ $inputClass }}" id="{{ $id }}" name="{{ $name }}"
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
