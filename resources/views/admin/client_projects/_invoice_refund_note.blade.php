@if (! empty($invoice) && $invoice->hasRefund() && ! $invoice->isRefunded())
  <div class="admin-form-help" style="margin-top:4px;">
    Refunded {{ \App\Models\ClientProject::formatMoney($invoice->refundCash()) }}
  </div>
@endif
