@php
  $workIndex = $workIndex ?? 0;
  $namePrefix = 'works['.$workIndex.'][materials]['.$i.']';
  $oldDot = 'works.'.$workIndex.'.materials.'.$i;
  $catalogId = 'sq-mat-catalog-w'.$workIndex.'-'.$i;
  $catalogMaterialId = old($oldDot.'.catalog_material_id', $row['catalog_material_id'] ?? '');
  $postedMode = old($oldDot.'.entry_mode');
  if ($postedMode !== null) {
      $sourceMode = in_array($postedMode, ['electrical', 'catalog', 'manual'], true) ? $postedMode : 'manual';
  } elseif (\App\Models\StandardQuotation::isElectricalMaterialRow($row)) {
      $sourceMode = 'electrical';
  } elseif (($row['entry_mode'] ?? '') === 'catalog' || filled($catalogMaterialId)) {
      $sourceMode = 'catalog';
  } else {
      $sourceMode = 'manual';
  }
  $hasCatalogPick = filled($catalogMaterialId) || filled($row['short_name'] ?? '') || filled($row['description'] ?? '');
  $hstInc = array_key_exists('hst_includes_unit', $row) ? filter_var($row['hst_includes_unit'], FILTER_VALIDATE_BOOLEAN) : false;
  $apply = array_key_exists('apply_hst', $row) ? filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN) : true;
  if ($hstInc) {
      $apply = true;
  }
  $descriptionValue = $row['description'] ?? '';
  if ($sourceMode === 'electrical' && trim((string) $descriptionValue) === '') {
      $descriptionValue = \App\Models\StandardQuotation::ELECTRICAL_LINE_DESCRIPTION;
  }
  $imageUrl = $sourceMode === 'electrical' ? '' : trim((string) ($row['image_url'] ?? ''));
@endphp
<div class="sq-material-card sq-material-row{{ $sourceMode === 'electrical' ? ' sq-material-row--electrical' : '' }}">
  <input type="hidden" name="{{ $namePrefix }}[assembly_line_id]" value="{{ $row['assembly_line_id'] ?? '' }}">
  <input type="hidden" name="{{ $namePrefix }}[assembly_layout_id]" value="{{ $row['assembly_layout_id'] ?? '' }}">
  <input type="hidden" name="{{ $namePrefix }}[assembly_covered_layouts]" value="{{ $row['assembly_covered_layouts'] ?? '' }}">
  <input type="hidden" name="{{ $namePrefix }}[assembly_quoted_at]" value="{{ $row['assembly_quoted_at'] ?? '' }}">
  <div class="sq-mat-fields">
    <div class="sq-mat-toolbar">
      <div class="sq-mat-toolbar-start">
        <div class="sq-mat-thumb" @if ($imageUrl === '') hidden @endif>
          <img @if ($imageUrl !== '') src="{{ $imageUrl }}" @endif alt="" class="sq-mat-thumb-img" decoding="async" referrerpolicy="no-referrer">
        </div>
        <div class="sq-mat-mode-segment" role="radiogroup" aria-label="How to add this line">
          <label class="sq-mat-mode-segment__item">
            <input type="radio" name="{{ $namePrefix }}[entry_mode]" value="manual"
                   class="sq-mat-source-radio" @checked($sourceMode === 'manual')>
            <span>Type manually</span>
          </label>
          <label class="sq-mat-mode-segment__item">
            <input type="radio" name="{{ $namePrefix }}[entry_mode]" value="catalog"
                   class="sq-mat-source-radio" @checked($sourceMode === 'catalog')>
            <span>From Materials DB</span>
          </label>
          <label class="sq-mat-mode-segment__item">
            <input type="radio" name="{{ $namePrefix }}[entry_mode]" value="electrical"
                   class="sq-mat-source-radio" @checked($sourceMode === 'electrical')>
            <span>Electrical work</span>
          </label>
        </div>
      </div>
      <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm sq-remove-material" aria-label="Remove material" title="Remove material">
        <i class="fa fa-trash"></i>
      </button>
    </div>

    <div class="sq-mat-catalog-wrap" @if ($sourceMode !== 'catalog') hidden @endif>
      <div class="admin-form-group sq-mat-catalog-group">
        <label class="sq-mat-catalog-label" for="{{ $catalogId }}-input">Search materials</label>
        @include('admin.partials.search_select', [
          'id' => $catalogId,
          'name' => $namePrefix.'[catalog_material_id]',
          'value' => (int) $catalogMaterialId ?: '',
          'hideLabel' => true,
          'placeholder' => 'Search name or SKU…',
          'ariaLabel' => 'Search materials database',
          'optionsSource' => 'admin-ss-catalog-options',
          'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
          'metaKeys' => ['sku', 'price_label', 'delivery_label'],
          'emptyMessage' => 'No matching materials',
          'wrapperClass' => 'sq-mat-catalog-select',
          'maxOptions' => 80,
        ])
      </div>
    </div>

    <div class="sq-mat-details-panel"
         @if ($sourceMode === 'catalog' && ! $hasCatalogPick) hidden @endif>
      <div class="sq-mat-row sq-mat-row--inputs">
        <div class="sq-mat-field sq-mat-field--short-name" @if ($sourceMode === 'electrical') hidden @endif>
          <label>Short name</label>
          <input type="text" name="{{ $namePrefix }}[short_name]" class="admin-input sq-mat-short-name"
                 maxlength="60" placeholder="e.g. Paint, Primer"
                 value="{{ $row['short_name'] ?? '' }}">
        </div>
        <div class="sq-mat-field sq-mat-field--description">
          <label>Description</label>
          <input type="text" name="{{ $namePrefix }}[description]" class="admin-input sq-mat-desc @error($oldDot.'.description') is-invalid @enderror"
                 maxlength="500" placeholder="{{ $sourceMode === 'electrical' ? \App\Models\StandardQuotation::ELECTRICAL_LINE_DESCRIPTION : 'Material or item' }}"
                 value="{{ $descriptionValue }}"
                 @error($oldDot.'.description') aria-invalid="true" aria-describedby="{{ $catalogId }}-desc-error" @enderror>
          @error($oldDot.'.description')
            <p class="admin-form-error" id="{{ $catalogId }}-desc-error">{{ $message }}</p>
          @enderror
        </div>
        <div class="sq-mat-field sq-mat-field--unit">
          <label>Unit price ($)</label>
          <input type="text" inputmode="decimal" autocomplete="off" name="{{ $namePrefix }}[unit_price]"
                 class="admin-input cp-money-input sq-mat-unit" placeholder="0.00"
                 value="{{ isset($row['unit_price']) && $row['unit_price'] !== '' ? number_format((float) $row['unit_price'], 2, '.', '') : '' }}">
          <input type="hidden" name="{{ $namePrefix }}[delivery_cad]" class="sq-mat-delivery"
                 value="{{ isset($row['delivery_cad']) && $row['delivery_cad'] !== '' ? number_format((float) $row['delivery_cad'], 2, '.', '') : '0.00' }}">
        </div>
        <div class="sq-mat-field sq-mat-field--qty">
          <label>Qty</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 name="{{ $namePrefix }}[quantity]"
                 class="admin-input sq-mat-qty" placeholder="1"
                 value="{{ $row['quantity'] ?? '' }}">
        </div>
      </div>

      <p class="admin-form-help sq-mat-delivery-note" @if ((float) ($row['delivery_cad'] ?? 0) <= 0) hidden @endif>
        Includes {{ \App\Models\ClientProject::formatMoney((float) ($row['delivery_cad'] ?? 0)) }} delivery (once, not × qty).
      </p>

      <div class="sq-mat-row sq-mat-row--url" @if ($sourceMode === 'electrical') hidden @endif>
        <div class="sq-mat-field sq-mat-field--url">
          <label>Product URL <span class="sq-mat-optional">(optional)</span></label>
          <div class="sq-mat-url-control">
            <input type="url" inputmode="url" autocomplete="off"
                   name="{{ $namePrefix }}[url]"
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
        <div class="sq-mat-field sq-mat-field--image-url">
          <label>Image URL <span class="sq-mat-optional">(optional)</span></label>
          <input type="url" inputmode="url" autocomplete="off"
                 name="{{ $namePrefix }}[image_url]"
                 class="admin-input sq-mat-image-url" maxlength="2000"
                 placeholder="https://supplier.com/image.jpg"
                 value="{{ $row['image_url'] ?? '' }}">
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
          <span class="sq-mat-hst-toggles-label">Tax on this line</span>
          <div class="sq-mat-hst-toggles">
            <input type="hidden" name="{{ $namePrefix }}[apply_hst]" value="0">
            <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label">
              <input type="checkbox" name="{{ $namePrefix }}[apply_hst]" value="1" class="admin-checkbox__input sq-mat-hst-cb" @checked($apply)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Add 13% HST</span>
            </label>
            <input type="hidden" name="{{ $namePrefix }}[hst_includes_unit]" value="0">
            <label class="admin-checkbox sq-checkbox-label sq-mat-apply-label">
              <input type="checkbox" name="{{ $namePrefix }}[hst_includes_unit]" value="1" class="admin-checkbox__input sq-mat-hst-inclusive-cb" @checked($hstInc)>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">Price incl. HST</span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <p class="admin-form-help sq-mat-catalog-empty" @if ($sourceMode !== 'catalog' || $hasCatalogPick) hidden @endif>
      <i class="fa fa-search" aria-hidden="true"></i> Search and select a material above to continue.
    </p>
  </div>
</div>
