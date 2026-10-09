<div id="br-plumbing-fields" class="br-plumbing-fields">
  <x-admin.info-tip>These answers are scope for a qualified plumber — they are not Zanini labour or materials. Type their quote as priced lines below.</x-admin.info-tip>
  @include('admin.partials.checkbox', [
    'name' => 'include_plumbing',
    'hiddenFallback' => true,
    'checked' => $brOn('include_plumbing'),
    'label' => 'Include plumbing rough-in',
    'info' => $brInfo['include_plumbing'],
    'inputClass' => 'admin-checkbox__input br-preview',
    'attrs' => ['data-br-reveal' => 'br-plumb-fields'],
    'clientFilled' => isset($brFilled) && $brFilled('include_plumbing'),
  ])
  <div id="br-plumb-fields" class="br-check-grid br-check-grid--inset" @unless($brOn('include_plumbing')) hidden @endunless>
    @include('admin.partials.checkbox', ['name' => 'bath_rough_in', 'hiddenFallback' => true, 'checked' => $brOn('bath_rough_in'), 'label' => 'Bathroom rough-in', 'info' => $brInfo['bath_rough_in'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => isset($brFilled) && $brFilled('bath_rough_in')])
    @include('admin.partials.checkbox', ['name' => 'sink_laundry', 'hiddenFallback' => true, 'checked' => $brOn('sink_laundry'), 'label' => 'Sink / laundry hookup', 'info' => $brInfo['sink_laundry'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => isset($brFilled) && $brFilled('sink_laundry')])
    @include('admin.partials.checkbox', ['name' => 'wet_bar', 'hiddenFallback' => true, 'checked' => $brOn('wet_bar'), 'label' => 'Wet bar', 'info' => $brInfo['wet_bar'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => isset($brFilled) && $brFilled('wet_bar')])
    @include('admin.partials.checkbox', ['name' => 'floor_drain', 'hiddenFallback' => true, 'checked' => $brOn('floor_drain'), 'label' => 'Floor drain', 'info' => $brInfo['floor_drain'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => isset($brFilled) && $brFilled('floor_drain')])
    @include('admin.partials.checkbox', ['name' => 'wh_reloc', 'hiddenFallback' => true, 'checked' => $brOn('wh_reloc'), 'label' => 'Water heater relocation', 'info' => $brInfo['wh_reloc'], 'inputClass' => 'admin-checkbox__input br-preview', 'clientFilled' => isset($brFilled) && $brFilled('wh_reloc')])
  </div>
  @include('admin.quotations._amount_lines', [
    'field' => 'plumbing_lines',
    'idPrefix' => 'br-plumb-line',
    'cardTitle' => "Plumber's quote",
    'intro' => 'Priced plumbing work from the qualified plumber. Each filled line appears on the client quotation.',
    'addLabel' => 'Add line',
    'placeholder' => 'e.g. Bath rough-in, laundry',
    'rows' => is_array($inputs['plumbing_lines'] ?? null) ? $inputs['plumbing_lines'] : [],
    'inputClass' => 'br-preview',
    'eventName' => 'trade-lines:change',
    'summaryMode' => 'tax-in',
    'defaultHstIncluded' => false,
  ])
</div>
