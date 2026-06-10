@php
  $m = $material ?? null;
@endphp

<div class="mat-form">
<div class="admin-form-group">
  <label for="mat-name">Name <span class="cp-required">*</span></label>
  <input type="text" id="mat-name" name="name" value="{{ old('name', $m?->name) }}" required maxlength="255"
         class="@error('name') is-invalid @enderror">
  @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
</div>

<div class="admin-form-group">
  <label for="mat-description">Description</label>
  <textarea id="mat-description" name="description" rows="3" maxlength="5000"
            class="@error('description') is-invalid @enderror">{{ old('description', $m?->description) }}</textarea>
  @error('description') <p class="admin-form-error">{{ $message }}</p> @enderror
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="mat-category">Category</label>
    <input type="text" id="mat-category" name="category" value="{{ old('category', $m?->category) }}" maxlength="120"
           placeholder="e.g. Lumber, Hardware, Paving"
           class="@error('category') is-invalid @enderror">
    @error('category') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-vendor">Vendor</label>
    <input type="text" id="mat-vendor" name="vendor" value="{{ old('vendor', $m?->vendor) }}" maxlength="120"
           placeholder="e.g. Home Depot"
           class="@error('vendor') is-invalid @enderror">
    @error('vendor') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="mat-sku">Store SKU</label>
    <input type="text" id="mat-sku" name="store_sku" value="{{ old('store_sku', $m?->store_sku) }}" maxlength="160"
           class="@error('store_sku') is-invalid @enderror">
    @error('store_sku') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-model">Model / item #</label>
    <input type="text" id="mat-model" name="model" value="{{ old('model', $m?->model) }}" maxlength="160"
           class="@error('model') is-invalid @enderror">
    @error('model') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="mat-price">Price (CAD)</label>
    <input type="number" id="mat-price" name="price_cad" value="{{ old('price_cad', $m?->price_cad ?? '0.00') }}"
           min="0" step="0.01" max="999999.99"
           class="@error('price_cad') is-invalid @enderror">
    @error('price_cad') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-unit">Unit</label>
    <input type="text" id="mat-unit" name="unit" value="{{ old('unit', $m?->unit) }}" maxlength="40"
           placeholder="each, box, bag…"
           class="@error('unit') is-invalid @enderror">
    @error('unit') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="admin-form-grid-2">
  <div class="admin-form-group">
    <label for="mat-qty-pkg">Quantity per package</label>
    <input type="number" id="mat-qty-pkg" name="quantity_per_package"
           value="{{ old('quantity_per_package', $m?->quantity_per_package) }}"
           min="1" max="999999" placeholder="For box/bag pricing"
           class="@error('quantity_per_package') is-invalid @enderror">
    @error('quantity_per_package') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-sort">Sort order</label>
    <input type="number" id="mat-sort" name="sort_order" value="{{ old('sort_order', $m?->sort_order ?? 0) }}"
           min="0" max="999999"
           class="@error('sort_order') is-invalid @enderror">
    @error('sort_order') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>
<p class="admin-form-help mat-qty-help">Optional. Use when the store price is per box/bag, not per single unit.</p>

<div class="mat-url-fields">
  <h3 class="mat-form-section-title">Store links</h3>
  <div class="admin-form-group">
    <label for="mat-product-url">Product URL</label>
    <input type="url" id="mat-product-url" name="product_url" value="{{ old('product_url', $m?->product_url) }}" maxlength="500"
           placeholder="https://www.homedepot.ca/product/…"
           class="@error('product_url') is-invalid @enderror" autocomplete="off" spellcheck="false">
    <p class="admin-form-help">Home Depot product link — used to fetch store price and details during bulk import sync.</p>
    @error('product_url') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>

  <div class="admin-form-group mat-image-url-group">
    <label for="mat-image-url">Image URL</label>
    <div class="mat-image-url-row">
      <div class="mat-image-url-row__field">
        <input type="url" id="mat-image-url" name="image_url" value="{{ old('image_url', $m?->image_url) }}" maxlength="500"
               placeholder="https://images.homedepot.ca/productimages/…"
               class="@error('image_url') is-invalid @enderror" autocomplete="off" spellcheck="false">
      </div>
      @php $imagePreview = old('image_url', $m?->image_url); @endphp
      @if (filled($imagePreview))
        <div class="mat-image-preview" aria-hidden="true">
          <img src="{{ $imagePreview }}" alt="" class="mat-image-preview__img" loading="lazy" width="72" height="72">
        </div>
      @endif
    </div>
    @error('image_url') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
</div>

<div class="mat-form-status">
  <label class="admin-checkbox admin-checkbox--block">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="admin-checkbox__input"
           @checked(old('is_active', $m?->is_active ?? true))>
    <span class="admin-checkbox__mark" aria-hidden="true"></span>
    <span class="admin-checkbox__text">Active (include in price sync)</span>
  </label>

  @if ($m && $m->last_scraped_at)
    <div class="mat-form-status__sync">
      <span class="mat-scrape-badge mat-scrape-badge--{{ $m->last_scrape_status ?? 'unknown' }}">
        {{ $m->last_scrape_status ?? 'unknown' }}
      </span>
      <span class="mat-form-status__sync-date">
        Last synced {{ \App\Support\EasternStandardTimeDisplay::formatInstantLongDate($m->last_scraped_at) }}
      </span>
      @if ($m->last_scrape_error)
        <p class="mat-scrape-meta__error">{{ $m->last_scrape_error }}</p>
      @endif
    </div>
  @endif
</div>
</div>
