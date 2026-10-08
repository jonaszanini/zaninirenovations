@php
  use App\Models\MillworkSettings;
  use App\Services\Millwork\MillworkPartBuilder;
  use App\Services\MillworkQuotationCalculator;

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
  <summary class="br-acc__summary"><span class="br-acc__num">1</span> Pieces</summary>
  <div class="br-acc__body">
    <p class="admin-form-help">Add each millwork item — cabinets, closets, vanities, trim, and more. Duplicate a piece to copy its sizes.</p>
    <div id="mw-piece-list">
      @foreach ($pieces as $pi => $piece)
        @include('admin.quotations.millwork._piece_card', ['pi' => $pi, 'piece' => is_array($piece) ? $piece : []])
      @endforeach
    </div>
    <button type="button" class="admin-btn admin-btn--ghost" id="mw-add-piece" style="margin-top:10px;">
      <i class="fa fa-plus"></i> Add another piece
    </button>
    <template id="mw-piece-template">
      @include('admin.quotations.millwork._piece_card', ['pi' => '__P__', 'piece' => MillworkQuotationCalculator::defaultPiece('shelf')])
    </template>
    <template id="mw-set-template">
      @include('admin.quotations.millwork._set_row', ['pi' => '__P__', 'si' => '__S__', 'set' => ['qty' => 1, 'unit' => 'in']])
    </template>
  </div>
</details>

<details class="br-acc admin-card" data-br-step="materials">
  <summary class="br-acc__summary"><span class="br-acc__num">2</span> Materials &amp; finish</summary>
  <div class="br-acc__body">
    <p class="admin-form-help">Sheet size defaults to 4×8 from Price configuration. Waste % is applied to edge banding, finish, and linear trim. Nesting leftover is already in the cut plan — add spare sheets if you want extras.</p>
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="mw-sheet">@include('admin.partials.info_tip', ['text' => 'Primary 4×8 (or catalog size). The cut plan picks smaller leftover sheets when they fit.']) Wood sheet</label>
        <select class="admin-input mw-preview" id="mw-sheet" name="sheet_id">
          @foreach ($opt(MillworkSettings::SLOT_WOOD_SHEETS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('sheet_id') === ($row['id'] ?? ''))>
              {{ $row['name'] }} ({{ rtrim(rtrim(number_format((float) ($row['thickness_in'] ?? 0.75), 2, '.', ''), '0'), '.') }}" · {{ (int) ($row['width_in'] ?? 48) }}×{{ (int) ($row['length_in'] ?? 96) }})
            </option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-mat="back" @if (! $needsBack) hidden @endif>
        <label for="mw-back">Back / 1/4" sheet</label>
        <select class="admin-input mw-preview" id="mw-back" name="back_sheet_id">
          @foreach ($opt(MillworkSettings::SLOT_WOOD_SHEETS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('back_sheet_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-mat="edge-banding" @if (! $needsEdgeBanding) hidden @endif>
        <label for="mw-eb">Edge banding</label>
        <select class="admin-input mw-preview" id="mw-eb" name="edge_banding_id">
          @foreach ($opt(MillworkSettings::SLOT_EDGE_BANDING) as $row)
            @php
              $ebCov = (float) ($row['coverage_lf'] ?? 0);
              $hasCovInName = preg_match('/\b\d+(?:\.\d+)?\s*(?:feet|foot|ft|lf)\b/i', $row['name'] ?? '');
              $covSuffix = ($ebCov > 0 && ! $hasCovInName) ? ' ('.rtrim(rtrim(number_format($ebCov, 1, '.', ''), '0'), '.').' LF roll)' : '';
            @endphp
            <option value="{{ $row['id'] }}" @selected($mwVal('edge_banding_id') === ($row['id'] ?? ''))>
              {{ $row['name'] }}{{ $covSuffix }}
            </option>
          @endforeach
        </select>
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
        <select class="admin-input mw-preview" id="mw-paint" name="paint_id">
          @foreach ($opt(MillworkSettings::SLOT_PAINTS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('paint_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-mat="stain" @if (! $needsStain) hidden @endif>
        <label for="mw-stain">Stain / clear coat</label>
        <select class="admin-input mw-preview" id="mw-stain" name="stain_id">
          @foreach ($opt(MillworkSettings::SLOT_STAINS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('stain_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-mat="trim">
        <label for="mw-trim">Trim stock (per LF)</label>
        <select class="admin-input mw-preview" id="mw-trim" name="trim_id">
          @foreach ($opt(MillworkSettings::SLOT_TRIM) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('trim_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <div class="admin-form-grid-2 mw-waste-row">
      <div class="admin-form-group">
        <label for="mw-waste">@include('admin.partials.info_tip', ['text' => 'Applied to linear trim and wainscoting rails. Sheet count comes from the cut plan.']) Waste % (linear)</label>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-waste" name="waste_pct"
               value="{{ old('waste_pct', $inputs['waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <label for="mw-edge-waste">Edge banding waste %</label>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-edge-waste" name="edge_waste_pct"
               value="{{ old('edge_waste_pct', $inputs['edge_waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <label for="mw-finish-waste">Finish waste %</label>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-finish-waste" name="finish_waste_pct"
               value="{{ old('finish_waste_pct', $inputs['finish_waste_pct'] ?? 10) }}">
      </div>
      <div class="admin-form-group">
        <label for="mw-spare">Spare sheets</label>
        <input type="number" min="0" step="1" class="admin-input mw-preview mw-input--qty" id="mw-spare" name="spare_sheets"
               value="{{ old('spare_sheets', $inputs['spare_sheets'] ?? 0) }}">
      </div>
    </div>
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="mw-owner-rate">Owner hourly rate (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input mw-preview" id="mw-owner-rate" name="owner_hourly_rate"
               value="{{ old('owner_hourly_rate', number_format((float) ($inputs['owner_hourly_rate'] ?? $settings->owner_hourly_rate), 2, '.', '')) }}">
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._include_helper', [
          'checked' => (bool) old('include_helper', $inputs['include_helper'] ?? false),
          'previewClass' => 'mw-preview',
          'rateId' => 'mw-helper-rate',
          'rateValue' => old('helper_hourly_rate', number_format((float) ($inputs['helper_hourly_rate'] ?? $settings->helper_hourly_rate), 2, '.', '')),
        ])
      </div>
      <div class="admin-form-group">
        <label for="mw-markup">Markup %</label>
        <input type="number" min="0" max="100" step="0.5" class="admin-input mw-preview mw-input--qty" id="mw-markup" name="markup_pct"
               value="{{ old('markup_pct', $inputs['markup_pct'] ?? 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="mw-delivery">Delivery (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input mw-preview" id="mw-delivery" name="delivery_amount"
               value="{{ old('delivery_amount', number_format((float) ($inputs['delivery_amount'] ?? 0), 2, '.', '')) }}">
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_install',
      'hiddenFallback' => true,
      'checked' => (bool) old('include_install', $inputs['include_install'] ?? true),
      'label' => 'Include on-site install labor',
      'info' => 'Shop labour (cut, assemble, finish) is always quoted. This adds install hours.',
      'inputClass' => 'admin-checkbox__input mw-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="hardware">
  <summary class="br-acc__summary"><span class="br-acc__num">3</span> Hardware</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-hinge">Hinges</label>
        <select class="admin-input mw-preview" id="mw-hinge" name="hinge_id">
          @foreach ($opt(MillworkSettings::SLOT_HINGES) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('hinge_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-pull">Pulls</label>
        <select class="admin-input mw-preview" id="mw-pull" name="pull_id">
          @foreach ($opt(MillworkSettings::SLOT_PULLS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('pull_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="mw-screw">Screws</label>
        <select class="admin-input mw-preview" id="mw-screw" name="screw_id">
          @foreach ($opt(MillworkSettings::SLOT_SCREWS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('screw_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="mw-nail">Nails</label>
        <select class="admin-input mw-preview" id="mw-nail" name="nail_id">
          @foreach ($opt(MillworkSettings::SLOT_NAILS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('nail_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-hw="cabinet-family" @if (! $needsCabinetHardware) hidden @endif>
        <label for="mw-slide">Drawer slides</label>
        <select class="admin-input mw-preview" id="mw-slide" name="slide_id">
          @foreach ($opt(MillworkSettings::SLOT_DRAWER_SLIDES) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('slide_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="mw-glue">Wood glue</label>
        <select class="admin-input mw-preview" id="mw-glue" name="glue_id">
          @foreach ($opt(MillworkSettings::SLOT_GLUE) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('glue_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-hw="closet-family" @if (! $needsClosetHardware) hidden @endif>
        <label for="mw-rod">Closet rods</label>
        <select class="admin-input mw-preview" id="mw-rod" name="rod_id">
          @foreach ($opt(MillworkSettings::SLOT_RODS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('rod_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group" data-mw-hw="closet-family" @if (! $needsClosetHardware) hidden @endif>
        <label for="mw-pins">Shelf pins</label>
        <select class="admin-input mw-preview" id="mw-pins" name="pin_id">
          @foreach ($opt(MillworkSettings::SLOT_SHELF_PINS) as $row)
            <option value="{{ $row['id'] }}" @selected($mwVal('pin_id') === ($row['id'] ?? ''))>{{ $row['name'] }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</details>
