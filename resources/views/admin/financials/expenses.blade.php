@extends('admin.layouts.app')

@section('heading', 'All Expenses')

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
/* Quick expense: Project ~30%, Description most space, Date/Amount fit content */
.gf-quick-expense-form {
  display: flex;
  flex-wrap: wrap;
  gap: 10px 12px;
  align-items: flex-end;
  margin-bottom: 16px;
}
.gf-quick-expense-form .admin-form-group { margin-bottom: 0; flex: 0 0 auto; }
.gf-quick-expense-form .admin-form-group--project { flex: 0 1 200px; min-width: 140px; }
.gf-quick-expense-form .admin-form-group--date { flex: 0 0 120px; }
.gf-quick-expense-form .admin-form-group--desc { flex: 1 1 200px; min-width: 120px; }
.gf-quick-expense-form .admin-form-group--amount { flex: 0 0 100px; }
.gf-quick-expense-form .admin-form-group--submit { flex: 0 0 auto; }
.gf-quick-expense-receipt { margin-top: 8px; }
@media (max-width: 700px) {
  .gf-quick-expense-form {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .gf-quick-expense-form .admin-form-group--project,
  .gf-quick-expense-form .admin-form-group--desc {
    grid-column: 1 / -1;
  }
  .gf-quick-expense-form .admin-form-group--date,
  .gf-quick-expense-form .admin-form-group--amount {
    grid-column: auto;
  }
  .gf-quick-expense-form .admin-form-group--submit {
    grid-column: 2;
  }
}
/* Table column widths - nowrap for key columns */
.gf-expenses-table { width: 100%; }
.gf-expenses-table th:nth-child(1),
.gf-expenses-table td:nth-child(1) { white-space: nowrap; } /* # */
.gf-expenses-table th:nth-child(2),
.gf-expenses-table td:nth-child(2) { white-space: nowrap; } /* Date */
.gf-expenses-table th:nth-child(3),
.gf-expenses-table td:nth-child(3) { white-space: nowrap; } /* Project */
.gf-expenses-table th:nth-child(4),
.gf-expenses-table td:nth-child(4) { white-space: nowrap; } /* Client */
.gf-expenses-table th:nth-child(6),
.gf-expenses-table td:nth-child(6) { white-space: nowrap; text-align: right; } /* Amount */
</style>
@endpush

@section('content')
  <div class="admin-card">
    <h3>
      Quick expense entry
      <x-admin.info-tip label="Quick expense entry">Add an expense to any active project. On mobile, tap the camera button to snap a receipt photo.</x-admin.info-tip>
    </h3>
    <form action="{{ route('admin.financials.expenses.store') }}" method="post" enctype="multipart/form-data" id="gf-quick-expense-form">
      @csrf
      <div class="gf-quick-expense-form">
        <div class="admin-form-group admin-form-group--project">
          <label for="gf-project-search">Project <span class="cp-required">*</span></label>
          @include('admin.partials.search_select', [
            'id' => 'gf-project-search',
            'name' => 'client_project_id',
            'placeholder' => 'Search projects…',
            'searchUrl' => route('admin.financials.projects.search'),
            'searchFields' => ['label'],
            'emptyMessage' => 'No active projects found',
            'maxOptions' => 20,
          ])
          @error('client_project_id') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group admin-form-group--date">
          <label for="gf-expense-date">Date <span class="cp-required">*</span></label>
          <input type="date" id="gf-expense-date" name="occurred_on" class="admin-input" value="{{ now()->toDateString() }}" required>
        </div>
        <div class="admin-form-group admin-form-group--desc">
          <label for="gf-expense-desc">Description <span class="cp-required">*</span></label>
          <input type="text" id="gf-expense-desc" name="description" class="admin-input" placeholder="e.g. Lumber, screws" maxlength="500" required>
          @error('description') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group admin-form-group--amount">
          <label for="gf-expense-amount">Amount <span class="cp-required">*</span></label>
          <input type="text" id="gf-expense-amount" name="amount" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" placeholder="0.00" required>
          @error('amount') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group admin-form-group--submit">
          <button type="submit" class="admin-btn admin-btn--primary">
            <i class="fa fa-plus"></i> Add
          </button>
        </div>
      </div>
      <div class="admin-form-group gf-quick-expense-receipt">
        <span class="admin-label-with-tip">
          <label for="gf-expense-receipt"><i class="fa fa-camera"></i> Receipt (optional)</label>
          <x-admin.info-tip label="Receipt">Image (auto-converted to WebP) or PDF. On mobile, tap to take a photo directly.</x-admin.info-tip>
        </span>
        <input type="file" id="gf-expense-receipt" name="receipt" class="admin-input" accept="image/*,application/pdf" capture="environment">
        @error('receipt') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
    </form>
  </div>

  @php
    $activeFilterCount = \App\Support\AdminListFilters::activeCount([
        'search' => $search ?? '',
        'date_from' => $dateFrom ?? '',
        'date_to' => $dateTo ?? '',
        'has_receipt' => $hasReceipt ?? '',
    ], [], [
        'dateRange' => ['date_from', 'date_to'],
        'minLength' => ['search' => 2],
        'allowed' => [
            'has_receipt' => ['yes', 'no'],
        ],
    ]);
  @endphp
  @component('admin.partials.filters_accordion', [
    'id' => 'admin-filters-expenses',
    'storageKey' => 'admin-filters:expenses',
    'activeCount' => $activeFilterCount,
  ])
    <form action="{{ route('admin.financials.expenses') }}" method="get" class="cp-filters">
      <div class="cp-filter cp-filter--search">
        <label for="gf-search">Search</label>
        <input type="text" id="gf-search" name="search" class="admin-input" placeholder="Description, project…" value="{{ $search }}" minlength="2">
      </div>
      <div class="cp-filter cp-filter--date">
        <label for="gf-date-from">From</label>
        <input type="date" id="gf-date-from" name="date_from" class="admin-input" value="{{ $dateFrom }}">
      </div>
      <div class="cp-filter cp-filter--date">
        <label for="gf-date-to">To</label>
        <input type="date" id="gf-date-to" name="date_to" class="admin-input" value="{{ $dateTo }}">
      </div>
      <div class="cp-filter cp-filter--select">
        <label for="gf-has-receipt">Receipt</label>
        <select id="gf-has-receipt" name="has_receipt" class="admin-input">
          <option value="">All</option>
          <option value="yes" @selected($hasReceipt === 'yes')>With</option>
          <option value="no" @selected($hasReceipt === 'no')>None</option>
        </select>
      </div>
      <div class="cp-filter cp-filter--actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
        <a href="{{ route('admin.financials.expenses') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
      </div>
    </form>
  @endcomponent

  <div class="gf-totals">
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Expenses</div>
      <div class="gf-total-tile__value">{{ number_format($totals['count']) }}</div>
    </div>
    <div class="gf-total-tile">
      <div class="gf-total-tile__label">Total</div>
      <div class="gf-total-tile__value">{{ \App\Models\ClientProject::formatMoney($totals['total']) }}</div>
    </div>
  </div>

  <div class="admin-card">
    @if ($expenses->isEmpty())
      <div class="admin-empty">
        <p>No expenses found.</p>
      </div>
    @else
      <div class="admin-table-scroll">
        <table class="admin-table admin-table--stack gf-expenses-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Project</th>
              <th>Client</th>
              <th>Description</th>
              <th style="text-align:right;">Amount</th>
              <th>Receipt</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @foreach ($expenses as $expense)
              @php $project = $expense->project; @endphp
              <tr>
                <td data-label="#">{{ $expense->expense_ref }}</td>
                <td data-label="Date">{{ \App\Models\ClientProject::formatDate($expense->occurred_on) }}</td>
                <td data-label="Project">
                  @if ($project)
                    <a href="{{ route('admin.client-projects.show', $project) }}#financials" class="admin-link">{{ $project->name }}</a>
                  @else
                    <span class="admin-muted">—</span>
                  @endif
                </td>
                <td data-label="Client">{{ $project?->client_name ?: '—' }}</td>
                <td data-label="Description">{{ $expense->description }}</td>
                <td data-label="Amount"><strong>{{ \App\Models\ClientProject::formatMoney($expense->amount) }}</strong></td>
                <td data-label="Receipt" class="cp-expense-receipt-cell">
                  @if ($expense->receiptUrl() && $project)
                    <a href="{{ $expense->receiptUrl() }}" target="_blank" rel="noopener" title="View receipt">
                      @if ($expense->receiptIsImagePreview())
                        <img src="{{ $expense->receiptUrl() }}" alt="Receipt" class="cp-expense-receipt-thumb" width="40" height="40" loading="lazy">
                      @else
                        <span class="cp-expense-receipt-pdf">PDF</span>
                      @endif
                    </a>
                  @else
                    <span class="admin-muted">—</span>
                  @endif
                </td>
                <td data-label="Actions" class="admin-table-actions">
                  <div class="admin-actions-dropdown">
                    <button type="button" class="admin-actions-dropdown__toggle" aria-label="Actions">
                      <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                    </button>
                    <div class="admin-actions-dropdown__menu">
                      @if ($project)
                        <a href="{{ route('admin.client-projects.show', $project) }}#financials" class="admin-actions-dropdown__item">
                          <i class="fa fa-folder-open"></i> Open project
                        </a>
                        @if ($expense->receiptUrl())
                          <a href="{{ $expense->receiptUrl() }}" target="_blank" class="admin-actions-dropdown__item">
                            <i class="fa fa-file-image-o"></i> View receipt
                          </a>
                        @endif
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
        {{ $expenses->links() }}
      </div>
    @endif
  </div>
@endsection
