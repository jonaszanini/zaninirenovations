@extends('admin.layouts.app')

@section('heading', 'Millwork price configuration')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-millwork.css') }}?v={{ @filemtime(public_path('css/admin-millwork.css')) ?: time() }}">
@endpush

@section('content')
  @php
    use App\Models\MillworkSettings;
    $qparam = $qpid ? ['project' => $qpid] : [];
    $requestedTab = old('tab', request('tab', 'rates'));
    $startTab = in_array($requestedTab, ['rates', 'catalog', 'cut'], true) ? $requestedTab : 'rates';
  @endphp
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="{{ route('admin.quotations.millwork.index', $qparam) }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
      @include('admin.partials.quotation_back_to_quote')
      <a href="{{ route('admin.materials.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cubes"></i> Materials catalog
      </a>
    </div>
    <div class="admin-toolbar-group admin-toolbar-actions">
      <button type="submit" form="mw-config-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </div>

  <script type="application/json" id="admin-ss-catalog-options">@json($catalogPickerOptions ?? [])</script>

  <form method="post" action="{{ route('admin.quotations.millwork.database.update', $qparam) }}" class="admin-card" id="mw-config-form">
    @csrf
    @if ($qpid)
      <input type="hidden" name="project" value="{{ $qpid }}">
    @endif
    @include('admin.partials.quotation_return_quote_hidden')
    <input type="hidden" name="tab" id="mw-config-tab" value="{{ $startTab }}">

    <div class="cp-tabs br-config-tabs" role="tablist" aria-label="Price configuration">
      <button type="button" class="cp-tab{{ $startTab === 'rates' ? ' is-active' : '' }}"
              id="mw-tab-rates" data-tab="rates" role="tab"
              aria-selected="{{ $startTab === 'rates' ? 'true' : 'false' }}"
              aria-controls="mw-config-rates">Rates</button>
      <button type="button" class="cp-tab{{ $startTab === 'catalog' ? ' is-active' : '' }}"
              id="mw-tab-catalog" data-tab="catalog" role="tab"
              aria-selected="{{ $startTab === 'catalog' ? 'true' : 'false' }}"
              aria-controls="mw-config-catalog">Catalog</button>
      <button type="button" class="cp-tab{{ $startTab === 'cut' ? ' is-active' : '' }}"
              id="mw-tab-cut" data-tab="cut" role="tab"
              aria-selected="{{ $startTab === 'cut' ? 'true' : 'false' }}"
              aria-controls="mw-config-cut">Cut settings</button>
    </div>

    <section class="cp-tab-panel{{ $startTab === 'rates' ? ' is-active' : '' }}"
             data-panel="rates" id="mw-config-rates" role="tabpanel" aria-labelledby="mw-tab-rates">
      <h3 class="br-config-heading">Labor rates</h3>
      <div class="admin-form-grid-4">
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
        </div>
      </div>
      <hr class="br-config-divider">
      <h3 class="br-config-heading">Labor productivity</h3>
      @include('admin.partials.quotation_productivity_groups')
    </section>

    <section class="cp-tab-panel{{ $startTab === 'catalog' ? ' is-active' : '' }}"
             data-panel="catalog" id="mw-config-catalog" role="tabpanel" aria-labelledby="mw-tab-catalog">
      <h3 class="br-config-heading">Millwork-only catalog</h3>
      <p class="admin-form-help">Imported Materials DB items are available only on Custom Millwork quotations.</p>
      @foreach (MillworkSettings::catalogSlots() as $slot => $label)
        <h4 class="br-review-heading">{{ $label }}</h4>
        <div class="admin-table-scroll">
          <table class="admin-table br-config-table mw-catalog-table">
            <thead>
              <tr>
                <th class="mw-col-mat">Materials DB</th>
                <th class="mw-col-name">Display name</th>
                <th class="mw-col-price">Unit price (CAD$)</th>
                @if ($slot === MillworkSettings::SLOT_WOOD_SHEETS)
                  <th class="mw-col-thickness">Thickness (in)</th>
                  <th class="mw-col-dims">W × L (in)</th>
                  <th class="mw-col-kind">Kind / grade</th>
                @elseif ($slot === MillworkSettings::SLOT_PAINTS || $slot === MillworkSettings::SLOT_STAINS)
                  <th class="mw-col-size">Size</th>
                  <th class="mw-col-coverage">Coverage (sq ft)</th>
                  <th></th>
                @elseif ($slot === MillworkSettings::SLOT_HINGES)
                  <th class="mw-col-style">Style</th>
                  <th colspan="2"></th>
                @elseif ($slot === MillworkSettings::SLOT_EDGE_BANDING)
                  <th class="mw-col-thickness">Thickness (in)</th>
                  <th colspan="2"></th>
                @elseif (in_array($slot, [MillworkSettings::SLOT_SCREWS, MillworkSettings::SLOT_NAILS], true))
                  <th class="mw-col-pack">Qty / pack</th>
                  <th colspan="2"></th>
                @else
                  <th colspan="3"></th>
                @endif
                <th class="mw-col-actions"></th>
              </tr>
            </thead>
            <tbody data-mw-catalog-body="{{ $slot }}">
              @foreach (($catalog[$slot] ?? []) as $ri => $row)
                @include('admin.quotations.millwork._catalog_row', ['slot' => $slot, 'ri' => $ri, 'row' => $row])
              @endforeach
            </tbody>
          </table>
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-mw-add-catalog="{{ $slot }}" style="margin:8px 0 18px;">
          <i class="fa fa-plus"></i> Add {{ strtolower($label) }}
        </button>
        <template data-mw-catalog-template="{{ $slot }}">
          <table>
            <tbody>
              @include('admin.quotations.millwork._catalog_row', ['slot' => $slot, 'ri' => '__R__', 'row' => []])
            </tbody>
          </table>
        </template>
      @endforeach
    </section>

    <section class="cp-tab-panel{{ $startTab === 'cut' ? ' is-active' : '' }}"
             data-panel="cut" id="mw-config-cut" role="tabpanel" aria-labelledby="mw-tab-cut">
      <h3 class="br-config-heading">Guillotine cut settings</h3>
      <div class="admin-form-grid-4">
        <div class="admin-form-group">
          <label for="kerf_in">Blade kerf (in)</label>
          <input type="number" step="0.001" min="0" class="admin-input" id="kerf_in" name="cut_settings[kerf_in]"
                 value="{{ old('cut_settings.kerf_in', $cutSettings['kerf_in'] ?? 0.125) }}">
          <p class="admin-form-help">0.125 = 1/8″. Reserved between every saw cut on the cut plan.</p>
        </div>
        <div class="admin-form-group">
          <label for="trim_in">Factory trim each edge (in)</label>
          <input type="number" step="0.001" min="0" class="admin-input" id="trim_in" name="cut_settings[trim_in]"
                 value="{{ old('cut_settings.trim_in', $cutSettings['trim_in'] ?? 0.125) }}">
        </div>
        <div class="admin-form-group">
          <label for="min_offcut_in">Min reusable offcut (in)</label>
          <input type="number" step="0.1" min="0" class="admin-input" id="min_offcut_in" name="cut_settings[min_offcut_in]"
                 value="{{ old('cut_settings.min_offcut_in', $cutSettings['min_offcut_in'] ?? 2) }}">
        </div>
        <div class="admin-form-group">
          <label for="edge_band_thickness_in">Edge band thickness (in)</label>
          <input type="number" step="0.001" min="0" class="admin-input" id="edge_band_thickness_in" name="cut_settings[edge_band_thickness_in]"
                 value="{{ old('cut_settings.edge_band_thickness_in', $cutSettings['edge_band_thickness_in'] ?? 0.018) }}">
        </div>
      </div>
    </section>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn--primary">
        <i class="fa fa-save"></i> Save configuration
      </button>
    </div>
  </form>
@endsection

@push('scripts')
  <script src="{{ asset('js/admin-millwork-quotation.js') }}?v={{ @filemtime(public_path('js/admin-millwork-quotation.js')) ?: time() }}"></script>
@endpush
