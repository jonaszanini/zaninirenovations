{{-- Expects: $quotation; optional $class (headline CSS class prefix) --}}
@php
  $supplyScope = $quotation->supplyScopePdfMeta();
  $wrapperClass = $class ?? 'cp-quotation-supply-scope';
@endphp
@if ($supplyScope['show'] ?? false)
  <p class="{{ $wrapperClass }}-headline">{!! $supplyScope['headline_html'] !!}</p>
@endif
