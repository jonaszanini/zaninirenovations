{{--
  Primary quotation heading (accordion + single-card): calculator label + number.
  Standard source: optional bold display title from StandardQuotation, then normal-weight label.
--}}
@php
  /** @var \App\Models\ClientProjectQuotation $quotation */
  $sqDisplay = $quotation->standardDisplayTitle();
  $isStd = $quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_STANDARD;
@endphp
@if ($isStd)
  <h3 @class(['admin-input', 
      'cp-quotation-primary-heading',
      'cp-quotation-primary-heading--standard',
      $headingClass => filled($headingClass),
  ])>
    @if (filled($sqDisplay))
      <span class="cp-quotation-primary-heading__display-title">{{ $sqDisplay }}</span>
    @endif
    <span class="cp-quotation-primary-heading__kind-line">
      <span class="cp-quotation-primary-heading__kind">{{ $title }}</span>
      <span class="cp-quotation-number">{{ $quotation->quotation_number }}</span>
    </span>
  </h3>
@else
  <h3 @class(['admin-input', 
      'cp-quotation-primary-heading',
      $headingClass => filled($headingClass),
  ])>
    {{ $title }}
    <span class="cp-quotation-number">{{ $quotation->quotation_number }}</span>
  </h3>
@endif
