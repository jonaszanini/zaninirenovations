@extends('admin.layouts.app')

@section('heading', 'Edit client — '.$client->name)

@push('head')
<style>
.cl-edit-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0 16px;
}
.cl-edit-form-grid > .admin-form-group { margin-bottom: 14px; }
.cl-edit-form-grid > .cl-edit-form-full { grid-column: 1 / -1; }
@media (max-width: 600px) {
  .cl-edit-form-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.clients.update', $client) }}" method="post">
      @csrf
      @method('PUT')

      <div class="cl-edit-form-grid">
        <div class="admin-form-group">
          <label for="cl-name">Name <span class="cp-required">*</span></label>
          <input @class(['admin-input', 'is-invalid' => $errors->has('name')]) type="text" id="cl-name" name="name" value="{{ old('name', $client->name) }}" required maxlength="255">
          @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-email-secondary">Secondary email</label>
          <input class="admin-input" type="email" id="cl-email-secondary" name="email_secondary" value="{{ old('email_secondary', $client->email_secondary) }}" maxlength="255">
          @error('email_secondary') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-email">Email</label>
          <input @class(['admin-input', 'is-invalid' => $errors->has('email')]) type="email" id="cl-email" name="email" value="{{ old('email', $client->email) }}" maxlength="255">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cl-phone">Phone</label>
          <input @class(['admin-input', 'is-invalid' => $errors->has('email_secondary')]) type="text" id="cl-phone" name="phone"
                 value="{{ \App\Models\ClientProject::formatPhone(old('phone', $client->phone)) }}"
                 class="cp-phone-input @error('phone') is-invalid @enderror"
                 inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx">
          @error('phone') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      @include('admin.partials.places_address_field', [
        'inputId' => 'cl-address',
        'addressName' => 'address',
        'label' => 'Address',
        'value' => old('address', $client->address),
        'placeIdName' => 'address_place_id',
        'placeIdValue' => old('address_place_id', $client->address_place_id),
        'latName' => 'address_latitude',
        'latValue' => old('address_latitude', $client->address_latitude),
        'lngName' => 'address_longitude',
        'lngValue' => old('address_longitude', $client->address_longitude),
      ])

      <div class="admin-form-group">
        <label for="cl-notes">Notes</label>
        <textarea class="admin-input" id="cl-notes" name="notes" rows="2" maxlength="5000">{{ old('notes', $client->notes) }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label>Tags <a href="{{ route('admin.client-tags.index') }}" class="cl-tag-picker__manage">Manage tags</a></label>
        @if ($tags->isEmpty())
          <p class="admin-form-help">No tags yet. <a href="{{ route('admin.client-tags.create') }}">Create one</a>.</p>
        @else
          @php $selectedTagIds = old('tags', $client->tags->pluck('id')->all()); @endphp
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

      <p class="admin-form-help" style="margin-bottom:14px;">
        Linked to <strong>{{ $client->projects()->count() }}</strong> project(s). Edits update the central directory.
      </p>

      <div class="admin-form-actions" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
        <button type="submit" id="cl-btn-save" class="admin-btn admin-btn--primary" data-hotkey-save>
          <i class="fa fa-save"></i> Save
        </button>
        <a href="{{ route('admin.clients.index') }}" class="admin-btn admin-btn--ghost">Back</a>
        @unless ($client->projects()->exists())
          <span style="flex:1;"></span>
          <button type="submit" form="cl-delete-form" class="admin-btn admin-btn--danger admin-btn--sm">
            <i class="fa fa-trash"></i> Delete
          </button>
        @endunless
      </div>
    </form>

    @unless ($client->projects()->exists())
      <form id="cl-delete-form" action="{{ route('admin.clients.destroy', $client) }}" method="post" style="display:none;"
            onsubmit="return confirm('Delete this client permanently?');">
        @csrf
        @method('DELETE')
      </form>
    @else
      <p class="admin-form-help" style="margin-top:16px;color:#b45309;">
        <i class="fa fa-info-circle"></i> Delete is disabled while linked to projects.
      </p>
    @endunless
  </div>
@endsection

@push('scripts')
  @include('admin.partials.google_maps_scripts')
@endpush
