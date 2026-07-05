{{-- Expects: $quotation; optional $class (wrapper CSS class) --}}
@php
  $supplyScope = $quotation->supplyScopePdfMeta();
  $wrapperClass = $class ?? 'cp-quotation-supply-scope';
@endphp
@if (($supplyScope['items'] ?? []) !== [])
  <div class="{{ $wrapperClass }}">
    <p class="{{ $wrapperClass }}-headline">{{ $supplyScope['headline'] }}</p>
    @foreach ($supplyScope['items'] as $item)
      <p @class([
        $wrapperClass.'-item',
        $wrapperClass.'-item--included' => $item['included'],
        $wrapperClass.'-item--excluded' => ! $item['included'],
      ])>
        @if ($item['included'])
          &#10003; {{ $item['label'] }} &mdash; included in this quotation
        @else
          {{ $item['label'] }} &mdash; not included (client responsibility)
        @endif
      </p>
    @endforeach
    @if (filled($supplyScope['detail'] ?? null))
      <p class="{{ $wrapperClass }}-detail">{{ $supplyScope['detail'] }}</p>
    @endif
  </div>
@endif
