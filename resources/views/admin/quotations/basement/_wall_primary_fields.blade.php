{{-- Primary new-wall inputs; relocated between Overview (wall preset) and Framing. --}}
<div id="br-wall-primary-fields" class="br-wall-primary-fields">
  <div class="admin-form-group">
    <span class="admin-label-with-tip">
  <label for="br-partition">@include('admin.quotations.basement._client_filled', ['key' => 'partition_lf', 'filledKeys' => $filledKeys ?? []]) New wall (lin. ft)</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['partition_lf'], 'tipLabel' => 'New wall (lin. ft)'])
</span>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-partition" name="partition_lf"
           value="{{ $brVal('partition_lf', 0) }}"
           title="Interior walls only. Height uses the ceiling height from Overview.">
  </div>
  <div class="admin-form-group" data-br-door-field>
    <div data-br-door-toggle-ui @if(($scopePreset ?? '') !== 'wall') hidden @endif>
      @include('admin.partials.checkbox', [
        'name' => '',
        'id' => 'br-wall-include-door',
        'checked' => (int) $brVal('framing_door_openings', 0) > 0 || (int) $brVal('door_qty', 0) > 0,
        'label' => 'Include a door',
        'info' => $brInfo['wall_include_door'] ?? null,
        'inputClass' => 'admin-checkbox__input br-preview',
        'clientFilled' => isset($brFilled) && ($brFilled('framing_door_openings') || $brFilled('door_qty')),
        'attrs' => ['data-br-wall-door' => '1'],
      ])
    </div>
    <div data-br-door-count-ui @if(($scopePreset ?? '') === 'wall') hidden @endif>
      <span class="admin-label-with-tip">
  <label for="br-frame-doors">@include('admin.quotations.basement._client_filled', ['key' => 'framing_door_openings', 'filledKeys' => $filledKeys ?? []]) Doors to frame</label>
  @include('admin.partials.info_tip', ['tipText' => $brInfo['framing_door_openings'], 'tipLabel' => 'Doors to frame'])
</span>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-frame-doors" name="framing_door_openings"
             value="{{ $brVal('framing_door_openings', 0) }}">
    </div>
  </div>
</div>
