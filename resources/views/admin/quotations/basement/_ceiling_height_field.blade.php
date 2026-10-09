<div class="admin-form-group br-lw__companion-field">
  <span class="admin-label-with-tip">
  <label for="br-height">@include('admin.quotations.basement._client_filled', ['key' => 'ceiling_height_ft', 'filledKeys' => $filledKeys ?? []])
    Ceiling height (ft)</label>
  @include('admin.partials.info_tip', ['tipText' => 'Used to estimate wall square footage for insulation, drywall, and paint.', 'tipLabel' => 'Ceiling height (ft)'])
</span>
  <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-height" name="ceiling_height_ft"
         value="{{ $value }}"
         title="Used to estimate wall square footage for insulation, drywall, and paint.">
</div>
