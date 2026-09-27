@extends('admin.layouts.app')

@section('heading', 'Shared prices')

@section('content')
  @php
    $catalogById = collect($catalogPickerOptions ?? [])->keyBy('id');
    $worksByGroup = $works->groupBy('group_key');
    $kindLabels = [
      \App\Models\SharedWork::KIND_PER_HOUR => 'Units / hour',
      \App\Models\SharedWork::KIND_HOURS_EACH => 'Hours each',
      \App\Models\SharedWork::KIND_FLAT => 'Flat / unit',
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
      <p class="admin-form-help">Used by Basement, Trimming, Shed, and Millwork when a quote does not override them. Paving keeps its own crew.</p>
      <div class="admin-form-grid-4">
        <div class="admin-form-group">
          <label for="owner_hourly_rate">Owner hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
                 class="admin-input cp-money-input" id="owner_hourly_rate" name="owner_hourly_rate"
                 value="{{ old('owner_hourly_rate', number_format((float) $crew->owner_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="admin-form-group">
          <label for="helper_hourly_rate">Helper hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
                 class="admin-input cp-money-input" id="helper_hourly_rate" name="helper_hourly_rate"
                 value="{{ old('helper_hourly_rate', number_format((float) $crew->helper_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="admin-form-group">
          <label for="hours_per_day">Hours per working day</label>
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
        <summary class="sp-group__summary">{{ $groupLabel }}</summary>
        <div class="sp-group__body">
          @foreach ($groupWorks as $work)
            @php
              $catalogId = (int) old('works.'.$work->id.'.catalog_material_id', $work->catalog_material_id ?? 0);
              $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
              $effective = \App\Services\SharedWorkCatalog::effectiveSellRate($work);
              $consumers = is_array($work->consumers) ? $work->consumers : [];
            @endphp
            <article class="sp-work" id="work-{{ $work->key }}">
              <input type="hidden" name="works[{{ $work->id }}][id]" value="{{ $work->id }}">
              <header class="sp-work__head">
                <h4 class="sp-work__title">{{ $work->label }}</h4>
                <div class="sp-chips" aria-label="Used by">
                  @foreach ($consumers as $consumer)
                    <span class="sp-chip">{{ \App\Services\SharedWorkCatalog::CONSUMER_LABELS[$consumer] ?? $consumer }}</span>
                  @endforeach
                </div>
              </header>
              <div class="sp-work__grid">
                <div class="admin-form-group">
                  <label for="sp-rate-{{ $work->id }}">{{ $kindLabels[$work->labor_kind] ?? 'Rate' }}</label>
                  <input type="number" step="0.01" min="0" class="admin-input" id="sp-rate-{{ $work->id }}"
                         name="works[{{ $work->id }}][rate]"
                         value="{{ old('works.'.$work->id.'.rate', $work->rate) }}">
                </div>
                <div class="admin-form-group">
                  <label for="sp-flat-{{ $work->id }}">Sell rate (CAD$ / {{ $work->unit }})</label>
                  <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4"
                         class="admin-input cp-money-input" id="sp-flat-{{ $work->id }}"
                         name="works[{{ $work->id }}][flat_rate]"
                         value="{{ old('works.'.$work->id.'.flat_rate', $work->flat_rate !== null ? number_format((float) $work->flat_rate, 4, '.', '') : '') }}">
                  @if ($effective !== null)
                    <p class="admin-form-help">{{ \App\Models\ClientProject::formatMoney($effective) }} / {{ $work->unit }} effective at current crew rate</p>
                  @endif
                </div>
                <div class="admin-form-group">
                  <label>Catalog material</label>
                  @include('admin.partials.search_select', [
                    'id' => 'sp-mat-'.$work->id,
                    'name' => 'works['.$work->id.'][catalog_material_id]',
                    'value' => $catalogId > 0 ? $catalogId : '',
                    'hideLabel' => true,
                    'placeholder' => 'Search Materials DB…',
                    'ariaLabel' => 'Catalog material for '.$work->label,
                    'wrapperClass' => $catalogId > 0 ? 'is-selected' : '',
                    'optionsSource' => 'admin-ss-catalog-options',
                    'selectedOption' => $selected,
                    'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                    'metaKeys' => ['sku', 'price_label', 'delivery_label'],
                    'emptyMessage' => 'No matching materials',
                    'maxOptions' => 80,
                    'listParent' => 'body',
                    'fill' => [
                      'name' => 'sp-mat-name-'.$work->id,
                      'price' => 'sp-mat-price-'.$work->id,
                    ],
                  ])
                </div>
                <div class="admin-form-group">
                  <label for="sp-mat-name-{{ $work->id }}">Material name</label>
                  <input type="text" class="admin-input" id="sp-mat-name-{{ $work->id }}"
                         name="works[{{ $work->id }}][material_name]"
                         value="{{ old('works.'.$work->id.'.material_name', $work->material_name) }}">
                </div>
                <div class="admin-form-group">
                  <label for="sp-mat-unit-{{ $work->id }}">Material unit</label>
                  <input type="text" class="admin-input" id="sp-mat-unit-{{ $work->id }}"
                         name="works[{{ $work->id }}][material_unit]"
                         value="{{ old('works.'.$work->id.'.material_unit', $work->material_unit) }}">
                </div>
                <div class="admin-form-group">
                  <label for="sp-mat-price-{{ $work->id }}">Material price (CAD$)</label>
                  <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4"
                         class="admin-input cp-money-input" id="sp-mat-price-{{ $work->id }}"
                         name="works[{{ $work->id }}][material_unit_price]"
                         value="{{ old('works.'.$work->id.'.material_unit_price', $work->material_unit_price !== null ? number_format((float) $work->material_unit_price, 4, '.', '') : '') }}">
                </div>
              </div>
            </article>
          @endforeach
        </div>
      </details>
    @endforeach
  </form>
@endsection
