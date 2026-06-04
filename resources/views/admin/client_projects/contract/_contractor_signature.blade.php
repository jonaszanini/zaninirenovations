@php
  $contractorSignaturePath = public_path('images/signatures/jonaszanini.png');
  $hasContractorSignature = file_exists($contractorSignaturePath);
  $forPdf = $forPdf ?? false;
  $contractorSignatureSrc = $forPdf
    ? $contractorSignaturePath
    : ($hasContractorSignature ? asset('images/signatures/jonaszanini.png') : null);
  $contractorSignedDate = \App\Models\ClientProject::formatDate(now());
@endphp
<div class="signature-contractor">
  @if ($hasContractorSignature)
    <img src="{{ $contractorSignatureSrc }}"
         alt="Jonas Zanini signature"
         class="contractor-signature-img"
         width="2007"
         height="853">
  @endif
  <div class="signature-line"></div>
  <p class="signature-name">Jonas Zanini — ZANINI RENOVATIONS</p>
  <p class="signature-date">Date: {{ $contractorSignedDate }}</p>
</div>
