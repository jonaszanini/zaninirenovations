@php
  use App\Models\MillworkSettings;
  $slot = $slot ?? 'wood_sheets';
  $ri = $ri ?? '__R__';
  $row = is_array($row ?? null) ? $row : [];
  $catalogId = (int) ($row['catalog_material_id'] ?? 0);
  $prefix = 'catalog['.$slot.']['.$ri.']';
@endphp
<tr class="br-config-row{{ $catalogId > 0 ? ' is-linked' : ' is-manual' }}" data-mw-catalog-row data-mw-slot="{{ $slot }}">
  <td data-label="Materials DB">
    <input type="hidden" name="{{ $prefix }}[id]" value="{{ $row['id'] ?? '' }}">
    @include('admin.partials.search_select', [
      'id' => 'mw-cat-'.$slot.'-'.$ri,
      'name' => $prefix.'[catalog_material_id]',
      'value' => $catalogId > 0 ? $catalogId : '',
      'hideLabel' => true,
      'placeholder' => 'Import from Materials DB…',
      'ariaLabel' => 'Import from materials database',
      'wrapperClass' => $catalogId > 0 ? 'is-selected' : '',
      'optionsSource' => 'admin-ss-catalog-options',
      'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
      'metaKeys' => ['sku', 'price_label', 'delivery_label'],
      'emptyMessage' => 'No matching materials',
      'maxOptions' => 80,
      'listParent' => 'body',
    ])
  </td>
  <td data-label="Name">
    <input type="text" class="admin-input" data-mw-cat-name name="{{ $prefix }}[name]" maxlength="160"
           value="{{ $row['name'] ?? '' }}" placeholder="Display name">
  </td>
  <td data-label="Price">
    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-mw-cat-price
           class="admin-input cp-money-input" name="{{ $prefix }}[unit_price]"
           value="{{ isset($row['unit_price']) ? number_format((float) $row['unit_price'], 2, '.', '') : '' }}">
  </td>
  @if ($slot === MillworkSettings::SLOT_WOOD_SHEETS)
    <td data-label="Thickness">
      <input type="number" step="0.001" min="0" class="admin-input" name="{{ $prefix }}[thickness_in]" value="{{ $row['thickness_in'] ?? 0.75 }}">
    </td>
    <td data-label="W×L">
      <div class="mw-inline-dims">
        <input type="number" step="0.1" min="1" class="admin-input" name="{{ $prefix }}[width_in]" value="{{ $row['width_in'] ?? 48 }}" aria-label="Sheet width inches">
        <input type="number" step="0.1" min="1" class="admin-input" name="{{ $prefix }}[length_in]" value="{{ $row['length_in'] ?? 96 }}" aria-label="Sheet length inches">
      </div>
    </td>
    <td data-label="Kind">
      <select class="admin-input" name="{{ $prefix }}[material_kind]">
        <option value="plywood" @selected(($row['material_kind'] ?? 'plywood') === 'plywood')>Plywood</option>
        <option value="mdf" @selected(($row['material_kind'] ?? '') === 'mdf')>MDF</option>
      </select>
      <select class="admin-input" name="{{ $prefix }}[grade]" style="margin-top:6px;">
        <option value="cabinet" @selected(($row['grade'] ?? 'cabinet') === 'cabinet')>Cabinet grade</option>
        <option value="utility" @selected(($row['grade'] ?? '') === 'utility')>Utility</option>
      </select>
    </td>
  @elseif ($slot === MillworkSettings::SLOT_PAINTS || $slot === MillworkSettings::SLOT_STAINS)
    <td data-label="Size">
      <input type="text" class="admin-input" name="{{ $prefix }}[size]" value="{{ $row['size'] ?? '' }}" placeholder="{{ $slot === 'paints' ? 'quart / half_gallon' : '237ml / 946ml' }}">
    </td>
    <td data-label="Coverage">
      <input type="number" step="0.1" min="0" class="admin-input" name="{{ $prefix }}[coverage_sf]" value="{{ $row['coverage_sf'] ?? '' }}" placeholder="sq ft">
    </td>
    <td></td>
  @elseif ($slot === MillworkSettings::SLOT_HINGES)
    <td data-label="Style">
      <select class="admin-input" name="{{ $prefix }}[hinge_style]">
        <option value="overlay" @selected(($row['hinge_style'] ?? 'overlay') === 'overlay')>Overlay</option>
        <option value="inset" @selected(($row['hinge_style'] ?? '') === 'inset')>Inset</option>
        <option value="soft_close" @selected(($row['hinge_style'] ?? '') === 'soft_close')>Soft-close</option>
      </select>
    </td>
    <td colspan="2"></td>
  @elseif ($slot === MillworkSettings::SLOT_EDGE_BANDING)
    <td data-label="Thickness">
      <input type="number" step="0.001" min="0" class="admin-input" name="{{ $prefix }}[thickness_in]" value="{{ $row['thickness_in'] ?? 0.018 }}">
    </td>
    <td colspan="2">
      <input type="hidden" name="{{ $prefix }}[unit]" value="lf">
    </td>
  @elseif (in_array($slot, [MillworkSettings::SLOT_SCREWS, MillworkSettings::SLOT_NAILS], true))
    <td data-label="Per pack">
      <input type="number" min="1" class="admin-input" name="{{ $prefix }}[qty_per_pack]" value="{{ $row['qty_per_pack'] ?? 100 }}">
    </td>
    <td colspan="2"></td>
  @else
    <td colspan="3">
      <input type="hidden" name="{{ $prefix }}[unit]" value="{{ $row['unit'] ?? 'each' }}">
    </td>
  @endif
  <td>
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-remove-catalog aria-label="Remove catalog item">
      <i class="fa fa-trash"></i>
    </button>
  </td>
</tr>
