<div class="admin-card q-additional-costs-card">
  @include('admin.quotations._amount_lines', [
    'field' => 'additional_costs',
    'idPrefix' => 'q-ac',
    'title' => 'Additional costs',
    'intro' => 'Optional extras (permits, rentals, extra labour, etc.). Choose whether each line is <strong>materials</strong> or <strong>labour</strong>. Hidden from the client PDF — folded into that subtotal.',
    'addLabel' => 'Add cost',
    'placeholder' => 'e.g. Dumpster rental, permits',
    'rows' => ($quotation ?? null) && is_array(($quotation->additional_costs ?? null)) ? $quotation->additional_costs : [],
    'isAdditionalCard' => true,
    'wrapClass' => '',
    'eventName' => 'additional-costs:change',
    'summaryMode' => 'split',
    'showCategory' => true,
  ])
</div>

<style>
  .q-additional-costs-card { margin-bottom: 16px; }
</style>
