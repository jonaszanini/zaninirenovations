@php
  use App\Models\Material;
  use App\Models\MaterialVendor;

  $m = $material ?? null;
  $syncProbe = Material::make([
    'vendor_id' => old('vendor_id', $m?->vendor_id),
    'store_sku' => old('store_sku', $m?->store_sku),
    'product_url' => old('product_url', $m?->product_url),
    'is_active' => true,
  ]);
  $priceSyncChannel = $syncProbe->priceSyncChannel();
  $priceSyncEligibilityError = $syncProbe->priceSyncEligibilityError();
  $selectedVendorId = (int) old('vendor_id', $m?->vendor_id);
  $amazonVendorId = MaterialVendor::amazonId();
  $homeDepotVendorId = MaterialVendor::homeDepotId();
@endphp

<div class="mat-form">
<div class="mat-form-grid-names">
  <div class="admin-form-group">
    <label for="mat-name">Store name <span class="cp-required">*</span></label>
    <input type="text" id="mat-name" name="name" value="{{ old('name', $m?->name) }}" required maxlength="255"
           class="@error('name') is-invalid @enderror">
    <p class="admin-form-help">Full product title from the store — updated when you sync prices.</p>
    @error('name') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-short-name">Short name</label>
    <input type="text" id="mat-short-name" name="short_name" value="{{ old('short_name', $m?->short_name) }}" maxlength="120"
           placeholder="e.g. 2x4x8 Stud"
           class="@error('short_name') is-invalid @enderror">
    <p class="admin-form-help">Shown in lists and pickers. Leave blank to use the store name.</p>
    @error('short_name') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
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
    <select id="mat-category" name="category_id" class="admin-input @error('category_id') is-invalid @enderror">
      <option value="">— Select category —</option>
      @foreach (($categories ?? collect()) as $category)
        <option value="{{ $category->id }}" @selected((int) old('category_id', $m?->category_id) === (int) $category->id)>
          {{ $category->name }}
        </option>
      @endforeach
    </select>
    @error('category_id') <p class="admin-form-error">{{ $message }}</p> @enderror
    <p class="admin-form-help">Manage categories on <a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a>.</p>
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
    <p class="admin-form-help">Manage vendors on <a href="{{ route('admin.materials.config') }}" class="admin-text-link">Materials config</a>.</p>
  </div>
</div>

@php
  $unitOptions = Material::unitOptions();
  $currentUnit = old('unit', $m?->unit);
@endphp

<div class="mat-form-grid-pricing">
  <div class="admin-form-group">
    <label for="mat-sku">Store SKU / ASIN</label>
    <input type="text" id="mat-sku" name="store_sku" value="{{ old('store_sku', $m?->store_sku) }}" maxlength="160"
           placeholder="Home Depot SKU or Amazon ASIN"
           class="@error('store_sku') is-invalid @enderror">
    <p class="admin-form-help">HD numeric SKU or Amazon ASIN (10 chars).</p>
    @error('store_sku') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-model">Model / item #</label>
    <input type="text" id="mat-model" name="model" value="{{ old('model', $m?->model) }}" maxlength="160"
           class="@error('model') is-invalid @enderror">
    @error('model') <p class="admin-form-error">{{ $message }}</p> @enderror
  </div>
  <div class="admin-form-group">
    <label for="mat-price">Price (CAD)</label>
    <input type="number" id="mat-price" name="price_cad" value="{{ old('price_cad', $m?->price_cad ?? '0.00') }}"
           min="0" step="0.01" max="999999.99"
           class="@error('price_cad') is-invalid @enderror">
    @error('price_cad') <p class="admin-form-error">{{ $message }}</p> @enderror
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
    <input type="number" id="mat-qty-pkg" name="quantity_per_package"
           value="{{ old('quantity_per_package', $m?->quantity_per_package) }}"
           min="1" max="999999" placeholder="Box/bag"
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
<p class="admin-form-help mat-qty-help">Optional qty per package — use when store price is per box/bag, not per single unit.</p>

<div class="mat-url-fields">
  <h3 class="mat-form-section-title">Store links</h3>
  <div class="admin-form-group">
    <label for="mat-product-url">Product URL</label>
    <input type="url" id="mat-product-url" name="product_url" value="{{ old('product_url', $m?->product_url) }}" maxlength="500"
           placeholder="https://www.homedepot.ca/product/…"
           class="@error('product_url') is-invalid @enderror" autocomplete="off" spellcheck="false">
    <p class="admin-form-help">
      @if ($selectedVendorId && $amazonVendorId && $selectedVendorId === $amazonVendorId)
        Amazon Canada product link (optional when a valid ASIN is in Store SKU).
      @elseif ($selectedVendorId && $homeDepotVendorId && $selectedVendorId === $homeDepotVendorId)
        Home Depot product link — used to fetch store price and details during sync.
      @else
        Home Depot or Amazon Canada product URL, depending on vendor.
      @endif
    </p>
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
  @error('is_active') <p class="admin-form-error">{{ $message }}</p> @enderror
  <p class="admin-form-help mat-active-sync-help">
    @if ($priceSyncChannel === 'amazon')
      Syncs via SerpApi Amazon Canada using the ASIN in Store SKU.
    @elseif ($priceSyncChannel === 'home_depot')
      Syncs via SerpApi or local Home Depot store API using the homedepot.ca product URL.
    @elseif ($priceSyncEligibilityError)
      To enable sync: {{ $priceSyncEligibilityError }}
    @else
      Choose Home Depot or Amazon vendor and add the matching product URL or ASIN.
    @endif
  </p>

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
