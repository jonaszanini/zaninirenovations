@extends('admin.layouts.app')

@section('heading', 'Basement renovation price configuration')

@section('content')
  @php
    $qparam = $qpid ? ['project' => $qpid] : [];
    $catalogById = collect($catalogPickerOptions ?? [])->keyBy('id');
    $materialErrors = $errors->has('materials') || collect($errors->keys())->contains(function ($key) {
        return str_starts_with((string) $key, 'materials.');
    });
    $requestedTab = old('tab', request('tab', 'rates'));
    $startTab = in_array($requestedTab, ['rates', 'materials'], true) ? $requestedTab : 'rates';
    if ($materialErrors) {
        $startTab = 'materials';
    }
  @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.basement.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
    </div>
    <div class="admin-toolbar-group admin-toolbar-actions">
      <button type="submit" form="br-config-form" class="admin-btn admin-btn--primary admin-btn--sm" data-hotkey-save>
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </div>

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ route('admin.quotations.basement.database.update', $qparam) }}" class="admin-card" id="br-config-form">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif
    @include('admin.partials.quotation_return_quote_hidden')
    <input type="hidden" name="tab" id="br-config-tab" value="{{ $startTab }}">

    <div class="cp-tabs br-config-tabs" role="tablist" aria-label="Price configuration">
      <button type="button" class="cp-tab{{ $startTab === 'rates' ? ' is-active' : '' }}"
              id="br-tab-rates" data-tab="rates" role="tab"
              aria-selected="{{ $startTab === 'rates' ? 'true' : 'false' }}"
              aria-controls="br-config-rates">Rates</button>
      <button type="button" class="cp-tab{{ $startTab === 'materials' ? ' is-active' : '' }}"
              id="br-tab-materials" data-tab="materials" role="tab"
              aria-selected="{{ $startTab === 'materials' ? 'true' : 'false' }}"
              aria-controls="br-config-materials">Materials</button>
    </div>

    @include('admin.quotations._shared_price_banner', ['consumer' => 'basement'])

    <section class="cp-tab-panel{{ $startTab === 'rates' ? ' is-active' : '' }}"
             data-panel="rates" id="br-config-rates" role="tabpanel" aria-labelledby="br-tab-rates">
      <h3 class="br-config-heading">This quote only — labour rates</h3>
      <p class="admin-form-help br-config-help">Shared crew and overlapping trades are edited in Shared prices. Each basement quote can still override owner and helper rates on the form.</p>
      <div class="admin-form-grid-4">
        <div class="admin-form-group">
          <label for="owner_hourly_rate">Owner hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="owner_hourly_rate" name="owner_hourly_rate"
                 value="{{ old('owner_hourly_rate', number_format((float) $settings->owner_hourly_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
        </div>
        <div class="admin-form-group">
          <label for="helper_hourly_rate">Helper hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input" id="helper_hourly_rate" name="helper_hourly_rate"
                 value="{{ old('helper_hourly_rate', number_format((float) $settings->helper_hourly_rate, 2, '.', '')) }}" required data-cp-money-entry="direct">
        </div>
        <div class="admin-form-group">
          <label for="hours_per_day">Hours per working day</label>
          <input type="number" step="0.25" min="0.5" max="16" class="admin-input" id="hours_per_day" name="hours_per_day"
                 value="{{ old('hours_per_day', $settings->hours_per_day) }}" required>
          <p class="admin-form-help">Used for calendar-day estimates (1 / 2 / 3 people).</p>
        </div>
      </div>

      <hr class="br-config-divider">
      <h3 class="br-config-heading">Labour productivity</h3>
      <p class="admin-form-help br-config-help">Hours per unit of work. Higher numbers mean faster production (more units per hour) except where the label says “hours each”.</p>

      @include('admin.partials.quotation_productivity_groups', [
        'excludeKeys' => \App\Services\SharedWorkCatalog::sharedProductivityKeys('basement'),
      ])
    </section>

    <section class="cp-tab-panel{{ $startTab === 'materials' ? ' is-active' : '' }}"
             data-panel="materials" id="br-config-materials" role="tabpanel" aria-labelledby="br-tab-materials">
      <h3 class="br-config-heading">Default materials</h3>
      <p class="admin-form-help br-config-help">
        Each line is priced in the unit shown: tile, LVP, and similar finishes are <strong>per sq ft</strong>; toilets and fans are <strong>each</strong>; drywall is per <strong>sheet</strong>; compound is per <strong>17 L pail</strong>.
        Link a Materials DB product to fill the name, a starting price, and delivery — or leave the search empty and type the CAD$ yourself.
        After linking you can still edit the price. On each quotation you can change it again if the client picks a cheaper or more expensive product.
        2x4 plate lumber is always the 2x4x8 stud price ÷ 8 (per linear foot) and cannot be linked separately.
      </p>
      <p class="br-config-legend" aria-label="How to read material rows">
        <span class="br-config-source br-config-source--linked"><i class="fa fa-link" aria-hidden="true"></i> Catalog</span>
        linked from Materials DB
        <span class="br-config-source br-config-source--manual"><i class="fa fa-pencil" aria-hidden="true"></i> Manual</span>
        typed here
        <span class="br-config-source br-config-source--derived"><i class="fa fa-lock" aria-hidden="true"></i> From stud</span>
        plate lumber only — price is locked
      </p>

      @php $materialDefaults = \App\Models\BasementSettings::defaultMaterials(); @endphp
      <div class="admin-table-scroll">
        <table class="admin-table br-config-table">
          <thead>
            <tr>
              <th>Line item</th>
              <th>Materials DB</th>
              <th>Display name</th>
              <th>Unit</th>
              <th>Unit price (CAD$)</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($materials as $key => $row)
              @if (in_array($key, \App\Services\SharedWorkCatalog::sharedMaterialKeys('basement'), true))
                @continue
              @endif
              @php
                $catalogId = (int) old('materials.'.$key.'.catalog_material_id', $row['catalog_material_id'] ?? 0);
                $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
                $lineLabel = $materialDefaults[$key]['name'] ?? $row['name'];
                $fallbackName = $materialDefaults[$key]['name'] ?? $row['name'];
                $fallbackPrice = number_format((float) ($materialDefaults[$key]['unit_price'] ?? $row['unit_price']), 4, '.', '');
                $derivedPlate = $key === \App\Models\BasementSettings::MATERIAL_PLATE_2X4;
                $displayName = old('materials.'.$key.'.name', $row['name']);
                $displayPrice = old(
                    'materials.'.$key.'.unit_price',
                    number_format((float) $row['unit_price'], 4, '.', '')
                );
              @endphp
              <tr class="br-config-row{{ $derivedPlate ? ' is-derived' : ($catalogId > 0 ? ' is-linked' : ' is-manual') }}"
                  data-br-config-row data-br-material-key="{{ $key }}"
                  @if ($derivedPlate) data-br-derived-from="{{ \App\Models\BasementSettings::MATERIAL_STUD_2X4 }}" @endif>
                <td data-label="Line item">
                  <div class="br-config-line-wrap">
                    <strong class="br-config-line">{{ $lineLabel }}</strong>
                    @if ($derivedPlate)
                      <span class="br-config-source br-config-source--derived" data-br-cfg-source>
                        <i class="fa fa-lock" aria-hidden="true"></i> From stud
                      </span>
                    @elseif ($catalogId > 0)
                      <span class="br-config-source br-config-source--linked" data-br-cfg-source>
                        <i class="fa fa-link" aria-hidden="true"></i> Catalog
                      </span>
                    @else
                      <span class="br-config-source br-config-source--manual" data-br-cfg-source>
                        <i class="fa fa-pencil" aria-hidden="true"></i> Manual
                      </span>
                    @endif
                  </div>
                </td>
                <td data-label="Materials DB">
                  @if ($derivedPlate)
                    <p class="admin-form-help br-config-derived">From 2x4x8 stud ÷ 8</p>
                    <input type="hidden" name="materials[{{ $key }}][catalog_material_id]" value="">
                  @else
                    @include('admin.partials.search_select', [
                      'id' => 'br-mat-pick-'.$key,
                      'name' => 'materials['.$key.'][catalog_material_id]',
                      'value' => $catalogId > 0 ? $catalogId : '',
                      'hideLabel' => true,
                      'placeholder' => 'Search Materials DB…',
                      'ariaLabel' => 'Catalog material for '.$lineLabel,
                      'wrapperClass' => $catalogId > 0 ? 'is-selected' : '',
                      'optionsSource' => 'admin-ss-catalog-options',
                      'selectedOption' => $selected,
                      'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                      'metaKeys' => ['sku', 'price_label', 'delivery_label'],
                      'emptyMessage' => 'No matching materials',
                      'maxOptions' => 80,
                      'listParent' => 'body',
                    ])
                  @endif
                </td>
                <td data-label="Display name">
                  <input type="text" class="admin-input" data-br-cfg-name id="br-mat-name-{{ $key }}"
                         name="materials[{{ $key }}][name]" maxlength="160"
                         data-fallback-name="{{ $fallbackName }}"
                         value="{{ $displayName }}">
                </td>
                <td data-label="Unit">
                  @include('admin.partials.quotation_unit_select', [
                    'name' => 'materials['.$key.'][unit]',
                    'id' => 'br-mat-unit-'.$key,
                    'value' => old('materials.'.$key.'.unit', $row['unit']),
                    'derivedPlate' => $derivedPlate,
                    'ariaLabel' => 'Unit for '.$lineLabel,
                  ])
                </td>
                <td data-label="Unit price (CAD$)">
                  <div class="br-config-price-wrap">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-places="4"
                           class="admin-input cp-money-input{{ $derivedPlate ? ' is-locked' : '' }}"
                           data-br-cfg-price id="br-mat-price-{{ $key }}"
                           name="materials[{{ $key }}][unit_price]"
                           data-fallback-price="{{ $fallbackPrice }}"
                           value="{{ $displayPrice }}"
                           title="{{ $derivedPlate ? 'Price is the 2x4x8 stud unit price ÷ 8 and cannot be typed.' : 'Base CAD$ in this line’s unit (per sq ft, each, sheet, pail, …). Link a catalog item to fill a starting price, or type it. Each quotation can still override this.' }}"
                           @if ($derivedPlate) readonly aria-readonly="true" @endif data-cp-money-entry="direct">
                    @if ($derivedPlate)
                      <i class="fa fa-lock br-config-price-lock" title="Locked — from 2x4x8 stud ÷ 8" aria-hidden="true"></i>
                    @endif
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary" data-hotkey-save>
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-basement-quotation.js') }}?v={{ @filemtime(public_path('js/admin-basement-quotation.js')) ?: time() }}"></script>
  <script>
    (function () {
      var root = document.getElementById('br-config-form');
      if (!root) return;
      var tabs = root.querySelectorAll('.cp-tab');
      var panels = root.querySelectorAll('.cp-tab-panel');
      var storageKey = 'brConfigTab';

      var tabInput = document.getElementById('br-config-tab');

      function show(key) {
        if (!key || !root.querySelector('.cp-tab[data-tab="' + key + '"]')) return;
        tabs.forEach(function (t) {
          var on = t.getAttribute('data-tab') === key;
          t.classList.toggle('is-active', on);
          t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        if (tabInput) tabInput.value = key;
        try { sessionStorage.setItem(storageKey, key); } catch (e) {}
        try {
          var url = new URL(window.location.href);
          url.searchParams.set('tab', key);
          if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url.toString());
          }
        } catch (e) {}
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
      });

      var urlTab = '';
      try { urlTab = new URL(window.location.href).searchParams.get('tab') || ''; } catch (e) {}
      var hashTab = (window.location.hash || '').replace('#', '');
      if (hashTab && root.querySelector('.cp-tab[data-tab="' + hashTab + '"]')) {
        show(hashTab);
      } else if (!urlTab) {
        try {
          var saved = sessionStorage.getItem(storageKey);
          if (saved) show(saved);
        } catch (e) {}
      }

      window.addEventListener('hashchange', function () {
        var tab = (window.location.hash || '').replace('#', '');
        show(tab);
      });

      root.addEventListener('submit', function (e) {
        var invalid = root.querySelector(':invalid');
        if (!invalid) return;
        var panel = invalid.closest('[data-panel]');
        if (panel && !panel.classList.contains('is-active')) {
          e.preventDefault();
          show(panel.getAttribute('data-panel'));
          if (typeof invalid.reportValidity === 'function') {
            invalid.reportValidity();
          }
        }
      });
    })();
  </script>
@endpush
