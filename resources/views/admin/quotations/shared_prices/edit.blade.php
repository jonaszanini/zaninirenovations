@extends('admin.layouts.app')

@section('heading', 'Shared prices')

@push('head')
<style>
/* Crew rates: compact inline row */
.sp-crew { margin-bottom: 1.5rem; }
.sp-crew .sp-crew-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem 1.5rem;
  align-items: flex-end;
}
.sp-crew .sp-crew-field {
  flex: 0 0 auto;
}
.sp-crew .sp-crew-field label {
  display: block;
  font-size: 0.7rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--admin-muted);
  margin-bottom: 4px;
}
.sp-crew .sp-crew-field .admin-input {
  width: 110px;
  padding: 0.35rem 0.5rem;
  font-size: 0.85rem;
  height: 34px;
}
.sp-heading { margin: 0 0 0.5rem; font-size: 1.1rem; }
.sp-group { margin-bottom: 1rem; }
.sp-group__summary {
  font-weight: 600;
  font-size: 1rem;
  padding: 0.75rem 0;
  cursor: pointer;
  color: var(--admin-heading);
}
.sp-group__summary::-webkit-details-marker { display: none; }
.sp-group__summary::before {
  content: '▶';
  display: inline-block;
  margin-right: 8px;
  font-size: 0.7em;
  transition: transform 0.15s;
}
.sp-group[open] > .sp-group__summary::before { transform: rotate(90deg); }

/* Desktop table */
.sp-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sp-table th {
  text-align: left;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  padding: 0.4rem 0.35rem;
  border-bottom: 1px solid var(--admin-border);
  white-space: nowrap;
}
.sp-table td {
  padding: 0.35rem 0.35rem;
  border-bottom: 1px solid var(--admin-border);
  vertical-align: middle;
}
.sp-table tr:last-child td { border-bottom: none; }
.sp-table .sp-col-label { width: 140px; min-width: 110px; }
.sp-table .sp-col-rate { width: 64px; }
.sp-table .sp-col-sell { width: 80px; }
.sp-table .sp-col-catalog { width: 90px; }
.sp-table .sp-col-name { width: auto; min-width: 260px; }
.sp-table .sp-col-unit { width: 64px; }
.sp-table .sp-col-price { width: 72px; }
.sp-table .sp-col-consumers { width: auto; min-width: 80px; max-width: 140px; }
.sp-mat-name-input { text-overflow: ellipsis; }
.sp-work-label { font-weight: 500; color: var(--admin-heading); font-size: 0.8rem; line-height: 1.3; }
.sp-work-label small { display: block; font-weight: 400; font-size: 0.75rem; color: var(--admin-muted); }

/* Used-by badges: smaller, inline, side by side */
.sp-chip {
  display: inline-flex;
  padding: 2px 6px;
  font-size: 0.65rem;
  font-weight: 600;
  background: var(--admin-primary-soft);
  color: var(--admin-primary);
  border-radius: 3px;
  margin: 2px 3px 2px 0;
  white-space: nowrap;
  vertical-align: middle;
}
.sp-col-consumers {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
  line-height: 1.4;
}

/* Compact inputs */
.sp-input {
  width: 100%;
  padding: 0.3rem 0.4rem;
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  background: var(--admin-input-bg);
  font-size: 0.8rem;
  color: var(--admin-text);
  height: 28px;
  box-sizing: border-box;
}
.sp-input:focus {
  outline: none;
  border-color: var(--admin-primary);
  box-shadow: 0 0 0 2px var(--admin-primary-ring);
}
.sp-input--sm { max-width: 100%; }
.sp-effective { font-size: 0.65rem; color: var(--admin-muted); margin-top: 2px; }

/* Catalog search: match input height */
.sp-search-wrap { position: relative; }
.sp-search-wrap .admin-ss-combobox { font-size: 0.8rem; }
.sp-search-wrap .admin-ss-combobox__input {
  padding: 0.25rem 0.4rem;
  min-height: 28px;
  height: 28px;
  font-size: 0.8rem;
}

/* Mobile: compact card layout */
@media (max-width: 900px) {
  .sp-table { display: block; }
  .sp-table thead { display: none; }
  .sp-table tbody { display: block; }
  .sp-table tr {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-areas:
      "label label"
      "rate sell"
      "unit price"
      "name name"
      "catalog catalog"
      "consumers consumers";
    gap: 0.4rem 0.75rem;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--admin-border);
    align-items: start;
  }
  .sp-table tr:last-child { border-bottom: none; }
  .sp-table td {
    display: block;
    padding: 0;
    border: none;
  }
  .sp-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 0.6rem;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--admin-muted);
    margin-bottom: 2px;
  }

  /* Grid areas */
  .sp-col-label { grid-area: label; }
  .sp-col-rate { grid-area: rate; }
  .sp-col-sell { grid-area: sell; }
  .sp-col-unit { grid-area: unit; }
  .sp-col-price { grid-area: price; }
  .sp-col-name { grid-area: name; }
  .sp-col-catalog { grid-area: catalog; }
  .sp-col-consumers { grid-area: consumers; }

  /* Label row styling */
  .sp-col-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--admin-heading);
    margin-bottom: 0.25rem;
  }
  .sp-col-label::before { display: none !important; }
  .sp-work-label { font-size: 0.9rem; }
  .sp-work-label small { display: inline; margin-left: 6px; font-size: 0.75rem; }

  /* Full width inputs */
  .sp-input { width: 100%; max-width: none; height: 34px; }
  .sp-input--sm { max-width: 100%; }

  /* Consumers inline */
  .sp-col-consumers::before { display: none; }

  /* Crew rates: stack on mobile */
  .sp-crew .sp-crew-grid {
    flex-direction: column;
    gap: 0.75rem;
  }
  .sp-crew .sp-crew-field .admin-input { width: 100%; }
}
</style>
@endpush

@section('content')
  @php
    $catalogById = collect($catalogPickerOptions ?? [])->keyBy('id');
    $worksByGroup = $works->groupBy('group_key');
    $kindLabels = [
      \App\Models\SharedWork::KIND_PER_HOUR => 'Units/hr',
      \App\Models\SharedWork::KIND_HOURS_EACH => 'Hrs each',
      \App\Models\SharedWork::KIND_FLAT => 'Flat',
    ];
  @endphp

  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to quotations
      </a>
    </div>
    <div class="admin-toolbar-group admin-toolbar-actions">
      <button type="submit" form="sp-lib-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> Save shared prices
      </button>
    </div>
  </div>

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ route('admin.quotations.shared-prices.update') }}" id="sp-lib-form">
    @csrf
    @method('PUT')

    <div class="admin-card sp-crew">
      <h3 class="sp-heading">Crew rates</h3>
      <p class="admin-form-help">Used by Basement, Trimming, Shed, and Millwork when a quote does not override them.</p>
      <div class="sp-crew-grid">
        <div class="sp-crew-field">
          <label for="owner_hourly_rate">Owner hourly (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="owner_hourly_rate" name="owner_hourly_rate"
                 value="{{ old('owner_hourly_rate', number_format((float) $crew->owner_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="sp-crew-field">
          <label for="helper_hourly_rate">Helper hourly (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="helper_hourly_rate" name="helper_hourly_rate"
                 value="{{ old('helper_hourly_rate', number_format((float) $crew->helper_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="sp-crew-field">
          <label for="hours_per_day">Hours / day</label>
          <input type="number" step="0.25" min="0.5" max="16" class="admin-input" id="hours_per_day" name="hours_per_day"
                 value="{{ old('hours_per_day', $crew->hours_per_day) }}" required>
        </div>
      </div>
    </div>

    @foreach ($groups as $groupKey => $groupLabel)
      @php $groupWorks = $worksByGroup->get($groupKey, collect()); @endphp
      @if ($groupWorks->isEmpty())
        @continue
      @endif
      <details class="admin-card sp-group" id="group-{{ $groupKey }}" open>
        <summary class="sp-group__summary">{{ $groupLabel }} ({{ $groupWorks->count() }})</summary>
        <div class="admin-table-scroll">
          <table class="sp-table">
            <thead>
              <tr>
                <th class="sp-col-label">Work item</th>
                <th class="sp-col-rate">Rate</th>
                <th class="sp-col-sell">Sell rate</th>
                <th class="sp-col-unit">Unit</th>
                <th class="sp-col-price">Mat. price</th>
                <th class="sp-col-name">Mat. name</th>
                <th class="sp-col-catalog">Catalog</th>
                <th class="sp-col-consumers">Used by</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($groupWorks as $work)
                @php
                  $catalogId = (int) old('works.'.$work->id.'.catalog_material_id', $work->catalog_material_id ?? 0);
                  $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
                  $isArea = $work->isPricedPerArea();
                  $effective = $isArea ? null : \App\Services\SharedWorkCatalog::effectiveSellRate($work);
                  $consumers = is_array($work->consumers) ? $work->consumers : [];
                  $areaPrice = old('works.'.$work->id.'.flat_rate');
                  if ($areaPrice === null) {
                      $derivedArea = \App\Services\SharedWorkCatalog::unitDisplayRate($work);
                      $areaPrice = $derivedArea > 0
                          ? number_format($derivedArea, 4, '.', '')
                          : '';
                  }
                @endphp
                <tr id="work-{{ $work->key }}" class="sp-row">
                  <input type="hidden" name="works[{{ $work->id }}][id]" value="{{ $work->id }}">
                  <td class="sp-col-label" data-label="Work item">
                    <span class="sp-work-label">
                      {{ $work->label }}
                      @if (!$isArea && isset($kindLabels[$work->labor_kind]))
                        <small>{{ $kindLabels[$work->labor_kind] }}</small>
                      @elseif ($isArea)
                        <small>{{ $work->isSquareFoot() ? '/sqft' : '/linft' }}</small>
                      @endif
                    </span>
                  </td>
                  <td class="sp-col-rate" data-label="Rate">
                    @if ($isArea)
                      <span class="admin-form-help">—</span>
                    @else
                      <input type="text" inputmode="decimal" autocomplete="off"
                             class="sp-input sp-input--sm cp-money-input" id="sp-rate-{{ $work->id }}"
                             name="works[{{ $work->id }}][rate]"
                             value="{{ old('works.'.$work->id.'.rate', $work->rate !== null ? number_format((float) $work->rate, 2, '.', '') : '') }}"
                             placeholder="0.00">
                    @endif
                  </td>
                  <td class="sp-col-sell" data-label="Sell rate">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           class="sp-input sp-input--sm cp-money-input" id="sp-flat-{{ $work->id }}"
                           name="works[{{ $work->id }}][flat_rate]"
                           value="{{ $isArea ? $areaPrice : old('works.'.$work->id.'.flat_rate', $work->flat_rate !== null ? number_format((float) $work->flat_rate, 4, '.', '') : '') }}"
                           placeholder="0.0000">
                    @if ($effective !== null)
                      <div class="sp-effective">{{ \App\Models\ClientProject::formatMoney($effective) }}/{{ $work->unit }}</div>
                    @endif
                  </td>
                  <td class="sp-col-unit" data-label="Unit">
                    <input type="text" class="sp-input sp-input--sm" id="sp-mat-unit-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_unit]"
                           value="{{ old('works.'.$work->id.'.material_unit', $work->material_unit) }}"
                           placeholder="—">
                  </td>
                  <td class="sp-col-price" data-label="Mat. price">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           class="sp-input sp-input--sm cp-money-input" id="sp-mat-price-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_unit_price]"
                           value="{{ old('works.'.$work->id.'.material_unit_price', $work->material_unit_price !== null ? number_format((float) $work->material_unit_price, 4, '.', '') : '') }}"
                           placeholder="0.00">
                  </td>
                  <td class="sp-col-name" data-label="Mat. name">
                    @php $matNameVal = old('works.'.$work->id.'.material_name', $work->material_name); @endphp
                    <input type="text" class="sp-input sp-mat-name-input" id="sp-mat-name-{{ $work->id }}"
                           name="works[{{ $work->id }}][material_name]"
                           value="{{ $matNameVal }}"
                           title="{{ $matNameVal }}"
                           placeholder="—">
                  </td>
                  <td class="sp-col-catalog" data-label="Catalog">
                    <div class="sp-search-wrap">
                      @include('admin.partials.search_select', [
                        'id' => 'sp-mat-'.$work->id,
                        'name' => 'works['.$work->id.'][catalog_material_id]',
                        'value' => $catalogId > 0 ? $catalogId : '',
                        'hideLabel' => true,
                        'placeholder' => 'Search',
                        'ariaLabel' => 'Catalog material for '.$work->label,
                        'wrapperClass' => ($catalogId > 0 ? 'is-selected' : '') . ' sp-search-wrap',
                        'optionsSource' => 'admin-ss-catalog-options',
                        'selectedOption' => $selected,
                        'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                        'metaKeys' => ['sku', 'price_label'],
                        'emptyMessage' => 'No match',
                        'maxOptions' => 50,
                        'listParent' => 'body',
                        'fill' => [
                          'name' => 'sp-mat-name-'.$work->id,
                          'price' => 'sp-mat-price-'.$work->id,
                        ],
                      ])
                    </div>
                  </td>
                  <td class="sp-col-consumers" data-label="Used by">
                    @foreach ($consumers as $consumer)
                      <span class="sp-chip">{{ \App\Services\SharedWorkCatalog::CONSUMER_LABELS[$consumer] ?? $consumer }}</span>
                    @endforeach
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </details>
    @endforeach
  </form>

  <script>
    document.querySelectorAll('.sp-mat-name-input').forEach(function(input) {
      input.addEventListener('input', function() {
        this.title = this.value;
      });
    });
  </script>
@endsection
