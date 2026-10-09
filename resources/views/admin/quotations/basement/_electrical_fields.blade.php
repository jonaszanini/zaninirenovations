<div id="br-electrical-fields" class="br-electrical-fields">
  <x-admin.info-tip label="Electrical scope">These answers are scope for a qualified electrician — they are not Zanini labour or materials. Type their quote as priced lines below.</x-admin.info-tip>
  <div class="br-qty-grid">
    <div class="admin-form-group">
      <span class="admin-label-with-tip">
        <label for="br-outlets">@include('admin.quotations.basement._client_filled', ['key' => 'outlet_qty', 'filledKeys' => $filledKeys ?? []]) New outlets</label>
        @include('admin.partials.info_tip', ['tipText' => $brInfo['outlet_qty'], 'tipLabel' => 'Outlet count'])
      </span>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-outlets" name="outlet_qty"
             value="{{ $brVal('outlet_qty', 0) }}">
    </div>
    <div class="admin-form-group">
      <label for="br-switches">@include('admin.quotations.basement._client_filled', ['key' => 'switch_qty', 'filledKeys' => $filledKeys ?? []]) Switches</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-switches" name="switch_qty"
             value="{{ $brVal('switch_qty', 0) }}">
    </div>
    <div class="admin-form-group br-field--wide" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <span class="admin-label-with-tip">
  <label for="br-lighting-type">@include('admin.quotations.basement._client_filled', ['key' => 'lighting_type', 'filledKeys' => $filledKeys ?? []]) Basement ceiling lighting</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['lighting_type'], 'tipLabel' => 'Basement ceiling lighting'])
</span>
      <select class="admin-input br-preview" id="br-lighting-type" name="lighting_type" data-br-lighting-type>
        <option value="fixture" @selected($brVal('lighting_type', 'spot') === 'fixture')>Surface fixtures</option>
        <option value="spot" @selected($brVal('lighting_type', 'spot') === 'spot')>Pot lights</option>
        <option value="both" @selected($brVal('lighting_type') === 'both')>Both</option>
      </select>
    </div>
    <div class="admin-form-group" id="br-lighting-fixture-qty" data-br-basement-lighting @if($scopePreset === 'bathroom' || $brVal('lighting_type', 'spot') !== 'both') hidden @endif>
      <label for="br-fixtures">@include('admin.quotations.basement._client_filled', ['key' => 'fixture_qty', 'filledKeys' => $filledKeys ?? []]) Fixtures</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-fixtures" name="fixture_qty"
             value="{{ $brVal('fixture_qty', 0) }}">
    </div>
    <div class="admin-form-group" data-br-basement-lighting @if($scopePreset === 'bathroom') hidden @endif>
      <span class="admin-label-with-tip">
        <label for="br-lights">@include('admin.quotations.basement._client_filled', ['key' => 'light_qty', 'filledKeys' => $filledKeys ?? []]) <span id="br-lights-label">{{ $brVal('lighting_type', 'spot') === 'fixture' ? 'Fixtures' : 'Pot lights' }}</span></label>
        @include('admin.partials.info_tip', ['tipText' => $brInfo['light_qty'], 'tipLabel' => 'Ceiling lights'])
      </span>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-lights" name="light_qty"
             value="{{ $brVal('light_qty', 0) }}">
    </div>
    <div class="admin-form-group">
      <span class="admin-label-with-tip">
  <label for="br-detectors">Smoke / CO</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['detector_qty'], 'tipLabel' => 'Smoke / CO'])
</span>
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
    'cardTitle' => "Electrician's quote",
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
