@php
  use App\Services\ShedQuotationCalculator;
  $shVal = function (string $key, $fallback = '') use ($inputs) {
      return old($key, $inputs[$key] ?? $fallback);
  };
  $shOn = function (string $key, $fallback = false) use ($inputs) {
      return (bool) old($key, $inputs[$key] ?? $fallback);
  };
  $step = 0;
@endphp

<details class="br-acc admin-card is-active" data-br-step="type" open>
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Shed type</summary>
  <div class="br-acc__body">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Roof shape drives rafter and roofing takeoff. Lean-to against a house is treated as attached.']) Style</label>
      <div class="sh-choice-grid">
        @foreach (ShedQuotationCalculator::SHED_TYPES as $key => $label)
          <label class="sh-choice">
            <input type="radio" name="shed_type" value="{{ $key }}" class="sh-preview" data-sh-shed-type
                   @checked($shVal('shed_type', 'gable') === $key)>
            <span>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div id="sh-attached-wrap">
      @include('admin.partials.checkbox', [
        'name' => 'attached',
        'id' => 'sh-attached',
        'hiddenFallback' => true,
        'checked' => $shOn('attached'),
        'label' => 'Attached to an existing building',
        'info' => 'Attached sheds (including a lean-to on the house) need a building permit even under 15 m².',
        'inputClass' => 'admin-checkbox__input sh-preview',
      ])
    </div>
  </div>
</details>

<details class="br-acc admin-card" data-br-step="size">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Size</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="sh-length">@include('admin.partials.info_tip', ['text' => 'Outside length of the floor. 12×14 already exceeds 15 m².']) Length (ft)</label>
        <input type="number" min="0" step="0.1" class="admin-input sh-preview" id="sh-length" name="length_ft"
               value="{{ $shVal('length_ft') }}">
      </div>
      <div class="admin-form-group">
        <label for="sh-width">Width (ft)</label>
        <input type="number" min="0" step="0.1" class="admin-input sh-preview" id="sh-width" name="width_ft"
               value="{{ $shVal('width_ft') }}">
      </div>
      <div class="admin-form-group">
        <label for="sh-height">@include('admin.partials.info_tip', ['text' => 'Eave wall height. Default 8 ft.']) Wall height (ft)</label>
        <input type="number" min="4" max="16" step="0.25" class="admin-input sh-preview" id="sh-height" name="wall_height_ft"
               value="{{ $shVal('wall_height_ft', 8) }}">
      </div>
      <div class="admin-form-group">
        <label for="sh-pitch">@include('admin.partials.info_tip', ['text' => 'Rise per 12 inches of run. Steeper roofs use more shingles and rafters.']) Pitch</label>
        <select class="admin-input sh-preview" id="sh-pitch" name="roof_pitch">
          @foreach ([4, 5, 6, 8, 10, 12] as $p)
            <option value="{{ $p }}" @selected((int) $shVal('roof_pitch', 6) === $p)>{{ $p }}/12</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="sh-overhang">@include('admin.partials.info_tip', ['text' => 'Eave overhang on all sides. Adds fascia, drip edge, and roof area.']) Overhang (in)</label>
        <input type="number" min="0" max="24" step="1" class="admin-input sh-preview" id="sh-overhang" name="overhang_in"
               value="{{ $shVal('overhang_in', 12) }}">
      </div>
      <div class="admin-form-group">
        <label>Area</label>
        <p class="sh-area-readout" id="sh-area-readout">—</p>
      </div>
    </div>
    <div class="sh-permit" id="sh-permit" hidden>
      <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
      <div id="sh-permit-list"></div>
    </div>
  </div>
</details>

<details class="br-acc admin-card" data-br-step="foundation">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Foundation</summary>
  <div class="br-acc__body">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Pad = poured slab. Piles = helical. Skids = PT 6x6. Blocks = deck blocks.']) Type</label>
      <div class="sh-choice-grid">
        @foreach (ShedQuotationCalculator::FOUNDATIONS as $key => $label)
          <label class="sh-choice">
            <input type="radio" name="foundation" value="{{ $key }}" class="sh-preview" data-sh-foundation
                   @checked($shVal('foundation', 'skids') === $key)>
            <span>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div class="admin-form-grid-2" id="sh-found-pad" hidden>
      <div class="admin-form-group">
        <label for="sh-pad-th">Pad thickness (in)</label>
        <input type="number" min="3" max="8" step="0.5" class="admin-input sh-preview" id="sh-pad-th" name="pad_thickness_in"
               value="{{ $shVal('pad_thickness_in', 4) }}">
      </div>
      <div class="admin-form-group">
        <label for="sh-gravel">Gravel base (in)</label>
        <input type="number" min="0" max="12" step="0.5" class="admin-input sh-preview" id="sh-gravel" name="gravel_depth_in"
               value="{{ $shVal('gravel_depth_in', 4) }}">
      </div>
    </div>
    <div class="admin-form-group" id="sh-found-piles" hidden>
      <label for="sh-piles">@include('admin.partials.info_tip', ['text' => 'Leave blank to auto-count from size (~8 ft grid, min 4).']) Helical piles</label>
      <input type="number" min="0" step="1" class="admin-input sh-preview" id="sh-piles" name="pile_qty"
             placeholder="Auto" value="{{ $shVal('pile_qty') }}">
    </div>
    <div class="admin-form-group" id="sh-found-skids" hidden>
      <label for="sh-skids">@include('admin.partials.info_tip', ['text' => 'Leave blank to auto-count from width.']) PT skids</label>
      <input type="number" min="0" step="1" class="admin-input sh-preview" id="sh-skids" name="skid_qty"
             placeholder="Auto" value="{{ $shVal('skid_qty') }}">
    </div>
    <div class="admin-form-group" id="sh-found-blocks" hidden>
      <label for="sh-blocks">@include('admin.partials.info_tip', ['text' => 'Leave blank to auto-count from size (~4 ft grid).']) Deck blocks</label>
      <input type="number" min="0" step="1" class="admin-input sh-preview" id="sh-blocks" name="block_qty"
             placeholder="Auto" value="{{ $shVal('block_qty') }}">
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_site_level',
      'hiddenFallback' => true,
      'checked' => $shOn('include_site_level'),
      'label' => 'Site leveling',
      'info' => 'Adds labor to grade and compact the footprint before foundation work.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="framing">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Floor &amp; framing</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label>@include('admin.partials.info_tip', ['text' => 'Floor joists. 2x6 is typical for sheds over 8 ft wide.']) Joists</label>
        <div class="sh-choice-grid sh-choice-grid--2">
          <label class="sh-choice"><input type="radio" name="joist_size" value="2x4" class="sh-preview" @checked($shVal('joist_size', '2x6') === '2x4')><span>2x4</span></label>
          <label class="sh-choice"><input type="radio" name="joist_size" value="2x6" class="sh-preview" @checked($shVal('joist_size', '2x6') === '2x6')><span>2x6</span></label>
        </div>
      </div>
      <div class="admin-form-group">
        <label>Joist spacing</label>
        <select class="admin-input sh-preview" name="joist_spacing_in">
          @foreach ([12, 16, 24] as $sp)
            <option value="{{ $sp }}" @selected((int) $shVal('joist_spacing_in', 16) === $sp)>{{ $sp }}" OC</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label>Floor sheathing</label>
        <div class="sh-choice-grid sh-choice-grid--2">
          <label class="sh-choice"><input type="radio" name="floor_sheathing" value="osb_58" class="sh-preview" @checked($shVal('floor_sheathing', 'osb_58') === 'osb_58')><span>5/8 OSB</span></label>
          <label class="sh-choice"><input type="radio" name="floor_sheathing" value="plywood_34" class="sh-preview" @checked($shVal('floor_sheathing', 'osb_58') === 'plywood_34')><span>3/4 plywood</span></label>
        </div>
      </div>
      <div class="admin-form-group">
        <label>@include('admin.partials.info_tip', ['text' => 'Wall studs. Use 2x6 when insulating for a finished interior.']) Wall studs</label>
        <div class="sh-choice-grid sh-choice-grid--2">
          <label class="sh-choice"><input type="radio" name="stud_size" value="2x4" class="sh-preview" @checked($shVal('stud_size', '2x4') === '2x4')><span>2x4</span></label>
          <label class="sh-choice"><input type="radio" name="stud_size" value="2x6" class="sh-preview" @checked($shVal('stud_size', '2x4') === '2x6')><span>2x6</span></label>
        </div>
      </div>
      <div class="admin-form-group">
        <label>Stud spacing</label>
        <select class="admin-input sh-preview" name="stud_spacing_in">
          @foreach ([12, 16, 24] as $sp)
            <option value="{{ $sp }}" @selected((int) $shVal('stud_spacing_in', 16) === $sp)>{{ $sp }}" OC</option>
          @endforeach
        </select>
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'wall_sheathing',
      'hiddenFallback' => true,
      'checked' => $shOn('wall_sheathing', true),
      'label' => 'OSB wall sheathing',
      'info' => 'Structural wall panels under the siding. Uncheck only if siding goes on open studs.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="roof">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Roof</summary>
  <div class="br-acc__body">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Not now skips covering. Weather coat seals the roof deck. Finish roofs include underlayment, ice & water, drip edge, and ridge vent.']) Covering</label>
      <div class="sh-choice-grid">
        @foreach (ShedQuotationCalculator::ROOF as $key => $label)
          <label class="sh-choice">
            <input type="radio" name="roof_type" value="{{ $key }}" class="sh-preview" @checked($shVal('roof_type', 'asphalt') === $key)>
            <span>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_gutters',
      'hiddenFallback' => true,
      'checked' => $shOn('include_gutters'),
      'label' => 'Gutters',
      'info' => 'Eave gutters along both long sides.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="siding">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Siding</summary>
  <div class="br-acc__body">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Not now skips siding. Weather coat seals the wall sheathing. Finish siding includes house wrap and corner trim.']) Material</label>
      <div class="sh-choice-grid">
        @foreach (ShedQuotationCalculator::SIDING as $key => $label)
          <label class="sh-choice">
            <input type="radio" name="siding" value="{{ $key }}" class="sh-preview" @checked($shVal('siding', 'vinyl') === $key)>
            <span>{{ $label }}</span>
          </label>
        @endforeach
      </div>
    </div>
    <div id="sh-paint-wrap">
    @include('admin.partials.checkbox', [
      'name' => 'include_paint',
      'hiddenFallback' => true,
      'checked' => $shOn('include_paint'),
      'label' => 'Exterior paint / stain',
      'info' => 'Adds paint labor and gallons for wood, Hardie, or board-and-batten. Skip for pre-finished vinyl.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
    </div>
  </div>
</details>

<details class="br-acc admin-card" data-br-step="openings">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Doors &amp; windows</summary>
  <div class="br-acc__body">
    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label>Door type</label>
        <select class="admin-input sh-preview" name="door_type">
          @foreach (ShedQuotationCalculator::DOOR_TYPES as $key => $label)
            <option value="{{ $key }}" @selected($shVal('door_type', 'custom') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="sh-door-qty">Doors</label>
        <input type="number" min="0" max="6" step="1" class="admin-input sh-preview" id="sh-door-qty" name="door_qty"
               value="{{ $shVal('door_qty', 1) }}">
      </div>
      <div class="admin-form-group">
        <label>Window type</label>
        <select class="admin-input sh-preview" name="window_type">
          @foreach (ShedQuotationCalculator::WINDOW_TYPES as $key => $label)
            <option value="{{ $key }}" @selected($shVal('window_type', 'slider') === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label for="sh-win-qty">Windows</label>
        <input type="number" min="0" max="12" step="1" class="admin-input sh-preview" id="sh-win-qty" name="window_qty"
               value="{{ $shVal('window_qty', 2) }}">
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'double_door',
      'hiddenFallback' => true,
      'checked' => $shOn('double_door'),
      'label' => 'Double door',
      'info' => 'Counts as a wider opening in the siding takeoff. Does not double the door quantity.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
    @include('admin.partials.checkbox', [
      'name' => 'include_ramp',
      'hiddenFallback' => true,
      'checked' => $shOn('include_ramp'),
      'label' => 'Access ramp',
      'info' => 'Pressure-treated ramp package at the door.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
</details>

<details class="br-acc admin-card" data-br-step="interior">
  <summary class="br-acc__summary"><span class="br-acc__num">{{ ++$step }}</span> Interior finish</summary>
  <div class="br-acc__body">
    <div class="admin-form-group">
      <label>@include('admin.partials.info_tip', ['text' => 'Rough = shell only. Finished adds insulation, vapor barrier, and drywall. May affect the permit exemption.']) Finish</label>
      <div class="sh-choice-grid sh-choice-grid--2">
        <label class="sh-choice">
          <input type="radio" name="interior_finish" value="rough" class="sh-preview" @checked($shVal('interior_finish', 'rough') === 'rough')>
          <span>Rough</span>
        </label>
        <label class="sh-choice">
          <input type="radio" name="interior_finish" value="finished" class="sh-preview" data-sh-finished
                 @checked($shVal('interior_finish', 'rough') === 'finished')>
          <span>Fully finished</span>
        </label>
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_electrical',
      'id' => 'sh-elec',
      'hiddenFallback' => true,
      'checked' => $shOn('include_electrical'),
      'label' => 'Electrical package',
      'info' => 'Permanent wiring. Typically needs ESA and may require a building permit.',
      'inputClass' => 'admin-checkbox__input sh-preview',
      'attrs' => ['data-sh-reveal' => 'sh-elec-fields'],
    ])
    <div id="sh-elec-fields" class="admin-form-grid-2" hidden>
      <div class="admin-form-group">
        <label for="sh-outlets">Outlets</label>
        <input type="number" min="0" max="20" class="admin-input sh-preview" id="sh-outlets" name="outlet_qty"
               value="{{ $shVal('outlet_qty', 2) }}">
      </div>
      <div class="admin-form-group">
        <label for="sh-lights">Lights</label>
        <input type="number" min="0" max="12" class="admin-input sh-preview" id="sh-lights" name="light_qty"
               value="{{ $shVal('light_qty', 1) }}">
      </div>
      <div class="admin-form-group" style="grid-column:1/-1;">
        @include('admin.partials.checkbox', [
          'name' => 'include_subpanel',
          'hiddenFallback' => true,
          'checked' => $shOn('include_subpanel'),
          'label' => 'Subpanel',
          'info' => 'Allowance to add a small subpanel in the shed.',
          'inputClass' => 'admin-checkbox__input sh-preview',
        ])
      </div>
    </div>
    @include('admin.partials.checkbox', [
      'name' => 'include_loft',
      'hiddenFallback' => true,
      'checked' => $shOn('include_loft'),
      'label' => 'Storage loft',
      'info' => 'Framed loft over ~40% of the floor. A loft can void the one-storey permit exemption.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
    @include('admin.partials.checkbox', [
      'name' => 'include_plumbing',
      'hiddenFallback' => true,
      'checked' => $shOn('include_plumbing'),
      'label' => 'Plumbing (permit warning only)',
      'info' => 'Does not add plumbing takeoff. Flags that plumbing voids the OBC shed exemption. Price plumbing via Additional costs.',
      'inputClass' => 'admin-checkbox__input sh-preview',
    ])
  </div>
</details>
