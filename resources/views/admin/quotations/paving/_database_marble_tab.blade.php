<div id="pq-db-panel-marbles" class="pq-db-subpanel" role="tabpanel" aria-labelledby="pq-db-tab-marbles"@if($activeDbTab !== 'marbles') hidden @endif>
  <div class="admin-card">
    <h4 class="cp-form-help-spaced">
      Marble types
      <x-admin.info-tip label="Marble types">Bagged decorative marble for shallow beds. Enter <strong>sq ft per bag</strong> at the <strong>reference depth</strong> (usually 2 in.). The calculator scales bag quantity when the quotation depth differs.</x-admin.info-tip>
    </h4>
    <div class="admin-table-scroll">
      <div class="pq-db-stone-list" style="margin-top:12px;">
        @foreach ($marbleTypes as $mt)
          @php
            $cuFtPerBag = $mt->cuFtPerBag();
            $exampleBags = $mt->sq_ft_per_bag_at_reference_depth > 0
                ? (int) ceil(100 / (float) $mt->sq_ft_per_bag_at_reference_depth - 1e-9)
                : 0;
          @endphp
          <div class="pq-db-stone-card">
            <form method="post" action="{{ route('admin.quotations.paving.database.marbles.update', $mt) }}" class="pq-db-stone-form pq-db-marble-editor" enctype="multipart/form-data">
              @csrf
              @method('PUT')

              <div class="pq-db-stone-card-header">
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Name</span>
                  <input class="admin-input" type="text" name="name" value="{{ $mt->name }}" required maxlength="120" aria-label="Marble name">
                </div>
                @include('admin.quotations.paving._database_stone_image', ['imageUrl' => $mt->image_url])
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Product link</span>
                  <div class="pq-db-stone-link-wrap">
                    <input class="admin-input" type="url" name="product_url" value="{{ $mt->product_url }}" maxlength="500" placeholder="https://…" aria-label="Product link">
                    @if (filled($mt->product_url))
                      <a href="{{ $mt->product_url }}" class="pq-db-stone-open-link" target="_blank" rel="noopener noreferrer" title="Open product page">
                        <i class="fa fa-external-link" aria-hidden="true"></i>
                      </a>
                    @endif
                  </div>
                </div>
                <div class="pq-db-stone-actions">
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save</button>
                  <button type="submit" form="pq-del-marble-{{ $mt->id }}" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </div>
              </div>

              <div class="pq-db-stone-card-body">
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Stone size (in)</span>
                  <input class="admin-input" type="number" name="stone_size_in" value="{{ $mt->stone_size_in }}" step="0.01" min="0.01" max="99" placeholder="—" aria-label="Stone size inches">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Reference depth (in)</span>
                  <input class="admin-input pq-db-marble-ref-depth" type="number" name="reference_depth_in" value="{{ $mt->reference_depth_in }}" step="0.01" min="0.5" max="12" required aria-label="Reference depth inches">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Sq ft / bag @ ref.</span>
                  <input class="admin-input pq-db-marble-sqft-bag" type="number" name="sq_ft_per_bag_at_reference_depth" value="{{ $mt->sq_ft_per_bag_at_reference_depth }}" step="0.0001" min="0.01" required aria-label="Sq ft per bag at reference depth">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">$/bag (CAD)</span>
                  <input type="text" name="price_per_bag" inputmode="decimal" autocomplete="off"
                         value="{{ number_format((float) $mt->price_per_bag, 2, '.', '') }}"
                         class="admin-input cp-money-input" required aria-label="Price per bag CAD" data-cp-money-entry="direct">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Bulk min bags</span>
                  <input class="admin-input" type="number" name="bulk_min_bags" value="{{ $mt->bulk_min_bags }}" step="1" min="1" placeholder="—" aria-label="Bulk minimum bags">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Bulk discount %</span>
                  <input class="admin-input" type="number" name="bulk_discount_pct" value="{{ $mt->bulk_discount_pct }}" step="0.01" min="0" max="100" placeholder="—" aria-label="Bulk discount percent">
                </div>
              </div>

              <p class="admin-form-help pq-db-marble-coverage-hint" style="margin:0 0 12px;">
                ≈ {{ number_format($cuFtPerBag, 2) }} cu ft/bag · ~{{ $exampleBags }} bags for 100 sq ft @ {{ rtrim(rtrim(number_format((float) $mt->reference_depth_in, 2), '0'), '.') }} in.
              </p>

              <div class="pq-db-stone-card-footer">
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Delivery cost (CAD)</span>
                  <input type="text" name="delivery_cost" inputmode="decimal" autocomplete="off"
                         value="{{ $mt->delivery_cost !== null ? number_format((float) $mt->delivery_cost, 2, '.', '') : '' }}"
                         class="admin-input cp-money-input" placeholder="0.00" aria-label="Delivery cost" data-cp-money-entry="direct">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Additional cost (CAD)</span>
                  <input type="text" name="additional_cost" inputmode="decimal" autocomplete="off"
                         value="{{ $mt->additional_cost !== null ? number_format((float) $mt->additional_cost, 2, '.', '') : '' }}"
                         class="admin-input cp-money-input" placeholder="0.00" aria-label="Additional cost" data-cp-money-entry="direct">
                </div>
                <div class="pq-db-field-group">
                  <span class="pq-db-field-label">Additional cost description</span>
                  <input type="text" name="additional_cost_description" value="{{ $mt->additional_cost_description }}"
                         class="admin-input" placeholder="Optional" maxlength="255" aria-label="Additional cost description">
                </div>
              </div>
            </form>
            <form id="pq-del-marble-{{ $mt->id }}" method="post" action="{{ route('admin.quotations.paving.database.marbles.destroy', $mt) }}"
                  class="pq-db-hidden-form" onsubmit="return confirm('Delete this marble type?');">
              @csrf
              @method('DELETE')
            </form>
          </div>
        @endforeach
      </div>
    </div>

    @php
      $addMarbleOpen = old('add_marble') === '1' || $errors->has('price_per_bag') && old('add_marble') === '1';
    @endphp
    <form method="post" action="{{ route('admin.quotations.paving.database.marbles.store') }}" id="pq-add-marble-form"
          class="admin-card pq-db-add-stone pq-db-marble-editor{{ $addMarbleOpen && $errors->any() ? ' pq-db-add-stone--error' : '' }}"
          style="margin-top:24px;padding:16px;border:1px dashed #cbd5e1;background:#f8fafc;" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="add_marble" value="1">
      <h5 class="cp-breakdown-intro" style="margin-bottom:16px;">Add new marble type</h5>
      @error('price_per_bag')
        <p class="admin-form-error" style="margin-bottom:12px;">{{ $message }}</p>
      @enderror

      <div class="pq-db-stone-card-header">
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Name</span>
          <input type="text" name="name" placeholder="Name" class="admin-input" required maxlength="120"
                 value="{{ old('add_marble') === '1' ? old('name') : '' }}">
        </div>
        @include('admin.quotations.paving._database_stone_image', ['imageUrl' => old('add_marble') === '1' ? old('image_url') : ''])
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Product link</span>
          <input type="url" name="product_url" placeholder="https://…" class="admin-input" maxlength="500"
                 value="{{ old('add_marble') === '1' ? old('product_url') : '' }}">
        </div>
      </div>

      <div class="pq-db-stone-card-body">
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Stone size (in)</span>
          <input type="number" name="stone_size_in" step="0.01" min="0.01" max="99" class="admin-input" placeholder="1.25"
                 value="{{ old('add_marble') === '1' ? old('stone_size_in') : '' }}">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Reference depth (in)</span>
          <input type="number" name="reference_depth_in" step="0.01" min="0.5" max="12" class="admin-input pq-db-marble-ref-depth" required
                 value="{{ old('add_marble') === '1' ? old('reference_depth_in', '2') : '2' }}">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Sq ft / bag @ ref.</span>
          <input type="number" name="sq_ft_per_bag_at_reference_depth" step="0.0001" min="0.01" class="admin-input pq-db-marble-sqft-bag" required
                 value="{{ old('add_marble') === '1' ? old('sq_ft_per_bag_at_reference_depth') : '' }}" placeholder="e.g. 9">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">$/bag (CAD)</span>
          <input type="text" name="price_per_bag" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" required placeholder="9.98"
                 value="{{ old('add_marble') === '1' ? old('price_per_bag') : '' }}" data-cp-money-entry="direct">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Bulk min bags</span>
          <input type="number" name="bulk_min_bags" step="1" min="1" class="admin-input" placeholder="70"
                 value="{{ old('add_marble') === '1' ? old('bulk_min_bags') : '' }}">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Bulk discount %</span>
          <input type="number" name="bulk_discount_pct" step="0.01" min="0" max="100" class="admin-input" placeholder="10"
                 value="{{ old('add_marble') === '1' ? old('bulk_discount_pct') : '' }}">
        </div>
      </div>

      <div class="pq-db-stone-card-footer">
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Delivery cost (CAD)</span>
          <input type="text" name="delivery_cost" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" placeholder="0.00"
                 value="{{ old('add_marble') === '1' ? old('delivery_cost') : '' }}" data-cp-money-entry="direct">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Additional cost (CAD)</span>
          <input type="text" name="additional_cost" inputmode="decimal" autocomplete="off" class="admin-input cp-money-input" placeholder="0.00"
                 value="{{ old('add_marble') === '1' ? old('additional_cost') : '' }}" data-cp-money-entry="direct">
        </div>
        <div class="pq-db-field-group">
          <span class="pq-db-field-label">Additional cost description</span>
          <input type="text" name="additional_cost_description" class="admin-input" maxlength="255" placeholder="Optional"
                 value="{{ old('add_marble') === '1' ? old('additional_cost_description') : '' }}">
        </div>
      </div>

      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" style="margin-top:12px;">Add marble type</button>
    </form>
  </div>
</div>
