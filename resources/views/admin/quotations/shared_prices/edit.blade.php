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
.sp-heading {
  display: flex;
  align-items: center;
  gap: 0.15rem;
  margin: 0 0 0.5rem;
  font-size: 1.1rem;
}
.sp-group { margin-bottom: 1rem; }
/* Tighter card padding so Rate / work-item / trailing all fit at 1024. */
@media (min-width: 901px) {
  details.sp-group.admin-card { padding: 1rem 0.65rem; }
}
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

/*
 * Desktop rows are CSS grid (not table-layout:fixed) so leftover width
 * goes to the work-item column, the name column stays narrower, and the
 * trailing search+tag column stays a consistent fixed width.
 */
.sp-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}
.sp-table thead,
.sp-table tbody {
  display: block;
  width: 100%;
}
.sp-table thead tr,
.sp-table tbody tr {
  display: grid;
  grid-template-columns:
    minmax(9rem, 1.4fr)
    minmax(6.5rem, 6.75rem)
    minmax(5.25rem, 5.5rem)
    minmax(3.25rem, 3.75rem)
    minmax(5.25rem, 5.5rem)
    minmax(5.75rem, 0.65fr)
    6.75rem;
  column-gap: 0.3rem;
  align-items: center;
  width: 100%;
}
.sp-table th,
.sp-table td {
  display: block;
  min-width: 0;
  padding: 0.4rem 0.15rem;
  border: none;
}
.sp-col-label,
.sp-col-name {
  overflow: hidden;
}
.sp-table thead tr {
  border-bottom: 1px solid var(--admin-border);
}
.sp-table tbody tr {
  border-bottom: 1px solid var(--admin-border);
}
.sp-table tbody tr:last-child { border-bottom: none; }
.sp-table th {
  text-align: left;
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  white-space: nowrap;
}
.sp-col-label,
.sp-col-rate,
.sp-col-sell,
.sp-col-unit,
.sp-col-price,
.sp-col-name,
.sp-col-trailing {
  min-width: 0;
}
.sp-col-name .sp-input,
.sp-mat-name-input { width: 100%; max-width: none; box-sizing: border-box; }
.sp-col-rate .sp-input,
.sp-col-sell .sp-input,
.sp-col-price .sp-input {
  width: 100%;
  min-width: 0;
  padding-left: 0.3rem;
  padding-right: 0.3rem;
  box-sizing: border-box;
  font-variant-numeric: tabular-nums;
}
.sp-col-unit .sp-input {
  width: 100%;
  min-width: 0;
  padding-left: 0.25rem;
  padding-right: 0.25rem;
  box-sizing: border-box;
}

.sp-work {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: 0.1rem;
  min-width: 0;
}
.sp-work-label {
  font-weight: 500;
  color: var(--admin-heading);
  font-size: 0.8rem;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  overflow-wrap: anywhere;
  word-break: break-word;
  white-space: normal;
}
.sp-work-meta {
  display: block;
  font-weight: 400;
  font-size: 0.68rem;
  line-height: 1.2;
  color: var(--admin-muted);
  white-space: nowrap;
}

.sp-rate-empty {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  color: var(--admin-muted);
  font-size: 0.8rem;
}

.sp-col-sell {
  position: relative;
}
.sp-col-sell .sp-input {
  padding-right: 1.35rem;
}
.sp-col-sell .admin-info-tip {
  position: absolute;
  top: 50%;
  right: 0;
  transform: translateY(-50%);
  width: 28px;
  height: 28px;
  margin: 0;
  font-size: 13px;
}

/* Trailing column: search button + category tag, vertically centred. */
.sp-col-trailing {
  position: relative;
}
.sp-trailing {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.3rem;
  min-height: 28px;
  min-width: 0;
}
.sp-consumers {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 2px;
  min-width: 0;
}
.sp-catalog-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 34px;
  height: 28px;
  padding: 0;
  border: 1px solid var(--admin-border);
  border-radius: 5px;
  background: var(--admin-input-bg);
  color: var(--admin-muted);
  cursor: pointer;
  font-size: 12px;
  transition: border-color 0.15s, background 0.15s;
}
.sp-catalog-btn:hover {
  border-color: var(--admin-primary);
  color: var(--admin-primary);
}
.sp-catalog-btn.is-selected {
  background: var(--admin-primary-soft);
  border-color: var(--admin-primary);
  color: var(--admin-primary);
}
.sp-search-wrap--hidden {
  position: absolute;
  width: 200px;
  right: 0;
  top: 100%;
  z-index: 100;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.1s;
}
.sp-search-wrap--hidden:focus-within {
  opacity: 1;
  pointer-events: auto;
}

.sp-chip {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.15rem 0.4rem;
  font-size: 0.55rem;
  font-weight: 600;
  line-height: 1;
  background: var(--admin-primary-soft);
  color: var(--admin-primary);
  border-radius: 3px;
  white-space: nowrap;
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

/* Catalog search: match input height */
.sp-search-wrap { position: relative; }
.sp-search-wrap .admin-search-select { font-size: 0.8rem; }
.sp-search-wrap .admin-search-select__input {
  padding: 0.25rem 0.4rem;
  min-height: 28px;
  height: 28px;
  font-size: 0.8rem;
}

/* Mobile: compact stacked cards, no horizontal page overflow */
@media (max-width: 900px) {
  .sp-group .admin-table-scroll {
    overflow-x: hidden;
  }
  .sp-table { display: block; }
  .sp-table thead { display: none; }
  .sp-table tbody { display: block; }
  .sp-table tr {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    grid-template-areas:
      "label label"
      "trailing trailing"
      "rate sell"
      "unit price"
      "name name";
    gap: 0.5rem 0.75rem;
    padding: 0.85rem 0;
    border-bottom: 1px solid var(--admin-border);
    align-items: center;
  }
  .sp-table tr:last-child { border-bottom: none; }
  .sp-table td {
    display: block;
    padding: 0;
    border: none;
    overflow: visible;
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

  .sp-col-label { grid-area: label; }
  .sp-col-rate { grid-area: rate; }
  .sp-col-sell { grid-area: sell; }
  .sp-col-unit { grid-area: unit; }
  .sp-col-price { grid-area: price; }
  .sp-col-name { grid-area: name; }
  .sp-col-trailing { grid-area: trailing; justify-self: stretch; }
  .sp-trailing {
    justify-content: flex-start;
    flex-wrap: nowrap;
  }
  .sp-consumers { flex: 1 1 auto; }

  .sp-col-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--admin-heading);
  }
  .sp-col-label::before { display: none !important; }
  .sp-col-trailing::before { display: none !important; }
  .sp-work-label { font-size: 0.9rem; -webkit-line-clamp: 3; }
  .sp-work-meta { font-size: 0.75rem; }

  .sp-input { width: 100%; max-width: none; height: 44px; }
  .sp-input--sm { max-width: 100%; }
  .sp-rate-empty { min-height: 44px; }
  .sp-catalog-btn {
    width: 44px;
    height: 44px;
  }
  .sp-trailing { min-height: 44px; }
  .sp-col-sell .admin-info-tip {
    width: 44px;
    height: 44px;
  }

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
      <h3 class="sp-heading">
        Crew rates
        @include('admin.partials.info_tip', ['text' => 'Used by Basement, Trimming, Shed, and Millwork when a quote does not override them.'])
      </h3>
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
                <th class="sp-col-trailing">Used by</th>
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
                  $workMeta = null;
                  if (! $isArea && isset($kindLabels[$work->labor_kind])) {
                      $workMeta = $kindLabels[$work->labor_kind];
                  } elseif ($isArea) {
                      $workMeta = $work->isSquareFoot() ? '/sqft' : '/linft';
                  }
                @endphp
                <tr id="work-{{ $work->key }}" class="sp-row">
                  <td class="sp-col-label" data-label="Work item">
                    <input type="hidden" name="works[{{ $work->id }}][id]" value="{{ $work->id }}">
                    <span class="sp-work">
                      <span class="sp-work-label">{{ $work->label }}</span>
                      @if ($workMeta)
                        <small class="sp-work-meta">{{ $workMeta }}</small>
                      @endif
                    </span>
                  </td>
                  <td class="sp-col-rate" data-label="Rate">
                    @if ($isArea)
                      <span class="sp-rate-empty">—</span>
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
                      @include('admin.partials.info_tip', [
                        'text' => 'Effective sell '.\App\Models\ClientProject::formatMoney($effective).'/'.$work->unit,
                      ])
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
                  <td class="sp-col-trailing" data-label="Used by">
                    <div class="sp-trailing">
                      <button type="button" class="sp-catalog-btn{{ $catalogId > 0 ? ' is-selected' : '' }}"
                              title="Search catalog materials"
                              aria-label="Search catalog materials for {{ $work->label }}"
                              onclick="this.nextElementSibling.querySelector('.admin-search-select__input').focus();">
                        <i class="fa fa-search" aria-hidden="true"></i>
                      </button>
                      <div class="sp-search-wrap sp-search-wrap--hidden">
                        @include('admin.partials.search_select', [
                          'id' => 'sp-mat-'.$work->id,
                          'name' => 'works['.$work->id.'][catalog_material_id]',
                          'value' => $catalogId > 0 ? $catalogId : '',
                          'hideLabel' => true,
                          'placeholder' => 'Type to search...',
                          'ariaLabel' => 'Catalog material for '.$work->label,
                          'wrapperClass' => 'sp-search-wrap--hidden-input',
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
                      <div class="sp-consumers">
                        @foreach ($consumers as $consumer)
                          <span class="sp-chip">{{ \App\Services\SharedWorkCatalog::CONSUMER_LABELS[$consumer] ?? $consumer }}</span>
                        @endforeach
                      </div>
                    </div>
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
