@extends('admin.layouts.app')

@section('heading', 'Basement renovation price configuration')

@section('content')
  @php
    $qparam = $qpid ? ['project' => $qpid] : [];
    $catalogById = collect($catalogPickerOptions ?? [])->keyBy('id');
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

  <form method="post" action="{{ route('admin.quotations.basement.database.update', $qparam) }}" class="admin-card">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif

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

    <hr class="br-config-divider">
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
                  'metaKeys' => ['sku', 'price_label'],
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

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">Save configuration</button>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-basement-quotation.js') }}?v={{ @filemtime(public_path('js/admin-basement-quotation.js')) ?: time() }}"></script>
@endpush
