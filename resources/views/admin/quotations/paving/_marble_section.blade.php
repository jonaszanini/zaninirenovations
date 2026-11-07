@php
  $marbleEnabled = filter_var(old('marble_enabled', $inputs['marble_enabled'] ?? false), FILTER_VALIDATE_BOOLEAN);
  $selectedMarbleId = (int) old('marble_type_id', $inputs['marble_type_id'] ?? 0);
  $marbleArea = old('marble_area_sq_ft', $inputs['marble_area_sq_ft'] ?? '');
  $marbleDepth = old('marble_depth_in', $inputs['marble_depth_in'] ?? 2);
  $marbleGeo = filter_var(old('marble_include_geotextile', $inputs['marble_include_geotextile'] ?? true), FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="admin-form-group pq-marble-section" id="pq-marble-section">
  <div class="pq-marble-section__head">
    <span class="admin-label-with-tip">
      <label class="admin-checkbox admin-checkbox--block">
        <input type="checkbox" name="marble_enabled" value="1" class="admin-checkbox__input pq-calc-in" id="pq-marble-enabled" @checked($marbleEnabled)>
        <span class="admin-checkbox__mark" aria-hidden="true"></span>
        <span class="admin-checkbox__text">Include decorative marble bed</span>
      </label>
      <x-admin.info-tip label="Decorative marble bed">Additive shallow bed — separate area and depth from paver stones. Includes bagged marble, shallow excavation, geotextile, spread labour.</x-admin.info-tip>
    </span>
  </div>

  <div class="pq-marble-section__fields" id="pq-marble-fields" @unless($marbleEnabled) hidden @endunless>
    <div class="admin-form-grid-2 pq-marble-type-row">
      <div class="admin-form-group">
        <label for="pq-marble-type">Marble type</label>
        <select id="pq-marble-type" name="marble_type_id" class="admin-input pq-calc-in pq-marble-type-select" @if($marbleEnabled) required @else disabled @endif>
          <option value="">— Select marble —</option>
          @foreach ($marbleTypes as $mt)
            <option value="{{ $mt->id }}"
              data-sqft-bag="{{ $mt->sq_ft_per_bag_at_reference_depth }}"
              data-ref-depth="{{ $mt->reference_depth_in }}"
              data-price-bag="{{ $mt->price_per_bag }}"
              data-bulk-min="{{ $mt->bulk_min_bags }}"
              data-bulk-pct="{{ $mt->bulk_discount_pct }}"
              data-cuft-bag="{{ $mt->cuFtPerBag() }}"
              @selected($selectedMarbleId === (int) $mt->id)>{{ $mt->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group pq-marble-preview-wrap">
        <span class="admin-form-group-label">Estimated bags</span>
        <p class="pq-marble-preview" id="pq-marble-bags-preview" aria-live="polite">—</p>
      </div>
    </div>

    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="pq-marble-area">Marble area (sq ft)</label>
        <input type="number" id="pq-marble-area" name="marble_area_sq_ft" class="admin-input pq-calc-in"
               step="0.01" inputmode="decimal"
               value="{{ $marbleArea }}" placeholder="e.g. 50"
               @if($marbleEnabled) min="0.1" required @else disabled @endif>
      </div>
      <div class="admin-form-group">
        <label for="pq-marble-depth">Marble bed depth (in)</label>
        <input type="number" id="pq-marble-depth" name="marble_depth_in" class="admin-input pq-calc-in"
               step="0.01" inputmode="decimal"
               value="{{ $marbleDepth }}" placeholder="2"
               @if($marbleEnabled) min="0.5" max="12" required @else disabled @endif>
      </div>
    </div>

    <label class="admin-checkbox">
      <input type="checkbox" name="marble_include_geotextile" value="1" class="admin-checkbox__input pq-calc-in" id="pq-marble-geo" @checked($marbleGeo)>
      <span class="admin-checkbox__mark" aria-hidden="true"></span>
      <span class="admin-checkbox__text">Geotextile fabric under marble bed</span>
    </label>
  </div>
</div>
