@php
  $brQty = function (string $key) use ($inputs) {
      $old = old($key);
      if ($old !== null) {
          return $old;
      }
      $v = $inputs[$key] ?? null;
      if ($v === null || $v === '') {
          return '';
      }

      return $v;
  };
  $brVal = function (string $key, $fallback = '') use ($inputs) {
      return old($key, $inputs[$key] ?? $fallback);
  };
  $brOn = function (string $key, $fallback = false) use ($inputs) {
      return (bool) old($key, $inputs[$key] ?? $fallback);
  };
  $step = 0;
  $scopePreset = $scopePreset ?? \App\Services\BasementQuotationCalculator::normalizePreset(old('scope_preset', $inputs['scope_preset'] ?? 'full'));
  $areaMode = \App\Services\BasementQuotationCalculator::normalizeAreaMode(old('area_entry_mode', $inputs['area_entry_mode'] ?? 'sqft'));
  $enabledNow = $enabledNow ?? \App\Services\BasementQuotationCalculator::enabledSections([
      'scope_preset' => $scopePreset,
      'enabled_sections' => old('enabled_sections', $inputs['enabled_sections'] ?? null),
  ]);
  $enabledCount = $enabledCount ?? max(1, count($enabledNow));
  $bathNestTrades = $scopePreset === 'bathroom';
  $qtyDimsPosted = old('qty_dims', $inputs['qty_dims'] ?? []);
  if (! is_array($qtyDimsPosted)) {
      $qtyDimsPosted = [];
  }
  $qtyDim = function (string $field) use ($qtyDimsPosted) {
      $row = is_array($qtyDimsPosted[$field] ?? null) ? $qtyDimsPosted[$field] : [];
      $len = $row['length_ft'] ?? '';
      $wid = $row['width_ft'] ?? '';

      return [
          'mode' => \App\Services\BasementQuotationCalculator::normalizeAreaMode($row['mode'] ?? 'sqft'),
          'length' => ($len === null || $len === '') ? '' : $len,
          'width' => ($wid === null || $wid === '') ? '' : $wid,
      ];
  };
  $filledKeys = $filledKeys ?? [];
  $brFilled = function (string $key) use ($filledKeys): bool {
      return in_array($key, $filledKeys, true);
  };
  $layoutClientProjectId = (int) ($layoutClientProjectId ?? 0);
  $layoutSearchUrl = route('admin.utilities.room-area.layouts', array_filter([
      'client_project_id' => $layoutClientProjectId > 0 ? $layoutClientProjectId : null,
  ]));
  $layoutImportId = trim((string) $brVal('room_area_import_id', ''));
  $layoutImportLabel = trim((string) $brVal('room_area_import_label', ''));
  $layoutSelected = $layoutImportId !== ''
      ? [
          'id' => $layoutImportId,
          'name' => $layoutImportLabel !== '' ? $layoutImportLabel : $layoutImportId,
          'label' => $layoutImportLabel !== '' ? $layoutImportLabel : $layoutImportId,
      ]
      : null;
  $layoutPerimeter = $brQty('perimeter_lf');
  $tradeInfo = [
      'demolition' => 'Tear-out of existing flooring, studs, drywall, and/or baseboard, plus dumpster / bin cost.',
      'framing' => 'New wood walls, bulkheads around ducts, headers, and door openings.',
      'electrical' => 'Outlets, switches, lights, detectors, cable runs, and optional panel upgrade.',
      'plumbing' => 'Drain, vent, and supply piping for a bath, laundry, wet bar, floor drain, or water-heater move.',
      'insulation' => 'Wall batts and vapour barrier, plus optional ceiling or under-slab insulation.',
      'drywall' => 'Hang, tape, mud, and sand walls and ceilings to the finish level you pick.',
      'ceiling' => 'New drywall ceiling, paint-grade, drop ceiling, or leave the existing ceiling.',
      'flooring' => 'Finish floor, underlayment, leveling, and transitions.',
      'doors_trim' => 'Interior doors, baseboards, door casing, and window trim kits.',
      'painting' => 'Prime and paint walls, ceiling, and trim (coats and colors).',
      'bathroom' => 'Vanity, toilet, shower or tub, exhaust fan, and tile — fixtures, not the plumbing pipes.',
      'finishing' => 'Extra hardware, final cleaning, punch-list touch-ups, and optional building-permit allowance.',
  ];
  $brInfo = [
      'scope_preset' => 'Pick a starting point, then add or skip trades. Unused steps stay out of the wizard.',
      'area_sqft' => 'Finished floor area of the work. Leave later quantity fields blank to auto-fill from this. Enter 0 on a field to skip it. For an irregular room, import the Room Area drawing on the Drywall step so walls use the real perimeter instead of a square. Not used on New wall quotes — enter wall length instead.',
      'room_count' => 'Rooms as they will exist when the work is done, including new dividing walls — not the current open basement. Example: rec room + bedroom + bath = 3, even if it is open now. Enter new wall length on the Framing step (or Overview for New wall).',
      'owner_hourly_rate' => 'Your labour rate used in the live total. Helpers use the helper rate.',
      'helper_hourly_rate' => 'Hourly rate for each helper in the 2- and 3-person ETA scenarios.',
      'existing_notes' => 'Site notes for you (moisture, headroom, existing bath). Not printed on the client PDF unless you copy them into the description.',
      'include_demolition' => 'Adds tear-out labour and dumpster cost. Then check flooring, studs, drywall, and/or baseboard and enter the quantities. Uncheck if the space is already stripped.',
      'demo_include_flooring' => 'Pull up existing floor covering. Uncheck if the floor stays.',
      'demo_include_studs' => 'Tear out existing stud walls. Enter linear feet, or leave blank to use the new partition length from Framing.',
      'demo_include_drywall' => 'Strip existing drywall or ceiling. Uncheck if the concrete or joists are already exposed.',
      'demo_include_baseboard' => 'Pry off existing baseboard. Blank uses the room perimeter from the work area.',
      'demo_flooring_sf' => 'Existing floor covering to pull up. Blank uses the work area; enter 0 if none.',
      'demo_studs_lf' => 'Linear feet of existing wall to strip. Blank uses the new partition length from Framing; enter 0 if none.',
      'demo_drywall_sf' => 'Existing drywall or ceiling to strip. Enter 0 if none.',
      'demo_baseboard_lf' => 'Existing baseboard to remove. Blank uses the room perimeter; enter 0 if none.',
      'demo_bin_qty' => 'How many dumpsters / bins. Unit price comes from Price configuration.',
      'partition_lf' => 'Length of the new interior 2x4 wall in linear feet. Height uses ceiling height. Drywall, paint, and baseboard use both sides. Use 0 if the bathroom already has walls.',
      'stud_spacing_in' => 'On-centre stud spacing. 16″ o.c. is the Ontario default for basement partitions. 24″ uses fewer studs; 12″ is for tile-ready or extra-stiff walls.',
      'wall_include_door' => 'Adds one rough opening to frame and one interior door to hang. Change the door count later on the Doors step if needed.',
      'bulkhead_lf' => 'Length of boxed-in ducts or beams (soffits) to work around, not a full-height wall. Use 0 if none.',
      'header_qty' => 'Load-bearing or wide openings that need a header or beam, counted each.',
      'framing_door_openings' => 'Rough openings to frame in new or existing walls, before hanging the door.',
      'outlet_qty' => 'New receptacles to install (GFCI at wet locations). Count each device, not each gang box if you prefer. Typical bathroom: 1 at the sink, 2 on the wall. Change the count if this job differs. Used as electrician scope, not Zanini labour.',
      'switch_qty' => 'New wall switches, including 3-way and exhaust-fan switches. Typical bathroom: 1 for the light, 1 for the exhaust. Used as electrician scope, not Zanini labour.',
      'lighting_type' => 'Living-area lights in the finished basement rooms, not the bathroom. Bathroom fixtures and spots are chosen under Bathroom lighting. Used as electrician scope, not Zanini labour.',
      'fixture_qty' => 'Ceiling light fixtures to hang in the basement (not recessed pots, not the bathroom). Used as electrician scope, not Zanini labour.',
      'light_qty' => 'Recessed pot / spot lights in the basement rooms. Optional extra pots. Bathroom spots are on the Bathroom step. Used as electrician scope, not Zanini labour.',
      'detector_qty' => 'Interconnected smoke/CO units. Ontario typically wants one on this level. Used as electrician scope, not Zanini labour.',
      'panel_upgrade' => 'Note that the electrician should upgrade or add capacity in the electrical panel when the new circuits will not fit. Used as electrician scope, not Zanini labour.',
      'include_plumbing' => 'Turns on plumbing scope questions to send to a qualified plumber. Priced plumbing lines sit below.',
      'bath_rough_in' => 'New drain, vent, and hot/cold supply for a basement bathroom.',
      'sink_laundry' => 'Water and drain for a laundry tub or washer hookup, not a full bathroom.',
      'wet_bar' => 'Supply and drain for a bar or kitchenette sink — not a full kitchen.',
      'floor_drain' => 'A floor drain and trap (mechanical room, bath, or low point).',
      'wh_reloc' => 'Move the existing water heater so the new layout fits. Not a new heater unless you add it as extra material.',
      'insulate_walls' => 'Batts in the studs plus 6 mil vapour barrier on the warm side of exterior walls.',
      'insulation_wall_sf' => 'Wall area to insulate. Blank uses perimeter × ceiling height.',
      'insulate_ceiling' => 'Insulation above the finished ceiling (between joists) when the floor above is cold or noisy.',
      'insulation_ceiling_sf' => 'Ceiling area to insulate. Blank uses the work area.',
      'underslab' => 'Rigid foam under a new slab or on the existing concrete before flooring, where specified.',
      'underslab_sf' => 'Floor area of under-slab foam. Blank uses the work area.',
      'drywall_wall_sf' => 'Wall area to hang and finish. Blank uses the imported Room Area perimeter × ceiling height, or a square from the floor area if you have not imported a layout. On New wall, blank is wall length × ceiling height × both sides.',
      'drywall_ceiling_sf' => 'Ceiling area to hang. Blank follows Ceiling type (skipped for drop ceiling or skip). New wall quotes skip ceiling — the basement is already finished.',
      'drywall_corners' => 'Outside corners that need metal or paper bead.',
      'drywall_inside_corners' => 'Inside wall angles to tape and mud. These take extra finish time; they are skipped on Level 0 (hang only).',
      'drywall_openings' => 'Other openings (archways) to cut and bead, counted each. Door and window counts above are added for labour.',
      'drywall_door_openings' => 'Door rough openings. Each deducts 21 sq ft (3×7 ft) from wall drywall unless you type Opening area.',
      'drywall_window_openings' => 'Window openings. Each deducts 12 sq ft (4×3 ft) from wall drywall unless you type Opening area.',
      'drywall_opening_sf' => 'Total opening area to subtract from wall drywall. Blank uses 21 sq ft per door and 12 sq ft per window. Enter 0 if the wall area you typed is already net.',
      'drywall_sheet_size' => '4×12 sheets cover a typical 8–9 ft basement wall in one piece (fewer seams). 4×8 is two rows. Sheet count and waste follow this choice.',
      'room_area_import' => 'Saved Room Area drawing. Wall drywall uses the measured wall area (openings already deducted when the layout has doors/windows). Ceiling drywall uses the floor square footage. Corners and opening counts come from the outline.',
      'finish_level' => 'How far the drywall is finished. Level 0 hangs boards only (no tape). Level 4 is standard paint-ready for living space.',
      'ceiling_type' => 'How the ceiling is finished. Drop ceiling uses grid and tiles instead of hanging drywall.',
      'ceiling_sf' => 'Ceiling area. Blank uses the work area.',
      'flooring_type' => 'Finish floor product. Skip if the client is keeping existing floor or hiring flooring out.',
      'flooring_sf' => 'Floor area to install. Blank uses the work area.',
      'transition_qty' => 'Reducer or T-mould strips where the new floor meets another floor or a different height.',
      'subfloor_prep' => 'Level or patch the concrete/subfloor before underlayment and finish flooring.',
      'underlayment' => 'Foam or cork under LVP, laminate, or carpet. Not used with tile or epoxy.',
      'door_qty' => 'Prehung or slab interior doors to hang, including bath and closet doors.',
      'baseboard_lf' => 'Baseboard length. Blank uses the room perimeter from the work area, or both sides of the new wall on the wall preset.',
      'include_shoe' => 'Install shoe moulding using the same shared rate as Flooring and Trimming.',
      'shoe_lf' => 'Shoe length. Blank uses the baseboard length.',
      'casing_openings' => 'Door openings to case (both sides). Blank uses the interior door count.',
      'window_trim_qty' => 'Window openings to wrap with stool, apron, and casing.',
      'paint_wall_sf' => 'Wall area to paint. Blank follows drywall wall area (both sides of a new wall on the wall preset).',
      'paint_ceiling_sf' => 'Ceiling area to paint. Blank follows drywall ceiling unless you chose a drop ceiling. Hidden on New wall — no ceiling work.',
      'paint_trim_lf' => 'Base and casing to paint. Blank uses baseboard plus door casing length.',
      'paint_colors' => 'One color = walls and ceiling the same. Two colors packs paint separately and adds tape / cut-in labour plus extra drywall finish time.',
      'paint_coats' => 'Finish coats after primer. Two is typical on new drywall.',
      'include_bathroom' => 'Adds bathroom fixture install and tile. Bathroom-only quotes keep electrical and plumbing on this step. On a full basement, turn on bathroom rough-in under Plumbing if new drains are needed.',
      'bath_type' => 'Full bath includes shower or tub. Half bath is toilet and a vanity or pedestal sink.',
      'include_vanity' => 'Vanity cabinet, top, and faucet. Uncheck this to use a pedestal sink instead. How many sinks (1 or 2) is the field below.',
      'include_pedestal_sink' => 'Simple pedestal bathroom sink and faucet instead of a vanity cabinet. Faster to install and a lower fixture cost. How many sinks (1 or 2) is the field below.',
      'bath_sink_qty' => 'Two sinks means two vanities or two pedestals and two faucets — not one double-basin cabinet.',
      'include_toilet' => 'Toilet supply, set, and connect.',
      'include_shower' => 'Shower unit or valve and surround install. Tile area is the field below.',
      'include_tub' => 'Bathtub set and connect, instead of or in addition to a shower.',
      'include_exhaust_fan' => 'Bath fan, duct, and roof/soffit cap. Count a switch with the electrical switches.',
      'include_bath_fixture' => 'Ceiling or vanity light fixture in the bathroom. Check this and set how many. Scope for the electrician — priced on Electrical lines, not as Zanini labour.',
      'include_bath_spot' => 'Recessed pot / spot in the bathroom (for example a shower pot). You can use this together with a light fixture. Scope for the electrician — priced on Electrical lines.',
      'bath_fixture_qty' => 'How many bathroom light fixtures to hang.',
      'bath_spot_qty' => 'How many bathroom recessed pots / spot lights.',
      'bath_tile_sf' => 'Split into named areas (floor, shower walls, accent). Each can be total sq ft or length × width. Choose Tile (ceramic / porcelain, thinset) or Vinyl (LVP flooring prices). Shower walls stay tile; use Vinyl on the floor.',
      'hardware_sets' => 'Extra towel bars, paper holders, or similar, beyond what comes with the vanity.',
      'include_cleaning' => 'Final broom/vacuum, wipe, and dust so the space is handover-ready.',
      'include_touchups' => 'Small paint and caulk fixes on a punch list after the main work.',
      'include_building_permit' => 'Optional extra-charge line for a municipal building permit. Leave off if the client handles the permit or none is needed.',
      'building_permit_amount' => 'Permit allowance in CAD$. HST is added like other materials. Use 0 if you only want the reminder without a price.',
      'material_waste_percent' => 'Ordering buffer on bulk materials (drywall, lumber, paint, insulation, flooring, trim). Doors and toilets stay exact. Default 10%.',
      'client_provides_all' => 'Labour only — you do not supply materials. Totals drop material cost (HST on labour still applies as configured).',
  ];
@endphp

{{-- 1. Overview --}}
@php
  $step++;
  $areaLabels = [
      'full' => 'Total basement area (sq ft)',
      'bathroom' => 'Bathroom / work area (sq ft)',
      'wall' => 'Basement area (sq ft, optional)',
      'custom' => 'Work area (sq ft)',
  ];
  $presetHelp = [
      'full' => 'Walks every trade. Area auto-fills insulation, drywall, flooring, paint, and cleanup.',
      'bathroom' => 'Plumbing and electrical questions stay as qualified-trade scope, plus drywall, paint, fixtures, and cleanup in one bathroom flow — not whole-basement flooring or insulation.',
      'wall' => 'Enter wall length and whether it needs a door. Framing, drywall, doors / trim / baseboard, paint — both sides of the new wall.',
      'custom' => 'Only the trades you check below. Overview and Review always stay.',
  ];
  $wallPreset = $scopePreset === 'wall';
@endphp
<details class="br-acc admin-card is-active" data-br-step="overview" @if(empty($isEdit)) open @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Project Overview</summary>
  <div class="br-acc__body">
    <fieldset class="br-scope-presets">
      <legend>@include('admin.partials.info_tip', ['text' => $brInfo['scope_preset']]) What are you quoting?</legend>
      <div class="br-scope-presets__grid">
        @foreach (\App\Services\BasementQuotationCalculator::SCOPE_PRESETS as $presetKey => $presetLabel)
          <label class="br-scope-option">
            <input type="radio" class="br-preview" name="scope_preset" value="{{ $presetKey }}"
                   data-br-scope-preset
                   @checked($scopePreset === $presetKey)>
            <span class="br-scope-option__body">
              <strong>{{ $presetLabel }}</strong>
              <span class="admin-form-help">{{ $presetHelp[$presetKey] }}</span>
            </span>
          </label>
        @endforeach
      </div>
    </fieldset>

    <input type="hidden" name="enabled_sections[]" value="overview">
    <fieldset class="br-scope-trades" id="br-enabled-sections">
      <legend>Trades to include</legend>
      <div class="br-check-grid">
        @foreach (\App\Services\BasementQuotationCalculator::tradeSections() as $tradeKey => $tradeLabel)
          @include('admin.partials.checkbox', [
            'name' => 'enabled_sections[]',
            'value' => $tradeKey,
            'checked' => in_array($tradeKey, $enabledNow, true),
            'label' => $tradeLabel,
            'info' => $tradeInfo[$tradeKey] ?? null,
            'inputClass' => 'admin-checkbox__input br-preview',
            'class' => ($bathNestTrades && in_array($tradeKey, ['electrical', 'plumbing', 'framing'], true)) ? 'br-trade-nested' : '',
            'attrs' => ['data-br-enabled-section' => $tradeKey],
          ])
        @endforeach
      </div>
    </fieldset>

    <div class="br-overview-geom" data-br-overview-geom data-br-overview-mode="{{ $wallPreset ? 'wall' : 'area' }}">
      <div data-br-area-overview @if($wallPreset) hidden @endif>
        @include('admin.quotations.basement._qty_lw', [
          'name' => 'area_sqft',
          'id' => 'br-area',
          'label' => $areaLabels[$scopePreset] ?? $areaLabels['full'],
          'labelId' => 'br-area-label',
          'value' => $brVal('area_sqft', 0),
          'modeName' => 'area_entry_mode',
          'lengthName' => 'area_length_ft',
          'widthName' => 'area_width_ft',
          'mode' => $areaMode,
          'lengthValue' => $brQty('area_length_ft'),
          'widthValue' => $brQty('area_width_ft'),
          'info' => $brInfo['area_sqft'],
          'title' => 'Finished floor area of the work. Leave other quantity fields blank to auto-calculate from this.',
          'zeroIfIncomplete' => true,
          'clientFilled' => $brFilled('area_sqft'),
        ])
      </div>
      <div class="br-wall-overview" data-br-wall-overview @unless($wallPreset) hidden @endunless>
        <div id="br-wall-primary-slot" class="br-wall-primary-slot">
          @if($wallPreset)
            @include('admin.quotations.basement._wall_primary_fields')
          @endif
        </div>
      </div>
      @include('admin.quotations.basement._ceiling_height_field', [
        'value' => $brVal('ceiling_height_ft', 8),
        'filledKeys' => $filledKeys,
      ])
    </div>
    <div class="br-qty-grid">
      <div class="admin-form-group" data-br-hide-on-wall @if($wallPreset) hidden @endif>
        <label for="br-rooms">@include('admin.partials.info_tip', ['text' => $brInfo['room_count']]) @include('admin.quotations.basement._client_filled', ['key' => 'room_count', 'filledKeys' => $filledKeys]) Finished rooms / zones (after reno)</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-rooms" name="room_count"
               value="{{ $brVal('room_count', 1) }}"
               title="Count rooms as they will exist when the work is done, including new dividing walls. Not the current open basement.">
      </div>
      <div class="admin-form-group">
        <label for="br-owner-rate">@include('admin.partials.info_tip', ['text' => $brInfo['owner_hourly_rate']]) Owner hourly rate (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input br-preview" id="br-owner-rate" name="owner_hourly_rate"
               value="{{ number_format((float) $brVal('owner_hourly_rate', $settings->owner_hourly_rate), 2, '.', '') }}">
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._include_helper', [
          'checked' => $brOn('include_helper'),
          'previewClass' => 'br-preview',
          'rateId' => 'br-helper-rate',
          'rateValue' => number_format((float) $brVal('helper_hourly_rate', $settings->helper_hourly_rate), 2, '.', ''),
        ])
      </div>
      <div class="admin-form-group">
        <label for="br-material-waste">
          @include('admin.partials.info_tip', ['text' => $brInfo['material_waste_percent']])
          Material waste (%)
        </label>
        <input type="number" step="0.1" min="0" max="100" class="admin-input br-preview br-input--narrow" id="br-material-waste"
               name="material_waste_percent"
               value="{{ old('material_waste_percent', $inputs['material_waste_percent'] ?? 10) }}">
      </div>
    </div>
    <div class="admin-form-group br-notes-field">
      <label for="br-notes">@include('admin.partials.info_tip', ['text' => $brInfo['existing_notes']]) Existing condition notes</label>
      <textarea class="admin-input br-preview" id="br-notes" name="existing_notes" rows="2" maxlength="2000"
                placeholder="Moisture, headroom, existing bath">{{ $brVal('existing_notes') }}</textarea>
    </div>
  </div>
</details>

{{-- 2. Demolition --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="demolition" @unless(in_array('demolition', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Demolition &amp; Preparation</summary>
  <div class="br-acc__body">
    @include('admin.partials.checkbox', [
      'name' => 'include_demolition',
      'hiddenFallback' => true,
      'checked' => $brOn('include_demolition'),
      'label' => 'Include demolition &amp; debris',
      'info' => $brInfo['include_demolition'],
      'inputClass' => 'admin-checkbox__input br-preview',
      'attrs' => ['data-br-reveal' => 'br-demo-fields'],
      'clientFilled' => $brFilled('include_demolition'),
    ])
    <div id="br-demo-fields" @unless($brOn('include_demolition')) hidden @endunless>
      <p class="br-demo-scope-label">What to tear out</p>
      <div class="br-demo-types br-qty-grid--inset">
        <div class="br-toggle-qty">
          @include('admin.partials.checkbox', [
            'name' => 'demo_include_flooring',
            'hiddenFallback' => true,
            'checked' => $brOn('demo_include_flooring', true),
            'label' => 'Flooring',
            'info' => $brInfo['demo_include_flooring'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-reveal' => 'br-demo-floor-fields'],
            'clientFilled' => $brFilled('demo_include_flooring'),
          ])
          <div id="br-demo-floor-fields" class="br-toggle-qty__fields" @unless($brOn('demo_include_flooring', true)) hidden @endunless>
            @include('admin.quotations.basement._qty_lw', [
              'name' => 'demo_flooring_sf',
              'id' => 'br-demo-floor',
              'label' => 'Existing flooring removal (sq ft)',
              'value' => $brQty('demo_flooring_sf'),
              'modeName' => 'qty_dims[demo_flooring_sf][mode]',
              'lengthName' => 'qty_dims[demo_flooring_sf][length_ft]',
              'widthName' => 'qty_dims[demo_flooring_sf][width_ft]',
              'mode' => $qtyDim('demo_flooring_sf')['mode'],
              'lengthValue' => $qtyDim('demo_flooring_sf')['length'],
              'widthValue' => $qtyDim('demo_flooring_sf')['width'],
              'placeholder' => 'Auto',
              'info' => $brInfo['demo_flooring_sf'],
              'workKey' => 'remove_floor',
              'gearLabel' => 'Edit floor removal rates',
              'rate' => \App\Services\SharedWorkCatalog::rateLine('remove_floor'),
            ])
          </div>
        </div>
        <div class="br-toggle-qty">
          @include('admin.partials.checkbox', [
            'name' => 'demo_include_studs',
            'hiddenFallback' => true,
            'checked' => $brOn('demo_include_studs'),
            'label' => 'Studs',
            'info' => $brInfo['demo_include_studs'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-reveal' => 'br-demo-studs-fields'],
            'clientFilled' => $brFilled('demo_include_studs'),
          ])
          <div id="br-demo-studs-fields" class="br-toggle-qty__fields" @unless($brOn('demo_include_studs')) hidden @endunless>
            <div class="admin-form-group">
              <label for="br-demo-studs">@include('admin.partials.info_tip', ['text' => $brInfo['demo_studs_lf']]) Existing studs (lf)</label>
              <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-studs" name="demo_studs_lf"
                     value="{{ $brQty('demo_studs_lf') }}" placeholder="Auto: partition wall length">
            </div>
          </div>
        </div>
        <div class="br-toggle-qty">
          @include('admin.partials.checkbox', [
            'name' => 'demo_include_drywall',
            'hiddenFallback' => true,
            'checked' => $brOn('demo_include_drywall', true),
            'label' => 'Drywall',
            'info' => $brInfo['demo_include_drywall'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-reveal' => 'br-demo-dw-fields'],
            'clientFilled' => $brFilled('demo_include_drywall'),
          ])
          <div id="br-demo-dw-fields" class="br-toggle-qty__fields" @unless($brOn('demo_include_drywall', true)) hidden @endunless>
            @include('admin.quotations.basement._qty_lw', [
              'name' => 'demo_drywall_sf',
              'id' => 'br-demo-dw',
              'label' => 'Existing drywall / ceiling removal (sq ft)',
              'value' => $brQty('demo_drywall_sf'),
              'modeName' => 'qty_dims[demo_drywall_sf][mode]',
              'lengthName' => 'qty_dims[demo_drywall_sf][length_ft]',
              'widthName' => 'qty_dims[demo_drywall_sf][width_ft]',
              'mode' => $qtyDim('demo_drywall_sf')['mode'],
              'lengthValue' => $qtyDim('demo_drywall_sf')['length'],
              'widthValue' => $qtyDim('demo_drywall_sf')['width'],
              'placeholder' => '0 if none',
              'info' => $brInfo['demo_drywall_sf'],
            ])
          </div>
        </div>
        <div class="br-toggle-qty">
          @include('admin.partials.checkbox', [
            'name' => 'demo_include_baseboard',
            'hiddenFallback' => true,
            'checked' => $brOn('demo_include_baseboard'),
            'label' => 'Baseboard',
            'info' => $brInfo['demo_include_baseboard'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-reveal' => 'br-demo-base-fields'],
            'clientFilled' => $brFilled('demo_include_baseboard'),
          ])
          <div id="br-demo-base-fields" class="br-toggle-qty__fields" @unless($brOn('demo_include_baseboard')) hidden @endunless>
            <div class="admin-form-group">
              <label for="br-demo-base">@include('admin.partials.info_tip', ['text' => $brInfo['demo_baseboard_lf']]) Existing baseboard (lf)</label>
              <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-base" name="demo_baseboard_lf"
                     value="{{ $brQty('demo_baseboard_lf') }}" placeholder="Auto">
            </div>
          </div>
        </div>
      </div>
      <div class="admin-form-group br-demo-bins">
        <label for="br-bins">@include('admin.partials.info_tip', ['text' => $brInfo['demo_bin_qty']]) Debris bins</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-bins" name="demo_bin_qty"
               value="{{ $brVal('demo_bin_qty', 1) }}"
               title="Dumpster / bin count. Price comes from the basement materials database.">
      </div>
    </div>
  </div>
</details>

{{-- 3. Framing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="framing" @if($bathNestTrades || ! in_array('framing', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Framing</summary>
  <div class="br-acc__body">
    <div id="br-framing-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._framing_fields')
      @endunless
    </div>
  </div>
</details>

{{-- 4. Electrical --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="electrical" @if($bathNestTrades || ! in_array('electrical', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Electrical</summary>
  <div class="br-acc__body">
    <div id="br-electrical-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._electrical_fields')
      @endunless
    </div>
  </div>
</details>

{{-- 5. Plumbing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="plumbing" @if($bathNestTrades || ! in_array('plumbing', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Plumbing</summary>
  <div class="br-acc__body">
    <div id="br-plumbing-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._plumbing_fields')
      @endunless
    </div>
  </div>
</details>

{{-- 6. Insulation --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="insulation" @unless(in_array('insulation', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Insulation &amp; Vapor Barrier</summary>
  <div class="br-acc__body">
    <div class="br-toggle-qty-grid">
      <div class="br-toggle-qty">
        @include('admin.partials.checkbox', ['name' => 'insulate_walls', 'hiddenFallback' => true, 'checked' => $brOn('insulate_walls', true), 'label' => 'Wall insulation + vapour barrier', 'info' => $brInfo['insulate_walls'], 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-wall'], 'clientFilled' => $brFilled('insulate_walls')])
        <div id="br-ins-wall" class="br-toggle-qty__fields" @unless($brOn('insulate_walls', true)) hidden @endunless>
          @include('admin.quotations.basement._qty_lw', [
            'name' => 'insulation_wall_sf',
            'id' => 'br-ins-wall-sf',
            'label' => 'Wall insulation (sq ft)',
            'value' => $brQty('insulation_wall_sf'),
            'modeName' => 'qty_dims[insulation_wall_sf][mode]',
            'lengthName' => 'qty_dims[insulation_wall_sf][length_ft]',
            'widthName' => 'qty_dims[insulation_wall_sf][width_ft]',
            'mode' => $qtyDim('insulation_wall_sf')['mode'],
            'lengthValue' => $qtyDim('insulation_wall_sf')['length'],
            'widthValue' => $qtyDim('insulation_wall_sf')['width'],
            'placeholder' => 'Auto: perimeter × height',
            'info' => $brInfo['insulation_wall_sf'],
          ])
        </div>
      </div>
      <div class="br-toggle-qty">
        @include('admin.partials.checkbox', ['name' => 'insulate_ceiling', 'hiddenFallback' => true, 'checked' => $brOn('insulate_ceiling'), 'label' => 'Ceiling insulation', 'info' => $brInfo['insulate_ceiling'], 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-ceil'], 'clientFilled' => $brFilled('insulate_ceiling')])
        <div id="br-ins-ceil" class="br-toggle-qty__fields" @unless($brOn('insulate_ceiling')) hidden @endunless>
          @include('admin.quotations.basement._qty_lw', [
            'name' => 'insulation_ceiling_sf',
            'id' => 'br-ins-ceil-sf',
            'label' => 'Ceiling insulation (sq ft)',
            'value' => $brQty('insulation_ceiling_sf'),
            'modeName' => 'qty_dims[insulation_ceiling_sf][mode]',
            'lengthName' => 'qty_dims[insulation_ceiling_sf][length_ft]',
            'widthName' => 'qty_dims[insulation_ceiling_sf][width_ft]',
            'mode' => $qtyDim('insulation_ceiling_sf')['mode'],
            'lengthValue' => $qtyDim('insulation_ceiling_sf')['length'],
            'widthValue' => $qtyDim('insulation_ceiling_sf')['width'],
            'placeholder' => 'Auto',
            'info' => $brInfo['insulation_ceiling_sf'],
          ])
        </div>
      </div>
      <div class="br-toggle-qty">
        @include('admin.partials.checkbox', ['name' => 'underslab', 'hiddenFallback' => true, 'checked' => $brOn('underslab'), 'label' => 'Under-slab insulation', 'info' => $brInfo['underslab'], 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-slab'], 'clientFilled' => $brFilled('underslab')])
        <div id="br-ins-slab" class="br-toggle-qty__fields" @unless($brOn('underslab')) hidden @endunless>
          @include('admin.quotations.basement._qty_lw', [
            'name' => 'underslab_sf',
            'id' => 'br-ins-slab-sf',
            'label' => 'Under-slab insulation (sq ft)',
            'value' => $brQty('underslab_sf'),
            'modeName' => 'qty_dims[underslab_sf][mode]',
            'lengthName' => 'qty_dims[underslab_sf][length_ft]',
            'widthName' => 'qty_dims[underslab_sf][width_ft]',
            'mode' => $qtyDim('underslab_sf')['mode'],
            'lengthValue' => $qtyDim('underslab_sf')['length'],
            'widthValue' => $qtyDim('underslab_sf')['width'],
            'placeholder' => 'Auto',
            'info' => $brInfo['underslab_sf'],
          ])
        </div>
      </div>
    </div>
  </div>
</details>

{{-- 7. Ceiling (before drywall so hang area follows the ceiling type) --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="ceiling" @unless(in_array('ceiling', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Ceiling Options</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      <div class="admin-form-group">
        <label for="br-ceil-type">@include('admin.partials.info_tip', ['text' => $brInfo['ceiling_type']]) @include('admin.quotations.basement._client_filled', ['key' => 'ceiling_type', 'filledKeys' => $filledKeys]) Ceiling type</label>
        <select class="admin-input br-preview" id="br-ceil-type" name="ceiling_type">
          <option value="drywall" @selected($brVal('ceiling_type', 'drywall') === 'drywall')>Drywall ceiling</option>
          <option value="paint" @selected($brVal('ceiling_type') === 'paint')>Paint-grade drywall</option>
          <option value="drop" @selected($brVal('ceiling_type') === 'drop')>Drop ceiling</option>
          <option value="none" @selected($brVal('ceiling_type') === 'none')>Leave existing / skip</option>
        </select>
      </div>
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'ceiling_sf',
        'id' => 'br-ceil-sf',
        'label' => 'Ceiling area (sq ft)',
        'value' => $brQty('ceiling_sf'),
        'modeName' => 'qty_dims[ceiling_sf][mode]',
        'lengthName' => 'qty_dims[ceiling_sf][length_ft]',
        'widthName' => 'qty_dims[ceiling_sf][width_ft]',
        'mode' => $qtyDim('ceiling_sf')['mode'],
        'lengthValue' => $qtyDim('ceiling_sf')['length'],
        'widthValue' => $qtyDim('ceiling_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['ceiling_sf'],
      ])
    </div>
  </div>
</details>

{{-- 8. Drywall --}}
@php
  $step++;
  $sheetSizeNow = \App\Services\BasementQuotationCalculator::normalizeSheetSize($brVal('drywall_sheet_size', '4x8'));
@endphp
<details class="br-acc admin-card" data-br-step="drywall" @unless(in_array('drywall', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Drywall / Mud / Tape / Sand</summary>
  <div class="br-acc__body">
    <div class="br-layout-import" data-br-layout-import data-br-skip-on-wall @if($scopePreset === 'wall') hidden @endif>
      <input type="hidden" name="perimeter_lf" id="br-perimeter-lf" class="br-preview" value="{{ $layoutPerimeter }}">
      <input type="hidden" name="room_area_import_label" id="br-layout-import-label" value="{{ $layoutImportLabel }}">
      <div class="admin-form-group br-layout-import__field">
        <label for="br-layout-import-input">@include('admin.partials.info_tip', ['text' => $brInfo['room_area_import']]) Import layout</label>
        @include('admin.partials.search_select', [
          'id' => 'br-layout-import',
          'name' => 'room_area_import_id',
          'value' => $layoutImportId,
          'hideLabel' => true,
          'placeholder' => 'Search saved rooms…',
          'ariaLabel' => 'Import a Room Area layout',
          'searchUrl' => $layoutSearchUrl,
          'selectedOption' => $layoutSelected,
          'metaKeys' => ['meta'],
          'emptyMessage' => 'No matching Room Area layouts',
          'emptyQueryMessage' => 'Type a room name, or pick a saved layout',
          'listParent' => 'body',
          'wrapperClass' => 'br-layout-import__picker',
          'trailing' => '<a href="'.e(route('admin.utilities.room-area')).'" class="admin-btn admin-btn--ghost admin-btn--sm" target="_blank" rel="noopener" title="Open Room Area"><i class="fa fa-object-ungroup" aria-hidden="true"></i><span class="br-layout-import__open-label">Room Area</span></a>',
          'attrs' => ['data-br-layout-picker' => '1'],
        ])
      </div>
      <p class="admin-form-help br-layout-import__status" id="br-layout-import-status" @if($layoutPerimeter === '' || (float) $layoutPerimeter <= 0) hidden @endif>
        @if ($layoutPerimeter !== '' && (float) $layoutPerimeter > 0)
          Using measured walls: {{ rtrim(rtrim(number_format((float) $layoutPerimeter, 2, '.', ''), '0'), '.') }} lf perimeter.
        @endif
      </p>
    </div>
    <fieldset class="br-sheet-size">
      <legend>@include('admin.partials.info_tip', ['text' => $brInfo['drywall_sheet_size']]) Drywall sheet size</legend>
      <div class="br-sheet-size__grid">
        @foreach (\App\Services\BasementQuotationCalculator::SHEET_SIZES as $sizeKey => $sizeLabel)
          <label class="br-sheet-size__option">
            <input type="radio" class="br-preview" name="drywall_sheet_size" value="{{ $sizeKey }}"
                   @checked($sheetSizeNow === $sizeKey)>
            <span>{{ $sizeLabel }}</span>
          </label>
        @endforeach
      </div>
    </fieldset>
    <div class="br-qty-grid">
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'drywall_wall_sf',
        'id' => 'br-dw-wall',
        'label' => 'Wall drywall (sq ft)',
        'value' => $brQty('drywall_wall_sf'),
        'modeName' => 'qty_dims[drywall_wall_sf][mode]',
        'lengthName' => 'qty_dims[drywall_wall_sf][length_ft]',
        'widthName' => 'qty_dims[drywall_wall_sf][width_ft]',
        'mode' => $qtyDim('drywall_wall_sf')['mode'],
        'lengthValue' => $qtyDim('drywall_wall_sf')['length'],
        'widthValue' => $qtyDim('drywall_wall_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['drywall_wall_sf'],
      ])
      <div data-br-skip-on-wall @if($scopePreset === 'wall') hidden @endif>
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'drywall_ceiling_sf',
        'id' => 'br-dw-ceil',
        'label' => 'Ceiling drywall (sq ft)',
        'value' => $brQty('drywall_ceiling_sf'),
        'modeName' => 'qty_dims[drywall_ceiling_sf][mode]',
        'lengthName' => 'qty_dims[drywall_ceiling_sf][length_ft]',
        'widthName' => 'qty_dims[drywall_ceiling_sf][width_ft]',
        'mode' => $qtyDim('drywall_ceiling_sf')['mode'],
        'lengthValue' => $qtyDim('drywall_ceiling_sf')['length'],
        'widthValue' => $qtyDim('drywall_ceiling_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['drywall_ceiling_sf'],
      ])
      </div>
      <div class="admin-form-group">
        <label for="br-dw-door-open">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_door_openings']]) Door openings</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-door-open" name="drywall_door_openings"
               value="{{ $brVal('drywall_door_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-window-open">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_window_openings']]) Window openings</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-window-open" name="drywall_window_openings"
               value="{{ $brVal('drywall_window_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-open">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_openings']]) Other openings</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-open" name="drywall_openings"
               value="{{ $brVal('drywall_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-opening-sf">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_opening_sf']]) Opening area (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-opening-sf" name="drywall_opening_sf"
               value="{{ $brQty('drywall_opening_sf') }}" placeholder="Auto">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-corners">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_corners']]) Outside corners</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-corners" name="drywall_corners"
               value="{{ $brVal('drywall_corners', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-inside-corners">@include('admin.partials.info_tip', ['text' => $brInfo['drywall_inside_corners']]) Inside corners</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-inside-corners" name="drywall_inside_corners"
               value="{{ $brVal('drywall_inside_corners', 0) }}">
      </div>
      <div class="admin-form-group br-finish-field">
        <label for="br-finish">@include('admin.partials.info_tip', ['text' => $brInfo['finish_level']]) Level of finish</label>
        <select class="admin-input br-preview" id="br-finish" name="finish_level">
          @foreach (\App\Services\BasementQuotationCalculator::FINISH_LEVELS as $lvl => $lbl)
            <option value="{{ $lvl }}" @selected((int) $brVal('finish_level', 4) === $lvl)>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <p class="admin-form-help br-dw-net" id="br-dw-net-hint" hidden></p>
  </div>
</details>

{{-- 9. Flooring --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="flooring" @unless(in_array('flooring', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Flooring</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      <div class="admin-form-group">
        <label for="br-fl-type">@include('admin.partials.info_tip', ['text' => $brInfo['flooring_type']]) @include('admin.quotations.basement._client_filled', ['key' => 'flooring_type', 'filledKeys' => $filledKeys]) Final flooring type</label>
        <select class="admin-input br-preview" id="br-fl-type" name="flooring_type">
          <option value="lvp" @selected($brVal('flooring_type', 'lvp') === 'lvp')>LVP</option>
          <option value="laminate" @selected($brVal('flooring_type') === 'laminate')>Laminate</option>
          <option value="tile" @selected($brVal('flooring_type') === 'tile')>Tile</option>
          <option value="carpet" @selected($brVal('flooring_type') === 'carpet')>Carpet</option>
          <option value="epoxy" @selected($brVal('flooring_type') === 'epoxy')>Epoxy</option>
          <option value="none" @selected($brVal('flooring_type') === 'none')>Skip flooring</option>
        </select>
      </div>
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'flooring_sf',
        'id' => 'br-fl-sf',
        'label' => 'Flooring area (sq ft)',
        'value' => $brQty('flooring_sf'),
        'modeName' => 'qty_dims[flooring_sf][mode]',
        'lengthName' => 'qty_dims[flooring_sf][length_ft]',
        'widthName' => 'qty_dims[flooring_sf][width_ft]',
        'mode' => $qtyDim('flooring_sf')['mode'],
        'lengthValue' => $qtyDim('flooring_sf')['length'],
        'widthValue' => $qtyDim('flooring_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['flooring_sf'],
        'workKey' => 'flooring_install',
        'gearLabel' => 'Edit flooring install rates',
        'rate' => \App\Services\SharedWorkCatalog::rateLine('flooring_install'),
      ])
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-trans',
          'name' => 'transition_qty',
          'label' => 'Transitions',
          'value' => $brVal('transition_qty', 0),
          'info' => $brInfo['transition_qty'],
          'workKey' => 'transition',
          'gearLabel' => 'Edit transition rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('transition'),
          'inputClass' => 'br-preview',
          'step' => '1',
        ])
      </div>
    </div>
    <div class="br-check-grid br-check-grid--inset">
      @include('admin.partials.checkbox', ['name' => 'subfloor_prep', 'hiddenFallback' => true, 'checked' => $brOn('subfloor_prep'), 'label' => 'Subfloor preparation / leveling', 'info' => $brInfo['subfloor_prep'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('subfloor_prep')])
      @include('admin.partials.checkbox', ['name' => 'underlayment', 'hiddenFallback' => true, 'checked' => $brOn('underlayment', true), 'label' => 'Underlayment (LVP / laminate / carpet)', 'info' => $brInfo['underlayment'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('underlayment')])
    </div>
  </div>
</details>

{{-- 10. Doors & trim --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="doors_trim" @unless(in_array('doors_trim', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Doors, Trim &amp; Baseboards</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-doors',
          'name' => 'door_qty',
          'label' => 'Interior doors',
          'value' => $brVal('door_qty', 0),
          'info' => $brInfo['door_qty'],
          'workKey' => 'door_install',
          'gearLabel' => 'Edit interior door rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('door_install'),
          'inputClass' => 'br-preview',
          'step' => '1',
          'clientFilled' => $brFilled('door_qty'),
        ])
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-base',
          'name' => 'baseboard_lf',
          'label' => 'Baseboards (linear ft)',
          'value' => $brQty('baseboard_lf'),
          'info' => $brInfo['baseboard_lf'],
          'workKey' => 'baseboard',
          'gearLabel' => 'Edit baseboard rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('baseboard'),
          'inputClass' => 'br-preview',
          'placeholder' => 'Auto',
        ])
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-casing',
          'name' => 'casing_openings',
          'label' => 'Casing / door trim (openings)',
          'value' => $brVal('casing_openings', 0),
          'info' => $brInfo['casing_openings'],
          'workKey' => 'casing_opening',
          'gearLabel' => 'Edit casing rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('casing_opening'),
          'inputClass' => 'br-preview',
          'step' => '1',
          'placeholder' => 'Auto: door count',
        ])
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-windows',
          'name' => 'window_trim_qty',
          'label' => 'Window trim kits',
          'value' => $brVal('window_trim_qty', 0),
          'info' => $brInfo['window_trim_qty'],
          'workKey' => 'window_trim',
          'gearLabel' => 'Edit window trim rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('window_trim'),
          'inputClass' => 'br-preview',
          'step' => '1',
        ])
      </div>
    </div>
    <div class="br-check-grid br-check-grid--inset">
      @include('admin.partials.checkbox', [
        'name' => 'include_shoe',
        'hiddenFallback' => true,
        'checked' => $brOn('include_shoe'),
        'label' => 'Include shoe moulding',
        'info' => $brInfo['include_shoe'],
        'inputClass' => 'admin-checkbox__input br-preview',
        'attrs' => ['data-br-reveal' => 'br-shoe-fields'],
      ])
    </div>
    <div id="br-shoe-fields" class="br-qty-grid" @unless($brOn('include_shoe')) hidden @endunless>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-shoe',
          'name' => 'shoe_lf',
          'label' => 'Shoe moulding (linear ft)',
          'value' => $brQty('shoe_lf'),
          'info' => $brInfo['shoe_lf'],
          'workKey' => 'shoe_install',
          'gearLabel' => 'Edit shoe moulding rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('shoe_install').' · '.\App\Services\SharedWorkCatalog::rateLine('shoe_caulk_paint'),
          'inputClass' => 'br-preview',
          'placeholder' => 'Auto: same as baseboard',
        ])
      </div>
    </div>
  </div>
</details>

{{-- 11. Painting --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="painting" @unless(in_array('painting', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Painting</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'paint_wall_sf',
        'id' => 'br-p-wall',
        'label' => 'Walls (sq ft)',
        'value' => $brQty('paint_wall_sf'),
        'modeName' => 'qty_dims[paint_wall_sf][mode]',
        'lengthName' => 'qty_dims[paint_wall_sf][length_ft]',
        'widthName' => 'qty_dims[paint_wall_sf][width_ft]',
        'mode' => $qtyDim('paint_wall_sf')['mode'],
        'lengthValue' => $qtyDim('paint_wall_sf')['length'],
        'widthValue' => $qtyDim('paint_wall_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['paint_wall_sf'],
        'workKey' => 'paint_walls',
        'gearLabel' => 'Edit wall paint rates',
        'rate' => \App\Services\SharedWorkCatalog::rateLine('paint_walls'),
      ])
      <div data-br-skip-on-wall @if($scopePreset === 'wall') hidden @endif>
      @include('admin.quotations.basement._qty_lw', [
        'name' => 'paint_ceiling_sf',
        'id' => 'br-p-ceil',
        'label' => 'Ceiling (sq ft)',
        'value' => $brQty('paint_ceiling_sf'),
        'modeName' => 'qty_dims[paint_ceiling_sf][mode]',
        'lengthName' => 'qty_dims[paint_ceiling_sf][length_ft]',
        'widthName' => 'qty_dims[paint_ceiling_sf][width_ft]',
        'mode' => $qtyDim('paint_ceiling_sf')['mode'],
        'lengthValue' => $qtyDim('paint_ceiling_sf')['length'],
        'widthValue' => $qtyDim('paint_ceiling_sf')['width'],
        'placeholder' => 'Auto',
        'info' => $brInfo['paint_ceiling_sf'],
      ])
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-p-trim',
          'name' => 'paint_trim_lf',
          'label' => 'Trim (linear ft)',
          'value' => $brQty('paint_trim_lf'),
          'info' => $brInfo['paint_trim_lf'],
          'workKey' => 'paint_trim',
          'gearLabel' => 'Edit trim paint rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('paint_trim'),
          'inputClass' => 'br-preview',
          'placeholder' => 'Auto',
        ])
      </div>
      <div class="admin-form-group">
        <label for="br-colors">@include('admin.partials.info_tip', ['text' => $brInfo['paint_colors']]) @include('admin.quotations.basement._client_filled', ['key' => 'paint_colors', 'filledKeys' => $filledKeys]) Wall / ceiling colors</label>
        <select class="admin-input br-preview" id="br-colors" name="paint_colors">
          <option value="1" @selected((int) $brVal('paint_colors', 1) === 1)>One color</option>
          <option value="2" @selected((int) $brVal('paint_colors', 1) === 2)>Two colors</option>
        </select>
      </div>
      <div class="admin-form-group">
        <label for="br-coats">@include('admin.partials.info_tip', ['text' => $brInfo['paint_coats']]) Number of coats</label>
        <input type="number" step="1" min="1" class="admin-input br-preview" id="br-coats" name="paint_coats"
               value="{{ $brVal('paint_coats', 2) }}">
      </div>
    </div>
  </div>
</details>

{{-- 12. Bathroom --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="bathroom" @unless(in_array('bathroom', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Bathroom</summary>
  <div class="br-acc__body">
    <div class="br-bath-include" data-br-bath-include-wrap @if($bathNestTrades) hidden @endif>
      @include('admin.partials.checkbox', [
        'name' => 'include_bathroom',
        'hiddenFallback' => true,
        'checked' => $brOn('include_bathroom') || $bathNestTrades,
        'label' => 'Include a bathroom in this basement',
        'info' => $brInfo['include_bathroom'],
        'inputClass' => 'admin-checkbox__input br-preview',
        'attrs' => ['data-br-reveal' => 'br-bath-fields'],
        'clientFilled' => $brFilled('include_bathroom'),
      ])
    </div>
    <div id="br-bath-fields" class="br-bath-fields" @unless($brOn('include_bathroom') || $bathNestTrades) hidden @endunless>
      <div class="br-bath-block">
        <div class="br-bath-top">
          <div class="admin-form-group">
            <label for="br-bath-type">@include('admin.partials.info_tip', ['text' => $brInfo['bath_type']]) @include('admin.quotations.basement._client_filled', ['key' => 'bath_type', 'filledKeys' => $filledKeys]) Bathroom type</label>
            <select class="admin-input br-preview" id="br-bath-type" name="bath_type">
              <option value="full" @selected($brVal('bath_type', 'full') === 'full')>Full bath</option>
              <option value="half" @selected($brVal('bath_type') === 'half')>Half bath</option>
            </select>
          </div>
        </div>
        <div class="br-nested-trade" id="br-framing-slot-bath" @unless($bathNestTrades) hidden @endunless>
          <div class="br-tile-areas__head">
            <span class="br-tile-areas__title">Walls &amp; ducts</span>
          </div>
          @if($bathNestTrades)
            @include('admin.quotations.basement._framing_fields')
          @endif
        </div>
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title">Fixtures</span>
        </div>
        <div class="br-check-grid">
          @include('admin.partials.checkbox', [
            'name' => 'include_vanity',
            'id' => 'br-include-vanity',
            'hiddenFallback' => true,
            'checked' => $brOn('include_pedestal_sink') ? false : $brOn('include_vanity', true),
            'label' => 'Vanity + faucet',
            'info' => $brInfo['include_vanity'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-exclusive-group' => 'bath-sink'],
            'clientFilled' => $brFilled('include_vanity'),
          ])
          @include('admin.partials.checkbox', [
            'name' => 'include_pedestal_sink',
            'id' => 'br-include-pedestal',
            'hiddenFallback' => true,
            'checked' => $brOn('include_pedestal_sink'),
            'label' => 'Pedestal sink + faucet',
            'info' => $brInfo['include_pedestal_sink'],
            'inputClass' => 'admin-checkbox__input br-preview',
            'attrs' => ['data-br-exclusive-group' => 'bath-sink'],
            'clientFilled' => $brFilled('include_pedestal_sink'),
          ])
          @php
            $bathSinkQty = (int) $brVal('bath_sink_qty', 1) === 2 ? 2 : 1;
            $showBathSinkQty = $brOn('include_pedestal_sink') || $brOn('include_vanity', true);
            if (! $brOn('include_vanity', true) && ! $brOn('include_pedestal_sink')) {
                $showBathSinkQty = false;
            }
          @endphp
          <div class="admin-form-group" id="br-bath-sink-qty" @unless($showBathSinkQty) hidden @endunless>
            <label for="br-bath-sink-qty-select">@include('admin.partials.info_tip', ['text' => $brInfo['bath_sink_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'bath_sink_qty', 'filledKeys' => $filledKeys]) How many sinks?</label>
            <select class="admin-input br-preview" id="br-bath-sink-qty-select" name="bath_sink_qty">
              <option value="1" @selected($bathSinkQty === 1)>1</option>
              <option value="2" @selected($bathSinkQty === 2)>2</option>
            </select>
          </div>
          @include('admin.partials.checkbox', ['name' => 'include_toilet', 'hiddenFallback' => true, 'checked' => $brOn('include_toilet', true), 'label' => 'Toilet', 'info' => $brInfo['include_toilet'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('include_toilet')])
          @include('admin.partials.checkbox', ['name' => 'include_shower', 'hiddenFallback' => true, 'checked' => $brOn('include_shower', true), 'label' => 'Shower', 'info' => $brInfo['include_shower'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('include_shower')])
          @include('admin.partials.checkbox', ['name' => 'include_tub', 'hiddenFallback' => true, 'checked' => $brOn('include_tub'), 'label' => 'Tub', 'info' => $brInfo['include_tub'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('include_tub')])
          @include('admin.partials.checkbox', ['name' => 'include_exhaust_fan', 'hiddenFallback' => true, 'checked' => $brOn('include_exhaust_fan', true), 'label' => 'Exhaust fan', 'info' => $brInfo['include_exhaust_fan'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('include_exhaust_fan')])
        </div>
      </div>
      <div class="br-nested-trade" id="br-plumbing-slot-bath" @unless($bathNestTrades) hidden @endunless>
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title">Plumbing</span>
        </div>
        @if($bathNestTrades)
          @include('admin.quotations.basement._plumbing_fields')
        @endif
      </div>
      <div class="br-nested-trade" id="br-electrical-slot-bath" @unless($bathNestTrades) hidden @endunless>
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title">Electrical</span>
        </div>
        @if($bathNestTrades)
          @include('admin.quotations.basement._electrical_fields')
        @endif
      </div>
      <div class="br-bath-lighting">
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title">
            @include('admin.partials.info_tip', ['text' => 'Bathroom ceiling lights only — scope for the electrician, priced on Electrical lines. You can check a light fixture, a spot light, or both. Living-area lights stay on the Electrical step when quoting a full basement.'])
            Bathroom lighting
          </span>
        </div>
        <div class="br-bath-light-grid">
          <div class="br-bath-light-row">
            @include('admin.partials.checkbox', [
              'name' => 'include_bath_fixture',
              'id' => 'br-bath-include-fixture',
              'hiddenFallback' => true,
              'checked' => $brOn('include_bath_fixture'),
              'label' => 'Light fixture',
              'info' => $brInfo['include_bath_fixture'],
              'inputClass' => 'admin-checkbox__input br-preview',
              'attrs' => ['data-br-reveal' => 'br-bath-fixture-qty-wrap', 'data-br-bath-light' => 'fixture'],
              'clientFilled' => $brFilled('include_bath_fixture'),
            ])
            <div class="admin-form-group" id="br-bath-fixture-qty-wrap" @unless($brOn('include_bath_fixture')) hidden @endunless>
              <label for="br-bath-fixture-qty">@include('admin.partials.info_tip', ['text' => $brInfo['bath_fixture_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'bath_fixture_qty', 'filledKeys' => $filledKeys]) How many</label>
              <input type="number" step="1" min="0" class="admin-input br-preview" id="br-bath-fixture-qty" name="bath_fixture_qty"
                     value="{{ $brVal('bath_fixture_qty', 1) }}">
            </div>
          </div>
          <div class="br-bath-light-row">
            @include('admin.partials.checkbox', [
              'name' => 'include_bath_spot',
              'id' => 'br-bath-include-spot',
              'hiddenFallback' => true,
              'checked' => $brOn('include_bath_spot'),
              'label' => 'Spot light',
              'info' => $brInfo['include_bath_spot'],
              'inputClass' => 'admin-checkbox__input br-preview',
              'attrs' => ['data-br-reveal' => 'br-bath-spot-qty-wrap', 'data-br-bath-light' => 'spot'],
              'clientFilled' => $brFilled('include_bath_spot'),
            ])
            <div class="admin-form-group" id="br-bath-spot-qty-wrap" @unless($brOn('include_bath_spot')) hidden @endunless>
              <label for="br-bath-spot-qty">@include('admin.partials.info_tip', ['text' => $brInfo['bath_spot_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'bath_spot_qty', 'filledKeys' => $filledKeys]) How many</label>
              <input type="number" step="1" min="0" class="admin-input br-preview" id="br-bath-spot-qty" name="bath_spot_qty"
                     value="{{ $brVal('bath_spot_qty', 0) }}">
            </div>
          </div>
        </div>
      </div>
      @php
        $tileAreas = old('bath_tile_areas', $inputs['bath_tile_areas'] ?? null);
        if (! is_array($tileAreas) || $tileAreas === []) {
            $legacyTile = (float) old('bath_tile_sf', $inputs['bath_tile_sf'] ?? 0);
            $legacyDim = $qtyDim('bath_tile_sf');
            $tileAreas = $legacyTile > 0
                ? [[
                    'label' => 'Tile work',
                    'sqft' => $legacyTile,
                    'mode' => $legacyDim['mode'],
                    'length_ft' => $legacyDim['length'],
                    'width_ft' => $legacyDim['width'],
                    'finish' => 'tile',
                ]]
                : \App\Services\BasementQuotationCalculator::defaultBathTileAreas();
        }
        $tileTotal = 0.0;
        foreach ($tileAreas as $tileRow) {
            $tileTotal += (float) ($tileRow['sqft'] ?? 0);
        }
      @endphp
      <div class="br-tile-areas">
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title">
            @include('admin.partials.info_tip', ['text' => $brInfo['bath_tile_sf']])
            @include('admin.quotations.basement._client_filled', ['key' => 'bath_tile_areas', 'filledKeys' => $filledKeys])
            Tile / vinyl
          </span>
          <span class="admin-form-help" data-br-tile-total>Combined: {{ $tileTotal > 0 ? rtrim(rtrim(number_format($tileTotal, 2, '.', ''), '0'), '.').' sq ft' : '0 sq ft' }}</span>
        </div>
        <div id="br-tile-list">
          @foreach ($tileAreas as $tileIndex => $tileArea)
            @include('admin.quotations.basement._bath_tile_area_row', ['i' => $tileIndex, 'area' => is_array($tileArea) ? $tileArea : []])
          @endforeach
        </div>
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-tile-add" style="margin-top:8px;">
          <i class="fa fa-plus"></i> Add area
        </button>
        <template id="br-tile-template">
          @include('admin.quotations.basement._bath_tile_area_row', ['i' => '__I__', 'area' => \App\Services\BasementQuotationCalculator::blankBathTileArea()])
        </template>
      </div>
    </div>
  </div>
</details>

{{-- 13. Finishing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="finishing" @unless(in_array('finishing', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Final Details &amp; Cleanup</summary>
  <div class="br-acc__body">
    <div class="br-finishing">
      <div class="br-finishing-row">
        <div class="admin-form-group br-hw-field">
          <label for="br-hw">@include('admin.partials.info_tip', ['text' => $brInfo['hardware_sets']]) Extra hardware sets</label>
          <input type="number" step="1" min="0" class="admin-input br-preview br-input--hardware" id="br-hw" name="hardware_sets"
                 value="{{ $brVal('hardware_sets', 0) }}">
        </div>
      </div>
      <div class="br-finishing-row br-finishing-row--checks">
        @include('admin.partials.checkbox', ['name' => 'include_cleaning', 'hiddenFallback' => true, 'checked' => $brOn('include_cleaning', $scopePreset !== 'wall'), 'label' => 'Final cleaning', 'info' => $brInfo['include_cleaning'], 'inputClass' => 'admin-checkbox__input br-preview', 'class' => 'br-finishing-check', 'clientFilled' => $brFilled('include_cleaning')])
        @include('admin.partials.checkbox', ['name' => 'include_touchups', 'hiddenFallback' => true, 'checked' => $brOn('include_touchups', $scopePreset !== 'wall'), 'label' => 'Touch-ups / punch list', 'info' => $brInfo['include_touchups'], 'inputClass' => 'admin-checkbox__input br-preview', 'class' => 'br-finishing-check', 'clientFilled' => $brFilled('include_touchups')])
      </div>
      <div class="br-finishing-row br-finishing-row--permit">
        @include('admin.partials.checkbox', [
          'name' => 'include_building_permit',
          'hiddenFallback' => true,
          'checked' => $brOn('include_building_permit'),
          'label' => 'Building permit (extra charge)',
          'info' => $brInfo['include_building_permit'],
          'inputClass' => 'admin-checkbox__input br-preview',
          'class' => 'br-finishing-check',
          'attrs' => ['data-br-reveal' => 'br-permit-fields'],
        ])
        <div id="br-permit-fields" class="admin-form-group br-permit-amount" @unless($brOn('include_building_permit')) hidden @endunless>
          <label for="br-permit-amount">@include('admin.partials.info_tip', ['text' => $brInfo['building_permit_amount']]) Permit amount (CAD$)</label>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input br-preview br-input--permit" id="br-permit-amount" name="building_permit_amount"
                 value="{{ number_format((float) $brVal('building_permit_amount', 0), 2, '.', '') }}">
        </div>
      </div>
    </div>
  </div>
</details>
