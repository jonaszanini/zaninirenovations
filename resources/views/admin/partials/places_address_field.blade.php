{{--
  Google Places Autocomplete address field.
  Requires @include('admin.partials.google_maps_scripts') on the page.
--}}
@php
  $inputId = $inputId ?? 'places-address';
  $addressName = $addressName ?? 'address';
  $label = $label ?? 'Address';
  $value = $value ?? '';
  $placeIdName = $placeIdName ?? 'address_place_id';
  $placeIdValue = $placeIdValue ?? '';
  $latName = $latName ?? 'address_latitude';
  $latValue = $latValue ?? '';
  $lngName = $lngName ?? 'address_longitude';
  $lngValue = $lngValue ?? '';
  $help = $help ?? null;
  $required = $required ?? false;
@endphp
<div class="admin-form-group">
  <label for="{{ $inputId }}">{{ $label }}@if ($required) <span class="cp-required">*</span>@endif</label>
  <input type="text"
         id="{{ $inputId }}"
         name="{{ $addressName }}"
         value="{{ $value }}"
         maxlength="500"
         @if ($required) required @endif
         class="admin-places-address-input @if ($errors->has($addressName)) is-invalid @endif"
         data-places-autocomplete
         data-place-id-input="{{ $inputId }}-place-id"
         data-lat-input="{{ $inputId }}-lat"
         data-lng-input="{{ $inputId }}-lng"
         autocomplete="off"
         placeholder="Start typing an address…">
  <input type="hidden" name="{{ $placeIdName }}" id="{{ $inputId }}-place-id" value="{{ $placeIdValue }}">
  <input type="hidden" name="{{ $latName }}" id="{{ $inputId }}-lat" value="{{ $latValue }}">
  <input type="hidden" name="{{ $lngName }}" id="{{ $inputId }}-lng" value="{{ $lngValue }}">
  @if ($help)
    <p class="admin-form-help">{{ $help }}</p>
  @else
    <p class="admin-form-help">Pick a suggestion from Google to save map coordinates for travel estimates.</p>
  @endif
  <p class="admin-form-help admin-places-status" data-places-status hidden aria-live="polite"></p>
  @error($addressName) <p class="admin-form-error">{{ $message }}</p> @enderror
</div>
