@php
  $i = $i ?? '__I__';
  $row = $row ?? [];
  $name = $row['name'] ?? '';
  $qty = $row['quantity'] ?? $row['qty'] ?? '';
  $unit = $row['unit'] ?? 'each';
  $price = isset($row['unit_price']) && $row['unit_price'] !== ''
    ? number_format((float) $row['unit_price'], 2, '.', '')
    : '';
  $catalogId = (int) ($row['catalog_material_id'] ?? 0);
@endphp
<div class="br-extra-row{{ $catalogId > 0 ? ' is-linked' : ' is-manual' }}" data-sh-extra-row>
  <div class="br-extra-row__catalog">
    <span class="br-config-source {{ $catalogId > 0 ? 'br-config-source--linked' : 'br-config-source--manual' }}" data-sh-extra-source>
      @if ($catalogId > 0)
        <i class="fa fa-link" aria-hidden="true"></i> Catalog
      @else
        <i class="fa fa-pencil" aria-hidden="true"></i> Manual
      @endif
    </span>
    @include('admin.partials.search_select', [
      'id' => 'sh-extra-cat-'.$i,
      'name' => 'extra_materials['.$i.'][catalog_material_id]',
      'value' => $catalogId > 0 ? $catalogId : '',
      'hideLabel' => true,
      'placeholder' => 'Search Materials DB…',
      'ariaLabel' => 'Search materials database',
      'wrapperClass' => $catalogId > 0 ? 'is-selected' : '',
      'optionsSource' => 'admin-ss-catalog-options',
      'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
      'metaKeys' => ['sku', 'price_label', 'delivery_label'],
      'emptyMessage' => 'No matching materials',
      'maxOptions' => 80,
      'listParent' => 'body',
    ])
  </div>
  <div class="admin-form-group br-extra-row__name">
    <label class="visually-hidden">Material name</label>
    <input type="text" class="admin-input sh-preview" data-sh-extra-name name="extra_materials[{{ $i }}][name]" maxlength="160"
           placeholder="Name" value="{{ $name }}">
  </div>
  <div class="admin-form-group">
    <label class="visually-hidden">Quantity</label>
    <input type="number" step="0.01" min="0" class="admin-input sh-preview" name="extra_materials[{{ $i }}][quantity]"
           placeholder="Qty" value="{{ $qty }}">
  </div>
  <div class="admin-form-group">
    <label class="visually-hidden">Unit</label>
    <input type="text" class="admin-input sh-preview" name="extra_materials[{{ $i }}][unit]" maxlength="40"
           placeholder="Unit" value="{{ $unit }}">
  </div>
  <div class="admin-form-group">
    <label class="visually-hidden">Unit price</label>
    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-sh-extra-price
           class="admin-input cp-money-input sh-preview" name="extra_materials[{{ $i }}][unit_price]"
           placeholder="0.00" value="{{ $price }}">
    <input type="hidden" name="extra_materials[{{ $i }}][delivery_cad]" data-sh-extra-delivery
           value="{{ isset($row['delivery_cad']) ? number_format((float) $row['delivery_cad'], 2, '.', '') : '0.00' }}">
  </div>
  <div class="br-extra-row__client">
    @include('admin.partials.checkbox', [
      'name' => 'extra_materials['.$i.'][client_provided]',
      'hiddenFallback' => true,
      'checked' => ! empty($row['client_provided']),
      'label' => 'Client provides',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm br-extra-remove" aria-label="Remove extra material">
    <i class="fa fa-trash"></i>
  </button>
</div>
