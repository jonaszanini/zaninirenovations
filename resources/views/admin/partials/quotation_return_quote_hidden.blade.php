@php
  $returnQuoteUrl = $returnQuoteUrl ?? \App\Support\QuotationAfterSaveRedirect::fromRequest(request());
@endphp
@if ($returnQuoteUrl)
  <input type="hidden" name="return" value="{{ $returnQuoteUrl }}">
@endif
