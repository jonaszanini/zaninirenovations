@extends('admin.layouts.app')

@php
    $prepareTitleName = trim((string) preg_replace('/\s*[—–]\s*/u', ', ', (string) $project->name));
    $prepareHeading = $prepareTitleName === '' ? 'Prepare billing' : 'Prepare billing, '.$prepareTitleName;
@endphp

@section('heading', $prepareHeading)

@push('head')
<link rel="stylesheet" href="{{ asset('css/admin-client-projects.css') }}?v={{ @filemtime(public_path('css/admin-client-projects.css')) ?: time() }}">
@endpush

@section('content')
  @php
    $preview = $preview ?? ['allowed' => false, 'blockers' => [], 'conflicts' => [], 'rows' => [], 'totals' => [], 'can_confirm' => false, 'needs_due_dates' => false, 'quote_terms' => null];
    $rows = $preview['rows'] ?? [];
    $blockers = $preview['blockers'] ?? [];
    $conflicts = $preview['conflicts'] ?? [];
    $quoteTerms = $preview['quote_terms'] ?? null;
    $needsDue = ! empty($preview['needs_due_dates']);
    $canConfirm = ! empty($preview['can_confirm']);
  @endphp

  <div class="cp-billing-prepare">
    <div class="cp-contract-editor-toolbar">
      <div class="cp-contract-editor-toolbar-left">
        <a href="{{ route('admin.client-projects.show', $project) }}#financials"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left" aria-hidden="true"></i> Back to project
        </a>
      </div>
    </div>

    <div class="admin-card">
      <h3 class="cp-form-help-flush">
        Review installment drafts
        <x-admin.info-tip label="Review installment drafts">
          Amounts come from the accepted quotation and the saved payment plan. Due dates are not on the quotation.
          Leave any installment blank when the finish date is unknown. The draft saves with no due date and shows Upon completion until you set one while editing or sending.
          Confirm creates <strong>draft</strong> invoices only (the same records as Financials and Invoices). Send remains a separate step.
        </x-admin.info-tip>
      </h3>

      @if ($quoteTerms)
        <p class="cp-billing-prepare__terms">
          Accepted terms:
          {{ (int) $quoteTerms['installments'] }} installment{{ (int) $quoteTerms['installments'] === 1 ? '' : 's' }}
          · pre-tax {{ \App\Models\ClientProject::formatMoney($quoteTerms['pre_tax']) }}
          · total {{ \App\Models\ClientProject::formatMoney($quoteTerms['grand']) }} CAD
        </p>
      @endif

      @include('admin.client_projects._billing_totals', ['billingTotals' => $preview['totals'] ?? []])

      @if ($blockers)
        <div class="cp-contract-warning cp-recalc-banner" role="status">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          <div class="cp-recalc-banner__body">
            <p class="cp-recalc-banner__title">Billing is not ready</p>
            @foreach ($blockers as $blocker)
              <p>{{ $blocker }}</p>
            @endforeach
          </div>
        </div>
      @endif

      @if ($conflicts)
        <div class="cp-contract-warning cp-recalc-banner" role="status" data-cp-billing-conflict>
          <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
          <div class="cp-recalc-banner__body">
            <p class="cp-recalc-banner__title">Accepted quotation and Financials do not match</p>
            @foreach ($conflicts as $conflict)
              <p>{{ $conflict }}</p>
            @endforeach
            <p>
              <a href="{{ route('admin.client-projects.show', $project) }}#financials" data-cp-jump-tab="financials">Open Financials</a>
              to review the plan. Confirm is blocked so amounts are not recalculated here.
            </p>
          </div>
        </div>
      @endif
    </div>

    @if ($preview['allowed'] && $rows)
      <form action="{{ route('admin.client-projects.billing.confirm', $project) }}"
            method="post"
            id="cp-prepare-billing-form"
            class="admin-card">
        @csrf
        <h3>Installments</h3>
        <div class="admin-table-scroll">
          <table class="admin-table admin-table--stack cp-billing-prepare-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Label</th>
                <th>%</th>
                <th class="cp-col-amount">Subtotal</th>
                <th class="cp-col-amount">HST</th>
                <th class="cp-col-amount">Total CAD</th>
                <th>
                  <span class="admin-label-with-tip">
                    Due date
                    <x-admin.info-tip label="Due date">
                      Optional. Leave it blank when you do not know the date yet, including on completion. You can set it later on the draft invoice.
                    </x-admin.info-tip>
                  </span>
                </th>
                <th>Invoice</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($rows as $row)
                @php
                  $n = (int) $row['number'];
                  $locked = in_array($row['action'], ['skip_issued', 'skip_paid'], true);
                  $dueValue = old('due_on.'.$n, $row['due_on']);
                @endphp
                <tr data-cp-billing-row="{{ $n }}" data-cp-billing-action="{{ $row['action'] }}">
                  <td data-label="#">{{ $n }}</td>
                  <td data-label="Label">{{ $row['label'] }}</td>
                  <td data-label="%">{{ number_format((float) $row['percentage'], 2) }}%</td>
                  <td class="cp-col-amount" data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($row['subtotal']) }}</td>
                  <td class="cp-col-amount" data-label="HST">{{ \App\Models\ClientProject::formatMoney($row['hst']) }}</td>
                  <td class="cp-col-amount" data-label="Total CAD"><strong>{{ \App\Models\ClientProject::formatMoney($row['total']) }}</strong></td>
                  <td data-label="Due date">
                    @if ($locked)
                      <span class="admin-form-help">{{ $dueValue ?: 'Upon completion' }}</span>
                    @else
                      <input type="date"
                             class="admin-input"
                             name="due_on[{{ $n }}]"
                             value="{{ $dueValue }}"
                             aria-label="Due date for installment {{ $n }}">
                      @error('due_on.'.$n)
                        <p class="admin-form-error">{{ $message }}</p>
                      @enderror
                    @endif
                  </td>
                  <td data-label="Invoice">
                    @if ($row['action'] === 'reuse_draft')
                      <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Draft {{ $row['invoice_number'] }}</span>
                    @elseif ($row['action'] === 'skip_issued')
                      <span class="cp-badge" style="background:#3b82f620;color:#1d4ed8;">{{ \App\Models\ClientProjectInvoice::statusLabel($row['invoice_status']) }} {{ $row['invoice_number'] }}</span>
                    @elseif ($row['action'] === 'skip_paid')
                      <span class="cp-badge" style="background:#22c55e20;color:#15803d;">Paid — skip</span>
                    @else
                      <span class="admin-form-help">Will create draft</span>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        @error('billing')
          <p class="admin-form-error">{{ $message }}</p>
        @enderror

        <div class="cp-billing-prepare__footer">
          <a href="{{ route('admin.client-projects.show', $project) }}#financials"
             class="admin-btn admin-btn--ghost">Cancel</a>
          <button type="submit"
                  class="admin-btn admin-btn--primary"
                  data-cp-confirm-billing
                  @disabled(! $canConfirm)>
            Confirm draft invoices
          </button>
        </div>
      </form>
    @endif
  </div>
@endsection
