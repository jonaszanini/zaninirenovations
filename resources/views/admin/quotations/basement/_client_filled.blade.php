{{-- Badge that a value was filled from the client intake form. --}}
@php
  $filledKeys = $filledKeys ?? [];
  $showFilled = ! empty($force) || (isset($key) && in_array($key, $filledKeys, true));
@endphp
@if ($showFilled)
  <span class="br-client-filled" title="Filled from client form" aria-label="Filled from client form">
    <i class="fa fa-user" aria-hidden="true"></i>
  </span>
@endif
