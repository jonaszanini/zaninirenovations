@extends('admin.layouts.app')

@section('heading', $supplierSite->exists ? 'Edit supplier' : 'Add supplier')

@section('content')
  <div class="admin-card">
    <form method="post" action="{{ $supplierSite->exists ? route('admin.supplier-sites.update', $supplierSite) : route('admin.supplier-sites.store') }}">
      @csrf
      @if ($supplierSite->exists) @method('PUT') @endif

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="ss-name">Store name <span class="cp-required">*</span></label>
          <input class="admin-input @error('name') is-invalid @enderror" type="text" id="ss-name" name="name"
                 value="{{ old('name', $supplierSite->name) }}" required maxlength="160" autocomplete="organization">
          @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="ss-phone">Phone</label>
          <input type="text"
                 id="ss-phone"
                 name="phone"
                 @class(['admin-input', 'cp-phone-input', 'is-invalid' => $errors->has('phone')])
                 inputmode="tel"
                 autocomplete="tel"
                 maxlength="14"
                 placeholder="(xxx) xxx-xxxx"
                 value="{{ \App\Models\ClientProject::formatPhone(old('phone', $supplierSite->phone)) }}">
          @error('phone') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="ss-email">Email</label>
          <input class="admin-input @error('email') is-invalid @enderror" type="email" id="ss-email" name="email"
                 value="{{ old('email', $supplierSite->email) }}" maxlength="255" autocomplete="email">
          @error('email') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="ss-url">Website URL <span class="cp-required">*</span></label>
          <input class="admin-input @error('url') is-invalid @enderror" type="url" id="ss-url" name="url"
                 value="{{ old('url', $supplierSite->url) }}" required maxlength="500" placeholder="https://debline.com" autocomplete="url">
          @error('url') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-group">
        <label for="ss-address">Address</label>
        <input class="admin-input @error('address') is-invalid @enderror" type="text" id="ss-address" name="address"
               value="{{ old('address', $supplierSite->address) }}" maxlength="500" autocomplete="street-address"
               placeholder="123 Main St, Carleton Place, ON">
        @error('address') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="ss-tags">Tags</label>
        <input class="admin-input @error('tags_input') is-invalid @enderror" type="text" id="ss-tags" name="tags_input"
               value="{{ old('tags_input', $supplierSite->exists ? $supplierSite->tagsInputValue() : '') }}"
               maxlength="2000" placeholder="Bin Rental, crushed stone, bedding sand">
        <p class="admin-form-help">Comma-separated. Tags are reusable — search or filter by them on the list.</p>
        @error('tags_input') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-group">
        <label for="ss-notes">Notes</label>
        <textarea id="ss-notes" name="notes" rows="4" class="admin-input @error('notes') is-invalid @enderror"
                  maxlength="5000" placeholder="Delivery area, account number, contact person…">{{ old('notes', $supplierSite->notes) }}</textarea>
        @error('notes') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label class="admin-checkbox admin-checkbox--block">
            <input type="hidden" name="is_favorite" value="0">
            <input type="checkbox" class="admin-checkbox__input" name="is_favorite" value="1"
                   @checked(old('is_favorite', $supplierSite->is_favorite))>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Favorite (pin to top of list)</span>
          </label>
        </div>
        <div class="admin-form-group">
          <label for="ss-sort">Sort order</label>
          <input class="admin-input @error('sort_order') is-invalid @enderror" type="number" id="ss-sort" name="sort_order"
                 min="0" max="32767" step="1" value="{{ old('sort_order', (int) ($supplierSite->sort_order ?? 0)) }}">
          @error('sort_order') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save</button>
        <a href="{{ route('admin.supplier-sites.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection
