{{-- Commute and debris stack full width of the form column on every viewport. --}}
<div class="q-expense-pair">
  @include('admin.quotations._commute_expense', [
    'quotation' => $quotation ?? null,
    'linkProject' => $linkProject ?? null,
  ])
  @include('admin.quotations._debris_removal', [
    'quotation' => $quotation ?? null,
  ])
</div>
