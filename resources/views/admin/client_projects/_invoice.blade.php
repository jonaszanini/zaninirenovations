{{--
  Invoice tab partial. Two distinct flows behind the same UI chrome:

  Fixed-price projects (unchanged)
    - One invoice per installment from the Financials payment plan,
      plus optional manual invoices.

  Hourly projects
    - No installments. Admin hits "Create invoice from hours" to
      snapshot every unbilled time entry into a new draft invoice,
      then tweaks the line items / due date / description before
      sending. Unbilled entries are tracked via a FK on the entry
      row (client_project_invoice_id).

  Variables expected:
    $project   – the ClientProject
--}}

@php
  $isHourly = $project->isHourly();
  $isAccepted = $project->isAccepted();
  $isClosed = $project->isClosed();
  $invoices = $project->invoices;
  $showExistingInvoices = $invoices->isNotEmpty();

  $missing = $project->missingClientContactFields();
  $hasContactInfo = empty($missing);
@endphp

@if ($project->usesInvoiceBillingOverride())
  <div class="admin-card cp-invoice-bill-to">
    <p class="cp-invoice-bill-to__text">
      <span class="admin-label-with-tip">
        <strong>Invoice bill-to:</strong>
        <x-admin.info-tip label="Invoice bill-to">
          PDFs use this legal name/address. Emails and SMS still use the client contact on file (@include('admin.partials.client_directory_link', ['project' => $project])).
        </x-admin.info-tip>
      </span>
      {{ $project->invoiceBillToName() }}
      @if (filled(trim((string) $project->invoice_billing_address)))
        <span class="admin-form-help cp-invoice-bill-to__address">{{ $project->invoice_billing_address }}</span>
      @endif
    </p>
    <a href="{{ route('admin.client-projects.edit', $project) }}" class="admin-btn admin-btn--ghost admin-btn--sm cp-form-group-spaced">Edit project</a>
  </div>
@endif

@if ($isClosed)
  {{-- Cancelled / rejected: keep past invoices visible and deletable.
       Creating, sending, and reminding stay locked. --}}
  <div class="admin-card cp-invoice-closed-note">
    <h3>{{ $project->statusLabel() }} project</h3>
    <p>
      @if ($showExistingInvoices)
        Existing invoices are shown read-only. You can view the PDF, refund a paid invoice, or delete an unpaid invoice.
        New invoices cannot be created while the project is {{ strtolower($project->statusLabel()) }}.
      @else
        Invoices stay locked while this project is {{ strtolower($project->statusLabel()) }}. No invoices were issued.
      @endif
    </p>
  </div>

  @if ($showExistingInvoices)
    <div class="admin-card cp-contract-card">
      <div class="cp-contract-header">
        <div class="cp-contract-meta">
          <h3>Existing invoices</h3>
        </div>
      </div>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:200px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($invoices->sortByDesc('id') as $invoice)
              @php
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
              @endphp
              <tr>
                <td data-label="Description">
                  {{ $invoice->installmentTitle() }}
                  @if ($invoice->isHstExempt())
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($invoice->subtotal) }}</td>
                <td class="cp-col-amount" data-label="HST">
                  @if ($invoice->isHstExempt())
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  @else
                    {{ \App\Models\ClientProject::formatMoney($invoice->hst) }}
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Total">
                  <strong>{{ \App\Models\ClientProject::formatMoney($invoice->total) }}</strong>
                </td>
                <td data-label="Status">
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                  </span>
                  @include('admin.client_projects._invoice_refund_note', ['invoice' => $invoice])
                </td>
                <td data-label="Actions">
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => $invoice->installment_number,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                    'invoiceActionsReadOnly' => true,
                  ])
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

@elseif ($isHourly)
  {{-- =================================================================
       HOURLY FLOW — separate from anything fixed-price below. Leaves
       all installment / payment-plan logic untouched.
       ================================================================= --}}
  @php
    $hourlyRate = round((float) ($project->hourly_rate ?? 0), 2);

    // Time entries with duration set (open entries without end_time are
    // ignored until closed out).
    $closedEntries = $project->timeEntries()
        ->whereNotNull('hours')
        ->get();

    $totalHours = round((float) $closedEntries->sum('hours'), 2);
    $unbilledEntries = $closedEntries->whereNull('client_project_invoice_id');
    $unbilledHours = round((float) $unbilledEntries->sum('hours'), 2);
    $billedHours = round($totalHours - $unbilledHours, 2);

    $unbilledSubtotal = round($unbilledHours * $hourlyRate, 2);
    $unbilledTotal = round($unbilledSubtotal * (1 + \App\Models\ClientProjectInvoice::HST_RATE), 2);

    $canCreateFromHours = $unbilledEntries->isNotEmpty() && $hourlyRate> 0;
  @endphp

  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>
          Invoices
          <x-admin.info-tip label="Invoices">
            This project is billed by the hour. Log time on the
            <a href="#time" data-cp-jump-tab="time">Time Tracking</a> tab, then create
            an invoice here whenever you're ready to bill the client for unbilled hours.
            You can also raise a manual invoice for anything not tied to tracked time
            (e.g. materials, delivery fees). Once you mark an invoice as paid, a receipt
            is generated and emailed to the client automatically.
          </x-admin.info-tip>
        </h3>
      </div>

      <div class="cp-contract-actions">
        @if ($canCreateFromHours)
          <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
            @csrf
            <input type="hidden" name="mode" value="hourly">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm"
                    title="Snapshot {{ $unbilledEntries->count() }} unbilled time entr{{ $unbilledEntries->count() === 1 ? 'y' : 'ies' }} into a new draft invoice.">
              <i class="fa fa-clock-o"></i> Create invoice from hours
            </button>
          </form>
        @else
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
                  title="{{ $hourlyRate <= 0 ? 'Set the hourly rate on the project first.' : 'No unbilled hours to invoice — log time on the Time Tracking tab first.' }}">
            <i class="fa fa-clock-o"></i> Create invoice from hours
          </button>
        @endif

        <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
          @csrf
          <input type="hidden" name="mode" value="manual">
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Create a manual invoice not tied to tracked hours (e.g. materials).">
            <i class="fa fa-plus"></i> New manual invoice
          </button>
        </form>
      </div>
    </div>

    {{-- Hours summary tiles (same visual language as Financials "set-aside"
         tiles — kept lightweight so it doesn't compete with the table). --}}
    <div class="cp-hourly-summary">
      <div class="cp-hourly-summary-tile">
        <div class="cp-hourly-summary-label">Hours logged</div>
        <div class="cp-hourly-summary-value">{{ number_format($totalHours, 2) }} h</div>
        @if ($hourlyRate> 0)
          <div class="cp-hourly-summary-sub">@ {{ \App\Models\ClientProject::formatMoney($hourlyRate) }}/h</div>
        @endif
      </div>
      <div class="cp-hourly-summary-tile">
        <div class="cp-hourly-summary-label">Already invoiced</div>
        <div class="cp-hourly-summary-value">{{ number_format($billedHours, 2) }} h</div>
      </div>
      <div class="cp-hourly-summary-tile cp-hourly-summary-tile--highlight">
        <div class="cp-hourly-summary-label">Unbilled</div>
        <div class="cp-hourly-summary-value">{{ number_format($unbilledHours, 2) }} h</div>
        @if ($unbilledHours> 0 && $hourlyRate> 0)
          <div class="cp-hourly-summary-sub">
            {{ \App\Models\ClientProject::formatMoney($unbilledSubtotal) }} + HST =
            <strong>{{ \App\Models\ClientProject::formatMoney($unbilledTotal) }}</strong>
          </div>
        @endif
      </div>
    </div>

    {{-- Existing invoices (drafts + sent + paid), newest first. Each
         invoice is a "manual-style" row — no installment column. --}}
    @if ($invoices->isEmpty())
      <p class="admin-form-help" style="margin-top:18px;">
        <i class="fa fa-info-circle"></i>
        No invoices yet. When you're ready to bill, click <strong>Create invoice from hours</strong>
        to snapshot the unbilled entries into a draft, then review and send.
      </p>
    @else
      <h4 class="cp-heading-md">
        Hourly invoices
        <x-admin.info-tip label="HST on hourly invoices">
          HST (13%) is added to each invoice once, on top of the pre-tax subtotal.
        </x-admin.info-tip>
      </h4>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST (13%)</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($invoices->sortByDesc('id') as $invoice)
              @php
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
                $entryCount = $invoice->timeEntries->count();
              @endphp
              <tr>
                <td data-label="Description">
                  {{ $invoice->installment_description ?: 'Hourly work' }}
                  @if ($entryCount> 0)
                    <div class="admin-form-help" style="margin-top:2px;">
                      <i class="fa fa-clock-o"></i>
                      {{ $entryCount }} time entr{{ $entryCount === 1 ? 'y' : 'ies' }} snapshotted
                    </div>
                  @endif
                  @if ($invoice->isHstExempt())
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($invoice->subtotal) }}</td>
                <td class="cp-col-amount" data-label="HST">
                  @if ($invoice->isHstExempt())
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  @else
                    {{ \App\Models\ClientProject::formatMoney($invoice->hst) }}
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Total">
                  <strong>{{ \App\Models\ClientProject::formatMoney($invoice->total) }}</strong>
                </td>
                <td data-label="Status">
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                  </span>
                  @include('admin.client_projects._invoice_reminders_paused_note', ['invoice' => $invoice])
                  @include('admin.client_projects._invoice_refund_note', ['invoice' => $invoice])
                </td>
                <td data-label="Actions">
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => null,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ])
                </td>
              </tr>
              @include('admin.client_projects._invoice_send_warning_row', ['colspan' => 6])
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

@elseif (!$isAccepted)
  <div class="admin-card cp-locked">
    <h3>Invoices are locked</h3>
    <p>Invoices become available once the project status is <strong>Proposal Accepted</strong> or later. Lock in the payment plan on the Financials tab first.</p>
  </div>

@else
  {{-- =================================================================
       FIXED-PRICE FLOW — untouched from the original implementation.
       Installment-driven invoices + optional manual invoices.
       ================================================================= --}}
  @php
    $installments = $project->installments;
    $installmentInvoices = $invoices->whereNotNull('installment_number')->keyBy('installment_number');
    $manualInvoices = $invoices->whereNull('installment_number')->values();
  @endphp

  {{-- ---------------------------------------------------------------- --}}
  {{-- Header + top action bar                                           --}}
  {{-- ---------------------------------------------------------------- --}}
  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>
          Invoices
          <x-admin.info-tip label="Invoices">
            One invoice per installment from your <a href="#financials" data-cp-jump-tab="financials">Payment plan</a>.
            When you mark an invoice as paid, a receipt is generated and emailed to the client automatically,
            and the matching installment on the Financials tab is marked paid for you.
            Unpaid sent invoices get a payment reminder each day after the due date — use <strong>Stop daily reminders</strong> on a row to pause that without deleting the invoice.
            Deleting an unpaid invoice removes only the invoice, not the payment-plan line.
          </x-admin.info-tip>
        </h3>
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

    @if ($installments->isEmpty())
      @php
        $invoiceHasAcceptedQuot = $project->quotationSubtotalForFinancials() !== null;
        $invoicePendingQuotes = ($project->relationLoaded('quotations')
            ? $project->quotations
            : $project->quotations()->orderBy('id')->get()
          )->filter(fn ($q) => $q->status !== \App\Models\ClientProjectQuotation::STATUS_ACCEPTED && $q->hasContent());
      @endphp
      <p class="admin-form-help cp-form-group-spaced">
        <i class="fa fa-info-circle"></i>
        @if (! $invoiceHasAcceptedQuot && $invoicePendingQuotes->isNotEmpty())
          No payment plan yet. On the
          <a href="#quotation" data-cp-jump-tab="quotation">Quotation</a>
          tab, set at least one quotation to <strong>Accepted</strong>, then save the installment schedule on
          <a href="#financials" data-cp-jump-tab="financials">Financials</a>
          — afterward you can create an invoice per installment here.
        @elseif (! $invoiceHasAcceptedQuot)
          No payment plan yet. Add a quotation with line items, mark it <strong>Accepted</strong> on the
          <a href="#quotation" data-cp-jump-tab="quotation">Quotation</a>
          tab, then save the plan on
          <a href="#financials" data-cp-jump-tab="financials">Financials</a>
          before creating installment invoices.
        @else
          No payment plan has been saved yet. Choose the installment schedule on the
          <a href="#financials" data-cp-jump-tab="financials">Financials</a>
          tab first, then come back here to invoice each installment.
        @endif
      </p>
    @else

      {{-- ================================================================
           Installment table: one row per installment from the payment
           plan. Each row breaks the installment amount down into
           Subtotal (pre-HST, from the Financials payment plan) + HST
           + Total (incl. HST), so it's visually obvious that HST is
           applied exactly once on top of the pre-tax installment.
           ================================================================ --}}
      <h4 class="cp-heading-md">
        Installment invoices
        <x-admin.info-tip label="Installment subtotal and HST">
          Subtotal comes from the pre-tax installment amount on the
          <a href="#financials" data-cp-jump-tab="financials">Financials payment plan</a>.
          HST is applied once at invoice time, sliced proportionally from the
          quotation's actual HST — so HST-exempt quotation lines (e.g. supplied
          material) never get re-taxed here.
          Existing draft / sent invoices are updated when you save a new payment
          plan on Financials (paid invoices stay as-is). Installments that do not
          have an invoice yet will use the new amounts when you create them.
        </x-admin.info-tip>
      </h4>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th style="width:50px;">#</th>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($installments as $idx => $inst)
              @php
                $n = $idx + 1;
                $invoice = $installmentInvoices->get($n);

                // Prefer the stored invoice values when an invoice exists —
                // those are what was (or will be) emailed. Otherwise project
                // the figures from the installment + quotation HST share.
                if ($invoice) {
                    $rowSubtotal = (float) $invoice->subtotal;
                    $rowHst = (float) $invoice->hst;
                    $rowTotal = (float) $invoice->total;
                } else {
                    $rowSubtotal = (float) $inst->amount;
                    $rowHst = $inst->effectiveHst();
                    $rowTotal = $inst->effectiveTotal();
                }

                // Highlight when the existing invoice's stored total no
                // longer matches the projected installment total (e.g.
                // an old invoice predates the HST fix). Admin should
                // delete + recreate to bring it in line.
                $expectedTotal = $inst->effectiveTotal();
                $hasMismatch = $invoice && abs($rowTotal - $expectedTotal)> 0.01;

                $status = $invoice?->status;
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($status);
              @endphp
              <tr>
                <td data-label="#">
                  <strong>{{ $n }}</strong>
                </td>
                <td data-label="Description">
                  {{ $inst->label ?: 'Installment '.$n }}
                  @if ($inst->is_paid && ! ($invoice && $invoice->isRefunded()))
                    <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:4px;">Installment paid</span>
                  @endif
                  @if ($hasMismatch)
                    <div class="admin-form-help" style="margin-top:4px;color:#0369a1;">
                      <i class="fa fa-info-circle"></i>
                      <strong>{{ \App\Models\ClientProject::formatMoney($rowTotal) }}</strong> vs plan {{ \App\Models\ClientProject::formatMoney($expectedTotal) }}
                      (Difference: {{ \App\Models\ClientProject::formatMoney(abs($rowTotal - $expectedTotal)) }}). Expected when discounted or edited.
                    </div>
                  @endif
                  @if ($invoice && $invoice->isHstExempt())
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($rowSubtotal) }}</td>
                <td class="cp-col-amount" data-label="HST">
                  @if ($invoice && $invoice->isHstExempt())
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  @else
                    {{ \App\Models\ClientProject::formatMoney($rowHst) }}
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Total">
                  <strong>{{ \App\Models\ClientProject::formatMoney($rowTotal) }}</strong>
                </td>
                <td data-label="Status">
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    @if ($invoice && $invoice->invoice_number)
                      <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                    @endif
                  </span>
                  @include('admin.client_projects._invoice_reminders_paused_note', ['invoice' => $invoice])
                  @include('admin.client_projects._invoice_refund_note', ['invoice' => $invoice])
                </td>
                <td data-label="Actions">
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => $n,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ])
                </td>
              </tr>
              @include('admin.client_projects._invoice_send_warning_row', ['colspan' => 7])
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    {{-- ================================================================
         Manual / ad-hoc invoices (no installment link)
         ================================================================ --}}
    @if ($manualInvoices->isNotEmpty())
      <h4 style="margin:24px 0 6px;">
        Manual invoices
        <x-admin.info-tip label="HST on manual invoices">
          HST (13%) is added to the manual invoice subtotal once — these invoices are not tied to the quotation, so the breakdown below is the only HST applied.
        </x-admin.info-tip>
      </h4>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST (13%)</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
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
                <td data-label="Description">
                  {{ $invoice->installment_description ?: 'Manual invoice' }}
                  @if ($invoice->isHstExempt())
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Subtotal">{{ \App\Models\ClientProject::formatMoney($invoice->subtotal) }}</td>
                <td class="cp-col-amount" data-label="HST">
                  @if ($invoice->isHstExempt())
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  @else
                    {{ \App\Models\ClientProject::formatMoney($invoice->hst) }}
                  @endif
                </td>
                <td class="cp-col-amount" data-label="Total">
                  <strong>{{ \App\Models\ClientProject::formatMoney($invoice->total) }}</strong>
                </td>
                <td data-label="Status">
                  <span class="cp-badge" style="background: {{ $statusColor }}20; color: {{ $statusColor }};">
                    {{ $statusLabel }}
                    <span style="font-weight:400;opacity:.8;">· {{ $invoice->invoice_number }}</span>
                  </span>
                  @include('admin.client_projects._invoice_reminders_paused_note', ['invoice' => $invoice])
                  @include('admin.client_projects._invoice_refund_note', ['invoice' => $invoice])
                </td>
                <td data-label="Actions">
                  @include('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => null,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ])
                </td>
              </tr>
              @include('admin.client_projects._invoice_send_warning_row', ['colspan' => 6])
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
@endif

{{-- =====================================================================
     Shared mark-as-paid modal + tab-jump script. Rendered for every
     non-locked state (hourly and fixed-price both use it). The pre-accept
     lock branch returns before this point, so we gate on that one case.
     ===================================================================== --}}
@if (($isHourly || $isAccepted) && ! $isClosed)
  <div class="cp-modal-backdrop" id="cp-mark-paid-modal" hidden>
    <div class="cp-modal" role="dialog" aria-labelledby="cp-mark-paid-title">
      <div class="cp-modal-header">
        <h3 id="cp-mark-paid-title">Mark invoice as paid</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post" id="cp-mark-paid-form">
        @csrf
        <div class="cp-modal-body">
          @php
            $markPaidCanEmail = filled(trim((string) $project->client_email));
            $markPaidCanSms = \App\Models\ClientProject::phoneDigitsForSms($project->client_phone) !== null
                && app(\App\Services\MoceanSmsService::class)->isConfigured();
            $markPaidDefaultEmail = $markPaidCanEmail;
            $markPaidDefaultSms = $markPaidCanSms && ! $markPaidCanEmail;
          @endphp
          <p class="admin-form-help" style="margin-top:0;" id="cp-mark-paid-intro">
            This will mark <strong id="cp-mark-paid-invoice-label">the invoice</strong>
            as paid and generate a <strong>Receipt PDF</strong>.
            @if ($markPaidCanEmail || $markPaidCanSms)
              You can optionally deliver the receipt to the client
              @if ($markPaidCanEmail && $markPaidCanSms)
                by email or SMS
              @elseif ($markPaidCanEmail)
                by email
              @else
                by SMS
              @endif
              using the options below.
            @else
              No client email or SMS-capable phone is on file — the receipt will be saved for you to send manually.
            @endif
            @unless ($isHourly) The linked installment on the Financials tab will be marked paid for you. @endunless
          </p>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="cp-mark-paid-date">Payment date</label>
              <input class="admin-input" type="date" id="cp-mark-paid-date" name="paid_on" required value="{{ now()->toDateString() }}">
            </div>
            <div class="admin-form-group">
              <label for="cp-mark-paid-amount">Amount received (CAD)</label>
              <input type="text"
                     id="cp-mark-paid-amount"
                     name="paid_amount"
                     class="admin-input cp-money-input"
                     inputmode="decimal"
                     autocomplete="off"
                    
                     placeholder="0.00"
                     required data-cp-money-entry="direct">
            </div>
          </div>
          <div class="admin-form-group cp-mark-paid-method">
            <label class="cp-mark-paid-method-label">Payment method</label>
            <div class="cp-mark-paid-method-options" role="radiogroup" aria-label="Payment method">
              <label class="cp-mark-paid-method-option">
                <input type="radio" name="payment_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER }}" checked>
                <span><i class="fa fa-bank"></i> e-Transfer</span>
              </label>
              <label class="cp-mark-paid-method-option">
                <input type="radio" name="payment_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_CASH }}">
                <span><i class="fa fa-money"></i> Cash</span>
              </label>
              <label class="cp-mark-paid-method-option">
                <input type="radio" name="payment_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_CHECK }}">
                <span><i class="fa fa-file-text-o"></i> Check</span>
              </label>
            </div>
          </div>

          @php
            $markPaidStatusLabels = collect(\App\Models\ClientProject::statusLabels())
              ->reject(fn ($label, $key) => in_array($key, \App\Models\ClientProject::terminalStatuses(), true));
          @endphp
          <fieldset id="cp-mark-paid-status-fieldset" class="cp-mark-paid-project-status" disabled>
            <legend class="cp-mark-paid-project-status-legend">Project status</legend>
            <p class="admin-form-help" style="margin:0 0 8px;">
              This is the <strong>first paid invoice</strong> on this project. Optionally move the project to a new workflow step now.
            </p>
            <div class="admin-form-group cp-label-flush">
              <label for="cp-mark-paid-project-status">Set project status to</label>
              <select id="cp-mark-paid-project-status" name="project_status" class="admin-input">
                <option value="">Keep current — {{ $project->statusLabel() }}</option>
                @foreach ($markPaidStatusLabels as $key => $lbl)
                  <option value="{{ $key }}">{{ $lbl }}</option>
                @endforeach
              </select>
            </div>
          </fieldset>

          <div class="cp-mark-paid-receipt-options" id="cp-mark-paid-receipt-options">
            @if ($markPaidCanEmail)
              <label class="admin-checkbox cp-checkbox-row">
                <input type="hidden" name="also_email_receipt" value="0">
                <input type="checkbox"
                       class="admin-checkbox__input"
                       id="cp-mark-paid-email-receipt"
                       name="also_email_receipt"
                       value="1"
                       @checked($markPaidDefaultEmail)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">Email receipt to {{ $project->clientEmailRecipientsLabel() }} now</span>
              </label>
            @else
              <input type="hidden" name="also_email_receipt" value="0">
            @endif

            @if ($markPaidCanSms)
              <label class="admin-checkbox cp-checkbox-row">
                <input type="hidden" name="also_sms_receipt" value="0">
                <input type="checkbox"
                       class="admin-checkbox__input"
                       id="cp-mark-paid-sms-receipt"
                       name="also_sms_receipt"
                       value="1"
                       @checked($markPaidDefaultSms)>
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text">SMS receipt to {{ $project->clientSmsRecipientsLabel() }}</span>
              </label>
            @else
              <input type="hidden" name="also_sms_receipt" value="0">
            @endif
          </div>
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
      var statusFieldset = document.getElementById('cp-mark-paid-status-fieldset');
      var statusSelect = document.getElementById('cp-mark-paid-project-status');

      function openModal(trigger) {
        var action = trigger.getAttribute('data-cp-markpaid-url');
        var amount = trigger.getAttribute('data-cp-markpaid-amount') || '0.00';
        var invoiceLabel = trigger.getAttribute('data-cp-markpaid-label') || 'this invoice';
        var askStatus = trigger.getAttribute('data-cp-markpaid-first-paid') === '1';
        form.action = action;
        amountInput.value = amount;
        label.textContent = invoiceLabel;

        if (statusFieldset) {
          statusFieldset.disabled = !askStatus;
        }
        if (statusSelect && askStatus) {
          statusSelect.value = '';
        }

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

      // ---- In-tab jump to another tab ---------------------------
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

{{-- Refund dialog. Also shown on cancelled and rejected projects, where the rest of this tab is read-only. --}}
<div class="cp-modal-backdrop" id="cp-refund-modal" hidden>
  <div class="cp-modal" role="dialog" aria-labelledby="cp-refund-title">
    <div class="cp-modal-header">
      <h3 id="cp-refund-title">Refund invoice</h3>
      <button type="button" class="cp-modal-close" data-cp-refund-close aria-label="Close">&times;</button>
    </div>
    <form method="post" id="cp-refund-form">
      @csrf
      <div class="cp-modal-body">
        @php
          $refundCanEmail = filled(trim((string) $project->client_email));
        @endphp
        <p class="admin-form-help" style="margin-top:0;">
          Return money already received on <strong id="cp-refund-invoice-label">this invoice</strong>.
          A full refund marks the invoice refunded. A smaller amount stays paid.
          The project status does not change.
        </p>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-refund-date">Refund date</label>
            <input class="admin-input" type="date" id="cp-refund-date" name="refunded_on" required value="{{ now()->toDateString() }}">
          </div>
          <div class="admin-form-group">
            <label for="cp-refund-amount">Amount to refund (CAD)</label>
            <input type="text"
                   id="cp-refund-amount"
                   name="refund_amount"
                   class="admin-input cp-money-input"
                   inputmode="decimal"
                   autocomplete="off"
                   placeholder="0.00"
                   required
                   data-cp-money-entry="direct">
          </div>
        </div>
        <div class="admin-form-group cp-mark-paid-method">
          <label class="cp-mark-paid-method-label">Refund method</label>
          <div class="cp-mark-paid-method-options" role="radiogroup" aria-label="Refund method">
            <label class="cp-mark-paid-method-option">
              <input type="radio" name="refund_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER }}" checked>
              <span><i class="fa fa-bank"></i> e-Transfer</span>
            </label>
            <label class="cp-mark-paid-method-option">
              <input type="radio" name="refund_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_CASH }}">
              <span><i class="fa fa-money"></i> Cash</span>
            </label>
            <label class="cp-mark-paid-method-option">
              <input type="radio" name="refund_method" value="{{ \App\Models\ClientProjectInvoice::PAYMENT_METHOD_CHECK }}">
              <span><i class="fa fa-file-text-o"></i> Check</span>
            </label>
          </div>
        </div>
        <div class="admin-form-group">
          <label for="cp-refund-note">Note <span class="admin-form-help">(optional)</span></label>
          <input class="admin-input" type="text" id="cp-refund-note" name="refund_note" maxlength="500" placeholder="Cancellation, kept deposit, …">
        </div>
        @if ($refundCanEmail)
          <label class="admin-checkbox">
            <input type="checkbox" name="also_email_refund" value="1" checked>
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Email refund receipt to {{ $project->clientEmailRecipientsLabel() }}</span>
          </label>
        @else
          <input type="hidden" name="also_email_refund" value="0">
        @endif
      </div>
      <div class="cp-modal-footer">
        <button type="button" class="admin-btn admin-btn--ghost" data-cp-refund-close>Cancel</button>
        <button type="submit" class="admin-btn admin-btn--danger">
          <i class="fa fa-undo"></i> Record refund
        </button>
      </div>
    </form>
  </div>
</div>
<script>
  (function () {
    var modal = document.getElementById('cp-refund-modal');
    var form = document.getElementById('cp-refund-form');
    var amountInput = document.getElementById('cp-refund-amount');
    var label = document.getElementById('cp-refund-invoice-label');
    if (!modal || !form || !amountInput) return;

    function openModal(trigger) {
      form.action = trigger.getAttribute('data-cp-refund-url');
      amountInput.value = trigger.getAttribute('data-cp-refund-amount') || '0.00';
      label.textContent = trigger.getAttribute('data-cp-refund-label') || 'this invoice';
      if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
        window.cpMoneyMask.wire([amountInput]);
      }
      modal.hidden = false;
      document.body.classList.add('cp-modal-open');
      amountInput.focus();
    }

    function closeModal() {
      modal.hidden = true;
      if (!document.querySelector('.cp-modal-backdrop:not([hidden])')) {
        document.body.classList.remove('cp-modal-open');
      }
    }

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-cp-refund-url]');
      if (trigger) {
        e.preventDefault();
        openModal(trigger);
        return;
      }
      if (e.target.closest('[data-cp-refund-close]') || e.target === modal) {
        closeModal();
      }
    });

    document.addEventListener('keydown', function (e) {
      if (!modal.hidden && e.key === 'Escape') closeModal();
    });
  })();
</script>

