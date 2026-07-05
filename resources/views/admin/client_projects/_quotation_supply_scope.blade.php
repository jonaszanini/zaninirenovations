{{-- Expects: $quotation; optional $class (wrapper CSS class) --}}
@php
  $supplyScope = $quotation->supplyScopePdfMeta();
  $wrapperClass = $class ?? 'cp-quotation-supply-scope';
  $isPdf = $wrapperClass === 'quote-supply-scope';
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
          @if ($isPdf)
            <span class="{{ $wrapperClass }}-mark" aria-hidden="true">&#10003;</span>
          @else
            <i class="fa fa-check {{ $wrapperClass }}-mark" aria-hidden="true"></i>
          @endif
          <span class="{{ $wrapperClass }}-label">{{ strtoupper($item['label']) }}</span><span class="{{ $wrapperClass }}-suffix"> &mdash; included in this quotation</span>
        @else
          <span class="{{ $wrapperClass }}-label">{{ strtoupper($item['label']) }}</span><span class="{{ $wrapperClass }}-suffix"> &mdash; not included (client responsibility)</span>
        @endif
      </p>
    @endforeach
    @if (filled($supplyScope['detail'] ?? null))
      <p class="{{ $wrapperClass }}-detail">{{ $supplyScope['detail'] }}</p>
    @endif
  </div>
@endif
