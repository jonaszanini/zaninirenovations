@php
  use App\Models\Material;

  $m = $material ?? null;
  $imagePreview = session()->hasOldInput('image_url')
    ? old('image_url')
    : $m?->previewImageUrl();
  $imageUrlFieldValue = session()->hasOldInput('image_url')
    ? old('image_url')
    : $m?->image_url;
  $hasImagePreview = filled($imagePreview);
@endphp

<div class="mat-form">
<div class="mat-form-hero">
  <div class="mat-form-hero__media" id="mat-form-hero-media">
  <div class="mat-form-hero__placeholder" id="mat-form-hero-placeholder" @if ($hasImagePreview) hidden @endif>
      <i class="fa fa-picture-o" aria-hidden="true"></i>
      <span class="mat-form-hero__placeholder-text">Product image</span>
      <span class="mat-form-hero__placeholder-hint">Filled when you sync or paste an image URL</span>
    </div>
    <img src="{{ $hasImagePreview ? $imagePreview : '' }}" alt="" id="mat-form-hero-img"
         class="mat-form-hero__img" loading="lazy" decoding="async" referrerpolicy="no-referrer"
         data-initial-src="{{ $hasImagePreview ? $imagePreview : '' }}"
         @unless ($hasImagePreview) hidden @endunless>
  </div>
  <div class="mat-form-hero__fields">
    <div class="mat-form-grid-names">
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mat-name">Store name</label>
          <span class="cp-required">*</span>
          <x-admin.info-tip label="Store name">Full product title from the store — updated when you sync prices.</x-admin.info-tip>
        </span>
        <input @class(['admin-input', 'is-invalid' => $errors->has('name')]) type="text" id="mat-name" name="name" value="{{ old('name', $m?->name) }}" required maxlength="255">
        @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mat-short-name">Short name</label>
          <x-admin.info-tip label="Short name">Shown in lists and pickers. Leave blank to use the store name.</x-admin.info-tip>
        </span>
        <input @class(['admin-input', 'is-invalid' => $errors->has('short_name')]) type="text" id="mat-short-name" name="short_name" value="{{ old('short_name', $m?->short_name) }}" maxlength="120"
               placeholder="e.g. 2x4x8 Stud">
        @error('short_name') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
    </div>

    <div class="admin-form-group mat-form-hero__description">
      <label for="mat-description">Description</label>
      <textarea @class(['admin-input', 'is-invalid' => $errors->has('description')]) id="mat-description" name="description" rows="1" maxlength="5000">{{ old('description', $m?->description) }}</textarea>
      @error('description') <p class="admin-form-error">{{ $message }}</p> @enderror
    </div>
  </div>
</div>

@php
  $unitOptions = Material::unitOptions();
  $currentUnit = old('unit', $m?->unit);
@endphp

<div class="mat-form-grid-details">
  <div class="admin-form-group">
    <label for="mat-category">Category</label>
    <select id="mat-category" name="category_id" class="admin-input @error('category_id') is-invalid @enderror">
      <option value="">— Select category —</option>
      @foreach (($categories ?? collect()) as $category)
        <option value="{{ $category->id }}" @selected((int) old('category_id', $m?->category_id) === (int) $category->id)>
          {{ $category->name }}
        </option>
      @endforeach
    </select>
    @error('category_id') <p class="admin-form-error">{{ $message }}</p> @enderror
    <p class="admin-form-help"><a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a></p>
  </div>
  <div class="admin-form-group">
    <label for="mat-vendor">Vendor</label>
    <select id="mat-vendor" name="vendor_id" class="admin-input @error('vendor_id') is-invalid @enderror">
      <option value="">— Select vendor —</option>
      @foreach (($vendors ?? collect()) as $vendor)
        <option value="{{ $vendor->id }}" @selected((int) old('vendor_id', $m?->vendor_id) === (int) $vendor->id)>
          {{ $vendor->name }}
        </option>
      @endforeach
    </select>
    @error('vendor_id') <p class="admin-form-error">{{ $message }}</p> @enderror
    <p class="admin-form-help"><a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a></p>
  </div>
  <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mat-sku">Store SKU / ASIN</label>
          <x-admin.info-tip label="Store SKU / ASIN">Leave blank for other suppliers. Needed only to sync Home Depot or Amazon prices.</x-admin.info-tip>
        </span>
        <input @class(['admin-input', 'is-invalid' => $errors->has('store_sku')]) type="text" id="mat-sku" name="store_sku" value="{{ old('store_sku', $m?->store_sku) }}" maxlength="160"
           placeholder="Optional — HD SKU or Amazon ASIN">
    @error('store_sku') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-model">Model / item #</label>
    <input @class(['admin-input', 'is-invalid' => $errors->has('model')]) type="text" id="mat-model" name="model" value="{{ old('model', $m?->model) }}" maxlength="160">
    @error('model') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-price">Price (CAD)</label>
    <input @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('price_cad')]) type="text" id="mat-price" name="price_cad"
           value="{{ old('price_cad', (isset($m) && (float) $m->price_cad > 0) ? number_format((float) $m->price_cad, 2, '.', '') : '') }}"
           inputmode="decimal" autocomplete="off" placeholder="0.00">
    @error('price_cad') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mat-delivery">Delivery (CAD)</label>
          <x-admin.info-tip label="Delivery">Added once on quotations that link this product.</x-admin.info-tip>
        </span>
        <input @class(['admin-input', 'cp-money-input', 'is-invalid' => $errors->has('delivery_cad')]) type="text" id="mat-delivery" name="delivery_cad"
           value="{{ old('delivery_cad', (isset($m) && (float) $m->delivery_cad > 0) ? number_format((float) $m->delivery_cad, 2, '.', '') : '') }}"
           inputmode="decimal" autocomplete="off" placeholder="0.00">
    @error('delivery_cad') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-unit">Unit</label>
    <select id="mat-unit" name="unit" class="admin-input @error('unit') is-invalid @enderror">
      <option value="">— Select unit —</option>
      @foreach ($unitOptions as $value => $label)
        <option value="{{ $value }}" @selected($currentUnit === $value)>{{ $label }}</option>
      @endforeach
      @if (filled($currentUnit) && ! array_key_exists($currentUnit, $unitOptions))
        <option value="{{ $currentUnit }}" selected>{{ $currentUnit }} (custom)</option>
      @endif
    </select>
    @error('unit') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-qty-pkg">Qty per package</label>
    <input @class(['admin-input', 'is-invalid' => $errors->has('quantity_per_package')]) type="number" id="mat-qty-pkg" name="quantity_per_package"
           value="{{ old('quantity_per_package', $m?->quantity_per_package) }}"
           min="1" max="999999" placeholder="Box/bag">
    @error('quantity_per_package') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-sort">Sort order</label>
    <input @class(['admin-input', 'is-invalid' => $errors->has('sort_order')]) type="number" id="mat-sort" name="sort_order" value="{{ old('sort_order', $m?->sort_order ?? 0) }}"
           min="0" max="999999">
    @error('sort_order') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="mat-url-fields">
  <h3 class="mat-form-section-title">Store links &amp; sync</h3>
  <div class="mat-form-url-row">
    <span class="admin-label-with-tip">
      <label class="mat-form-url-row__label" for="mat-product-url">Product URL</label>
      <x-admin.info-tip label="Product URL">Any supplier URL is fine. Automatic price sync still only runs for Home Depot and Amazon items with a SKU/ASIN or store URL.</x-admin.info-tip>
    </span>
    <label class="mat-form-url-row__label" for="mat-image-url">Image URL</label>
    <div class="mat-form-url-row__active-head">
      <span class="mat-form-url-row__label">Active</span>
      @if ($m && $m->last_scraped_at)
        <div class="mat-form-status__sync">
          <span class="mat-scrape-badge mat-scrape-badge--{{ $m->last_scrape_status ?? 'unknown' }}">
            {{ $m->last_scrape_status ?? 'unknown' }}
          </span>
          <span class="mat-form-status__sync-date">
            Last synced {{ \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($m->last_scraped_at) }}
          </span>
        </div>
      @endif
    </div>

    <input @class(['admin-input', 'mat-form-url-row__control', 'is-invalid' => $errors->has('product_url')]) type="url" id="mat-product-url" name="product_url" value="{{ old('product_url', $m?->product_url) }}" maxlength="500"
           placeholder="https://…"
           autocomplete="off" spellcheck="false">
    <input @class(['admin-input', 'mat-form-url-row__control', 'is-invalid' => $errors->has('image_url')]) type="url" id="mat-image-url" name="image_url" value="{{ $imageUrlFieldValue }}" maxlength="500"
           placeholder="https://images.homedepot.ca/productimages/…"
           autocomplete="off" spellcheck="false">
    <div class="mat-form-url-row__active-control">
      <input type="hidden" name="is_active" value="0">
      <label class="admin-checkbox admin-checkbox--block">
        <input type="checkbox" name="is_active" value="1" class="admin-checkbox__input"
               @checked(old('is_active', $m?->is_active ?? true))>
        <span class="admin-checkbox__mark" aria-hidden="true"></span>
        <span class="admin-checkbox__text">Show in quotation pickers</span>
      </label>
    </div>

    @error('product_url') <p class="admin-form-error mat-form-url-row__error">{{ $message }}</p> @enderror
    @error('image_url') <p class="admin-form-error mat-form-url-row__error">{{ $message }}</p> @enderror
    @error('is_active') <p class="admin-form-error mat-form-url-row__error">{{ $message }}</p> @enderror
  </div>
  @if ($m && filled($m->last_scrape_error))
    <p class="mat-form-sync-error mat-scrape-meta__error" role="alert">{{ $m->last_scrape_error }}</p>
  @endif
</div>
</div>

@push('scripts')
<script>
(function () {
  'use strict';
  var input = document.getElementById('mat-image-url');
  var img = document.getElementById('mat-form-hero-img');
  var placeholder = document.getElementById('mat-form-hero-placeholder');
  if (!input || !img || !placeholder) return;

  function syncHeroImage() {
    var url = String(input.value || '').trim();
    if (url === '') {
      url = String(img.getAttribute('data-initial-src') || '').trim();
    }
    if (url === '') {
      img.hidden = true;
      img.removeAttribute('src');
      placeholder.hidden = false;
      return;
    }
    img.onload = function () {
      img.hidden = false;
      placeholder.hidden = true;
    };
    img.onerror = function () {
      img.hidden = true;
      placeholder.hidden = false;
    };
    if (img.getAttribute('src') !== url) {
      img.src = url;
    } else if (img.complete && img.naturalWidth > 0) {
      img.hidden = false;
      placeholder.hidden = true;
    }
  }

  input.addEventListener('input', syncHeroImage);
  input.addEventListener('change', syncHeroImage);
  syncHeroImage();
})();
</script>
@endpush
