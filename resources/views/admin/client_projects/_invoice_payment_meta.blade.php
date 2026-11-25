{{-- Method and reference recorded when the invoice was marked paid. Blank for older rows. --}}
@if (! empty($invoice) && ($paymentMeta = $invoice->paymentDetailsLabel()))
  <div class="cp-invoice-payment-meta">{{ $paymentMeta }}</div>
@endif
