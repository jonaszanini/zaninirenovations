@php
  use App\Models\ClientProjectQuotation;
  /** @var \App\Models\ClientProjectQuotation $quotation */
  $sqDisplay = trim((string) ($quotation->standardDisplayTitle() ?? ''));
  $isStd = $quotation->source_type === ClientProjectQuotation::SOURCE_STANDARD;
@endphp
@if ($isStd)
  @php
    $previewHeading = $sqDisplay !== ''
        ? $sqDisplay
        : $typeLabel.' #'.$quotation->quotation_number;
  @endphp
  <div class="cp-quotation-doc-display-title">{{ $previewHeading }}</div>
@else
  <div class="cp-quotation-doc-title">{{ $typeLabel }} #{{ $quotation->quotation_number }}</div>
@endif
