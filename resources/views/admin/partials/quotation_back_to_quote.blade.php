@php
  $returnQuoteUrl = $returnQuoteUrl ?? \App\Support\QuotationAfterSaveRedirect::fromRequest(request());
@endphp
@if ($returnQuoteUrl)
  <a href="{{ $returnQuoteUrl }}" class="admin-btn admin-btn--ghost admin-btn--sm">
    <i class="fa fa-arrow-left"></i> Back to quotation
  </a>
@endif
