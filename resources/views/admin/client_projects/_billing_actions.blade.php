{{-- Admin-only billing shortcuts. Expects $project. --}}
@php
  $billingCompact = $billingCompact ?? false;
  $canPrepareBilling = ! $project->isHourly()
      && $project->isAccepted()
      && ! $project->excludesInvoicesFromReceivables();
  $hasInstallmentPlan = $project->installments->isNotEmpty();
@endphp
@if ($canPrepareBilling)
  <div class="cp-billing-actions {{ $billingCompact ? 'cp-billing-actions--compact' : '' }}">
    <a href="{{ route('admin.client-projects.billing.prepare', $project) }}"
       class="admin-btn admin-btn--primary {{ $billingCompact ? 'admin-btn--sm' : '' }}"
       data-cp-prepare-billing>
      <i class="fa fa-list-alt" aria-hidden="true"></i>
      Prepare billing
    </a>
    @if ($hasInstallmentPlan)
      <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
        @csrf
        <input type="hidden" name="mode" value="next">
        <button type="submit"
                class="admin-btn admin-btn--ghost {{ $billingCompact ? 'admin-btn--sm' : '' }}"
                data-cp-next-installment
                title="Create invoice for next installment">
          <i class="fa fa-file-text-o" aria-hidden="true"></i>
          <span>{{ $billingCompact ? 'Next' : 'Create invoice for next installment' }}</span>
        </button>
      </form>
    @endif
    <x-admin.info-tip label="Prepare billing">
      Review the accepted quotation’s installment terms and due dates, then create <strong>draft</strong> invoices.
      Drafts appear on Financials and Invoices. Nothing is issued, emailed, or charged until you send from the invoice.
    </x-admin.info-tip>
  </div>
@endif
