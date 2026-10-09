@php
  use App\Models\MillworkSettings;
  use App\Services\Millwork\MillworkPartBuilder;
  use App\Services\MillworkQuotationCalculator;

  $errors = $errors ?? new \Illuminate\Support\ViewErrorBag();
  $mwVal = function (string $key, $fallback = '') use ($inputs) {
      return old($key, $inputs[$key] ?? $fallback);
  };
  $pieces = old('pieces', $inputs['pieces'] ?? MillworkQuotationCalculator::defaultInputs()['pieces']);
  if (! is_array($pieces) || $pieces === []) {
      $pieces = MillworkQuotationCalculator::defaultInputs()['pieces'];
  }
  $catalog = $catalog ?? ($settings->resolvedCatalog());
  $opt = function (string $slot) use ($catalog) {
      return is_array($catalog[$slot] ?? null) ? $catalog[$slot] : [];
  };
  $needsCabinetHardware = false;
  $needsClosetHardware = false;
  $needsBack = false;
  $needsEdgeBanding = false;
  $needsPaint = false;
  $needsStain = false;
  $isLegacyQuote = $isLegacyQuote ?? (((int) ($cutPlanVersion ?? $builderVersion ?? 2)) < 2);

  foreach ($pieces as $pieceRow) {
      if (! is_array($pieceRow)) {
          continue;
      }
      $pieceType = MillworkPartBuilder::normalizeType((string) ($pieceRow['type'] ?? 'shelf'));
      if (in_array($pieceType, ['cabinet', 'cabinet_door', 'vanity', 'closet'], true)) {
          $needsCabinetHardware = true;
      }
      if ($pieceType === 'closet') {
          $needsClosetHardware = true;
      }
      if ($pieceType === 'bench' && (($pieceRow['below'] ?? '') === 'drawers')) {
          $needsCabinetHardware = true;
      }
      if (in_array($pieceType, ['floating_shelf', 'kallax', 'cabinet', 'vanity', 'closet', 'bench'], true) && ! empty($pieceRow['include_back'])) {
          $needsBack = true;
      }
      if (array_key_exists('edge_banding', $pieceRow) ? ! empty($pieceRow['edge_banding']) : true) {
          $needsEdgeBanding = true;
      }
      $finish = (string) ($pieceRow['finish'] ?? 'none');
      if ($finish === 'paint') {
          $needsPaint = true;
      } elseif (in_array($finish, ['stain', 'clear'], true)) {
          $needsStain = true;
      }
  }
@endphp

<details class="br-acc admin-card is-active" data-br-step="pieces" open>
  <summary class="br-acc__summary">
    <span class="br-acc__num">1</span> Pieces
    <x-admin.info-tip label="Pieces">Add each millwork item — cabinets, closets, vanities, trim, and more. Duplicate a piece to copy its sizes.</x-admin.info-tip>
  </summary>
  <div class="br-acc__body">
    <div id="mw-piece-list">
      @foreach ($pieces as $pi => $piece)
        @include('admin.quotations.millwork._piece_card', ['pi' => $pi, 'piece' => is_array($piece) ? $piece : [], 'isLegacyQuote' => $isLegacyQuote ?? (((int) ($cutPlanVersion ?? 2)) < 2)])
      @endforeach
    </div>
    <button type="button" class="admin-btn admin-btn--ghost" id="mw-add-piece" style="margin-top:10px;">
      <i class="fa fa-plus"></i> Add another piece
    </button>
    <template id="mw-piece-template">
      @include('admin.quotations.millwork._piece_card', ['pi' => '__P__', 'piece' => MillworkQuotationCalculator::defaultPiece('shelf'), 'isLegacyQuote' => $isLegacyQuote ?? (((int) ($cutPlanVersion ?? 2)) < 2)])
    </template>
    <template id="mw-set-template">
      @include('admin.quotations.millwork._set_row', ['pi' => '__P__', 'si' => '__S__', 'set' => ['qty' => 1, 'unit' => 'in']])
    </template>
  </div>
</details>

<details class="br-acc admin-card" data-br-step="materials" data-qh-step="2">
  <summary class="br-acc__summary">
    <span class="br-acc__num">2</span> Materials &amp; finish
    <x-admin.info-tip label="Materials & finish">Sheet size defaults to 4×8 from Price configuration. Waste % is applied to edge banding, finish, and linear trim. Nesting leftover is already in the cut plan — add spare sheets if you want extras.</x-admin.info-tip>
    @include('admin.partials.quotation_price_config_gear', [
      'url' => route('admin.quotations.millwork.price-configuration', request()->filled('project') ? ['project' => (int) request('project')] : []),
    ])
  </summary>
  <div class="br-acc__body">
    <div class="admin-form-group mw-sheet-field">
      <span class="admin-label-with-tip">
        <label for="mw-sheet">Wood sheet</label>
        <x-admin.info-tip label="Wood sheet">Primary 4×8 (or catalog size). The cut plan picks smaller leftover sheets when they fit.</x-admin.info-tip>
      </span>
      @include('admin.quotations.millwork._catalog_select', [
        'selectId' => 'mw-sheet',
        'selectName' => 'sheet_id',
        'slot' => MillworkSettings::SLOT_WOOD_SHEETS,
        'rows' => $opt(MillworkSettings::SLOT_WOOD_SHEETS),
        'selected' => $mwVal('sheet_id'),
        'selectClass' => 'mw-sheet-select',
        'manageLabel' => 'wood sheets',
      ])
    </div>
    <div class="admin-form-grid-2">
      <div class="admin-form-group" data-mw-mat="back" @if (! $needsBack) hidden @endif>
        <label for="mw-back">Back / 1/4" sheet</label>
        @php
          $backSelected = (string) $mwVal('back_sheet_id');
          if ($backSelected === '') {
              foreach ($opt(MillworkSettings::SLOT_WOOD_SHEETS) as $row) {
                  if ((float) ($row['thickness_in'] ?? 0) <= 0.3) {
                      $backSelected = (string) ($row['id'] ?? '');
                      break;
                  }
              }
          }
        @endphp
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-back',
          'selectName' => 'back_sheet_id',
          'slot' => MillworkSettings::SLOT_WOOD_SHEETS,
          'rows' => $opt(MillworkSettings::SLOT_WOOD_SHEETS),
          'selected' => $backSelected,
          'manageLabel' => 'wood sheets',
        ])
      </div>
      <div class="admin-form-group" data-mw-mat="edge-banding" @if (! $needsEdgeBanding) hidden @endif>
        <label for="mw-eb">Edge banding</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-eb',
          'selectName' => 'edge_banding_id',
          'slot' => MillworkSettings::SLOT_EDGE_BANDING,
          'rows' => $opt(MillworkSettings::SLOT_EDGE_BANDING),
          'selected' => $mwVal('edge_banding_id'),
          'manageLabel' => 'edge banding',
        ])
        <p class="admin-form-help" id="mw-eb-meta">
          @php
            $initialEdgeLf = (float) ($initialPreview['summary']['edge_band_lf'] ?? 0);
          @endphp
          @if ($initialEdgeLf > 0)
            Total edge banding: {{ rtrim(rtrim(number_format($initialEdgeLf, 1, '.', ''), '0'), '.') }} LF required
          @endif
        </p>
      </div>
      <div class="admin-form-group" data-mw-mat="paint" @if (! $needsPaint) hidden @endif>
        <label for="mw-paint">Paint (quart / half gallon)</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-paint',
          'selectName' => 'paint_id',
          'slot' => MillworkSettings::SLOT_PAINTS,
          'rows' => $opt(MillworkSettings::SLOT_PAINTS),
          'selected' => $mwVal('paint_id'),
          'manageLabel' => 'paint',
        ])
      </div>
      <div class="admin-form-group" data-mw-mat="stain" @if (! $needsStain) hidden @endif>
        <label for="mw-stain">Stain / clear coat</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-stain',
          'selectName' => 'stain_id',
          'slot' => MillworkSettings::SLOT_STAINS,
          'rows' => $opt(MillworkSettings::SLOT_STAINS),
          'selected' => $mwVal('stain_id'),
          'manageLabel' => 'stain',
        ])
      </div>
      <div class="admin-form-group" data-mw-mat="trim">
        <label for="mw-trim">Trim stock (per LF)</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-trim',
          'selectName' => 'trim_id',
          'slot' => MillworkSettings::SLOT_TRIM,
          'rows' => $opt(MillworkSettings::SLOT_TRIM),
          'selected' => $mwVal('trim_id'),
          'manageLabel' => 'trim stock',
        ])
      </div>
    </div>
    <div class="mw-short-row">
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mw-waste">Waste %</label>
          <x-admin.info-tip label="Waste %">Applied to linear trim and wainscoting rails. Sheet count comes from the cut plan.</x-admin.info-tip>
        </span>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-waste" name="waste_pct"
               value="{{ old('waste_pct', $inputs['waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mw-edge-waste">Edge waste %</label>
          <x-admin.info-tip label="Edge waste %">Extra edge banding beyond the cut-plan length.</x-admin.info-tip>
        </span>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-edge-waste" name="edge_waste_pct"
               value="{{ old('edge_waste_pct', $inputs['edge_waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="mw-finish-waste">Finish waste %</label>
          <x-admin.info-tip label="Finish waste %">Extra paint or stain beyond the measured faces.</x-admin.info-tip>
        </span>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-finish-waste" name="finish_waste_pct"
               value="{{ old('finish_waste_pct', $inputs['finish_waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <label for="mw-spare">Spare sheets</label>
        <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty{{ $errors->has('spare_sheets') ? ' is-invalid' : '' }}" id="mw-spare" name="spare_sheets"
               value="{{ old('spare_sheets', $inputs['spare_sheets'] ?? 0) }}"
               @if ($errors->has('spare_sheets')) aria-invalid="true" @endif>
        @error('spare_sheets')
          <p class="admin-form-error">{{ $message }}</p>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="mw-markup">Markup %</label>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-markup" name="markup_pct"
               value="{{ old('markup_pct', $inputs['markup_pct'] ?? 0) }}">
      </div>
    </div>
    <div class="mw-money-row">
      <div class="admin-form-group">
        <label for="mw-owner-rate">Owner rate</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input mw-preview mw-input--money" id="mw-owner-rate" name="owner_hourly_rate"
               value="{{ old('owner_hourly_rate', number_format((float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate), 2, '.', '')) }}" data-cp-money-entry="direct">
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._include_helper', [
          'checked' => (bool) old('include_helper', $inputs['include_helper'] ?? false),
          'previewClass' => 'mw-preview',
          'rateId' => 'mw-helper-rate',
          'rateValue' => old('helper_hourly_rate', number_format((float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate), 2, '.', '')),
        ])
      </div>
      <div class="admin-form-group" id="mw-delivery-wrap" @if (old('client_provides_all', $inputs['client_provides_all'] ?? false)) hidden @endif>
        <label for="mw-delivery">Delivery</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input mw-preview mw-input--money" id="mw-delivery" name="delivery_amount"
               value="{{ old('delivery_amount', number_format((float) ($inputs['delivery_amount'] ?? 0), 2, '.', '')) }}" data-cp-money-entry="direct">
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_install',
      'hiddenFallback' => true,
      'checked' => (bool) old('include_install', $inputs['include_install'] ?? true),
      'label' => 'Include on-site install labour',
      'info' => 'Shop labour (cut, assemble, finish) is always quoted. This adds install hours.',
      'inputClass' => 'admin-checkbox__input mw-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="hardware">
  <summary class="br-acc__summary"><span class="br-acc__num">3</span> Hardware</summary>
  <div class="br-acc__body">
    <div class="mw-hw-grid">
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-hinge">Hinges</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-hinge',
          'selectName' => 'hinge_id',
          'slot' => MillworkSettings::SLOT_HINGES,
          'rows' => $opt(MillworkSettings::SLOT_HINGES),
          'selected' => $mwVal('hinge_id'),
          'manageLabel' => 'hinges',
          'extraSelectAttrs' => 'data-mw-hw-select',
          'withPrice' => true,
        ])
        <p class="admin-form-help mw-hw-price" data-mw-hw-price hidden></p>
      </div>
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-pull">Pulls</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-pull',
          'selectName' => 'pull_id',
          'slot' => MillworkSettings::SLOT_PULLS,
          'rows' => $opt(MillworkSettings::SLOT_PULLS),
          'selected' => $mwVal('pull_id'),
          'manageLabel' => 'pulls',
          'extraSelectAttrs' => 'data-mw-hw-select',
          'withPrice' => true,
        ])
        <p class="admin-form-help mw-hw-price" data-mw-hw-price hidden></p>
      </div>
      <div class="admin-form-group">
        <label for="mw-screw">Screws</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-screw',
          'selectName' => 'screw_id',
          'slot' => MillworkSettings::SLOT_SCREWS,
          'rows' => $opt(MillworkSettings::SLOT_SCREWS),
          'selected' => $mwVal('screw_id'),
          'manageLabel' => 'screws',
        ])
      </div>
      <div class="admin-form-group">
        <label for="mw-nail">Nails</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-nail',
          'selectName' => 'nail_id',
          'slot' => MillworkSettings::SLOT_NAILS,
          'rows' => $opt(MillworkSettings::SLOT_NAILS),
          'selected' => $mwVal('nail_id'),
          'manageLabel' => 'nails',
        ])
      </div>
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-slide">Drawer slides</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-slide',
          'selectName' => 'slide_id',
          'slot' => MillworkSettings::SLOT_DRAWER_SLIDES,
          'rows' => $opt(MillworkSettings::SLOT_DRAWER_SLIDES),
          'selected' => $mwVal('slide_id'),
          'manageLabel' => 'drawer slides',
        ])
      </div>
      <div class="admin-form-group">
        <label for="mw-glue">Wood glue</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-glue',
          'selectName' => 'glue_id',
          'slot' => MillworkSettings::SLOT_GLUE,
          'rows' => $opt(MillworkSettings::SLOT_GLUE),
          'selected' => $mwVal('glue_id'),
          'manageLabel' => 'wood glue',
        ])
      </div>
      <div class="admin-form-group" data-mw-hw="closet-family" @if (! $needsClosetHardware) hidden @endif>
        <label for="mw-rod">Closet rods</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-rod',
          'selectName' => 'rod_id',
          'slot' => MillworkSettings::SLOT_RODS,
          'rows' => $opt(MillworkSettings::SLOT_RODS),
          'selected' => $mwVal('rod_id'),
          'manageLabel' => 'closet rods',
        ])
      </div>
      <div class="admin-form-group" data-mw-hw="closet-family" @if (! $needsClosetHardware) hidden @endif>
        <label for="mw-pins">Shelf pins</label>
        @include('admin.quotations.millwork._catalog_select', [
          'selectId' => 'mw-pins',
          'selectName' => 'pin_id',
          'slot' => MillworkSettings::SLOT_SHELF_PINS,
          'rows' => $opt(MillworkSettings::SLOT_SHELF_PINS),
          'selected' => $mwVal('pin_id'),
          'manageLabel' => 'shelf pins',
        ])
      </div>
    </div>
  </div>
</details>
