<div id="br-plumbing-fields" class="br-plumbing-fields">
  @include('admin.partials.checkbox', [
    'name' => 'include_plumbing',
    'hiddenFallback' => true,
    'checked' => $brOn('include_plumbing'),
    'label' => 'Include plumbing rough-in',
    'info' => $brInfo['include_plumbing'],
    'inputClass' => 'admin-checkbox__input br-preview',
    'attrs' => ['data-br-reveal' => 'br-plumb-fields'],
  ])
  <div id="br-plumb-fields" class="br-check-grid br-check-grid--inset" @unless($brOn('include_plumbing')) hidden @endunless>
    @include('admin.partials.checkbox', ['name' => 'bath_rough_in', 'hiddenFallback' => true, 'checked' => $brOn('bath_rough_in'), 'label' => 'Bathroom rough-in', 'info' => $brInfo['bath_rough_in'], 'inputClass' => 'admin-checkbox__input br-preview'])
    @include('admin.partials.checkbox', ['name' => 'sink_laundry', 'hiddenFallback' => true, 'checked' => $brOn('sink_laundry'), 'label' => 'Sink / laundry hookup', 'info' => $brInfo['sink_laundry'], 'inputClass' => 'admin-checkbox__input br-preview'])
    @include('admin.partials.checkbox', ['name' => 'wet_bar', 'hiddenFallback' => true, 'checked' => $brOn('wet_bar'), 'label' => 'Wet bar', 'info' => $brInfo['wet_bar'], 'inputClass' => 'admin-checkbox__input br-preview'])
    @include('admin.partials.checkbox', ['name' => 'floor_drain', 'hiddenFallback' => true, 'checked' => $brOn('floor_drain'), 'label' => 'Floor drain', 'info' => $brInfo['floor_drain'], 'inputClass' => 'admin-checkbox__input br-preview'])
    @include('admin.partials.checkbox', ['name' => 'wh_reloc', 'hiddenFallback' => true, 'checked' => $brOn('wh_reloc'), 'label' => 'Water heater relocation', 'info' => $brInfo['wh_reloc'], 'inputClass' => 'admin-checkbox__input br-preview'])
  </div>
</div>
