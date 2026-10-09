<div class="admin-form-group br-lw__companion-field">
  @include('admin.partials.tipped_label', [
    'label' => 'Ceiling height (ft)',
    'for' => 'br-height',
    'tipText' => 'Used to estimate wall square footage for insulation, drywall, and paint.',
    'tipLabel' => 'Ceiling height (ft)',
    'prefixHtml' => view('admin.quotations.basement._client_filled', [
      'key' => 'ceiling_height_ft',
      'filledKeys' => $filledKeys ?? [],
    ])->render(),
  ])
  <input type="number" step="0.01" min="0" class="admin-input br-preview" id="br-height" name="ceiling_height_ft"
         value="{{ $value }}"
         title="Used to estimate wall square footage for insulation, drywall, and paint.">
</div>
