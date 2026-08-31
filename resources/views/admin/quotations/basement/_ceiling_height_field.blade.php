<div class="admin-form-group br-lw__companion-field">
  <label for="br-height">
    @include('admin.partials.info_tip', ['text' => 'Used to estimate wall square footage for insulation, drywall, and paint.'])
    Ceiling height (ft)
  </label>
  <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-height" name="ceiling_height_ft"
         value="{{ $value }}"
         title="Used to estimate wall square footage for insulation, drywall, and paint.">
</div>
