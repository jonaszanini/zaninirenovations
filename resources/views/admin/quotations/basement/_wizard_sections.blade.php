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
  $enabledNow = $enabledNow ?? \App\Services\BasementQuotationCalculator::enabledSections([
      'scope_preset' => $scopePreset,
      'enabled_sections' => old('enabled_sections', $inputs['enabled_sections'] ?? null),
  ]);
  $enabledCount = $enabledCount ?? max(1, count($enabledNow));
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
      'bathroom' => 'Plumbing, electrical, drywall, paint, fixtures, and cleanup — not whole-basement flooring or insulation.',
      'wall' => 'Framing, drywall, paint, and cleanup. Drywall and paint use wall length × height × both sides.',
      'custom' => 'Only the trades you check below. Overview and Review always stay.',
  ];
@endphp
<details class="br-acc admin-card is-active" data-br-step="overview" @if(empty($isEdit)) open @endif>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Project Overview</summary>
  <div class="br-acc__body">
    <fieldset class="br-scope-presets">
      <legend>What are you quoting?</legend>
      <p class="admin-form-help">Pick a starting point, then add or skip trades. Unused steps stay out of the wizard.</p>
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
    </fieldset>

    <input type="hidden" name="enabled_sections[]" value="overview">
    <fieldset class="br-provide-grid br-scope-trades" id="br-enabled-sections">
      <legend>Trades to include</legend>
      @foreach (\App\Services\BasementQuotationCalculator::tradeSections() as $tradeKey => $tradeLabel)
        @include('admin.partials.checkbox', [
          'name' => 'enabled_sections[]',
          'value' => $tradeKey,
          'checked' => in_array($tradeKey, $enabledNow, true),
          'label' => $tradeLabel,
          'inputClass' => 'admin-checkbox__input br-preview',
          'attrs' => ['data-br-enabled-section' => $tradeKey],
        ])
      @endforeach
    </fieldset>

    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-area" id="br-area-label">{{ $areaLabels[$scopePreset] ?? $areaLabels['full'] }}</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-area" name="area_sqft"
               value="{{ $brVal('area_sqft', 0) }}"
               title="Finished floor area of the work. Leave other quantity fields blank to auto-calculate from this.">
        <p class="admin-form-help" id="br-area-help">Leave later quantity fields blank to auto-fill from this area. Enter 0 on a field to skip it.</p>
      </div>
      <div class="admin-form-group">
        <label for="br-height">Ceiling height (ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-height" name="ceiling_height_ft"
               value="{{ $brVal('ceiling_height_ft', 8) }}"
               title="Used to estimate wall square footage for insulation, drywall, and paint.">
      </div>
      <div class="admin-form-group">
        <label for="br-rooms">Finished rooms / zones (after reno)</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-rooms" name="room_count"
               value="{{ $brVal('room_count', 1) }}"
               title="Count rooms as they will exist when the work is done, including new dividing walls. Not the current open basement.">
        <p class="admin-form-help">Planned layout after the work — e.g. rec room + bedroom + bath = 3, even if it is open now. Enter new wall length on the Framing step.</p>
      </div>
      <div class="admin-form-group">
        <label for="br-owner-rate">Owner hourly rate (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
               class="admin-input cp-money-input br-preview" id="br-owner-rate" name="owner_hourly_rate"
               value="{{ number_format((float) $brVal('owner_hourly_rate', $settings->owner_hourly_rate), 2, '.', '') }}">
      </div>
      <div class="admin-form-group">
        <label for="br-helper-rate">Helper hourly rate (CAD$)</label>
        <input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"
               class="admin-input cp-money-input br-preview" id="br-helper-rate" name="helper_hourly_rate"
               value="{{ number_format((float) $brVal('helper_hourly_rate', $settings->helper_hourly_rate), 2, '.', '') }}">
      </div>
    </div>
    <div class="admin-form-group">
      <label for="br-notes">Existing condition notes</label>
      <textarea class="admin-input br-preview" id="br-notes" name="existing_notes" rows="3" maxlength="2000"
                placeholder="Moisture, low headroom, existing bathroom, knob-and-tube, etc.">{{ $brVal('existing_notes') }}</textarea>
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
      'inputClass' => 'admin-checkbox__input br-preview',
      'attrs' => ['data-br-reveal' => 'br-demo-fields'],
    ])
    <div id="br-demo-fields" class="admin-form-grid-2" style="margin-top:12px;" @unless($brOn('include_demolition')) hidden @endunless>
      <div class="admin-form-group">
        <label for="br-demo-floor">Existing flooring removal (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-floor" name="demo_flooring_sf"
               value="{{ $brQty('demo_flooring_sf') }}" placeholder="Auto: basement area">
      </div>
      <div class="admin-form-group">
        <label for="br-demo-dw">Existing drywall / ceiling removal (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-demo-dw" name="demo_drywall_sf"
               value="{{ $brQty('demo_drywall_sf') }}" placeholder="0 if none">
      </div>
      <div class="admin-form-group">
        <label for="br-bins">Debris bins</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-bins" name="demo_bin_qty"
               value="{{ $brVal('demo_bin_qty', 1) }}"
               title="Dumpster / bin count. Price comes from the basement materials database.">
      </div>
    </div>
  </div>
</details>

{{-- 3. Framing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="framing" @unless(in_array('framing', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Framing</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-partition">New partition walls (linear ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-partition" name="partition_lf"
               value="{{ $brVal('partition_lf', 0) }}"
               title="Interior walls only. Height uses the ceiling height from Overview.">
      </div>
      <div class="admin-form-group">
        <label for="br-bulkhead">Framing around ducts / bulkheads (linear ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-bulkhead" name="bulkhead_lf"
               value="{{ $brVal('bulkhead_lf', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-headers">Header / beam openings</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-headers" name="header_qty"
               value="{{ $brVal('header_qty', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-frame-doors">Door openings to frame</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-frame-doors" name="framing_door_openings"
               value="{{ $brVal('framing_door_openings', 0) }}">
      </div>
    </div>
  </div>
</details>

{{-- 4. Electrical --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="electrical" @unless(in_array('electrical', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Electrical</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-outlets">New outlets</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-outlets" name="outlet_qty"
               value="{{ $brVal('outlet_qty', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-switches">Switches</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-switches" name="switch_qty"
               value="{{ $brVal('switch_qty', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-lighting-type">Ceiling lighting</label>
        <select class="admin-input br-preview" id="br-lighting-type" name="lighting_type" data-br-lighting-type>
          <option value="fixture" @selected($brVal('lighting_type', 'spot') === 'fixture')>Light fixtures</option>
          <option value="spot" @selected($brVal('lighting_type', 'spot') === 'spot')>Spot lights (pots / recessed)</option>
          <option value="both" @selected($brVal('lighting_type') === 'both')>Both</option>
        </select>
        <p class="admin-form-help">Electrical materials and hours follow this choice. Set unit prices under Price configuration.</p>
      </div>
      <div class="admin-form-group">
        <label for="br-lights" id="br-lights-label">{{ $brVal('lighting_type', 'spot') === 'fixture' ? 'How many light fixtures' : 'How many spot lights' }}</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-lights" name="light_qty"
               value="{{ $brVal('light_qty', 0) }}">
      </div>
      <div class="admin-form-group" id="br-lighting-fixture-qty" @if($brVal('lighting_type', 'spot') !== 'both') hidden @endif>
        <label for="br-fixtures">How many light fixtures</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-fixtures" name="fixture_qty"
               value="{{ $brVal('fixture_qty', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-detectors">Smoke / CO detectors</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-detectors" name="detector_qty"
               value="{{ $brVal('detector_qty', 2) }}"
               title="Ontario typically requires interconnected smoke/CO on each level.">
      </div>
      <div class="admin-form-group">
        <label for="br-runs">Wiring runs</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-runs" name="wiring_runs"
               value="{{ $brVal('wiring_runs', 0) }}" placeholder="Auto: outlets + switches + lights">
        <p class="admin-form-help">Leave 0 to estimate one run per device.</p>
      </div>
    </div>
    <div style="margin-top:8px;">
      @include('admin.partials.checkbox', [
        'name' => 'panel_upgrade',
        'hiddenFallback' => true,
        'checked' => $brOn('panel_upgrade'),
        'label' => 'Panel upgrade needed',
        'inputClass' => 'admin-checkbox__input br-preview',
      ])
    </div>
  </div>
</details>

{{-- 5. Plumbing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="plumbing" @unless(in_array('plumbing', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Plumbing</summary>
  <div class="br-acc__body">
    @include('admin.partials.checkbox', [
      'name' => 'include_plumbing',
      'hiddenFallback' => true,
      'checked' => $brOn('include_plumbing'),
      'label' => 'Include plumbing rough-in',
      'inputClass' => 'admin-checkbox__input br-preview',
      'attrs' => ['data-br-reveal' => 'br-plumb-fields'],
    ])
    <div id="br-plumb-fields" class="br-check-stack" style="margin-top:12px;" @unless($brOn('include_plumbing')) hidden @endunless>
      @include('admin.partials.checkbox', ['name' => 'bath_rough_in', 'hiddenFallback' => true, 'checked' => $brOn('bath_rough_in'), 'label' => 'Bathroom rough-in (drain / vent / supply)', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'sink_laundry', 'hiddenFallback' => true, 'checked' => $brOn('sink_laundry'), 'label' => 'Sink / laundry hookup', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'wet_bar', 'hiddenFallback' => true, 'checked' => $brOn('wet_bar'), 'label' => 'Wet bar', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'floor_drain', 'hiddenFallback' => true, 'checked' => $brOn('floor_drain'), 'label' => 'Floor drain', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'wh_reloc', 'hiddenFallback' => true, 'checked' => $brOn('wh_reloc'), 'label' => 'Water heater relocation', 'inputClass' => 'admin-checkbox__input br-preview'])
    </div>
  </div>
</details>

{{-- 6. Insulation --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="insulation" @unless(in_array('insulation', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Insulation &amp; Vapor Barrier</summary>
  <div class="br-acc__body">
    <div class="br-check-stack">
      @include('admin.partials.checkbox', ['name' => 'insulate_walls', 'hiddenFallback' => true, 'checked' => $brOn('insulate_walls', true), 'label' => 'Wall insulation + vapor barrier', 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-wall']])
    </div>
    <div class="admin-form-group" id="br-ins-wall" @unless($brOn('insulate_walls', true)) hidden @endunless>
      <label for="br-ins-wall-sf">Wall insulation (sq ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-ins-wall-sf" name="insulation_wall_sf"
             value="{{ $brQty('insulation_wall_sf') }}" placeholder="Auto: perimeter × height">
    </div>
    <div class="br-check-stack">
      @include('admin.partials.checkbox', ['name' => 'insulate_ceiling', 'hiddenFallback' => true, 'checked' => $brOn('insulate_ceiling'), 'label' => 'Ceiling insulation', 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-ceil']])
    </div>
    <div class="admin-form-group" id="br-ins-ceil" @unless($brOn('insulate_ceiling')) hidden @endunless>
      <label for="br-ins-ceil-sf">Ceiling insulation (sq ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-ins-ceil-sf" name="insulation_ceiling_sf"
             value="{{ $brQty('insulation_ceiling_sf') }}" placeholder="Auto: basement area">
    </div>
    <div class="br-check-stack">
      @include('admin.partials.checkbox', ['name' => 'underslab', 'hiddenFallback' => true, 'checked' => $brOn('underslab'), 'label' => 'Under-slab insulation', 'inputClass' => 'admin-checkbox__input br-preview', 'attrs' => ['data-br-reveal' => 'br-ins-slab']])
    </div>
    <div class="admin-form-group" id="br-ins-slab" @unless($brOn('underslab')) hidden @endunless>
      <label for="br-ins-slab-sf">Under-slab insulation (sq ft)</label>
      <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-ins-slab-sf" name="underslab_sf"
             value="{{ $brQty('underslab_sf') }}" placeholder="Auto: basement area">
    </div>
  </div>
</details>

{{-- 7. Drywall --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="drywall" @unless(in_array('drywall', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Drywall / Mud / Tape / Sand</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-dw-wall">Wall drywall (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-dw-wall" name="drywall_wall_sf"
               value="{{ $brQty('drywall_wall_sf') }}" placeholder="Auto from area × height">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-ceil">Ceiling drywall (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-dw-ceil" name="drywall_ceiling_sf"
               value="{{ $brQty('drywall_ceiling_sf') }}" placeholder="Auto if ceiling is drywall">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-corners">Outside corners</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-dw-corners" name="drywall_corners"
               value="{{ $brVal('drywall_corners', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-dw-open">Openings (doors / windows)</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-dw-open" name="drywall_openings"
               value="{{ $brVal('drywall_openings', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-finish">Level of finish</label>
        <select class="admin-input br-preview" id="br-finish" name="finish_level">
          @foreach ([1 => 'Level 1 — fire tape', 2 => 'Level 2 — tape + coat', 3 => 'Level 3 — paint-ready economy', 4 => 'Level 4 — standard paint-ready'] as $lvl => $lbl)
            <option value="{{ $lvl }}" @selected((int) $brVal('finish_level', 4) === $lvl)>{{ $lbl }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>
</details>

{{-- 8. Ceiling --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="ceiling" @unless(in_array('ceiling', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Ceiling Options</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-ceil-type">Ceiling type</label>
        <select class="admin-input br-preview" id="br-ceil-type" name="ceiling_type">
          <option value="drywall" @selected($brVal('ceiling_type', 'drywall') === 'drywall')>Drywall ceiling</option>
          <option value="paint" @selected($brVal('ceiling_type') === 'paint')>Paint-grade drywall</option>
          <option value="drop" @selected($brVal('ceiling_type') === 'drop')>Drop ceiling</option>
          <option value="none" @selected($brVal('ceiling_type') === 'none')>Leave existing / skip</option>
        </select>
      </div>
      <div class="admin-form-group">
        <label for="br-ceil-sf">Ceiling area (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-ceil-sf" name="ceiling_sf"
               value="{{ $brQty('ceiling_sf') }}" placeholder="Auto: basement area">
      </div>
    </div>
  </div>
</details>

{{-- 9. Flooring --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="flooring" @unless(in_array('flooring', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Flooring</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-fl-type">Final flooring type</label>
        <select class="admin-input br-preview" id="br-fl-type" name="flooring_type">
          <option value="lvp" @selected($brVal('flooring_type', 'lvp') === 'lvp')>LVP</option>
          <option value="laminate" @selected($brVal('flooring_type') === 'laminate')>Laminate</option>
          <option value="tile" @selected($brVal('flooring_type') === 'tile')>Tile</option>
          <option value="carpet" @selected($brVal('flooring_type') === 'carpet')>Carpet</option>
          <option value="epoxy" @selected($brVal('flooring_type') === 'epoxy')>Epoxy</option>
          <option value="none" @selected($brVal('flooring_type') === 'none')>Skip flooring</option>
        </select>
      </div>
      <div class="admin-form-group">
        <label for="br-fl-sf">Flooring area (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-fl-sf" name="flooring_sf"
               value="{{ $brQty('flooring_sf') }}" placeholder="Auto: basement area">
      </div>
      <div class="admin-form-group">
        <label for="br-trans">Transitions</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-trans" name="transition_qty"
               value="{{ $brVal('transition_qty', 0) }}">
      </div>
    </div>
    <div class="br-check-stack" style="margin-top:8px;">
      @include('admin.partials.checkbox', ['name' => 'subfloor_prep', 'hiddenFallback' => true, 'checked' => $brOn('subfloor_prep'), 'label' => 'Subfloor preparation / leveling', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'underlayment', 'hiddenFallback' => true, 'checked' => $brOn('underlayment', true), 'label' => 'Underlayment (LVP / laminate / carpet)', 'inputClass' => 'admin-checkbox__input br-preview'])
    </div>
  </div>
</details>

{{-- 10. Doors & trim --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="doors_trim" @unless(in_array('doors_trim', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Doors, Trim &amp; Baseboards</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-doors">Interior doors</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-doors" name="door_qty"
               value="{{ $brVal('door_qty', 0) }}">
      </div>
      <div class="admin-form-group">
        <label for="br-base">Baseboards (linear ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-base" name="baseboard_lf"
               value="{{ $brQty('baseboard_lf') }}" placeholder="Auto: room perimeter">
      </div>
      <div class="admin-form-group">
        <label for="br-casing">Casing / door trim (openings)</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-casing" name="casing_openings"
               value="{{ $brVal('casing_openings', 0) }}" placeholder="Auto: door count">
      </div>
      <div class="admin-form-group">
        <label for="br-windows">Window trim kits</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-windows" name="window_trim_qty"
               value="{{ $brVal('window_trim_qty', 0) }}">
      </div>
    </div>
  </div>
</details>

{{-- 11. Painting --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="painting" @unless(in_array('painting', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Painting</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-p-wall">Walls (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-p-wall" name="paint_wall_sf"
               value="{{ $brQty('paint_wall_sf') }}" placeholder="Auto from drywall walls">
      </div>
      <div class="admin-form-group">
        <label for="br-p-ceil">Ceiling (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-p-ceil" name="paint_ceiling_sf"
               value="{{ $brQty('paint_ceiling_sf') }}" placeholder="Auto unless drop ceiling">
      </div>
      <div class="admin-form-group">
        <label for="br-p-trim">Trim (linear ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-p-trim" name="paint_trim_lf"
               value="{{ $brQty('paint_trim_lf') }}" placeholder="Auto from base + casing">
      </div>
      <div class="admin-form-group">
        <label for="br-colors">Wall / ceiling colors</label>
        <select class="admin-input br-preview" id="br-colors" name="paint_colors">
          <option value="1" @selected((int) $brVal('paint_colors', 1) === 1)>One color (walls and ceiling the same)</option>
          <option value="2" @selected((int) $brVal('paint_colors', 1) === 2)>Two colors (walls and ceiling different)</option>
        </select>
        <p class="admin-form-help">Two colors packs paint separately and adds tape / cut-in labor plus extra drywall finish time.</p>
      </div>
      <div class="admin-form-group">
        <label for="br-coats">Number of coats</label>
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
    @include('admin.partials.checkbox', [
      'name' => 'include_bathroom',
      'hiddenFallback' => true,
      'checked' => $brOn('include_bathroom'),
      'label' => 'Include a bathroom in this basement',
      'inputClass' => 'admin-checkbox__input br-preview',
      'attrs' => ['data-br-reveal' => 'br-bath-fields'],
    ])
    <div id="br-bath-fields" style="margin-top:12px;" @unless($brOn('include_bathroom')) hidden @endunless>
      <p class="admin-form-help">Turn on bathroom rough-in under Plumbing if new drains are needed.</p>
      <div class="admin-form-group">
        <label for="br-bath-type">Bathroom type</label>
        <select class="admin-input br-preview" id="br-bath-type" name="bath_type">
          <option value="full" @selected($brVal('bath_type', 'full') === 'full')>Full bath</option>
          <option value="half" @selected($brVal('bath_type') === 'half')>Half bath</option>
        </select>
      </div>
      <div class="br-check-stack">
        @include('admin.partials.checkbox', ['name' => 'include_vanity', 'hiddenFallback' => true, 'checked' => $brOn('include_vanity', true), 'label' => 'Vanity + faucet', 'inputClass' => 'admin-checkbox__input br-preview'])
        @include('admin.partials.checkbox', ['name' => 'include_toilet', 'hiddenFallback' => true, 'checked' => $brOn('include_toilet', true), 'label' => 'Toilet', 'inputClass' => 'admin-checkbox__input br-preview'])
        @include('admin.partials.checkbox', ['name' => 'include_shower', 'hiddenFallback' => true, 'checked' => $brOn('include_shower', true), 'label' => 'Shower', 'inputClass' => 'admin-checkbox__input br-preview'])
        @include('admin.partials.checkbox', ['name' => 'include_tub', 'hiddenFallback' => true, 'checked' => $brOn('include_tub'), 'label' => 'Tub', 'inputClass' => 'admin-checkbox__input br-preview'])
        @include('admin.partials.checkbox', ['name' => 'include_exhaust_fan', 'hiddenFallback' => true, 'checked' => $brOn('include_exhaust_fan', true), 'label' => 'Exhaust fan', 'inputClass' => 'admin-checkbox__input br-preview'])
      </div>
      <div class="admin-form-group">
        <label for="br-tile">Tile work (sq ft)</label>
        <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-tile" name="bath_tile_sf"
               value="{{ $brVal('bath_tile_sf', 0) }}" placeholder="Floor + shower walls">
      </div>
    </div>
  </div>
</details>

{{-- 13. Finishing --}}
@php $step++; @endphp
<details class="br-acc admin-card" data-br-step="finishing" @unless(in_array('finishing', $enabledNow, true)) hidden @endunless>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ $step }} of {{ $enabledCount }}</span> Final Details &amp; Cleanup</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="br-hw">Extra hardware sets</label>
        <input type="number" step="1" min="0" class="admin-input br-preview" id="br-hw" name="hardware_sets"
               value="{{ $brVal('hardware_sets', 0) }}">
      </div>
    </div>
    <div class="br-check-stack" style="margin-top:8px;">
      @include('admin.partials.checkbox', ['name' => 'include_cleaning', 'hiddenFallback' => true, 'checked' => $brOn('include_cleaning', true), 'label' => 'Final cleaning', 'inputClass' => 'admin-checkbox__input br-preview'])
      @include('admin.partials.checkbox', ['name' => 'include_touchups', 'hiddenFallback' => true, 'checked' => $brOn('include_touchups', true), 'label' => 'Touch-ups / punch list', 'inputClass' => 'admin-checkbox__input br-preview'])
    </div>
  </div>
</details>
