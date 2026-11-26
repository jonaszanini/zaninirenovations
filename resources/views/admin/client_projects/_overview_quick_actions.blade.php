@php
  $quotations = $project->quotations->sortByDesc('id')->values();
  $quotationHub = route('admin.quotations.index', ['project' => $project->id]);

  $latestInvoice = $project->invoices->sortByDesc('id')->first();
  $isHourly = $project->isHourly();
  $isAccepted = $project->isAccepted();
  $isClosed = $project->isClosed();
@endphp

<div class="admin-card cp-overview-quick-actions-card" data-cp-overview-quick-actions>
  <h3 class="cp-card-head">
    <span class="cp-card-head__icon" aria-hidden="true"><i class="fa fa-bolt"></i></span>
    <span>Quick actions</span>
  </h3>

  {{-- QUOTATION SECTION ------------------------------------------------ --}}
  <section class="cp-qa-section" data-cp-qa-section="quotation">
    <div class="cp-qa-section__head">
      <h4 class="cp-qa-section__title">
        <span>Quotation</span>
        @if ($quotations->isNotEmpty())
          <span class="cp-qa-section__count">{{ $quotations->count() }}</span>
        @endif
      </h4>
      <a href="#quotation" data-cp-jump-tab="quotation" class="cp-qa-tab-link">
        <span>Quotation tab</span>
        <i class="fa fa-arrow-right" aria-hidden="true"></i>
      </a>
    </div>

    @if ($quotations->isEmpty())
      <div class="cp-qa-empty">
        <span class="cp-qa-empty__text">No quotation attached yet.</span>
        <div class="cp-qa-actions">
          <a href="{{ $quotationHub }}" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-plus" aria-hidden="true"></i> Create quotation
          </a>
        </div>
      </div>
    @else
      <div class="cp-qa-list">
        @foreach ($quotations as $quotation)
          @php
            $hasContent = $quotation->hasContent();
            $legacy = $quotation->isLegacyManualSource();
            $grand = $hasContent ? $quotation->grandTotal() : null;
          @endphp
          <div class="cp-qa-item">
            <div class="cp-qa-item__top">
              <div class="cp-qa-item__identity">
                <span class="cp-qa-item__number">{{ $quotation->quotation_number }}</span>
                <span class="cp-qa-item__type">{{ $quotation->typeLabel() }}</span>
              </div>
              <div class="cp-qa-item__badges">
                @if ($legacy)
                  <span class="cp-badge" style="background:#f4f4f5;color:#52525b;">Legacy</span>
                @endif
                <span class="cp-badge" style="background:#e0e7ff;color:#3730a3;">{{ $quotation->statusLabel() }}</span>
                @if ($quotation->isSent())
                  <span class="cp-badge" style="background:#22c55e20;color:#15803d;"><i class="fa fa-check" aria-hidden="true"></i> Sent</span>
                @endif
              </div>
            </div>

            <div class="cp-qa-item__meta">
              <span class="cp-qa-item__title">{{ $quotation->resolvedTitle() }}</span>
              <span class="cp-qa-item__total">
                {{ $grand !== null ? \App\Models\ClientProject::formatMoney($grand) : '—' }}
              </span>
            </div>

            <div class="cp-qa-actions">
              @if ($hasContent)
                <button type="button"
                        class="admin-btn admin-btn--ghost admin-btn--sm"
                        onclick="cpOpenQuotationDrawer('qd-{{ $quotation->id }}')">
                  <i class="fa fa-eye" aria-hidden="true"></i> View
                </button>
              @endif
              @unless ($legacy)
                <a href="{{ route('admin.client-projects.quotation.edit', [$project, $quotation]) }}"
                   class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil" aria-hidden="true"></i> Edit
                </a>
              @endunless
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </section>

  {{-- INVOICE SECTION -------------------------------------------------- --}}
  <section class="cp-qa-section" data-cp-qa-section="invoice">
    <div class="cp-qa-section__head">
      <h4 class="cp-qa-section__title">
        <span>Invoice</span>
        @if ($project->invoices->count() > 1)
          <span class="cp-qa-section__count">{{ $project->invoices->count() }}</span>
        @endif
      </h4>
      <a href="#invoice" data-cp-jump-tab="invoice" class="cp-qa-tab-link">
        <span>Invoice tab</span>
        <i class="fa fa-arrow-right" aria-hidden="true"></i>
      </a>
    </div>

    @if (! $latestInvoice)
      <div class="cp-qa-empty">
        <span class="cp-qa-empty__text">
          @if ($isClosed)
            No invoice was issued before this project was {{ strtolower($project->statusLabel()) }}.
          @elseif (! $isHourly && ! $isAccepted)
            Invoices unlock once status reaches Proposal Accepted.
          @else
            No invoice created yet.
          @endif
        </span>
        <div class="cp-qa-actions">
          @if (! $isClosed)
            @if ($isHourly)
              @php
                $qaHourlyRate = round((float) ($project->hourly_rate ?? 0), 2);
                $qaHasUnbilled = $project->timeEntries->whereNotNull('hours')->whereNull('client_project_invoice_id')->isNotEmpty();
                $qaCanCreateHourly = $qaHasUnbilled && $qaHourlyRate > 0;
              @endphp
              @if ($qaCanCreateHourly)
                <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
                  @csrf
                  <input type="hidden" name="mode" value="hourly">
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
                    <i class="fa fa-clock-o" aria-hidden="true"></i> Create invoice from hours
                  </button>
                </form>
              @endif
              <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
                @csrf
                <input type="hidden" name="mode" value="manual">
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-plus" aria-hidden="true"></i> New manual invoice
                </button>
              </form>
            @elseif ($isAccepted)
              @include('admin.client_projects._billing_actions', ['project' => $project, 'billingCompact' => true])
              <form action="{{ route('admin.client-projects.invoices.create', $project) }}" method="post" class="cp-inline-form">
                @csrf
                <input type="hidden" name="mode" value="manual">
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-plus" aria-hidden="true"></i> New manual invoice
                </button>
              </form>
            @endif
          @endif
          <a href="#invoice" data-cp-jump-tab="invoice" class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-file-text-o" aria-hidden="true"></i> Go to Invoice tab
          </a>
        </div>
      </div>
    @else
      @php
        $invTotal = round((float) $latestInvoice->total, 2);
        $invPaid = ($latestInvoice->isPaid() || $latestInvoice->isRefunded())
            ? $latestInvoice->refundableBalance()
            : round(max(0, (float) ($latestInvoice->paid_amount ?? 0) - $latestInvoice->refundCash()), 2);
        $invBalance = $latestInvoice->isSettled()
            ? 0.0
            : round(max(0, $invTotal - $invPaid), 2);
        $invStatusLabel = \App\Models\ClientProjectInvoice::statusLabel($latestInvoice->status);
        $invStatusColor = \App\Models\ClientProjectInvoice::statusColor($latestInvoice->status);

        $invHasContent = $latestInvoice->hasContent();
        $invCompleteItems = array_filter(
            $latestInvoice->cleanItems(),
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
        );
        $invSendBlockers = [];
        if (blank(trim((string) $project->client_email))) {
            $invSendBlockers[] = "Add the client's email address.";
        }
        if (count($invCompleteItems) === 0) {
            $invSendBlockers[] = 'Add at least one line item with a description and amount.';
        }
        if (empty($latestInvoice->due_on)) {
            $invSendBlockers[] = 'Set a payment due date.';
        }
        $invCanSend = empty($invSendBlockers);
        $invSendTitle = $invCanSend
            ? 'Generate a fresh PDF and email it to the client.'
            : implode(' ', $invSendBlockers);

        $invClientEmail = $project->clientEmailRecipientsLabel();
        $invSendConfirm = "This will generate a new PDF, email it to {$invClientEmail}, and save it on the server. Continue?";
        $invReceiptCount = $project->expenses->filter(fn ($expense) => filled($expense->receipt_path))->count();
        $invSendReceiptCheckbox = $invReceiptCount > 0;
        $invSendConfirmLabel = $latestInvoice->wasEverSent() ? 'Resend' : 'Send';

        $invCanMarkPaid = ! $latestInvoice->isSettled()
            && ! $isClosed
            && $invHasContent
            && $invTotal > 0;
        $invShowMarkPaidStatusPicker = $project->invoices
            ->where('status', \App\Models\ClientProjectInvoice::STATUS_PAID)
            ->isEmpty() && ! $isClosed;

        $invCanStopReminders = ! $isClosed
            && $latestInvoice->isSent()
            && ! $latestInvoice->dailyRemindersPaused();
        $invPauseConfirm = "Stop daily payment reminders for invoice {$latestInvoice->invoice_number}? The invoice stays on this line. You can still send a one-off reminder, or resume daily reminders later.";
      @endphp

      <div class="cp-qa-item">
        <div class="cp-qa-item__top">
          <div class="cp-qa-item__identity">
            <span class="cp-qa-item__number">{{ $latestInvoice->invoice_number }}</span>
            <span class="cp-qa-item__type">{{ $latestInvoice->installmentTitle() }}</span>
          </div>
          <div class="cp-qa-item__badges">
            <span class="cp-badge" style="background: {{ $invStatusColor }}20; color: {{ $invStatusColor }};">
              {{ $invStatusLabel }}
            </span>
          </div>
        </div>

        <div class="cp-qa-metrics">
          <div class="cp-qa-metric">
            <span class="cp-qa-metric__label">Total</span>
            <strong class="cp-qa-metric__value">{{ \App\Models\ClientProject::formatMoney($invTotal) }}</strong>
          </div>
          <div class="cp-qa-metric">
            <span class="cp-qa-metric__label">Amount paid</span>
            <strong class="cp-qa-metric__value cp-qa-metric__value--paid">{{ \App\Models\ClientProject::formatMoney($invPaid) }}</strong>
          </div>
          <div class="cp-qa-metric">
            <span class="cp-qa-metric__label">Balance due</span>
            <strong class="cp-qa-metric__value {{ $invBalance > 0 ? 'cp-qa-metric__value--due' : '' }}">
              {{ \App\Models\ClientProject::formatMoney($invBalance) }}
            </strong>
          </div>
        </div>

        <div class="cp-qa-actions">
          @if (! $latestInvoice->isSettled() && ! $isClosed)
            <a href="{{ route('admin.client-projects.invoices.edit', [$project, $latestInvoice]) }}"
               class="admin-btn admin-btn--ghost admin-btn--sm">
              <i class="fa fa-eye" aria-hidden="true"></i> View
            </a>
          @else
            <a href="#invoice"
               data-cp-jump-tab="invoice"
               class="admin-btn admin-btn--ghost admin-btn--sm">
              <i class="fa fa-eye" aria-hidden="true"></i> View
            </a>
          @endif

          @if ($invHasContent || $invTotal > 0)
            <a href="{{ route('admin.client-projects.invoices.pdf', [$project, $latestInvoice]) }}"
               target="_blank"
               rel="noopener"
               class="admin-btn admin-btn--ghost admin-btn--sm">
              <i class="fa fa-download" aria-hidden="true"></i> Download PDF
            </a>
          @endif

          @if (! $latestInvoice->isSettled() && ! $isClosed)
            @if ($invCanSend)
              <form action="{{ route('admin.client-projects.invoices.send', [$project, $latestInvoice]) }}"
                    method="post"
                    class="cp-inline-form"
                    @if ($invSendReceiptCheckbox)
                      data-confirm="{{ $invSendConfirm }}"
                      data-confirm-title="Send invoice"
                      data-confirm-label="{{ $invSendConfirmLabel }}"
                      data-confirm-checkbox-template="cp-send-invoice-receipt-option"
                      data-confirm-checkbox-name="attach_expense_receipts"
                    @else
                      onsubmit="return confirm({{ json_encode($invSendConfirm) }});"
                    @endif>
                @csrf
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
                  <i class="fa fa-paper-plane" aria-hidden="true"></i> Send invoice
                </button>
              </form>
            @else
              <button type="button"
                      class="admin-btn admin-btn--primary admin-btn--sm"
                      disabled
                      title="{{ $invSendTitle }}">
                <i class="fa fa-paper-plane" aria-hidden="true"></i> Send invoice
              </button>
            @endif
          @endif

          @if ($invCanMarkPaid)
            <button type="button"
                    class="admin-btn admin-btn--success admin-btn--sm"
                    data-cp-markpaid-url="{{ route('admin.client-projects.invoices.mark-paid', [$project, $latestInvoice]) }}"
                    data-cp-markpaid-amount="{{ number_format($invTotal, 2, '.', '') }}"
                    data-cp-markpaid-label="Invoice #{{ $latestInvoice->invoice_number }}"
                    data-cp-markpaid-first-paid="{{ $invShowMarkPaidStatusPicker ? '1' : '0' }}">
              <i class="fa fa-check" aria-hidden="true"></i> Mark paid
            </button>
          @endif

          @if ($invCanStopReminders)
            <form action="{{ route('admin.client-projects.invoices.reminders.toggle', [$project, $latestInvoice]) }}"
                  method="post"
                  class="cp-inline-form"
                  onsubmit="return confirm({{ json_encode($invPauseConfirm) }});">
              @csrf
              <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
                <i class="fa fa-pause" aria-hidden="true"></i> Stop reminders
              </button>
            </form>
          @endif
        </div>
      </div>
    @endif
  </section>
</div>
