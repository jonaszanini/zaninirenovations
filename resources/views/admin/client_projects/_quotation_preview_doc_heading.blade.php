@php
  use App\Models\ClientProjectQuotation;
  /** @var \App\Models\ClientProjectQuotation $quotation */
  $sqDisplay = $quotation->standardDisplayTitle();
  $isStd = $quotation->source_type === ClientProjectQuotation::SOURCE_STANDARD;
  $docKindLabel = $isStd ? ($quotation->title ?: $typeLabel) : $typeLabel;
@endphp
@if ($isStd)
  @if (filled($sqDisplay))
    <div class="cp-quotation-doc-display-title">{{ $sqDisplay }}</div>
  @endif
  <div class="cp-quotation-doc-title cp-quotation-doc-title--standard">
    {{ $docKindLabel }} #{{ $quotation->quotation_number }}
  </div>
@else
  <div class="cp-quotation-doc-title">{{ $typeLabel }} #{{ $quotation->quotation_number }}</div>
@endif
