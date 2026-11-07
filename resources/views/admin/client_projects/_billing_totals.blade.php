{{-- Compact scheduled / draft / issued / paid / outstanding strip.
     Drafts are listed separately so they never look like receivables. --}}
@php
  $billingTotals = $billingTotals ?? [];
@endphp
<div class="cp-billing-totals" role="group" aria-label="Billing status">
  <div class="cp-hourly-summary-tile">
    <div class="cp-hourly-summary-label">Scheduled</div>
    <div class="cp-hourly-summary-value">{{ \App\Models\ClientProject::formatMoney($billingTotals['scheduled'] ?? 0) }}</div>
    <div class="cp-hourly-summary-sub">Payment plan</div>
  </div>
  <div class="cp-hourly-summary-tile">
    <div class="cp-hourly-summary-label">Draft</div>
    <div class="cp-hourly-summary-value">{{ \App\Models\ClientProject::formatMoney($billingTotals['draft'] ?? 0) }}</div>
    <div class="cp-hourly-summary-sub">Not issued</div>
  </div>
  <div class="cp-hourly-summary-tile">
    <div class="cp-hourly-summary-label">Issued</div>
    <div class="cp-hourly-summary-value">{{ \App\Models\ClientProject::formatMoney($billingTotals['issued'] ?? 0) }}</div>
    <div class="cp-hourly-summary-sub">Sent, unpaid</div>
  </div>
  <div class="cp-hourly-summary-tile">
    <div class="cp-hourly-summary-label">Paid</div>
    <div class="cp-hourly-summary-value">{{ \App\Models\ClientProject::formatMoney($billingTotals['paid'] ?? 0) }}</div>
    <div class="cp-hourly-summary-sub">Collected</div>
  </div>
  <div class="cp-hourly-summary-tile cp-hourly-summary-tile--highlight">
    <div class="cp-hourly-summary-label">Outstanding</div>
    <div class="cp-hourly-summary-value">{{ \App\Models\ClientProject::formatMoney($billingTotals['outstanding'] ?? 0) }}</div>
    <div class="cp-hourly-summary-sub">Issued only</div>
  </div>
</div>
