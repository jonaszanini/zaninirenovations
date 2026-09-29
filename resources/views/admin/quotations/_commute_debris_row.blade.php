{{-- Commute and debris sit side by side from the 900px admin breakpoint. --}}
<div class="q-expense-pair">
  @include('admin.quotations._commute_expense', [
    'quotation' => $quotation ?? null,
    'linkProject' => $linkProject ?? null,
  ])
  @include('admin.quotations._debris_removal', [
    'quotation' => $quotation ?? null,
  ])
</div>
