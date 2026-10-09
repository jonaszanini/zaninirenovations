{{--
  Google Places Autocomplete address field (Places API New).
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
  $directoryHintId = $directoryHintId ?? null;
  $required = $required ?? false;
@endphp
<div class="admin-form-group admin-places-field">
  <label for="{{ $inputId }}-widget">
    {{ $label }}@if ($required) <span class="cp-required">*</span>@endif
    @if ($help)
      <x-admin.info-tip>{{ $help }}</x-admin.info-tip>
    @endif
  </label>
  <div class="admin-places-autocomplete-wrap"
       data-places-mount
       data-address-input="{{ $inputId }}"
       data-place-id-input="{{ $inputId }}-place-id"
       data-lat-input="{{ $inputId }}-lat"
       data-lng-input="{{ $inputId }}-lng"></div>
  <input type="hidden"
         name="{{ $addressName }}"
         id="{{ $inputId }}"
         value="{{ $value }}"
         data-places-address-value
         @if ($required) required @endif>
  <input type="hidden" name="{{ $placeIdName }}" id="{{ $inputId }}-place-id" value="{{ $placeIdValue }}">
  <input type="hidden" name="{{ $latName }}" id="{{ $inputId }}-lat" value="{{ $latValue }}">
  <input type="hidden" name="{{ $lngName }}" id="{{ $inputId }}-lng" value="{{ $lngValue }}">
  @if ($directoryHintId)
    <p class="admin-form-help cp-directory-address-hint" id="{{ $directoryHintId }}" hidden></p>
  @endif
  <p class="admin-form-help admin-places-status" data-places-status hidden aria-live="polite"></p>
  @error($addressName) <p class="admin-form-error">{{ $message }}</p> @enderror
</div>
