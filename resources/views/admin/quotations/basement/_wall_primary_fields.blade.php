{{-- Primary new-wall inputs; relocated between Overview (wall preset) and Framing. --}}
<div id="br-wall-primary-fields" class="br-wall-primary-fields">
  <div class="admin-form-group">
    <label for="br-partition">@include('admin.partials.info_tip', ['text' => $brInfo['partition_lf']]) @include('admin.quotations.basement._client_filled', ['key' => 'partition_lf', 'filledKeys' => $filledKeys ?? []]) New wall (lin. ft)</label>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-partition" name="partition_lf"
           value="{{ $brVal('partition_lf', 0) }}"
           title="Interior walls only. Height uses the ceiling height from Overview.">
  </div>
  <div class="admin-form-group" data-br-door-field>
    <div data-br-door-toggle-ui @if(($scopePreset ?? '') !== 'wall') hidden @endif>
      <label class="admin-checkbox" for="br-wall-include-door">
        <input type="checkbox"
               id="br-wall-include-door"
               class="admin-checkbox__input br-preview"
               data-br-wall-door="1"
               @checked((int) $brVal('framing_door_openings', 0) > 0 || (int) $brVal('door_qty', 0) > 0)>
        <span class="admin-checkbox__mark" aria-hidden="true"></span>
        @include('admin.partials.info_tip', ['text' => $brInfo['wall_include_door'] ?? null])
        @if ($brFilled('framing_door_openings') || $brFilled('door_qty'))
          @include('admin.quotations.basement._client_filled', ['force' => true])
        @endif
        <span class="admin-checkbox__text">Include a door</span>
      </label>
    </div>
    <div data-br-door-count-ui @if(($scopePreset ?? '') === 'wall') hidden @endif>
      <label for="br-frame-doors">@include('admin.partials.info_tip', ['text' => $brInfo['framing_door_openings']]) @include('admin.quotations.basement._client_filled', ['key' => 'framing_door_openings', 'filledKeys' => $filledKeys ?? []]) Doors to frame</label>
      <input type="number" step="1" min="0" class="admin-input br-preview" id="br-frame-doors" name="framing_door_openings"
             value="{{ $brVal('framing_door_openings', 0) }}">
    </div>
  </div>
</div>
