@php
  $gstHstLabel = \App\Support\GstHstRegistration::displayLabel();
@endphp
<p class="info-line">53 Antonakos Dr, Carleton Place, ON K7C 0L1</p>
<p class="info-line">(647) 834-7570</p>
<p class="info-line">zaninirenovations@gmail.com</p>
<p class="info-line">zaninirenovations.ca</p>
@if ($gstHstLabel)
  <p class="info-line gst-hst-line"><strong>GST/HST #</strong> {{ $gstHstLabel }}</p>
@endif
