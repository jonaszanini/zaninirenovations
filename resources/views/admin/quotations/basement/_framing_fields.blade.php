<div id="br-framing-fields" class="br-framing-fields admin-form-grid-4">
  <div class="admin-form-group">
    <label for="br-partition">@include('admin.partials.info_tip', ['text' => $brInfo['partition_lf']]) @include('admin.quotations.basement._client_filled', ['key' => 'partition_lf', 'filledKeys' => $filledKeys ?? []]) New wall (linear ft)</label>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-partition" name="partition_lf"
           value="{{ $brVal('partition_lf', 0) }}"
           title="Interior walls only. Height uses the ceiling height from Overview.">
  </div>
  <div class="admin-form-group">
    <label for="br-bulkhead">@include('admin.partials.info_tip', ['text' => $brInfo['bulkhead_lf']]) Work around duct (linear ft)</label>
    <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-bulkhead" name="bulkhead_lf"
           value="{{ $brVal('bulkhead_lf', 0) }}">
  </div>
  <div class="admin-form-group">
    <label for="br-headers">@include('admin.partials.info_tip', ['text' => $brInfo['header_qty']]) Header / beam openings</label>
    <input type="number" step="1" min="0" class="admin-input br-preview" id="br-headers" name="header_qty"
           value="{{ $brVal('header_qty', 0) }}">
  </div>
  <div class="admin-form-group">
    <label for="br-frame-doors">@include('admin.partials.info_tip', ['text' => $brInfo['framing_door_openings']]) Door openings to frame</label>
    <input type="number" step="1" min="0" class="admin-input br-preview" id="br-frame-doors" name="framing_door_openings"
           value="{{ $brVal('framing_door_openings', 0) }}">
  </div>
</div>
