<div id="br-electrical-fields">
  <div class="admin-form-grid-2">
    <div class="admin-form-group">
      <label for="br-outlets">@include('admin.partials.info_tip', ['text' => $brInfo['outlet_qty']]) New outlets</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-outlets" name="outlet_qty"
             value="{{ $brVal('outlet_qty', 0) }}">
      <p class="admin-form-help" data-br-bathroom-electrical-hint @if($scopePreset !== 'bathroom') hidden @endif>Typical bathroom: 1 at the sink, 2 on the wall. Change the count if this job differs.</p>
    </div>
    <div class="admin-form-group">
      <label for="br-switches">@include('admin.partials.info_tip', ['text' => $brInfo['switch_qty']]) Switches</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-switches" name="switch_qty"
             value="{{ $brVal('switch_qty', 0) }}">
      <p class="admin-form-help" data-br-bathroom-electrical-hint @if($scopePreset !== 'bathroom') hidden @endif>Typical bathroom: 1 for the light, 1 for the exhaust.</p>
    </div>
    <div class="admin-form-group" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <label for="br-lighting-type">@include('admin.partials.info_tip', ['text' => $brInfo['lighting_type']]) Basement ceiling lighting</label>
      <select class="admin-input br-preview" id="br-lighting-type" name="lighting_type" data-br-lighting-type>
        <option value="fixture" @selected($brVal('lighting_type', 'spot') === 'fixture')>Light fixtures</option>
        <option value="spot" @selected($brVal('lighting_type', 'spot') === 'spot')>Spot lights (pots / recessed)</option>
        <option value="both" @selected($brVal('lighting_type') === 'both')>Light fixtures and spot lights</option>
      </select>
      <p class="admin-form-help">Living-area lights only. Bathroom fixtures and spots are chosen under Bathroom lighting. Set unit prices under Price configuration.</p>
    </div>
    <div class="admin-form-group" id="br-lighting-fixture-qty" data-br-basement-lighting @if($scopePreset === 'bathroom' || $brVal('lighting_type', 'spot') !== 'both') hidden @endif>
      <label for="br-fixtures">@include('admin.partials.info_tip', ['text' => $brInfo['fixture_qty']]) How many light fixtures</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-fixtures" name="fixture_qty"
             value="{{ $brVal('fixture_qty', 0) }}">
    </div>
    <div class="admin-form-group" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <label for="br-lights">@include('admin.partials.info_tip', ['text' => $brInfo['light_qty']]) <span id="br-lights-label">{{ $brVal('lighting_type', 'spot') === 'fixture' ? 'How many light fixtures' : 'How many spot lights' }}</span></label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-lights" name="light_qty"
             value="{{ $brVal('light_qty', 0) }}">
      <p class="admin-form-help" id="br-spot-hint" @if($brVal('lighting_type', 'spot') === 'fixture') hidden @endif>Optional extra pots in the basement rooms.</p>
    </div>
    <div class="admin-form-group">
      <label for="br-detectors">@include('admin.partials.info_tip', ['text' => $brInfo['detector_qty']]) Smoke / CO detectors</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-detectors" name="detector_qty"
             value="{{ $brVal('detector_qty', 2) }}"
             title="Ontario typically requires interconnected smoke/CO on each level.">
    </div>
    <div class="admin-form-group">
      <label for="br-runs">@include('admin.partials.info_tip', ['text' => $brInfo['wiring_runs']]) Wiring runs</label>
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
      'info' => $brInfo['panel_upgrade'],
      'inputClass' => 'admin-checkbox__input br-preview',
    ])
  </div>
</div>
