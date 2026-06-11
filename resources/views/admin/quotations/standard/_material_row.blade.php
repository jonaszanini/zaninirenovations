@php
  $catalogMaterialId = old('materials.'.$i.'.catalog_material_id', $row['catalog_material_id'] ?? '');
  $sourceMode = old('materials.'.$i.'.entry_mode', filled($catalogMaterialId) ? 'catalog' : 'manual');
  $hstInc = array_key_exists('hst_includes_unit', $row) ? filter_var($row['hst_includes_unit'], FILTER_VALIDATE_BOOLEAN) : false;
  $apply = array_key_exists('apply_hst', $row) ? filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN) : true;
  if ($hstInc) {
      $apply = true;
  }
@endphp
<div class="sq-material-card sq-material-row">
  <div class="sq-mat-fields">
    <div class="sq-mat-row sq-mat-row--source">
      <div class="sq-mat-field sq-mat-field--source">
        <span class="sq-mat-source-label">Entry</span>
        <div class="sq-mat-source-toggle" role="radiogroup" aria-label="Material entry mode">
          <label class="sq-mat-source-option">
            <input type="radio" name="materials[{{ $i }}][entry_mode]" value="manual"
                   class="sq-mat-source-radio" @checked($sourceMode === 'manual')>
            <span>Manual</span>
          </label>
          <label class="sq-mat-source-option">
            <input type="radio" name="materials[{{ $i }}][entry_mode]" value="catalog"
                   class="sq-mat-source-radio" @checked($sourceMode === 'catalog')>
            <span>Material DB</span>
          </label>
        </div>
      </div>
      <div class="sq-mat-field sq-mat-field--catalog fq-catalog-picker sq-mat-catalog-wrap"
           @if ($sourceMode !== 'catalog') hidden @endif>
        <label>Catalog material</label>
        <select name="materials[{{ $i }}][catalog_material_id]"
                class="admin-input sq-mat-catalog-select"
                data-initial="{{ (int) $catalogMaterialId }}"
                aria-label="Search materials catalog">
          <option value="">— Type name or SKU —</option>
        </select>
      </div>
    </div>

    <div class="sq-mat-row sq-mat-row--inputs">
      <div class="sq-mat-field sq-mat-field--short-name">
        <label>Short name</label>
        <input type="text" name="materials[{{ $i }}][short_name]" class="admin-input sq-mat-short-name"
               maxlength="60" placeholder="e.g. Paint, Primer"
               value="{{ $row['short_name'] ?? '' }}">
      </div>
      <div class="sq-mat-field sq-mat-field--description">
        <label>Description</label>
        <input type="text" name="materials[{{ $i }}][description]" class="admin-input sq-mat-desc"
               maxlength="500" placeholder="Material or item"
               value="{{ $row['description'] ?? '' }}">
      </div>
      <div class="sq-mat-field sq-mat-field--unit">
        <label>Unit price ($)</label>
        <input type="text" inputmode="decimal" autocomplete="off" name="materials[{{ $i }}][unit_price]"
               class="admin-input cp-money-input sq-mat-unit" placeholder="0.00"
               value="{{ isset($row['unit_price']) && $row['unit_price'] !== '' ? number_format((float) $row['unit_price'], 2, '.', '') : '' }}">
      </div>
      <div class="sq-mat-field sq-mat-field--qty">
        <label>Qty</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               name="materials[{{ $i }}][quantity]"
               class="admin-input sq-mat-qty" placeholder="1"
               value="{{ $row['quantity'] ?? '' }}">
      </div>
    </div>

    <div class="sq-mat-row sq-mat-row--url">
      <div class="sq-mat-field sq-mat-field--url">
        <label>Product URL <span class="sq-mat-optional">(optional)</span></label>
        <div class="sq-mat-url-control">
          <input type="url" inputmode="url" autocomplete="off"
                 name="materials[{{ $i }}][url]"
                 class="admin-input sq-mat-url" maxlength="2000"
                 placeholder="https://supplier.com/product"
                 value="{{ $row['url'] ?? '' }}">
          <a href="{{ $row['url'] ?? '#' }}"
             class="admin-btn admin-btn--ghost admin-btn--sm sq-mat-url-open"
             target="_blank" rel="noopener" title="Open link in new tab"
             @if (empty($row['url'])) hidden @endif>
            <i class="fa fa-external-link"></i>
          </a>
        </div>
      </div>
    </div>

    <div class="sq-mat-row sq-mat-row--computed">
      <div class="sq-mat-field sq-mat-field--readonly">
        <label>Subtotal</label>
        <div class="sq-mat-readout"><span class="sq-mat-sub">—</span></div>
      </div>
      <div class="sq-mat-field sq-mat-field--readonly">
        <label>HST (13%)</label>
        <div class="sq-mat-readout"><span class="sq-mat-hst">—</span></div>
      </div>
      <div class="sq-mat-field sq-mat-field--readonly">
        <label>Total w/ HST</label>
        <div class="sq-mat-readout"><span class="sq-mat-total">—</span></div>
      </div>
      <div class="sq-mat-field sq-mat-field--apply">
        <label class="sq-mat-hst-toggles-label">HST</label>
        <div class="sq-mat-hst-toggles">
          <input type="hidden" name="materials[{{ $i }}][hst_includes_unit]" value="0">
          <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label">
            <input type="checkbox" name="materials[{{ $i }}][hst_includes_unit]" value="1" class="admin-checkbox__input sq-mat-hst-inclusive-cb" @checked($hstInc)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">HST-incl. price</span>
          </label>
          <input type="hidden" name="materials[{{ $i }}][apply_hst]" value="0">
          <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label">
            <input type="checkbox" name="materials[{{ $i }}][apply_hst]" value="1" class="admin-checkbox__input sq-mat-hst-cb" @checked($apply)>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Pre-tax + HST</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div class="sq-mat-actions">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm sq-remove-material" aria-label="Remove row" title="Remove material">
      <i class="fa fa-trash"></i>
    </button>
  </div>
</div>
