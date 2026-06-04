@extends('admin.layouts.app')

@section('heading', 'New client')

@section('content')
  <div class="admin-card admin-card--narrow">
    <form action="{{ route('admin.clients.store') }}" method="post">
      @csrf

      <div class="admin-form-group">
        <label for="cl-name">Name <span class="cp-required">*</span></label>
        <input type="text" id="cl-name" name="name" value="{{ old('name') }}" required maxlength="255"
               class="@error('name') is-invalid @enderror">
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cl-email">Email</label>
          <input type="email" id="cl-email" name="email" value="{{ old('email') }}" maxlength="255"
                 class="@error('email') is-invalid @enderror">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-phone">Phone</label>
          <input type="text" id="cl-phone" name="phone" value="{{ \App\Models\ClientProject::formatPhone(old('phone')) }}"
                 class="cp-phone-input @error('phone') is-invalid @enderror"
                 inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx">
          @error('phone') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cl-email-secondary">Secondary email <small class="admin-form-help cp-inline-help">(optional)</small></label>
        <input type="email" id="cl-email-secondary" name="email_secondary" value="{{ old('email_secondary') }}" maxlength="255"
               class="@error('email_secondary') is-invalid @enderror">
        @error('email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      @include('admin.partials.places_address_field', [
        'inputId' => 'cl-address',
        'addressName' => 'address',
        'label' => 'Address',
        'value' => old('address'),
        'placeIdName' => 'address_place_id',
        'placeIdValue' => old('address_place_id'),
        'latName' => 'address_latitude',
        'latValue' => old('address_latitude'),
        'lngName' => 'address_longitude',
        'lngValue' => old('address_longitude'),
      ])

      <div class="admin-form-group">
        <label for="cl-notes">Notes</label>
        <textarea id="cl-notes" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-check"></i> Save client
        </button>
        <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  @include('admin.partials.google_maps_scripts')
@endpush
