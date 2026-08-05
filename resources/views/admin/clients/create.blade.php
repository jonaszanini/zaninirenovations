@extends('admin.layouts.app')

@section('heading', 'New client')

@section('content')
  @if (session('duplicate_candidates') && session('duplicate_candidates')->isNotEmpty())
    <div class="admin-card cl-duplicate-warning">
      <p class="cl-duplicate-warning__title">
        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
        Possible duplicate {{ session('duplicate_candidates')->count() === 1 ? 'client' : 'clients' }}
      </p>
      <p class="admin-form-help">These existing clients share the same name, email, or phone number:</p>
      <ul class="cl-duplicate-warning__list">
        @foreach (session('duplicate_candidates') as $dup)
          <li>
            <a href="{{ route('admin.clients.edit', $dup) }}" target="_blank" rel="noopener">{{ $dup->name }}</a>
            <span class="cl-duplicate-warning__meta">
              {{ collect([$dup->email, $dup->phone ? \App\Models\ClientProject::formatPhone($dup->phone) : null])->filter()->implode(' · ') ?: '—' }}
            </span>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="admin-card">
    <form action="{{ route('admin.clients.store') }}" method="post">
      @csrf
      @if (session('duplicate_candidates'))
        <input type="hidden" name="confirm_duplicate" value="1">
      @endif

      <div class="admin-form-group">
        <label for="cl-name">Name <span class="cp-required">*</span></label>
        <input @class(['admin-input', 'is-invalid' => $errors->has('name')]) type="text" id="cl-name" name="name" value="{{ old('name') }}" required maxlength="255">
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cl-email">Email</label>
          <input @class(['admin-input', 'is-invalid' => $errors->has('email')]) type="email" id="cl-email" name="email" value="{{ old('email') }}" maxlength="255">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-phone">Phone</label>
          <input @class(['admin-input', 'cp-phone-input', 'is-invalid' => $errors->has('phone')]) type="text" id="cl-phone" name="phone" value="{{ \App\Models\ClientProject::formatPhone(old('phone')) }}"
                 inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx">
          @error('phone') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cl-email-secondary">Secondary email <small class="admin-form-help cp-inline-help">(optional)</small></label>
        <input @class(['admin-input', 'is-invalid' => $errors->has('email_secondary')]) type="email" id="cl-email-secondary" name="email_secondary" value="{{ old('email_secondary') }}" maxlength="255">
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
        <textarea class="admin-input" id="cl-notes" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label>Tags <a href="{{ route('admin.client-tags.index') }}" class="cl-tag-picker__manage">Manage tags</a></label>
        @if ($tags->isEmpty())
          <p class="admin-form-help">No tags yet. <a href="{{ route('admin.client-tags.create') }}">Create one</a>.</p>
        @else
          @php $selectedTagIds = old('tags', []); @endphp
          <div class="cl-tag-picker">
            @foreach ($tags as $tag)
              <label class="cl-tag-picker__option">
                <input type="checkbox" name="tags[]" value="{{ $tag->id }}" @checked(in_array($tag->id, $selectedTagIds))>
                <span class="admin-badge" style="background:{{ $tag->color }}1a;color:{{ $tag->color }};border:1px solid {{ $tag->color }}55;">{{ $tag->name }}</span>
              </label>
            @endforeach
          </div>
        @endif
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-check"></i> {{ session('duplicate_candidates') ? 'Save anyway' : 'Save client' }}
        </button>
        <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  @include('admin.partials.google_maps_scripts')
@endpush
