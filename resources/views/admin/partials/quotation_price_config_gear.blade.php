{{-- Compact price-configuration link for priced fields. --}}
@php
  $gearUrl = $url ?? $priceConfigUrl ?? null;
@endphp
@if (filled($gearUrl))
  <span class="qh-priced-gear">
    <a href="{{ $gearUrl }}" class="qh-price-gear" title="Price configuration">
      <i class="fa fa-cog" aria-hidden="true"></i>
      <span class="qh-price-gear__text">Price configuration</span>
    </a>
    <x-admin.info-tip label="Price configuration">Labour, material, and productivity rates for this quote type.</x-admin.info-tip>
  </span>
@endif
