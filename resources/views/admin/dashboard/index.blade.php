@extends('admin.layouts.app')

@php
  use App\Models\ClientProject;
  $p = $d['projection'];
  $y = $d['ytd'];
  $c = $d['cards'];
  $pipeline = $d['pipeline'];
  $openQuoteRows = $pipeline['open_quotation_rows'] ?? [];
  $fenceRows = $pipeline['standalone_fence_rows'] ?? [];
@endphp

@section('heading', 'Dashboard')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v={{ @filemtime(public_path('css/admin-dashboard.css')) ?: time() }}">
@endpush

@section('content')
  <div class="dash-page">
    <div class="dash-toolbar">
      <div>
        <p class="dash-toolbar-meta">Snapshot for {{ \App\Support\EasternStandardTimeDisplay::formatDateTimeMedium($d['generated_at']) }}</p>
        <strong class="dash-toolbar-title">What needs action</strong>
      </div>
      <div class="dash-toolbar-actions">
        <a href="{{ route('admin.dashboard', ['refresh' => 1]) }}" class="admin-btn admin-btn--secondary admin-btn--sm" title="Bypass cache and recompute figures">
          <i class="fa fa-refresh" aria-hidden="true"></i> Refresh data
        </a>
        @if (!empty($refreshed))
          <span class="dash-badge">Updated</span>
        @endif
      </div>
    </div>

    @if (!empty($d['material_alert']))
      <div class="dash-alert-banner" id="dash-material-alert" data-alert-key="{{ $d['material_alert']['updated_at'] }}" role="status">
        <span><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> {{ $d['material_alert']['message'] }}</span>
        <span class="dash-alert-banner__actions">
          <a href="{{ route($d['material_alert']['route']) }}">Open fence config →</a>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-dash-dismiss-alert>Dismiss</button>
        </span>
      </div>
      <script>
        (function () {
          var el = document.getElementById('dash-material-alert');
          if (!el) return;
          try {
            if (sessionStorage.getItem('zanini-dash-material-alert') === el.getAttribute('data-alert-key')) {
              el.hidden = true;
            }
          } catch (e) {}
        })();
      </script>
    @endif

    {{-- Hero: exactly four action/cash cards --}}
    <div class="dash-grid dash-grid--cards dash-grid--spaced">
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
      <div class="dash-card dash-card--stat {{ ($c['overdue_invoice_count'] ?? 0) > 0 ? 'dash-card--alert' : '' }}" title="Sent invoices past their due date.">
        <span class="dash-stat-label">Overdue</span>
        <span class="dash-stat-value">{{ $c['overdue_invoice_count'] }}</span>
        <span class="dash-stat-sub">
          {{ ClientProject::formatMoney($c['overdue_invoice_total'] ?? 0) }} past due
        </span>
      </div>
      <div class="dash-card dash-card--stat" title="Projects not completed, cancelled, or rejected, with contract total where set.">
        <span class="dash-stat-label">Active projects <span class="dash-tip" title="Excludes completed, cancelled &amp; rejected">ⓘ</span></span>
        <span class="dash-stat-value">{{ $c['active_projects_count'] }}</span>
        <span class="dash-stat-sub">Contract total {{ ClientProject::formatMoney($c['active_projects_value']) }}</span>
      </div>
    </div>

    {{-- Do today: overdue invoices + attention, above charts --}}
    <h2 class="dash-block-title">Do today</h2>
    @if (($d['pending_etransfers'] ?? 0) > 0)
      <div class="dash-panel dash-etransfer-today">
        <div class="dash-etransfer-today__copy">
          <strong>{{ $d['pending_etransfers'] }} incoming e-transfer{{ (int) $d['pending_etransfers'] === 1 ? '' : 's' }}</strong>
          waiting to be matched to an invoice.
        </div>
        <a href="{{ route('admin.financials.etransfers') }}" class="admin-btn admin-btn--primary admin-btn--sm">Review e-transfers</a>
      </div>
    @endif
    <div class="dash-two-col dash-two-col--today">
      <div class="dash-panel">
        <h3 class="dash-section-title">Overdue invoices</h3>
        @if (count($d['overdue_invoices']) === 0)
          <p class="dash-empty">No overdue sent invoices with a due date.</p>
        @else
          <ul class="dash-overdue-list">
            @foreach ($d['overdue_invoices'] as $row)
              <li class="dash-overdue-item">
                <div class="dash-overdue-item__info">
                  <div class="dash-overdue-item__top">
                    <span class="dash-overdue-item__inv">{{ $row['invoice_number'] }}</span>
                    <span class="dash-overdue-item__amt">{{ ClientProject::formatMoney($row['total']) }}</span>
                  </div>
                  <div class="dash-overdue-item__meta">
                    Due {{ $row['due_display'] }}
                    @if ($row['project_id'])
                      · <a href="{{ route('admin.client-projects.show', $row['project_id']) }}#invoice">{{ $row['project_name'] }}</a>
                    @endif
                  </div>
                </div>
                @if (!empty($row['reminder_url']))
                  <form method="post" action="{{ $row['reminder_url'] }}" class="dash-overdue-item__action" onsubmit="return confirm('Send payment reminder for {{ $row['invoice_number'] }}?');">
                    @csrf
                    <button type="submit" class="admin-btn admin-btn--sm admin-btn--secondary">Remind</button>
                  </form>
                @endif
              </li>
            @endforeach
          </ul>
        @endif
      </div>

      <div class="dash-panel dash-panel--attention">
        <h3 class="dash-section-title">Attention</h3>
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
          <h4 class="dash-attention-subheading">Top contract values</h4>
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

    {{-- Pipeline / follow-up --}}
    <h2 class="dash-block-title">Pipeline / follow-up</h2>
    <div class="dash-two-col">
      <div class="dash-panel">
        <h3 class="dash-section-title">Who to chase</h3>
        <div class="dash-pipeline dash-pipeline--summary">
          <div>
            <strong>{{ $pipeline['open_quotations_count'] }}</strong> open project quotations<br>
            <span class="dash-stat-sub">Potential {{ ClientProject::formatMoney($pipeline['open_quotations_potential']) }} (incl. HST where quoted)</span>
          </div>
          <div>
            <strong>{{ $pipeline['standalone_fence_count'] }}</strong> standalone fence quotes<br>
            <span class="dash-stat-sub">Grand total {{ ClientProject::formatMoney($pipeline['standalone_fence_value']) }}</span>
          </div>
        </div>

        <h4 class="dash-attention-subheading">Open quotations</h4>
        @if (count($openQuoteRows) === 0)
          <p class="dash-empty">No open visit / quote-stage projects.</p>
        @else
          <ul class="dash-chase-list">
            @foreach ($openQuoteRows as $row)
              <li>
                <a class="dash-link" href="{{ $row['url'] }}">{{ $row['name'] }}</a>
                <span class="dash-attention-meta">
                  — {{ $row['client_name'] ?: 'No client' }}
                  · {{ $row['status'] }}
                  @if (($row['potential'] ?? 0) > 0)
                    · {{ ClientProject::formatMoney($row['potential']) }}
                  @endif
                </span>
              </li>
            @endforeach
          </ul>
        @endif

        <h4 class="dash-attention-subheading">Standalone fence quotes</h4>
        @if (count($fenceRows) === 0)
          <p class="dash-empty">No unlinked fence quotations.</p>
        @else
          <ul class="dash-chase-list">
            @foreach ($fenceRows as $row)
              <li>
                <a class="dash-link" href="{{ $row['url'] }}">{{ $row['quote_number'] }}</a>
                <span class="dash-attention-meta">
                  — {{ $row['client_name'] ?: 'No client' }}
                  · {{ ClientProject::formatMoney($row['grand_total']) }}
                </span>
              </li>
            @endforeach
          </ul>
          <p class="dash-panel-footer-link">
            <a href="{{ route('admin.quotations.fence.index') }}">All fence quotations →</a>
          </p>
        @endif
      </div>

      <div class="dash-panel">
        <h3 class="dash-section-title">Upcoming (30 days)</h3>
        @if (count($d['upcoming']) === 0)
          <p class="dash-empty">No project start or end dates in the next 30 days.</p>
        @else
          <div class="admin-table-scroll">
            <table class="dash-table dash-table--stack">
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
                    <td data-label="When">{{ $row['date_display'] }}<br><span class="dash-badge">{{ $row['kind'] }}</span></td>
                    <td data-label="Project"><a href="{{ $row['url'] }}">{{ $row['name'] }}</a></td>
                    <td data-label="Address">{{ \Illuminate\Support\Str::limit($row['address'] ?? '—', 42) }}</td>
                    <td data-label="Status">{{ $row['status'] }}</td>
                    <td data-label="Next payment">{{ $row['next_payment'] ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
        <p class="dash-panel-footer-link">
          <a href="{{ route('admin.client-projects.calendar') }}" class="admin-btn admin-btn--secondary admin-btn--sm">Full calendar</a>
        </p>
      </div>
    </div>

    {{-- Demoted charts --}}
    <details class="dash-panel dash-finance-glance" id="dash-finance-glance">
      <summary class="dash-finance-glance__summary">
        <span class="dash-finance-glance__title">Finance glance</span>
        <span class="dash-section-note">Profit projection, year-to-date, and margin</span>
      </summary>
      <div class="dash-finance-glance__body">
        <div class="dash-panel--split dash-panel--split-2">
          <div>
            <h3 class="dash-section-title">
              Profit projection — next 6 months
              <span class="dash-section-note">{{ $p['disclaimer'] }}</span>
            </h3>
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
            <h3 class="dash-section-title">
              {{ $y['year'] }} year-to-date
              <span class="dash-section-note">vs {{ $y['previous_year_label'] }} full year {{ ClientProject::formatMoney($y['previous_year_revenue']) }}</span>
            </h3>
            <div class="dash-chart-wrap dash-chart-wrap--sm">
              <canvas id="dash-chart-ytd" aria-label="Monthly revenue and costs"></canvas>
            </div>
            <div class="dash-kpi-row">
              <span><strong>Revenue:</strong> {{ ClientProject::formatMoney($y['revenue']) }}</span>
              <span><strong>Costs (net):</strong> {{ ClientProject::formatMoney($y['costs']) }}</span>
              <span><strong>Net profit:</strong> {{ ClientProject::formatMoney($y['profit']) }}</span>
            </div>
            <div class="dash-kpi-row">
              <span title="(YTD paid invoices − expenses + returns) ÷ YTD paid invoices.">
                <strong>Gross margin (YTD):</strong>
                @if ($c['gross_margin_ytd_pct'] !== null)
                  {{ $c['gross_margin_ytd_pct'] }}%
                @else
                  —
                @endif
              </span>
            </div>
          </div>
        </div>
      </div>
    </details>

    {{-- Footer / secondary --}}
    <div class="dash-secondary">
      <div class="dash-panel">
        <h2 class="dash-section-title">Quick actions</h2>
        <div class="dash-quick-actions">
          @foreach ($d['quick_actions'] as $action)
            <a class="admin-btn admin-btn--{{ $action['variant'] === 'primary' ? 'primary' : 'secondary' }}" href="{{ route($action['route'], $action['query'] ?? []) }}">
              <i class="fa {{ $action['icon'] }}" aria-hidden="true"></i> {{ $action['label'] }}
            </a>
          @endforeach
          <a class="admin-btn admin-btn--secondary" href="{{ route('admin.client-projects.index') }}#invoice" title="Open a project, then use the Invoices section">
            <i class="fa fa-file-text-o" aria-hidden="true"></i> Create / send invoice
          </a>
        </div>
      </div>

      <div class="dash-panel">
        <h2 class="dash-section-title">Project status overview</h2>
        <p class="dash-section-note dash-section-note--block">Click a status to filter the project list.</p>
        <div class="dash-status-grid">
          @foreach ($d['status_overview'] as $row)
            <a href="{{ route('admin.client-projects.index', ['status' => $row['key']]) }}" class="dash-status-pill" style="border-left:4px solid {{ $row['color'] }};">
              <div class="dash-status-count">{{ $row['count'] }}</div>
              <div class="dash-status-label">{{ $row['label'] }}</div>
            </a>
          @endforeach
        </div>
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
      var dismissBtn = document.querySelector('[data-dash-dismiss-alert]');
      var banner = document.getElementById('dash-material-alert');
      if (dismissBtn && banner) {
        dismissBtn.addEventListener('click', function () {
          try {
            sessionStorage.setItem('zanini-dash-material-alert', banner.getAttribute('data-alert-key') || '1');
          } catch (e) {}
          banner.hidden = true;
        });
      }

      var glance = document.getElementById('dash-finance-glance');
      var chartProjection = null;
      var chartYtd = null;

      function chartColors() {
        var isDark = document.documentElement.classList.contains('admin-dark');
        return {
          primary: getComputedStyle(document.documentElement).getPropertyValue('--admin-primary').trim() || '#024768',
          success: getComputedStyle(document.documentElement).getPropertyValue('--admin-success').trim() || '#2e7d4a',
          danger: getComputedStyle(document.documentElement).getPropertyValue('--admin-danger').trim() || '#c62828',
          textColor: isDark ? '#e2e8f0' : '#373435',
          gridColor: isDark ? 'rgba(148, 163, 184, 0.15)' : 'rgba(0, 0, 0, 0.08)',
          barBgColor: isDark ? 'rgba(56, 189, 248, 0.35)' : 'rgba(2, 71, 104, 0.35)',
          barBgColor2: isDark ? 'rgba(56, 189, 248, 0.4)' : 'rgba(2, 71, 104, 0.4)',
        };
      }

      function initCharts() {
        if (typeof Chart === 'undefined') return;

        var colors = chartColors();
        var proj = @json($p);
        var ytd = @json($y);

        var elP = document.getElementById('dash-chart-projection');
        if (elP && !chartProjection) {
          chartProjection = new Chart(elP, {
            type: 'bar',
            data: {
              labels: proj.labels,
              datasets: [
                {
                  label: 'Projected revenue (CAD)',
                  data: proj.revenue,
                  order: 2,
                  backgroundColor: colors.barBgColor,
                  borderColor: colors.primary,
                  borderWidth: 1,
                  yAxisID: 'y',
                },
                {
                  label: 'Projected profit (est.)',
                  data: proj.profit,
                  type: 'line',
                  order: 1,
                  borderColor: colors.success,
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
                  borderColor: colors.danger,
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
                legend: { position: 'bottom', labels: { color: colors.textColor } },
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
                x: {
                  ticks: { color: colors.textColor },
                  grid: { color: colors.gridColor },
                },
                y: {
                  beginAtZero: true,
                  position: 'left',
                  ticks: {
                    color: colors.textColor,
                    callback: function (value) {
                      return 'CA$' + Number(value).toLocaleString('en-CA', { maximumFractionDigits: 0 });
                    },
                  },
                  grid: { color: colors.gridColor },
                },
                y1: {
                  beginAtZero: true,
                  position: 'right',
                  grid: { drawOnChartArea: false },
                  ticks: {
                    color: colors.textColor,
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
        if (elY && !chartYtd) {
          chartYtd = new Chart(elY, {
            type: 'bar',
            data: {
              labels: ytd.month_labels,
              datasets: [
                {
                  label: 'Revenue (paid invoices)',
                  data: ytd.month_revenue,
                  backgroundColor: colors.barBgColor2,
                  borderColor: colors.primary,
                  borderWidth: 1,
                },
                {
                  label: 'Costs (expenses − returns)',
                  data: ytd.month_costs.map(function (c) { return Math.max(0, c); }),
                  backgroundColor: 'rgba(198, 40, 40, 0.2)',
                  borderColor: colors.danger,
                  borderWidth: 1,
                },
              ],
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: { position: 'bottom', labels: { color: colors.textColor } },
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
                x: {
                  ticks: { color: colors.textColor },
                  grid: { color: colors.gridColor },
                },
                y: {
                  beginAtZero: true,
                  ticks: {
                    color: colors.textColor,
                    callback: function (value) {
                      return 'CA$' + Number(value).toLocaleString('en-CA', { maximumFractionDigits: 0 });
                    },
                  },
                  grid: { color: colors.gridColor },
                },
              },
            },
          });
        }

        chartsReady = true;
        if (chartProjection) chartProjection.resize();
        if (chartYtd) chartYtd.resize();
      }

      if (glance) {
        glance.addEventListener('toggle', function () {
          if (glance.open) {
            initCharts();
          }
        });
        if (glance.open) {
          initCharts();
        }
      } else {
        initCharts();
      }
    })();
  </script>
@endpush
