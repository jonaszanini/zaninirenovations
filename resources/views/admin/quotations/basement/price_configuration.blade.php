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
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
    </div>
  </div>

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ route('admin.quotations.basement.database.update', $qparam) }}" class="admin-card" id="br-config-form">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif
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

    <section class="cp-tab-panel{{ $startTab === 'rates' ? ' is-active' : '' }}"
             data-panel="rates" id="br-config-rates" role="tabpanel" aria-labelledby="br-tab-rates">
      <h3 class="br-config-heading">Labor rates</h3>
      <p class="admin-form-help br-config-help">Defaults for new basement quotations. Each quote can still override owner and helper rates.</p>
      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="owner_hourly_rate">Owner hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
                 class="admin-input cp-money-input" id="owner_hourly_rate" name="owner_hourly_rate"
                 value="{{ old('owner_hourly_rate', number_format((float) $settings->owner_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="admin-form-group">
          <label for="helper_hourly_rate">Helper hourly rate (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
                 class="admin-input cp-money-input" id="helper_hourly_rate" name="helper_hourly_rate"
                 value="{{ old('helper_hourly_rate', number_format((float) $settings->helper_hourly_rate, 2, '.', '')) }}" required>
        </div>
        <div class="admin-form-group">
          <label for="hours_per_day">Hours per working day</label>
          <input type="number" step="0.25" min="0.5" max="16" class="admin-input" id="hours_per_day" name="hours_per_day"
                 value="{{ old('hours_per_day', $settings->hours_per_day) }}" required>
          <p class="admin-form-help">Used for calendar-day estimates (1 / 2 / 3 people).</p>
        </div>
      </div>

      <hr class="br-config-divider">
      <h3 class="br-config-heading">Labor productivity</h3>
      <p class="admin-form-help br-config-help">Hours per unit of work. Higher numbers mean faster production (more units per hour) except where the label says “hours each”.</p>

      @foreach ($productivityGroups as $group => $rows)
        <h4 class="br-review-heading">{{ $group }}</h4>
        <div class="admin-form-grid-2">
          @foreach ($rows as $key => $label)
            <div class="admin-form-group">
              <label for="prod-{{ $key }}">{{ $label }}</label>
              <input type="number" step="0.01" min="0" class="admin-input" id="prod-{{ $key }}"
                     name="productivity[{{ $key }}]"
                     value="{{ old('productivity.'.$key, $productivity[$key] ?? '') }}">
            </div>
          @endforeach
        </div>
      @endforeach
    </section>

    <section class="cp-tab-panel{{ $startTab === 'materials' ? ' is-active' : '' }}"
             data-panel="materials" id="br-config-materials" role="tabpanel" aria-labelledby="br-tab-materials">
      <h3 class="br-config-heading">Default materials</h3>
      <p class="admin-form-help br-config-help">
        Link a Materials DB row to pull live name and price. Linked unit prices are locked to the catalog.
        Clear the search field to type a custom default.
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
              @php
                $catalogId = (int) old('materials.'.$key.'.catalog_material_id', $row['catalog_material_id'] ?? 0);
                $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
                $lineLabel = $materialDefaults[$key]['name'] ?? $row['name'];
                $fallbackName = $materialDefaults[$key]['name'] ?? $row['name'];
                $fallbackPrice = number_format((float) ($materialDefaults[$key]['unit_price'] ?? $row['unit_price']), 4, '.', '');
                $linked = $catalogId > 0 && is_array($selected);
                $displayName = old('materials.'.$key.'.name', $linked ? ($selected['name'] ?? $row['name']) : $row['name']);
                $displayPrice = old(
                    'materials.'.$key.'.unit_price',
                    $linked && isset($selected['price'])
                      ? number_format((float) $selected['price'], 4, '.', '')
                      : number_format((float) $row['unit_price'], 4, '.', '')
                );
              @endphp
              <tr class="br-config-row" data-br-config-row>
                <td data-label="Line item">
                  <strong class="br-config-line">{{ $lineLabel }}</strong>
                </td>
                <td data-label="Materials DB">
                  @include('admin.partials.search_select', [
                    'id' => 'br-mat-pick-'.$key,
                    'name' => 'materials['.$key.'][catalog_material_id]',
                    'value' => $catalogId > 0 ? $catalogId : '',
                    'hideLabel' => true,
                    'placeholder' => 'Search Materials DB…',
                    'ariaLabel' => 'Catalog material for '.$lineLabel,
                    'optionsSource' => 'admin-ss-catalog-options',
                    'selectedOption' => $selected,
                    'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
                    'metaKeys' => ['sku', 'price_label', 'delivery_label'],
                    'emptyMessage' => 'No matching materials',
                    'maxOptions' => 80,
                    'listParent' => 'body',
                  ])
                </td>
                <td data-label="Display name">
                  <input type="text" class="admin-input" data-br-cfg-name id="br-mat-name-{{ $key }}"
                         name="materials[{{ $key }}][name]" maxlength="160"
                         data-fallback-name="{{ $fallbackName }}"
                         value="{{ $displayName }}">
                </td>
                <td data-label="Unit">
                  <input type="text" class="admin-input" name="materials[{{ $key }}][unit]" maxlength="40"
                         value="{{ old('materials.'.$key.'.unit', $row['unit']) }}">
                </td>
                <td data-label="Unit price (CAD$)">
                  <div class="br-config-price-wrap">
                    <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4"
                           class="admin-input cp-money-input{{ $linked ? ' is-locked' : '' }}"
                           data-br-cfg-price id="br-mat-price-{{ $key }}"
                           name="materials[{{ $key }}][unit_price]"
                           data-fallback-price="{{ $fallbackPrice }}"
                           value="{{ $displayPrice }}"
                           title="{{ $linked ? 'Price comes from the Materials DB. Unlink to set a custom default.' : 'Default unit price when no catalog material is linked.' }}"
                           @if ($linked) readonly aria-readonly="true" @endif>
                    <i class="fa fa-lock br-config-price-lock" aria-hidden="true" @unless($linked) hidden @endunless></i>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">Save configuration</button>
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
