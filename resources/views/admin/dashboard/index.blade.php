@extends('admin.layouts.app')

@php
  use App\Models\ClientProject;
  $p = $d['projection'];
  $y = $d['ytd'];
  $c = $d['cards'];
@endphp

@section('heading', 'Dashboard')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v={{ @filemtime(public_path('css/admin-dashboard.css')) ?: time() }}">
@endpush

@section('content')
  <div class="dash-page">
    <div class="dash-toolbar">
      <div>
        <p class="dash-toolbar-meta" style="margin:0 0 4px;">
          Snapshot for {{ \Illuminate\Support\Carbon::parse($d['generated_at'])->timezone(config('app.timezone'))->format('l, M j, Y g:i A T') }}
        </p>
        <strong style="font-size:1.05rem;">Business overview</strong>
      </div>
      <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <a href="{{ route('admin.dashboard', ['refresh' => 1]) }}" class="admin-btn admin-btn--secondary admin-btn--sm" title="Bypass cache and recompute figures">
          <i class="fa fa-refresh" aria-hidden="true"></i> Refresh data
        </a>
        @if (!empty($refreshed))
          <span class="dash-badge">Updated</span>
        @endif
      </div>
    </div>

    @if (!empty($d['material_alert']))
      <div class="dash-alert-banner" role="status">
        <span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $d['material_alert']['message'] }}</span>
        <a href="{{ route($d['material_alert']['route']) }}">Open fence config →</a>
      </div>
    @endif

    {{-- Summary cards --}}
    <div class="dash-grid dash-grid--cards" style="margin-bottom:1.25rem;">
      <div class="dash-card dash-card--stat" title="Projects not completed or rejected, with contract total where set.">
        <span class="dash-stat-label">Active projects <span class="dash-tip" title="Excludes completed & rejected">ⓘ</span></span>
        <span class="dash-stat-value">{{ $c['active_projects_count'] }}</span>
        <span class="dash-stat-sub">Contract total {{ ClientProject::formatMoney($c['active_projects_value']) }}</span>
      </div>
      <div class="dash-card dash-card--stat" title="Sum of totals on sent, unpaid invoices.">
        <span class="dash-stat-label">Outstanding receivables</span>
        <span class="dash-stat-value">{{ ClientProject::formatMoney($c['outstanding_receivables']) }}</span>
        <span class="dash-stat-sub">Sent invoices awaiting payment</span>
      </div>
      <div class="dash-card dash-card--stat" title="Cash recorded from invoices marked paid this calendar month.">
        <span class="dash-stat-label">Cash this month</span>
        <span class="dash-stat-value">{{ ClientProject::formatMoney($c['cash_this_month']) }}</span>
        <span class="dash-stat-sub">Paid invoice amounts (MTD)</span>
      </div>
      <div class="dash-card dash-card--stat" title="Average total_amount on accepted projects that have a value.">
        <span class="dash-stat-label">Avg project value</span>
        <span class="dash-stat-value">{{ ClientProject::formatMoney($c['average_project_value']) }}</span>
        <span class="dash-stat-sub">Accepted projects with totals</span>
      </div>
      <div class="dash-card dash-card--stat" title="(YTD paid invoices − expenses + returns) ÷ YTD paid invoices.">
        <span class="dash-stat-label">Gross margin (YTD)</span>
        <span class="dash-stat-value">
          @if ($c['gross_margin_ytd_pct'] !== null)
            {{ $c['gross_margin_ytd_pct'] }}%
          @else
            —
          @endif
        </span>
        <span class="dash-stat-sub">Requires expense tracking</span>
      </div>
      <div class="dash-card dash-card--stat">
        <span class="dash-stat-label">Overdue invoices</span>
        <span class="dash-stat-value">{{ $c['overdue_invoice_count'] }}</span>
        <span class="dash-stat-sub">Reminders sent (all time): {{ $c['reminders_sent_all_time'] }}</span>
      </div>
    </div>

    <div class="dash-panel dash-panel--split dash-panel--split-2">
      <div>
        <h2 class="dash-section-title">
          Profit projection — next 6 months
          <span class="dash-section-note">{{ $p['disclaimer'] }}</span>
        </h2>
        <div class="dash-chart-wrap">
          <canvas id="dash-chart-projection" aria-label="Projected revenue and profit chart"></canvas>
        </div>
        <div class="dash-kpi-row">
          <span title="Sum of projected collections across the chart window."><strong>6-mo projected revenue:</strong> {{ ClientProject::formatMoney($p['forward_total_revenue']) }}</span>
          <span title="Rough profit using YTD net margin on projected revenue."><strong>Projected profit:</strong> {{ ClientProject::formatMoney($p['forward_total_profit']) }}</span>
        </div>
        <div class="dash-kpi-row">
          <span><strong>YTD collected (invoices):</strong> {{ ClientProject::formatMoney($y['collected_invoice_basis']) }}</span>
          <span><strong>YTD + 6-mo projection:</strong> {{ ClientProject::formatMoney($d['ytd_plus_forward_revenue']) }}</span>
        </div>
      </div>
      <div>
        <h2 class="dash-section-title">
          {{ $y['year'] }} year-to-date
          <span class="dash-section-note">vs {{ $y['previous_year_label'] }} full year {{ ClientProject::formatMoney($y['previous_year_revenue']) }}</span>
        </h2>
        <div class="dash-chart-wrap dash-chart-wrap--sm">
          <canvas id="dash-chart-ytd" aria-label="Monthly revenue and costs"></canvas>
        </div>
        <div class="dash-kpi-row">
          <span><strong>Revenue:</strong> {{ ClientProject::formatMoney($y['revenue']) }}</span>
          <span><strong>Costs (net):</strong> {{ ClientProject::formatMoney($y['costs']) }}</span>
          <span><strong>Net profit:</strong> {{ ClientProject::formatMoney($y['profit']) }}</span>
        </div>
      </div>
    </div>

    <div class="dash-two-col">
      <div class="dash-panel">
        <h2 class="dash-section-title">Quick actions</h2>
        <div class="dash-quick-actions">
          @foreach ($d['quick_actions'] as $action)
            <a class="dash-action-btn dash-action-btn--{{ $action['variant'] }}" href="{{ route($action['route'], $action['query'] ?? []) }}">
              <i class="fa {{ $action['icon'] }}" aria-hidden="true"></i> {{ $action['label'] }}
            </a>
          @endforeach
          <a class="dash-action-btn dash-action-btn--secondary" href="{{ route('admin.client-projects.index') }}#invoice" title="Open a project, then use the Invoices section">
            <i class="fa fa-file-text-o" aria-hidden="true"></i> Create / send invoice
          </a>
        </div>
      </div>
      <div class="dash-panel">
        <h2 class="dash-section-title">Pipeline</h2>
        <div class="dash-pipeline">
          <div>
            <strong>{{ $d['pipeline']['open_quotations_count'] }}</strong> open project quotations<br>
            <span class="dash-stat-sub">Potential value {{ ClientProject::formatMoney($d['pipeline']['open_quotations_potential']) }} (incl. HST where quoted)</span>
          </div>
          <div>
            <strong>{{ $d['pipeline']['standalone_fence_count'] }}</strong> standalone fence quotes<br>
            <span class="dash-stat-sub">Grand total {{ ClientProject::formatMoney($d['pipeline']['standalone_fence_value']) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="dash-panel">
      <h2 class="dash-section-title">Project status overview</h2>
      <p class="dash-section-note" style="margin:-0.5rem 0 1rem;">Click a status to filter the project list.</p>
      <div class="dash-status-grid">
        @foreach ($d['status_overview'] as $row)
          <a href="{{ route('admin.client-projects.index', ['status' => $row['key']]) }}" class="dash-status-pill" style="border-left:4px solid {{ $row['color'] }};">
            <div class="dash-status-count">{{ $row['count'] }}</div>
            <div class="dash-status-label">{{ $row['label'] }}</div>
          </a>
        @endforeach
      </div>
    </div>

    <div class="dash-two-col">
      <div class="dash-panel">
        <h2 class="dash-section-title">Upcoming (30 days)</h2>
        @if (count($d['upcoming']) === 0)
          <p class="dash-empty">No project start or end dates in the next 30 days.</p>
        @else
          <div style="overflow-x:auto;">
            <table class="dash-table">
              <thead>
                <tr>
                  <th>When</th>
                  <th>Project</th>
                  <th>Address</th>
                  <th>Status</th>
                  <th>Next payment</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($d['upcoming'] as $row)
                  <tr>
                    <td>{{ $row['date_display'] }}<br><span class="dash-badge">{{ $row['kind'] }}</span></td>
                    <td><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></td>
                    <td>{{ \Illuminate\Support\Str::limit($row['address'] ?? '—', 42) }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['next_payment'] ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        <p style="margin:0.75rem 0 0;font-size:0.88rem;">
          <a href="{{ route('admin.client-projects.calendar') }}" class="admin-btn admin-btn--secondary admin-btn--sm">Full calendar</a>
        </p>
      </div>

      <div class="dash-panel dash-panel--attention">
        <h2 class="dash-section-title">Attention</h2>
        @if (count($d['attention']['alerts']) === 0)
          <p class="dash-empty">No overdue invoices or behind-schedule flags on active projects.</p>
        @else
          <ul class="dash-attention-list">
            @foreach ($d['attention']['alerts'] as $row)
              <li>
                <a class="dash-link" href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                <span class="dash-attention-meta">— {{ implode('; ', $row['reasons']) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
        @if (count($d['attention']['high_value']) > 0)
          <h3 class="dash-attention-subheading">Top contract values</h3>
          <ul class="dash-attention-list dash-attention-list--compact">
            @foreach ($d['attention']['high_value'] as $row)
              <li>
                <a class="dash-link" href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                <span class="dash-attention-meta">— {{ ClientProject::formatMoney($row['value']) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </div>
    </div>

    <div class="dash-two-col">
      <div class="dash-panel">
        <h2 class="dash-section-title">Overdue invoices</h2>
        @if (count($d['overdue_invoices']) === 0)
          <p class="dash-empty">No overdue sent invoices with a due date.</p>
        @else
          <div style="overflow-x:auto;">
            <table class="dash-table">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Due</th>
                  <th>Total</th>
                  <th>Project</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach ($d['overdue_invoices'] as $row)
                  <tr>
                    <td>{{ $row['invoice_number'] }}</td>
                    <td>{{ $row['due_display'] }}</td>
                    <td>{{ ClientProject::formatMoney($row['total']) }}</td>
                    <td>
                      @if ($row['project_id'])
                        <a href="{{ route('admin.client-projects.show', $row['project_id']) }}#invoice">{{ $row['project_name'] }}</a>
                      @else
                        —
                      @endif
                    </td>
                    <td>
                      @if (!empty($row['reminder_url']))
                        <form method="post" action="{{ $row['reminder_url'] }}" class="dash-inline-form" onsubmit="return confirm('Send payment reminder for {{ $row['invoice_number'] }}?');">
                          @csrf
                          <button type="submit" class="admin-btn admin-btn--sm admin-btn--secondary">Remind</button>
                        </form>
                      @endif
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>

      <div class="dash-panel dash-panel--recent-activity">
        <h2 class="dash-section-title">Recent activity</h2>
        @if (count($d['activity']) === 0)
          <p class="dash-empty">No history entries yet.</p>
        @else
          @foreach ($d['activity'] as $row)
            <div class="dash-activity-item">
              <div class="dash-activity-time">{{ $row['changed_display'] }}</div>
              @if ($row['url'])
                <div class="dash-activity-line">
                  <a class="dash-link" href="{{ $row['url'] }}">{{ $row['project_name'] }}</a>
                  <span class="dash-activity-meta"> — {{ \Illuminate\Support\Str::limit($row['note'], 220) }}</span>
                </div>
              @else
                <div class="dash-activity-line dash-activity-line--solo">{{ \Illuminate\Support\Str::limit($row['note'], 220) }}</div>
              @endif
            </div>
          @endforeach
        @endif
      </div>
    </div>
  </div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
  <script>
    (function () {
      var primary = getComputedStyle(document.documentElement).getPropertyValue('--admin-primary').trim() || '#024768';
      var success = getComputedStyle(document.documentElement).getPropertyValue('--admin-success').trim() || '#2e7d4a';
      var danger = getComputedStyle(document.documentElement).getPropertyValue('--admin-danger').trim() || '#c62828';

      var proj = @json($p);
      var ytd = @json($y);

      var elP = document.getElementById('dash-chart-projection');
      if (elP && typeof Chart !== 'undefined') {
        new Chart(elP, {
          type: 'bar',
          data: {
            labels: proj.labels,
            datasets: [
              {
                label: 'Projected revenue (CAD)',
                data: proj.revenue,
                order: 2,
                backgroundColor: 'rgba(2, 71, 104, 0.35)',
                borderColor: primary,
                borderWidth: 1,
                yAxisID: 'y',
              },
              {
                label: 'Projected profit (est.)',
                data: proj.profit,
                type: 'line',
                order: 1,
                borderColor: success,
                backgroundColor: 'rgba(46, 125, 74, 0.08)',
                tension: 0.25,
                fill: false,
                yAxisID: 'y',
              },
              {
                label: 'Cumulative revenue',
                data: proj.cumulative_revenue,
                type: 'line',
                order: 0,
                borderColor: danger,
                borderDash: [4, 4],
                tension: 0.2,
                fill: false,
                yAxisID: 'y1',
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: function (ctx) {
                    var v = ctx.parsed.y;
                    if (v == null && ctx.parsed !== undefined && ctx.parsed.r != null) v = ctx.parsed.r;
                    var n = typeof v === 'number' ? v : parseFloat(v);
                    return ctx.dataset.label + ': CA$' + n.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                  },
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                position: 'left',
                ticks: {
                  callback: function (value) {
                    return 'CA$' + Number(value).toLocaleString('en-CA', { maximumFractionDigits: 0 });
                  },
                },
              },
              y1: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                ticks: {
                  callback: function (value) {
                    return 'CA$' + Number(value).toLocaleString('en-CA', { maximumFractionDigits: 0 });
                  },
                },
              },
            },
          },
        });
      }

      var elY = document.getElementById('dash-chart-ytd');
      if (elY && typeof Chart !== 'undefined') {
        new Chart(elY, {
          type: 'bar',
          data: {
            labels: ytd.month_labels,
            datasets: [
              {
                label: 'Revenue (paid invoices)',
                data: ytd.month_revenue,
                backgroundColor: 'rgba(2, 71, 104, 0.4)',
                borderColor: primary,
                borderWidth: 1,
              },
              {
                label: 'Costs (expenses − returns)',
                data: ytd.month_costs.map(function (c) { return Math.max(0, c); }),
                backgroundColor: 'rgba(198, 40, 40, 0.2)',
                borderColor: danger,
                borderWidth: 1,
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: function (ctx) {
                    var n = ctx.parsed.y;
                    return ctx.dataset.label + ': CA$' + n.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                  },
                },
              },
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: function (value) {
                    return 'CA$' + Number(value).toLocaleString('en-CA', { maximumFractionDigits: 0 });
                  },
                },
              },
            },
          },
        });
      }
    })();
  </script>
@endpush
