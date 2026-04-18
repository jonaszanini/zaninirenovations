@php
  $revenue = $project->totalRevenueCollected();
  $spent = $project->totalSpent();
  $returns = $project->totalReturnsAmount();
  $gross = $project->grossProfit();
  $hst = $project->hstToSave();
  $netAfterHst = $project->netProfitAfterHst();
  $totalHours = $project->totalHours();
  $hourlyRate = $project->effectiveHourlyRate();
@endphp

<div class="admin-card cp-final-summary">
  <h3><i class="fa fa-check-circle"></i> Project completed — Final summary</h3>
  <p class="admin-form-help">All figures below are calculated from paid installments, expenses, returns, and logged time. A thank-you + review request was sent to the client (if an email was on file).</p>

  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total revenue</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($revenue) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total spent</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($spent) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Returns</div>
      <div class="cp-profit-value">−{{ \App\Models\ClientProject::formatMoney($returns) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Gross profit</div>
      <div class="cp-profit-value {{ $gross >= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($gross) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">HST to save (13%)</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($hst) }}</div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value {{ $netAfterHst >= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($netAfterHst) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total hours</div>
      <div class="cp-profit-value">{{ number_format($totalHours, 2) }}</div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Effective hourly rate</div>
      <div class="cp-profit-value">{{ $hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h' }}</div>
    </div>
  </div>

  <div class="cp-final-dates">
    <div><strong>Start:</strong> {{ \App\Models\ClientProject::formatDate($project->start_date) }}</div>
    <div><strong>End:</strong> {{ \App\Models\ClientProject::formatDate($project->end_date) }}</div>
    <div><strong>Total amount:</strong> {{ \App\Models\ClientProject::formatMoney($project->total_amount) }}</div>
    <div><strong>Paid:</strong> {{ number_format($project->percentPaid(), 1) }}%</div>
  </div>
</div>
