<div id="br-electrical-fields" class="br-electrical-fields">
  <p class="admin-form-help br-trade-scope-hint">
    These answers are scope for a qualified electrician — they are not Zanini labour or materials. Type their quote as priced lines below.
  </p>
  <div class="br-qty-grid">
    <div class="admin-form-group">
      <label for="br-outlets">@include('admin.partials.info_tip', ['text' => $brInfo['outlet_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'outlet_qty', 'filledKeys' => $filledKeys ?? []]) New outlets</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-outlets" name="outlet_qty"
             value="{{ $brVal('outlet_qty', 0) }}">
    </div>
    <div class="admin-form-group">
      <label for="br-switches">@include('admin.partials.info_tip', ['text' => $brInfo['switch_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'switch_qty', 'filledKeys' => $filledKeys ?? []]) Switches</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-switches" name="switch_qty"
             value="{{ $brVal('switch_qty', 0) }}">
    </div>
    <div class="admin-form-group" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <label for="br-lighting-type">@include('admin.partials.info_tip', ['text' => $brInfo['lighting_type']]) @include('admin.quotations.basement._client_filled', ['key' => 'lighting_type', 'filledKeys' => $filledKeys ?? []]) Basement ceiling lighting</label>
      <select class="admin-input br-preview" id="br-lighting-type" name="lighting_type" data-br-lighting-type>
        <option value="fixture" @selected($brVal('lighting_type', 'spot') === 'fixture')>Light fixtures</option>
        <option value="spot" @selected($brVal('lighting_type', 'spot') === 'spot')>Spot lights (pots / recessed)</option>
        <option value="both" @selected($brVal('lighting_type') === 'both')>Light fixtures and spot lights</option>
      </select>
    </div>
    <div class="admin-form-group" id="br-lighting-fixture-qty" data-br-basement-lighting @if($scopePreset === 'bathroom' || $brVal('lighting_type', 'spot') !== 'both') hidden @endif>
      <label for="br-fixtures">@include('admin.partials.info_tip', ['text' => $brInfo['fixture_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'fixture_qty', 'filledKeys' => $filledKeys ?? []]) How many light fixtures</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-fixtures" name="fixture_qty"
             value="{{ $brVal('fixture_qty', 0) }}">
    </div>
    <div class="admin-form-group" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <label for="br-lights">@include('admin.partials.info_tip', ['text' => $brInfo['light_qty']]) @include('admin.quotations.basement._client_filled', ['key' => 'light_qty', 'filledKeys' => $filledKeys ?? []]) <span id="br-lights-label">{{ $brVal('lighting_type', 'spot') === 'fixture' ? 'How many light fixtures' : 'How many spot lights' }}</span></label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-lights" name="light_qty"
             value="{{ $brVal('light_qty', 0) }}">
    </div>
    <div class="admin-form-group">
      <label for="br-detectors">@include('admin.partials.info_tip', ['text' => $brInfo['detector_qty']]) Smoke / CO detectors</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-detectors" name="detector_qty"
             value="{{ $brVal('detector_qty', 2) }}"
             title="Ontario typically requires interconnected smoke/CO on each level.">
    </div>
  </div>
  <div class="br-check-grid br-electrical-fields__toggles">
    @include('admin.partials.checkbox', [
      'name' => 'panel_upgrade',
      'hiddenFallback' => true,
      'checked' => $brOn('panel_upgrade'),
      'label' => 'Panel upgrade needed',
      'info' => $brInfo['panel_upgrade'],
      'inputClass' => 'admin-checkbox__input br-preview',
      'clientFilled' => isset($brFilled) && $brFilled('panel_upgrade'),
    ])
  </div>
  @include('admin.quotations._amount_lines', [
    'field' => 'electrical_lines',
    'idPrefix' => 'br-elec-line',
    'intro' => 'Priced electrical work from the qualified electrician. Each filled line appears on the client quotation.',
    'addLabel' => 'Add line',
    'placeholder' => 'e.g. Rough-in, pot lights, panel',
    'rows' => is_array($inputs['electrical_lines'] ?? null) ? $inputs['electrical_lines'] : [],
    'inputClass' => 'br-preview',
    'eventName' => 'trade-lines:change',
    'summaryMode' => 'tax-in',
    'defaultHstIncluded' => false,
  ])
</div>
