@php
  use App\Services\Millwork\MillworkCatalog;

  $selectId = $selectId ?? '';
  $selectName = $selectName ?? '';
  $slot = $slot ?? '';
  $rows = is_array($rows ?? null) ? $rows : [];
  $selected = (string) ($selected ?? '');
  $selectClass = trim('admin-input mw-preview '.($selectClass ?? ''));
  $manageLabel = $manageLabel ?? 'list';
  $extraSelectAttrs = $extraSelectAttrs ?? '';
  $withPrice = ! empty($withPrice);
@endphp
<div class="fq-catalog-picker-wrap mw-catalog-picker">
  <select class="{{ $selectClass }}" id="{{ $selectId }}" name="{{ $selectName }}" data-mw-catalog-slot="{{ $slot }}" {!! $extraSelectAttrs !!}>
    @foreach ($rows as $row)
      @php
        $rowId = (string) ($row['id'] ?? '');
        $hwPrice = (float) ($row['unit_price'] ?? 0);
      @endphp
      <option value="{{ $rowId }}"
              @selected($selected === $rowId)
              @if ($withPrice) data-price="{{ number_format($hwPrice, 2, '.', '') }}" @endif>
        {{ MillworkCatalog::optionLabel($slot, $row) }}
      </option>
    @endforeach
  </select>
  <button type="button"
          class="admin-btn admin-btn--ghost admin-btn--sm mw-catalog-manage"
          data-mw-manage-slot="{{ $slot }}"
          data-mw-manage-select="{{ $selectId }}"
          aria-label="Manage {{ $manageLabel }}">
    <i class="fa fa-list" aria-hidden="true"></i>
  </button>
</div>
