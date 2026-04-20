{{--
  Invoice tab partial — lists every installment in the current Financials
  payment plan, shows its invoice status (no invoice / draft / sent / paid),
  and exposes per-row actions (Create / Edit / Download PDF / Send / Mark Paid
  / Download Receipt).

  Separate section at the bottom for manual (non-installment) invoices.

  Variables expected:
    $project   – the ClientProject
--}}

@php
  $isHourly = $project->isHourly();
  $isAccepted = $project->isAccepted();
  $installments = $project->installments;
  $invoices = $project->invoices;
  $installmentInvoices = $invoices->whereNotNull('installment_number')->keyBy('installment_number');
  $manualInvoices = $invoices->whereNull('installment_number')->values();

  $missing = $project->missingClientContactFields();
  $hasContactInfo = empty($missing);

  $hstRate = \App\Models\ClientProject::HST_RATE;
  $withTax = 1 + $hstRate;
@endphp

{{-- ========================================================================
     Locked: hourly projects and projects still in pre-accept state don't
     get the invoice flow. Invoices are tied to a payment plan, which
     only exists for accepted, fixed-billing projects.
     ======================================================================== --}}
@if ($isHourly)
  <div class="admin-card cp-locked">
    <h3>Invoices</h3>
    <p>This project is billed by the hour. Hourly projects don't use the payment-plan invoice flow — use the Financials tab to record time and the Time Tracking tab to log hours.</p>
  </div>
@elseif (!$isAccepted)
  <div class="admin-card cp-locked">
    <h3>Invoices are locked</h3>
    <p>Invoices become available once the project status is <strong>Proposal Accepted</strong> or later. Lock in the payment plan on the Financials tab first.</p>
  </div>
@else

  {{-- ---------------------------------------------------------------- --}}
  {{-- Header + top action bar                                           --}}
  {{-- ---------------------------------------------------------------- --}}
  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>Invoices</h3>
        <p class="admin-form-help" style="margin:6px 0 0;">
          One invoice per installment from your <a href="#financials" data-cp-jump-tab="financials">Payment plan</a>.
          When you mark an invoice as paid, a receipt is generated and emailed to the client automatically,
          and the matching installment on the Financials tab is marked paid for you.
        </p>
      </div>

      <div class="cp-contract-actions">
        {{-- Manual invoice creation (no installment link) --}}
        <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
          @csrf
          <input type="hidden" name="mode" value="manual">
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Create an invoice not tied to a specific installment (e.g. extra work, materials).">
            <i class="fa fa-plus"></i> New manual invoice
          </button>
        </form>
      </div>
    </div>

    @if (!$hasContactInfo)
      <div class="cp-contract-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div>
          <strong>Can't email invoices yet.</strong>
          Missing client info: <strong>{{ implode(', ', $missing) }}</strong>.
          <a href="{{ route('admin.client-projects.edit', $project) }}">Edit project details to fix.</a>
        </div>
      </div>
    @endif

    @if ($installments->isEmpty())
      <p class="admin-form-help" style="margin-top:10px;">
        <i class="fa fa-info-circle"></i>
        No payment plan has been saved yet. Add the total amount and installments on the
        <a href="#financials" data-cp-jump-tab="financials">Financials</a> tab first, then come back here to invoice each installment.
      </p>
    @else

      {{-- ================================================================
           Installment table: one row per installment from the payment
           plan. Each row summarises the installment (description + amount
           including HST) and its current invoice status, with all the
           per-state action buttons inline.
           ================================================================ --}}
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th style="width:70px;">#</th>
              <th>Description</th>
              <th style="width:150px;">Amount <span class="admin-form-help cp-incl-hst">(incl. 13% HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($installments as $idx => $inst)
              @php
                $n = $idx + 1;
                $invoice = $installmentInvoices->get($n);
                $amountIncl = (float) $inst->amount * $withTax;
                $status = $invoice?->status;
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($status);
              @endphp
              <tr>
                <td><strong>{{ $n }}</strong></td>
                <td>
                  {{ $inst->label ?: 'Installment '.$n }}
                  @if ($inst->is_paid)
                    <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:4px;">Installment paid</span>
                  @endif
                </td>
                <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($amountIncl) }}</td>
                <td>
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    @if ($invoice && $invoice->invoice_number)
                      <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                    @endif
                  </span>
                </td>
                <td>
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => $n,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ])
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ================================================================
         Manual / ad-hoc invoices (no installment link)
         ================================================================ --}}
    @if ($manualInvoices->isNotEmpty())
      <h4 style="margin:24px 0 10px;">Manual invoices</h4>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:150px;">Amount <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($manualInvoices as $invoice)
              @php
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
              @endphp
              <tr>
                <td>
                  {{ $invoice->installment_description ?: 'Manual invoice' }}
                </td>
                <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($invoice->total) }}</td>
                <td>
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                  </span>
                </td>
                <td>
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => null,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ])
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  {{-- Mark-as-paid modal (one shared modal; data attrs populate it
       when a row's "Mark as paid" button is clicked). --}}
  <div class="cp-modal-backdrop" id="cp-mark-paid-modal" hidden>
    <div class="cp-modal" role="dialog" aria-labelledby="cp-mark-paid-title">
      <div class="cp-modal-header">
        <h3 id="cp-mark-paid-title">Mark invoice as paid</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post" id="cp-mark-paid-form">
        @csrf
        <div class="cp-modal-body">
          <p class="admin-form-help" style="margin-top:0;">
            This will mark <strong id="cp-mark-paid-invoice-label">the invoice</strong>
            as paid, generate a <strong>Receipt PDF</strong>, and — if the client's email is on file —
            send the receipt to them automatically. The linked installment on the Financials tab
            will be marked paid for you.
          </p>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="cp-mark-paid-date">Payment date</label>
              <input type="date" id="cp-mark-paid-date" name="paid_on" required value="{{ now()->toDateString() }}">
            </div>
            <div class="admin-form-group">
              <label for="cp-mark-paid-amount">Amount received (CAD)</label>
              <input type="text"
                     id="cp-mark-paid-amount"
                     name="paid_amount"
                     class="cp-money-input"
                     inputmode="decimal"
                     autocomplete="off"
                     placeholder="0.00"
                     required>
            </div>
          </div>
          <label class="cp-checkbox-row" style="margin-top:10px;">
            <input type="hidden" name="also_email_receipt" value="0">
            <input type="checkbox" name="also_email_receipt" value="1" checked>
            <span>Email receipt to client now</span>
          </label>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-check"></i> Confirm and generate receipt
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      // ---- Mark-paid modal ---------------------------------------
      var modal = document.getElementById('cp-mark-paid-modal');
      var form = document.getElementById('cp-mark-paid-form');
      var amountInput = document.getElementById('cp-mark-paid-amount');
      var label = document.getElementById('cp-mark-paid-invoice-label');

      function openModal(trigger) {
        var action = trigger.getAttribute('data-cp-markpaid-url');
        var amount = trigger.getAttribute('data-cp-markpaid-amount') || '0.00';
        var invoiceLabel = trigger.getAttribute('data-cp-markpaid-label') || 'this invoice';
        form.action = action;
        amountInput.value = amount;
        label.textContent = invoiceLabel;

        // Re-wire the money mask on the freshly-shown input.
        if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
          window.cpMoneyMask.wire([amountInput]);
        }
        modal.hidden = false;
        document.body.classList.add('cp-modal-open');
      }

      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
      }

      document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-cp-markpaid-url]');
        if (t) {
          e.preventDefault();
          openModal(t);
          return;
        }
        if (e.target.matches('[data-cp-modal-close]') || e.target === modal) {
          closeModal();
        }
      });

      document.addEventListener('keydown', function (e) {
        if (!modal.hidden && e.key === 'Escape') closeModal();
      });

      // ---- In-tab jump to Financials ------------------------------
      document.querySelectorAll('[data-cp-jump-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          var key = a.getAttribute('data-cp-jump-tab');
          var btn = document.querySelector('.cp-tab[data-tab="' + key + '"]');
          if (btn) {
            e.preventDefault();
            btn.click();
          }
        });
      });
    })();
  </script>
@endif
