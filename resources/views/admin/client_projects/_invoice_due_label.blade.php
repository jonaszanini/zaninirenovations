{{-- Due date under an invoice status badge. Null stays "Upon completion". --}}
@if (! empty($invoice))
  <div class="cp-invoice-due">Due: {{ $invoice->dueOnLabel() }}</div>
@endif
