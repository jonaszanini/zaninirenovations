@php
  use App\Support\QuotationSharedLabourRates;

  $sharedOwnerHourly = QuotationSharedLabourRates::ownerHourlyForNewQuotation();
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
  $wizardStep = 0;
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
  $splitCeiling = ! empty($isEdit) && \App\Services\BasementCeilingQuantities::usesSplit(is_array($inputs ?? null) ? $inputs : []);
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
      'area_sqft' => 'Finished floor area of the work. Leave later quantity fields blank to auto-fill from this. Enter 0 on a field to skip it. For an irregular room, use Import layout on Overview — it fills all available drawing quantities (floor, walls, ceiling, partitions, openings, trim, bulkheads). Not used on New wall quotes — enter wall length instead.',
      'room_count' => 'Rooms as they will exist when the work is done, including new dividing walls — not the current open basement. Example: rec room + bedroom + bath = 3, even if it is open now. Enter new wall length on the Framing step (or Overview for New wall).',
      'owner_hourly_rate' => 'Your labour rate used in the live total. Helpers use the helper rate.',
      'helper_hourly_rate' => 'Hourly rate for each helper in the 2- and 3-person ETA scenarios.',
      'existing_notes' => 'Site notes for you (moisture, headroom, existing bath). Not printed on the client PDF unless you copy them into the description.',
      'include_demolition' => 'Choose Yes or No. Yes adds tear-out labour and dumpster cost — then check flooring, studs, drywall, and/or baseboard and enter a positive quantity for each checked line (or bins only). No keeps those fields for later but charges nothing and leaves demolition out of generated client scope. Save as draft does not require a choice.',
      'demo_include_flooring' => 'Pull up existing floor covering. Uncheck if the floor stays.',
      'demo_include_studs' => 'Tear out existing stud walls. Enter linear feet, or leave blank to use the new partition length from Framing.',
      'demo_include_drywall' => 'Strip existing drywall or ceiling. Uncheck if the concrete or joists are already exposed.',
      'demo_include_baseboard' => 'Pry off existing baseboard. Blank uses the room perimeter from the work area.',
      'demo_flooring_sf' => 'Existing floor covering to pull up. Blank uses the work area. A checked Flooring line needs a positive quantity — uncheck Flooring instead of entering 0.',
      'demo_studs_lf' => 'Linear feet of existing wall to strip. Blank uses the new partition length from Framing. Uncheck Studs instead of entering 0.',
      'demo_drywall_sf' => 'Existing drywall or ceiling to strip. A checked Drywall line needs a positive quantity — uncheck Drywall instead of entering 0.',
      'demo_baseboard_lf' => 'Existing baseboard to remove. Blank uses the room perimeter. Uncheck Baseboard instead of entering 0.',
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
      'electrical_scope' => 'These answers are scope for a qualified electrician — they are not Zanini labour or materials. Type their quote as priced lines below.',
      'plumbing_scope' => 'These answers are scope for a qualified plumber — they are not Zanini labour or materials. Type their quote as priced lines below.',
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
      'drywall_ceiling_sf' => 'Saved hang area for this quote. Totals keep this figure — including leftover hang on drop or skip — until you confirm using Ceiling area.',
      'drywall_corners' => 'Outside corners that need metal or paper bead.',
      'drywall_inside_corners' => 'Inside wall angles to tape and mud. These take extra finish time; they are skipped on Level 0 (hang only).',
      'drywall_openings' => 'Other openings (archways) to cut and bead, counted each. Door and window counts above are added for labour.',
      'drywall_door_openings' => 'Door rough openings. Each deducts 21 sq ft (3×7 ft) from wall drywall unless you type Opening area.',
      'drywall_window_openings' => 'Window openings. Each deducts 12 sq ft (4×3 ft) from wall drywall unless you type Opening area.',
      'drywall_opening_sf' => 'Total opening area to subtract from wall drywall. Blank uses 21 sq ft per door and 12 sq ft per window. Enter 0 if the wall area you typed is already net.',
      'drywall_sheet_size' => '4×12 sheets cover a typical 8–9 ft basement wall in one piece (fewer seams). 4×8 is two rows. Sheet count and waste follow this choice.',
      'room_area_import' => 'Fills layout quantities from the saved drawing: floor, walls, ceiling, partitions, doors, windows, openings, baseboard, casing, bulkheads, corners, and painting takeoffs. Wall paint fills from walls and bulkheads marked Paint when the layout has paint choices; otherwise it fills with the same wall drywall area imported for Drywall (both sides of a new wall on the wall preset). Ceiling and trim paint fill with their layout lengths/areas. A mixed or zero paint takeoff that would replace a typed wall-paint quantity asks for confirmation first. Does not change rates, flooring type, ceiling type, paint colors/coats, or shared prices.',
      'finish_level' => 'How far the drywall is finished. Level 0 hangs boards only (no tape). Level 4 is standard paint-ready for living space.',
      'ceiling_type' => 'How the ceiling is finished. Drywall hang/mud and drop-ceiling tiles both use Ceiling area on this step. Leave existing / skip charges neither.',
      'ceiling_sf' => 'One ceiling area for this quote. Drywall hang, tape, and mud use it when the type is drywall. Drop-ceiling tiles use it when the type is drop. Blank uses the work area.',
      'ceiling_sf_legacy' => 'This saved quote has not switched to Ceiling area. Preview and resave keep the original hang (blank or missing follows work area; an explicit 0 stays 0) and the original blank-paint fallback until you confirm.',
      'flooring_type' => 'Finish floor product. Skip if the client is keeping existing floor or hiring flooring out.',
      'flooring_sf' => 'Floor area to install. Blank uses the work area.',
      'transition_qty' => 'Reducer or T-mould strips where the new floor meets another floor or a different height.',
      'subfloor_prep' => 'Level or patch the concrete/subfloor before underlayment and finish flooring.',
      'underlayment' => 'Foam or cork under LVP or laminate. Not used with tile or epoxy.',
      'door_qty' => 'Prehung or slab interior doors to hang, including bath and closet doors.',
      'baseboard_lf' => 'Baseboard length. Blank uses the room perimeter from the work area, or both sides of the new wall on the wall preset.',
      'include_shoe' => 'Install shoe moulding using the same shared rate as Flooring and Trimming.',
      'shoe_lf' => 'Shoe length. Blank uses the baseboard length.',
      'casing_openings' => 'Door openings to case (both sides). Blank uses the interior door count.',
      'window_trim_qty' => 'Window openings to wrap with stool, apron, and casing.',
      'paint_wall_sf' => 'Wall area to paint. Blank follows drywall wall area (both sides of a new wall on the wall preset). Import fills with Paint-marked wall and bulkhead area when the layout has paint choices; otherwise with the same imported drywall wall area.',
      'paint_ceiling_sf' => 'Ceiling area to paint. Blank follows Ceiling area unless you chose a drop ceiling. Hidden on New wall — no ceiling work.',
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
      'include_building_permit' => 'Optional extra-charge municipal building-permit fee (city hall disbursement). Not included by default. Leave off unless you are pricing that city fee on this quotation. The contractor fee does not apply to this amount.',
      'building_permit_amount' => 'Municipal / city-hall permit fee in CAD$ before HST (pass-through). Use 0 if you only want the reminder without a price. This city fee is not marked up with the contractor fee. Engineer signing is not this amount — that professional service belongs on BCIN drawings.',
      'include_bcin_drawings' => 'Optional extra-charge service for BCIN drawings. A BCIN qualified designer is not the same credential as a professional engineer. When a professional engineer must sign or stamp, that signing service is part of this extra. Not included by default.',
      'bcin_drawings_amount' => 'BCIN drawings extra-charge in CAD$ before HST. Treated as a subcontracted professional service for the contractor fee, including engineer signing when required. Not the municipal permit fee.',
      'flooring_waste_percent' => 'Ordering buffer for finish flooring only. Defaults: vinyl (LVP) 7%, wood (hardwood / engineered / laminate) 8%, tile 10%. Drywall sheets, batt bags, and studs at 16 inch centres still use the global material waste below. No carpet.',
      'material_waste_percent' => 'Ordering buffer on bulk materials other than finish flooring (drywall sheets, lumber, paint, insulation batts, trim). Doors and toilets stay exact. Default 10%. Finish flooring has its own waste on the Flooring step.',
      'client_provides_all' => 'Labour only — you do not supply materials. Totals drop material cost (HST on labour still applies as configured).',
  ];
@endphp

{{-- 1. Overview --}}
@php
  $wizardStep++;
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
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Project Overview</summary>
  <div class="br-acc__body">
    <fieldset class="br-scope-presets">
      <legend>
        <span class="admin-label-with-tip">
          <span>What are you quoting?</span>
          <x-admin.info-tip label="What are you quoting?">
            {{ $brInfo['scope_preset'] }}
            @foreach (\App\Services\BasementQuotationCalculator::SCOPE_PRESETS as $presetKey => $presetLabel)
              <br><strong>{{ $presetLabel }}:</strong> {{ $presetHelp[$presetKey] }}
            @endforeach
          </x-admin.info-tip>
        </span>
      </legend>
      <div class="br-scope-presets__grid">
        @foreach (\App\Services\BasementQuotationCalculator::SCOPE_PRESETS as $presetKey => $presetLabel)
          <label class="br-scope-option">
            <input type="radio" class="br-preview" name="scope_preset" value="{{ $presetKey }}"
                   data-br-scope-preset
                   @checked($scopePreset === $presetKey)>
            <span class="br-scope-option__body">
              <strong>{{ $presetLabel }}</strong>
            </span>
          </label>
        @endforeach
      </div>
    </fieldset>

    <input type="hidden" name="enabled_sections[]" value="overview">
    <fieldset class="br-scope-trades" id="br-enabled-sections">
      <legend>
        <span class="admin-label-with-tip">
          <span>Trades to include</span>
          <x-admin.info-tip label="Trades to include">Tick the trades this quote covers. Each selected trade gets its own step with quantities and priced lines.</x-admin.info-tip>
        </span>
      </legend>
      <div class="br-check-grid">
        @foreach (\App\Services\BasementQuotationCalculator::tradeSections() as $tradeKey => $tradeLabel)
          @include('admin.partials.checkbox', [
            'name' => 'enabled_sections[]',
            'checkboxValue' => $tradeKey,
            'checked' => in_array($tradeKey, $enabledNow, true),
            'label' => $tradeLabel,
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
    <div class="br-layout-import" data-br-layout-import data-br-skip-on-wall @if($scopePreset === 'wall') hidden @endif>
      <input type="hidden" name="perimeter_lf" id="br-perimeter-lf" class="br-preview" value="{{ $layoutPerimeter }}">
      <input type="hidden" name="room_area_import_label" id="br-layout-import-label" value="{{ $layoutImportLabel }}">
      <div class="admin-form-group br-layout-import__field">
        <span class="admin-label-with-tip">
  <label for="br-layout-import-input">Import layout</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['room_area_import'], 'tipLabel' => 'Import layout'])
</span>
        @include('admin.partials.search_select', [
          'id' => 'br-layout-import',
          'name' => 'room_area_import_id',
          'value' => $layoutImportId,
          'hideLabel' => true,
          'placeholder' => 'Search rooms…',
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
    <div class="br-qty-grid">
      <div class="admin-form-group" data-br-hide-on-wall @if($wallPreset) hidden @endif>
        @include('admin.partials.tipped_label', [
          'label' => 'Finished rooms',
          'for' => 'br-rooms',
          'tipText' => $brInfo['room_count'],
          'tipLabel' => 'Finished rooms',
          'prefixHtml' => view('admin.quotations.basement._client_filled', ['key' => 'room_count', 'filledKeys' => $filledKeys])->render(),
        ])
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-rooms" name="room_count"
               value="{{ $brVal('room_count', 1) }}"
               title="Count rooms as they will exist when the work is done, including new dividing walls. Not the current open basement.">
      </div>
      <div class="admin-form-group">
        @include('admin.partials.tipped_label', [
          'label' => 'Owner rate (CAD$)',
          'for' => 'br-owner-rate',
          'tipText' => $brInfo['owner_hourly_rate'],
          'tipLabel' => 'Owner rate (CAD$)',
        ])
        <input type="text" inputmode="decimal" autocomplete="off"
               class="admin-input cp-money-input br-preview" id="br-owner-rate" name="owner_hourly_rate"
               value="{{ number_format((float) $brVal('owner_hourly_rate', $sharedOwnerHourly), 2, '.', '') }}" data-cp-money-entry="direct">
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
        @include('admin.partials.tipped_label', [
          'label' => 'Material waste (%)',
          'for' => 'br-material-waste',
          'tipText' => $brInfo['material_waste_percent'],
          'tipLabel' => 'Material waste (%)',
        ])
        <input type="number" step="0.1" min="0" max="100" class="admin-input br-preview br-input--narrow" id="br-material-waste"
               name="material_waste_percent"
               value="{{ old('material_waste_percent', $inputs['material_waste_percent'] ?? 10) }}">
      </div>
    </div>
    <div class="admin-form-group br-notes-field">
      <span class="admin-label-with-tip">
  <label for="br-notes">Existing condition notes</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['existing_notes'], 'tipLabel' => 'Existing condition notes'])
</span>
      <textarea class="admin-input br-preview" id="br-notes" name="existing_notes" rows="2" maxlength="2000"
                placeholder="Moisture, headroom, existing bath">{{ $brVal('existing_notes') }}</textarea>
    </div>
  </div>
</details>

{{-- 2. Demolition --}}
@php
  $wizardStep++;
  $demoChoice = null;
  $oldDemo = old('include_demolition');
  if ($oldDemo !== null && $oldDemo !== '') {
      $demoChoice = $oldDemo === '1' || $oldDemo === 1 || $oldDemo === true || $oldDemo === 'true' || $oldDemo === 'on';
  } elseif (! empty($isEdit) && array_key_exists('include_demolition', $inputs)) {
      $demoChoice = (bool) $inputs['include_demolition'];
  }
  $demoChoiceRequired = in_array('demolition', $enabledNow, true);
@endphp
<details class="br-acc admin-card" data-br-step="demolition" data-qh-step="2" @unless(in_array('demolition', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary">
    <span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Demolition &amp; Preparation
    @include('admin.partials.quotation_price_config_gear', [
      'url' => route('admin.quotations.basement.price-configuration', request()->filled('project') ? ['project' => (int) request('project')] : []),
    ])
  </summary>
  <div class="br-acc__body">
    <div class="admin-label-with-tip" style="margin-bottom:8px;">
      <span>Include demolition &amp; debris? <span class="cp-required">*</span></span>
      @include('admin.partials.info_tip', [
        'tipText' => $brInfo['include_demolition'],
        'tipLabel' => 'Include demolition & debris?',
      ])
      @if ($brFilled('include_demolition'))
        <span class="admin-filled-from-client" title="Filled from client form">Filled</span>
      @endif
    </div>
    <fieldset class="q-commute-choice" role="radiogroup" aria-label="Include demolition and debris">
      <legend class="util-sr-only">Include demolition and debris on this quotation?</legend>
      <label class="q-commute-choice__option{{ $demoChoice === true ? ' is-selected' : '' }}">
        <input type="radio" name="include_demolition" value="1" class="q-commute-choice__input br-preview"
               data-br-demo-choice="1"
               @checked($demoChoice === true)>
        <span class="q-commute-choice__text">Yes</span>
      </label>
      <label class="q-commute-choice__option{{ $demoChoice === false ? ' is-selected' : '' }}">
        <input type="radio" name="include_demolition" value="0" class="q-commute-choice__input br-preview"
               data-br-demo-choice="0"
               @checked($demoChoice === false)>
        <span class="q-commute-choice__text">No</span>
      </label>
    </fieldset>
    @error('include_demolition')
      <p class="admin-form-error" style="margin-top:8px;">{{ $message }}</p>
    @enderror
    <div id="br-demo-fields" @unless($demoChoice === true) hidden @endunless>
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
              <span class="admin-label-with-tip">
  <label for="br-demo-studs">Existing studs (lf)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['demo_studs_lf'], 'tipLabel' => 'Existing studs (lf)'])
</span>
              <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-studs" name="demo_studs_lf"
                     value="{{ $brQty('demo_studs_lf') }}" placeholder="Auto" @if ($errors->has('demo_studs_lf')) aria-invalid="true" aria-describedby="br-demo-studs-error" @endif>
              @error('demo_studs_lf')
                <p class="admin-form-error" id="br-demo-studs-error" role="alert">{{ $message }}</p>
              @enderror
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
              'placeholder' => 'Quantity if checked',
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
              <span class="admin-label-with-tip">
  <label for="br-demo-base">Existing baseboard (lf)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['demo_baseboard_lf'], 'tipLabel' => 'Existing baseboard (lf)'])
</span>
              <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-base" name="demo_baseboard_lf"
                     value="{{ $brQty('demo_baseboard_lf') }}" placeholder="Auto" @if ($errors->has('demo_baseboard_lf')) aria-invalid="true" aria-describedby="br-demo-base-error" @endif>
              @error('demo_baseboard_lf')
                <p class="admin-form-error" id="br-demo-base-error" role="alert">{{ $message }}</p>
              @enderror
            </div>
          </div>
        </div>
      </div>
      <div class="admin-form-group br-demo-bins">
        <span class="admin-label-with-tip">
  <label for="br-bins">Debris bins</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['demo_bin_qty'], 'tipLabel' => 'Debris bins'])
</span>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-bins" name="demo_bin_qty"
               value="{{ $brVal('demo_bin_qty', 1) }}"
               title="Dumpster / bin count. Price comes from the basement materials database.">
      </div>
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'demolition'])
  </div>
</details>

{{-- 3. Framing --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="framing" @if($bathNestTrades || ! in_array('framing', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Framing</summary>
  <div class="br-acc__body">
    <div id="br-framing-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._framing_fields')
      @endunless
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'framing'])
  </div>
</details>

{{-- 4. Electrical --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="electrical" @if($bathNestTrades || ! in_array('electrical', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Electrical</summary>
  <div class="br-acc__body">
    <div id="br-electrical-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._electrical_fields')
      @endunless
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'electrical'])
  </div>
</details>

{{-- 5. Plumbing --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="plumbing" @if($bathNestTrades || ! in_array('plumbing', $enabledNow, true)) hidden @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Plumbing</summary>
  <div class="br-acc__body">
    <div id="br-plumbing-slot-step">
      @unless($bathNestTrades)
        @include('admin.quotations.basement._plumbing_fields')
      @endunless
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'plumbing'])
  </div>
</details>

{{-- 6. Insulation --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="insulation" @unless(in_array('insulation', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Insulation &amp; Vapor Barrier</summary>
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
            'placeholder' => 'Auto',
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
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'insulation'])
  </div>
</details>

{{-- 7. Ceiling (before drywall so hang area follows the ceiling type) --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="ceiling" @unless(in_array('ceiling', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Ceiling Options</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      <div class="admin-form-group br-field--wide">
        <span class="admin-label-with-tip">
  <label for="br-ceil-type">@include('admin.quotations.basement._client_filled', ['key' => 'ceiling_type', 'filledKeys' => $filledKeys]) Ceiling type</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['ceiling_type'], 'tipLabel' => 'Ceiling type'])
</span>
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
        'info' => $splitCeiling ? $brInfo['ceiling_sf_legacy'] : $brInfo['ceiling_sf'],
      ])
      <input type="hidden" name="ceiling_hang_unified" id="br-ceiling-hang-unified" value="{{ ! empty($isEdit) ? (\App\Services\BasementCeilingQuantities::hasAdoptedUnifiedHang(is_array($inputs ?? null) ? $inputs : []) ? '1' : '0') : '1' }}">
      @if($splitCeiling)
        <input type="hidden" name="drywall_ceiling_sf" id="br-dw-ceil" value="{{ $brQty('drywall_ceiling_sf') }}">
        <div class="admin-form-help" data-br-legacy-ceiling-notice>
          @php
            $savedHangQty = $brQty('drywall_ceiling_sf');
            $savedHangLabel = $savedHangQty === '' || $savedHangQty === null
              ? 'follows work area'
              : ((string) $savedHangQty).' sq ft';
          @endphp
          Saved hang ({{ $savedHangLabel }}) stays on preview and resave. Blank paint keeps the original hang-or-work-area fallback.
          <button type="button" class="admin-btn admin-btn--small" data-br-adopt-ceiling-hang
                  data-br-adopt-message="Use Ceiling area for hang, tape, mud, and blank paint from now on? Drop, skip, and ceiling-off will stop charging the saved hang. Totals will change.">
            Use Ceiling area
          </button>
        </div>
      @endif
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'ceiling'])
  </div>
</details>

{{-- 8. Drywall --}}
@php
  $wizardStep++;
  $sheetSizeNow = \App\Services\BasementQuotationCalculator::normalizeSheetSize($brVal('drywall_sheet_size', '4x8'));
@endphp
<details class="br-acc admin-card" data-br-step="drywall" @unless(in_array('drywall', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Drywall / Mud / Tape / Sand</summary>
  <div class="br-acc__body">
    <fieldset class="br-sheet-size">
      <legend>
        <span class="admin-label-with-tip">
          <span>Drywall sheet size</span>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_sheet_size'], 'tipLabel' => 'Drywall sheet size'])
        </span>
      </legend>
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
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="br-dw-door-open">Door openings</label>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_door_openings'], 'tipLabel' => 'Door openings'])
        </span>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-door-open" name="drywall_door_openings"
               value="{{ $brVal('drywall_door_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="br-dw-window-open">Window openings</label>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_window_openings'], 'tipLabel' => 'Window openings'])
        </span>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-window-open" name="drywall_window_openings"
               value="{{ $brVal('drywall_window_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="br-dw-open">Other openings</label>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_openings'], 'tipLabel' => 'Other openings'])
        </span>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-open" name="drywall_openings"
               value="{{ $brVal('drywall_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
  <label for="br-dw-opening-sf">Opening area</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_opening_sf'], 'tipLabel' => 'Opening area'])
</span>
        <input type="number" step="0.01" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-opening-sf" name="drywall_opening_sf"
               value="{{ $brQty('drywall_opening_sf') }}" placeholder="Auto">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-corners">Outside corners</label>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-corners" name="drywall_corners"
               value="{{ $brVal('drywall_corners', 0) }}">
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="br-dw-inside-corners">Inside corners</label>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['drywall_inside_corners'], 'tipLabel' => 'Inside corners'])
        </span>
        <input type="number" step="1" min="0" class="admin-input br-preview br-input--narrow" id="br-dw-inside-corners" name="drywall_inside_corners"
               value="{{ $brVal('drywall_inside_corners', 0) }}">
      </div>
      <div class="admin-form-group br-finish-field">
        <span class="admin-label-with-tip">
  <label for="br-finish">Level of finish</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['finish_level'], 'tipLabel' => 'Level of finish'])
</span>
        <select class="admin-input br-preview" id="br-finish" name="finish_level">
          @foreach (\App\Services\BasementQuotationCalculator::FINISH_LEVELS as $lvl => $lbl)
            <option value="{{ $lvl }}" @selected((int) $brVal('finish_level', 4) === $lvl)>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <p class="admin-form-help br-dw-net" id="br-dw-net-hint" hidden></p>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'drywall'])
  </div>
</details>

{{-- 9. Flooring --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="flooring" @unless(in_array('flooring', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Flooring</summary>
  <div class="br-acc__body">
    <div class="br-qty-grid">
      <div class="admin-form-group br-field--wide">
        <span class="admin-label-with-tip">
  <label for="br-fl-type">@include('admin.quotations.basement._client_filled', ['key' => 'flooring_type', 'filledKeys' => $filledKeys]) Final flooring type</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['flooring_type'], 'tipLabel' => 'Final flooring type'])
</span>
        <select class="admin-input br-preview" id="br-fl-type" name="flooring_type" data-br-flooring-type>
          @php
            $flCurrent = (string) $brVal('flooring_type', 'lvp');
            if (! array_key_exists($flCurrent, \App\Services\BasementQuotationCalculator::FLOORING_TYPES)) {
              $flCurrent = 'lvp';
            }
            $floorWasteDefault = \App\Services\BasementQuotationCalculator::flooringWasteDefault($flCurrent);
            if (! empty($isEdit) && is_array($inputs) && ! array_key_exists('flooring_waste_percent', $inputs)) {
                $floorWasteDefault = (float) ($inputs['material_waste_percent'] ?? 10);
            }
          @endphp
          @foreach (\App\Services\BasementQuotationCalculator::FLOORING_TYPES as $flKey => $flLabel)
            <option value="{{ $flKey }}" @selected($flCurrent === $flKey)
                    data-br-floor-waste="{{ \App\Services\BasementQuotationCalculator::flooringWasteDefault($flKey) }}">{{ $flLabel }}</option>
          @endforeach
        </select>
        <p class="br-hardwood-caution" id="br-hardwood-caution" @if($flCurrent !== 'hardwood') hidden @endif>
          <span class="admin-label-with-tip">
            <span>Basement humidity caution for solid hardwood</span>
            @include('admin.partials.info_tip', [
              'tipText' => 'Solid hardwood is more sensitive to slab moisture and seasonal humidity than vinyl plank, engineered wood, laminate, or tile. Choosing hardwood here does not change the quoted product specification; confirm moisture conditions before install.',
              'tipLabel' => 'Basement humidity caution for solid hardwood',
            ])
          </span>
        </p>
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
          <label for="br-fl-waste">Flooring waste (%)</label>
          @include('admin.partials.info_tip', ['tipText' => $brInfo['flooring_waste_percent'], 'tipLabel' => 'Flooring waste (%)'])
        </span>
        <input type="number" min="0" max="100" step="0.1" class="admin-input br-preview" id="br-fl-waste"
               name="flooring_waste_percent" value="{{ old('flooring_waste_percent', $inputs['flooring_waste_percent'] ?? $floorWasteDefault) }}">
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
      @include('admin.partials.checkbox', ['name' => 'underlayment', 'hiddenFallback' => true, 'checked' => $brOn('underlayment', true), 'label' => 'Underlayment (LVP / laminate)', 'info' => $brInfo['underlayment'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => $brFilled('underlayment')])
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'flooring'])
  </div>
</details>

{{-- 10. Doors & trim --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="doors_trim" @unless(in_array('doors_trim', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Doors, Trim &amp; Baseboards</summary>
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
          'label' => 'Baseboards (lin. ft)',
          'value' => $brQty('baseboard_lf'),
          'info' => $brInfo['baseboard_lf'],
          'workKey' => 'baseboard',
          'gearLabel' => 'Edit baseboard rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('baseboard'),
          'inputClass' => 'br-preview',
          'step' => '0.01',
          'placeholder' => 'Auto',
        ])
      </div>
      <div class="admin-form-group">
        @include('admin.quotations._shared_qty_field', [
          'id' => 'br-casing',
          'name' => 'casing_openings',
          'label' => 'Casing openings',
          'value' => $brVal('casing_openings', 0),
          'info' => $brInfo['casing_openings'],
          'workKey' => 'casing_opening',
          'gearLabel' => 'Edit casing rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('casing_opening'),
          'inputClass' => 'br-preview',
          'step' => '1',
          'placeholder' => 'Auto',
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
          'label' => 'Shoe (lin. ft)',
          'value' => $brQty('shoe_lf'),
          'info' => $brInfo['shoe_lf'],
          'workKey' => 'shoe_install',
          'gearLabel' => 'Edit shoe moulding rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('shoe_install').' · '.\App\Services\SharedWorkCatalog::rateLine('shoe_caulk_paint'),
          'inputClass' => 'br-preview',
          'step' => '0.01',
          'placeholder' => 'Auto',
        ])
      </div>
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'doors_trim'])
  </div>
</details>

{{-- 11. Painting --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="painting" @unless(in_array('painting', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Painting</summary>
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
      <div class="br-field--wide" data-br-skip-on-wall @if($scopePreset === 'wall') hidden @endif>
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
          'label' => 'Trim (lin. ft)',
          'value' => $brQty('paint_trim_lf'),
          'info' => $brInfo['paint_trim_lf'],
          'workKey' => 'paint_trim',
          'gearLabel' => 'Edit trim paint rates',
          'rate' => \App\Services\SharedWorkCatalog::rateLine('paint_trim'),
          'inputClass' => 'br-preview',
          'step' => '0.01',
          'placeholder' => 'Auto',
        ])
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
  <label for="br-colors">@include('admin.quotations.basement._client_filled', ['key' => 'paint_colors', 'filledKeys' => $filledKeys]) Wall / ceiling colors</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['paint_colors'], 'tipLabel' => 'Wall / ceiling colors'])
</span>
        <select class="admin-input br-preview" id="br-colors" name="paint_colors">
          <option value="1" @selected((int) $brVal('paint_colors', 1) === 1)>One color</option>
          <option value="2" @selected((int) $brVal('paint_colors', 1) === 2)>Two colors</option>
        </select>
      </div>
      <div class="admin-form-group">
        <span class="admin-label-with-tip">
  <label for="br-coats">Number of coats</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['paint_coats'], 'tipLabel' => 'Number of coats'])
</span>
        <input type="number" step="1" min="1" class="admin-input br-preview" id="br-coats" name="paint_coats"
               value="{{ $brVal('paint_coats', 2) }}">
      </div>
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'painting'])
  </div>
</details>

{{-- 12. Bathroom --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="bathroom" @unless(in_array('bathroom', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Bathroom</summary>
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
            <span class="admin-label-with-tip">
  <label for="br-bath-type">@include('admin.quotations.basement._client_filled', ['key' => 'bath_type', 'filledKeys' => $filledKeys]) Bathroom type</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['bath_type'], 'tipLabel' => 'Bathroom type'])
</span>
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
            <span class="admin-label-with-tip">
  <label for="br-bath-sink-qty-select">@include('admin.quotations.basement._client_filled', ['key' => 'bath_sink_qty', 'filledKeys' => $filledKeys]) How many sinks?</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['bath_sink_qty'], 'tipLabel' => 'How many sinks?'])
</span>
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
        @if($bathNestTrades)
          @include('admin.quotations.basement._plumbing_fields')
        @endif
      </div>
      <div class="br-nested-trade" id="br-electrical-slot-bath" @unless($bathNestTrades) hidden @endunless>
        @if($bathNestTrades)
          @include('admin.quotations.basement._electrical_fields')
        @endif
      </div>
      <div class="br-bath-lighting">
        <div class="br-tile-areas__head">
          <span class="br-tile-areas__title admin-label-with-tip">
            <span>Bathroom lighting</span>
            @include('admin.partials.info_tip', ['tipText' => 'Bathroom ceiling lights only — scope for the electrician, priced on Electrical lines. You can check a light fixture, a spot light, or both. Living-area lights stay on the Electrical step when quoting a full basement.', 'tipLabel' => 'Bathroom lighting'])
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
              <label for="br-bath-fixture-qty">@include('admin.quotations.basement._client_filled', ['key' => 'bath_fixture_qty', 'filledKeys' => $filledKeys]) How many</label>
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
              <label for="br-bath-spot-qty">@include('admin.quotations.basement._client_filled', ['key' => 'bath_spot_qty', 'filledKeys' => $filledKeys]) How many</label>
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
          <span class="br-tile-areas__title admin-label-with-tip">
            @include('admin.quotations.basement._client_filled', ['key' => 'bath_tile_areas', 'filledKeys' => $filledKeys])
            Tile / vinyl
            @include('admin.partials.info_tip', ['tipText' => $brInfo['bath_tile_sf'], 'tipLabel' => 'Tile / vinyl'])
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
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'bathroom'])
  </div>
</details>

{{-- 13. Finishing --}}
@php $wizardStep++; @endphp
<details class="br-acc admin-card" data-br-step="finishing" @unless(in_array('finishing', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $wizardStep }} of {{ $enabledCount }}</span> Final Details &amp; Cleanup</summary>
  <div class="br-acc__body">
    <div class="br-finishing">
      <div class="br-finishing-row">
        <div class="admin-form-group br-hw-field">
          <span class="admin-label-with-tip">
  <label for="br-hw">Extra hardware sets</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['hardware_sets'], 'tipLabel' => 'Extra hardware sets'])
</span>
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
          'label' => 'City building permit — optional extra-charge service',
          'info' => $brInfo['include_building_permit'],
          'inputClass' => 'admin-checkbox__input br-preview',
          'class' => 'br-finishing-check',
          'attrs' => ['data-br-reveal' => 'br-permit-fields'],
        ])
        <div id="br-permit-fields" class="admin-form-group br-permit-amount" @unless($brOn('include_building_permit')) hidden @endunless>
          <span class="admin-label-with-tip">
  <label for="br-permit-amount">City permit amount (CAD$)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['building_permit_amount'], 'tipLabel' => 'City permit amount (CAD$)'])
</span>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input br-preview br-input--permit" id="br-permit-amount" name="building_permit_amount"
                 value="{{ number_format((float) $brVal('building_permit_amount', 0), 2, '.', '') }}" data-cp-money-entry="direct">
        </div>
      </div>
      <div class="br-finishing-row br-finishing-row--permit">
        @include('admin.partials.checkbox', [
          'name' => 'include_bcin_drawings',
          'hiddenFallback' => true,
          'checked' => $brOn('include_bcin_drawings'),
          'label' => 'BCIN drawings — optional extra-charge service',
          'info' => $brInfo['include_bcin_drawings'],
          'inputClass' => 'admin-checkbox__input br-preview',
          'class' => 'br-finishing-check',
          'attrs' => ['data-br-reveal' => 'br-bcin-fields'],
        ])
        <div id="br-bcin-fields" class="admin-form-group br-permit-amount" @unless($brOn('include_bcin_drawings')) hidden @endunless>
          <span class="admin-label-with-tip">
  <label for="br-bcin-amount">BCIN drawings amount (CAD$)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['bcin_drawings_amount'], 'tipLabel' => 'BCIN drawings amount (CAD$)'])
</span>
          <input type="text" inputmode="decimal" autocomplete="off"
                 class="admin-input cp-money-input br-preview br-input--permit" id="br-bcin-amount" name="bcin_drawings_amount"
                 value="{{ number_format((float) $brVal('bcin_drawings_amount', 0), 2, '.', '') }}" data-cp-money-entry="direct">
        </div>
      </div>
    </div>
    @include('admin.quotations.basement._section_subtotal', ['sectionKey' => 'finishing'])
  </div>
</details>

@include('admin.quotations.basement._client_scope')
