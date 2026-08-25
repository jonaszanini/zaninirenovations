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

    <h3 style="margin:0 0 8px;">Labor rates</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">Defaults for new basement quotations. Each quote can still override owner and helper rates.</p>
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

    <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">
    <h3 style="margin:0 0 8px;">Labor productivity</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">Hours per unit of work. Higher numbers mean faster production (more units per hour) except where the label says “hours each”.</p>

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

    <hr style="margin:24px 0;border:none;border-top:1px solid #e2e8f0;">
    <h3 style="margin:0 0 8px;">Default materials</h3>
    <p class="admin-form-help" style="margin:0 0 16px;">
      Link a Materials DB row to pull live name and price. If nothing is linked, the unit price below is used.
    </p>

    @foreach ($materials as $key => $row)
      @php
        $catalogId = (int) ($row['catalog_material_id'] ?? 0);
        $selected = $catalogId > 0 ? ($catalogById->get($catalogId) ?? null) : null;
      @endphp
      <div class="br-config-mat">
        <div class="br-config-mat__picker">
          <label class="admin-form-help" for="br-mat-pick-{{ $key }}-input">{{ $row['name'] }}</label>
          @include('admin.partials.search_select', [
            'id' => 'br-mat-pick-'.$key,
            'name' => 'materials['.$key.'][catalog_material_id]',
            'value' => $catalogId > 0 ? $catalogId : '',
            'hideLabel' => true,
            'placeholder' => 'Search Materials DB…',
            'ariaLabel' => 'Catalog material for '.$row['name'],
            'optionsSource' => 'admin-ss-catalog-options',
            'selectedOption' => $selected,
            'searchFields' => ['label', 'name', 'sku', 'model', 'vendor', 'store_name'],
            'metaKeys' => ['sku', 'price_label'],
            'emptyMessage' => 'No matching materials',
            'maxOptions' => 80,
            'listParent' => 'body',
            'fill' => [
              'name' => 'br-mat-name-'.$key,
              'price' => 'br-mat-price-'.$key,
            ],
          ])
        </div>
        <div class="admin-form-group">
          <label>Display name</label>
          <input type="text" class="admin-input" id="br-mat-name-{{ $key }}" name="materials[{{ $key }}][name]"
                 maxlength="160" value="{{ old('materials.'.$key.'.name', $row['name']) }}">
        </div>
        <div class="admin-form-group">
          <label>Unit</label>
          <input type="text" class="admin-input" name="materials[{{ $key }}][unit]" maxlength="40"
                 value="{{ old('materials.'.$key.'.unit', $row['unit']) }}">
        </div>
        <div class="admin-form-group">
          <label>Unit price (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4"
                 class="admin-input cp-money-input" id="br-mat-price-{{ $key }}"
                 name="materials[{{ $key }}][unit_price]"
                 value="{{ old('materials.'.$key.'.unit_price', number_format((float) $row['unit_price'], 4, '.', '')) }}">
        </div>
      </div>
    @endforeach

    <div style="margin-top:20px;">
      <button type="submit" class="admin-btn admin-btn--primary">Save configuration</button>
    </div>
  </form>
@endsection
