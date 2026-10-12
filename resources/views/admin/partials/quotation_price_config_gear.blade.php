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
  </span>
@endif
