@extends('admin.layouts.app')

@section('heading', 'All Invoices')

@push('head')
<link rel="stylesheet" href="{{ asset('css/admin-client-projects.css') }}?v={{ @filemtime(public_path('css/admin-client-projects.css')) ?: time() }}">
<style>
.gf-totals {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 12px;
  margin-bottom: 16px;
}
.gf-total-tile {
  padding: 12px 14px;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: 8px;
}
.gf-total-tile__label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--admin-muted);
  margin-bottom: 4px;
}
.gf-total-tile__value {
  font-size: 18px;
  font-weight: 700;
  color: var(--admin-heading);
}
.gf-total-tile__value--success { color: #16a34a; }
.gf-total-tile__value--warning { color: #d97706; }
</style>
@endpush

@section('content')
  <div class="admin-card cp-filters">
    <form action="{{ route('admin.financials.invoices') }}" method="get" class="cp-filters-form">
      <div class="cp-filter">
        <label for="gf-status">Status</label>
        <select id="gf-status" name="status" class="admin-input">
          <option value="">All</option>
          <option value="draft" @selected($status === 'draft')>Draft</option>
          <option value="sent" @selected($status === 'sent')>Sent</option>
          <option value="paid" @selected($status === 'paid')>Paid</option>
        </select>
      </div>
      <div class="cp-filter">
        <label for="gf-search">Search</label>
        <input type="text" id="gf-search" name="search" class="admin-input" placeholder="Invoice #, project, client…" value="{{ $search }}" minlength="2">
      </div>
      <div class="cp-filter">
        <label for="gf-date-from">From</label>
        <input type="date" id="gf-date-from" name="date_from" class="admin-input" value="{{ $dateFrom }}">
      </div>
      <div class="cp-filter">
        <label for="gf-date-to">To</label>
        <input type="date" id="gf-date-to" name="date_to" class="admin-input" value="{{ $dateTo }}">
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
        <a href="{{ route('admin.financials.invoices') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
    </form>
  </div>

  <div class="gf-totals">
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Invoices</div>
      <div class="gf-total-tile__value">{{ number_format($totals['count']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Subtotal</div>
      <div class="gf-total-tile__value">{{ \App\Models\ClientProject::formatMoney($totals['subtotal']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">HST</div>
      <div class="gf-total-tile__value">{{ \App\Models\ClientProject::formatMoney($totals['hst']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Total</div>
      <div class="gf-total-tile__value">{{ \App\Models\ClientProject::formatMoney($totals['total']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Paid</div>
      <div class="gf-total-tile__value gf-total-tile__value--success">{{ \App\Models\ClientProject::formatMoney($totals['paid']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Outstanding</div>
      <div class="gf-total-tile__value gf-total-tile__value--warning">{{ \App\Models\ClientProject::formatMoney($totals['outstanding']) }}</div>
    </div>
  </div>

  <div class="admin-card">
    @if ($invoices->isEmpty())
      <div class="admin-empty">
        <p>No invoices found.</p>
      </div>
    @else
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--stack">
          <thead>
            <tr>
              <th>Invoice #</th>
              <th>Project</th>
              <th>Client</th>
              <th>Description</th>
              <th>Subtotal</th>
              <th>Total</th>
              <th>Status</th>
              <th>Date</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($invoices as $invoice)
              @php
                $project = $invoice->project;
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
                $invoiceDate = $invoice->sent_at ? $invoice->sent_at : $invoice->created_at;
              @endphp
              <tr>
                <td data-label="Invoice #">
                  <code class="admin-code">{{ $invoice->invoice_number ?: '—' }}</code>
                </td>
                <td data-label="Project">
                  @if ($project)
                    <a href="{{ route('admin.client-projects.show', $project) }}#invoice" class="admin-link">{{ $project->name }}</a>
                  @else
                    <span class="admin-muted">—</span>
                  @endif
                </td>
                <td data-label="Client">{{ $project?->client_name ?: '—' }}</td>
                <td data-label="Description">{{ $invoice->installment_description ?: ($invoice->isManual() ? 'Manual invoice' : 'Installment '.$invoice->installment_number) }}</td>
                <td data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($invoice->subtotal) }}</td>
                <td data-label="Total"><strong>{{ \App\Models\ClientProject::formatMoney($invoice->total) }}</strong></td>
                <td data-label="Status">
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">{{ $statusLabel }}</span>
                </td>
                <td data-label="Date">{{ \App\Models\ClientProject::formatDate($invoiceDate) }}</td>
                <td data-label="Actions" class="admin-table-actions">
                  <div class="admin-actions-dropdown">
                    <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions">
                      <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                    </button>
                    <div class="admin-actions-dropdown__menu">
                      @if ($project)
                        <a href="{{ route('admin.client-projects.show', $project) }}#invoice" class="admin-actions-dropdown__item">
                          <i class="fa fa-folder-open"></i> Open project
                        </a>
                        @if ($invoice->invoice_pdf_path)
                          <a href="{{ route('admin.client-projects.invoices.pdf', [$project, $invoice]) }}" target="_blank" class="admin-actions-dropdown__item">
                            <i class="fa fa-file-pdf-o"></i> View PDF
                          </a>
                        @endif
                        @if ($invoice->isPaid() && $invoice->receipt_pdf_path)
                          <a href="{{ route('admin.client-projects.invoices.receipt', [$project, $invoice]) }}" target="_blank" class="admin-actions-dropdown__item">
                            <i class="fa fa-file-text-o"></i> View receipt
                          </a>
                        @endif
                        <a href="{{ route('admin.client-projects.invoices.edit', [$project, $invoice]) }}" class="admin-actions-dropdown__item">
                          <i class="fa fa-pencil"></i> Edit
                        </a>
                      @endif
                    </div>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="admin-pagination">
        {{ $invoices->links() }}
      </div>
    @endif
  </div>
@endsection

@push('scripts')
<script>
(function () {
  document.querySelectorAll('.admin-actions-dropdown').forEach(function (dropdown) {
    var toggle = dropdown.querySelector('.admin-actions-dropdown__toggle');
    var menu = dropdown.querySelector('.admin-actions-dropdown__menu');
    if (!toggle || !menu) return;

    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.classList.contains('is-open');
      document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(function (d) {
        d.classList.remove('is-open');
      });
      if (!isOpen) {
        dropdown.classList.add('is-open');
      }
    });
  });

  document.addEventListener('click', function () {
    document.querySelectorAll('.admin-actions-dropdown.is-open').forEach(function (d) {
      d.classList.remove('is-open');
    });
  });
})();
</script>
@endpush
