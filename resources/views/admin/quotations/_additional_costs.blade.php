<div class="admin-card q-additional-costs-card">
  @include('admin.quotations._amount_lines', [
    'field' => 'additional_costs',
    'idPrefix' => 'q-ac',
    'title' => 'Additional costs',
    'intro' => 'Optional extras (permits, rentals, etc.). Added into the <strong>materials</strong> subtotal and grand total. Hidden from the client PDF.',
    'addLabel' => 'Add cost',
    'placeholder' => 'e.g. Dumpster rental, permits',
    'rows' => ($quotation ?? null) && is_array(($quotation->additional_costs ?? null)) ? $quotation->additional_costs : [],
    'isAdditionalCard' => true,
    'wrapClass' => '',
    'eventName' => 'additional-costs:change',
    'summaryMode' => 'materials',
  ])
</div>

<style>
  .q-additional-costs-card { margin-bottom: 16px; }
</style>
