@php
  $isHourly = $project->isHourly();
  $installments = $project->installments;
  $totalAmount = (float) ($project->total_amount ?? 0);
  $paymentPlan = (int) ($project->payment_plan ?? 1);
  $paidInstallmentCount = $installments->where('is_paid', true)->count();
  $planIsLocked = $paidInstallmentCount> 0;
  $hourlyRateConfigured = (float) ($project->hourly_rate ?? 0);
  $quotSub = $project->quotationSubtotalForFinancials();
  $quotGrand = $project->quotationGrandTotalForFinancials();
  $hasQuot = $quotSub !== null && $quotSub>= 0.01;
  // Pre-tax basis for installment % (matches DB total_amount when in sync).
  $hiddenPreTax = $paidInstallmentCount> 0
      ? $totalAmount
      : ($quotSub ?? $totalAmount);
  $canSavePaymentPlan = $paidInstallmentCount> 0 || $hasQuot;

  $allQuotations = $project->relationLoaded('quotations')
      ? $project->quotations
      : $project->quotations()->orderBy('id')->get();
  $quotationCount = $allQuotations->count();
  $pendingContentQuotations = $allQuotations
      ->filter(function ($q) {
          return $q->status !== \App\Models\ClientProjectQuotation::STATUS_ACCEPTED
              && $q->hasContent();
      })
      ->values();
  $acceptedEmptyCount = $allQuotations
      ->filter(fn ($q) => $q->isAccepted() && ! $q->hasContent())
      ->count();
  $needsAcceptedQuotation = ! $isHourly && ! $hasQuot && $paidInstallmentCount === 0;

  // The stored `total_amount` and each installment's `amount` are
  // pre-tax (profit / HST set-aside calculations depend on that).
  //
  // For the client-facing "what they actually pay" figure we add the
  // installment's PROPORTIONAL slice of the quotation's HST — not a
  // flat 13% — because a quotation line may have been HST-exempt
  // (e.g. supplied material vs labour). The effective rate is the
  // quotation's HST ÷ subtotal, so it equals 13% only when every line
  // had HST applied. See ClientProjectInstallment::effectiveHst().
  $effectiveHstRate = $hasQuot && $quotSub > 0
      ? (float) $project->acceptedQuotationsFinancialAggregateHst() / (float) $quotSub
      : \App\Models\ClientProject::HST_RATE;

  $recalcAnalysis = null;
  if (! $isHourly && $hasQuot) {
      $recalcAnalysis = app(\App\Services\RecalculateRemainingInstallmentsService::class)->analyze($project);
  }

  $quotationTabUrl = route('admin.client-projects.show', $project).'#quotation';
@endphp

@if ($isHourly)
  {{-- HOURLY BILLING — RATE FORM ---------------------------------------- --}}
  <div class="admin-card">
    <h3>Hourly billing</h3>
    <p class="admin-form-help">This project is billed by the hour. The amount earned is calculated from the rate × hours logged in the Time Tracking tab.</p>

    <form action="{{ route('admin.client-projects.update', $project) }}" method="post">
      @csrf
      @method('PUT')
      {{-- Required fields the controller validates on every update. --}}
      <input type="hidden" name="name" value="{{ $project->name }}">
      <input type="hidden" name="client_name" value="{{ $project->client_name }}">
      <input type="hidden" name="client_email" value="{{ $project->client_email }}">
      <input type="hidden" name="client_phone" value="{{ $project->client_phone }}">
      <input type="hidden" name="client_address" value="{{ $project->client_address }}">
      <input type="hidden" name="notes" value="{{ $project->notes }}">
      <input type="hidden" name="start_date" value="{{ optional($project->start_date)->toDateString() }}">
      <input type="hidden" name="end_date" value="{{ optional($project->end_date)->toDateString() }}">
      <input type="hidden" name="billing_type" value="{{ \App\Models\ClientProject::BILLING_HOURLY }}">

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cp-hourly-rate-edit">Rate (CAD / hour) <span class="cp-required">*</span></label>
          <input type="text" id="cp-hourly-rate-edit" name="hourly_rate" required
                 class="admin-input cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                
                 placeholder="0.00"
                 value="{{ $hourlyRateConfigured> 0 ? number_format($hourlyRateConfigured, 2, '.', '') : '' }}" data-cp-money-entry="direct">
          @error('hourly_rate') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label>Earned so far</label>
          <p class="cp-hourly-earned">
            {{ \App\Models\ClientProject::formatMoney($project->hourlyAmountEarned()) }}
            <span class="admin-form-help">
              {{ number_format($project->totalHours(), 2) }} h ×
              {{ \App\Models\ClientProject::formatMoney($hourlyRateConfigured) }} / h
            </span>
          </p>
        </div>
      </div>

      <button type="submit" class="admin-btn admin-btn--primary">Save hourly rate</button>
    </form>
  </div>
@else
  {{-- PAYMENT PLAN FORM ----------------------------------------------------- --}}
  <div class="admin-card">
    <h3>Payment plan</h3>

    @if ($needsAcceptedQuotation)
      <div class="cp-contract-warning cp-recalc-banner" role="status">
        <i class="fa fa-info-circle" aria-hidden="true"></i>
        <div class="cp-recalc-banner__body">
          <p class="cp-recalc-banner__title">Accepted quotation required</p>
          @if ($pendingContentQuotations->isNotEmpty())
            <p>
              This project has
              {{ $pendingContentQuotations->count() === 1 ? 'a quotation' : $pendingContentQuotations->count().' quotations' }}
              with line items, but
              {{ $pendingContentQuotations->count() === 1 ? 'it is' : 'none are' }}
              marked <strong>Accepted</strong> yet
              (current status:
              @foreach ($pendingContentQuotations as $pendingQ)
                <strong>{{ $pendingQ->statusLabel() }}</strong>@if (! $loop->last), @endif
              @endforeach).
              Set at least one to <strong>Accepted</strong> on the
              <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation</a>
              tab — then you can save a payment plan and create installment invoices.
            </p>
          @elseif ($acceptedEmptyCount > 0)
            <p>
              An accepted quotation is on file, but it has no priced line items yet.
              Add line items (or open the calculator) on the
              <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation</a>
              tab, then return here to save the payment plan.
            </p>
          @elseif ($quotationCount > 0)
            <p>
              Quotations on this project do not have priced line items yet.
              Finish a quotation on the
              <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation</a>
              tab, mark it <strong>Accepted</strong>, then save the payment plan here.
            </p>
          @else
            <p>
              Add a quotation with line items on the
              <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation</a>
              tab and set its workflow status to <strong>Accepted</strong>.
              The payment plan total and installment invoices use accepted quotations only.
            </p>
          @endif
        </div>
      </div>
    @endif

    @if ($recalcAnalysis && ($recalcAnalysis['has_discrepancy'] || ($recalcAnalysis['blocked'] && ! empty($recalcAnalysis['finance_committed']))))
      <div class="cp-contract-warning cp-recalc-banner" role="status">
        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
        <div class="cp-recalc-banner__body">
          @if ($recalcAnalysis['blocked'] && $recalcAnalysis['block_reason'])
            <p class="cp-recalc-banner__title">Cannot recalculate remaining installments</p>
            <p>{{ $recalcAnalysis['block_reason'] }}</p>
          @else
            <p class="cp-recalc-banner__title">Project total has changed</p>
            <p>
              Current payment plan total:
              <strong>{{ \App\Models\ClientProject::formatMoney($recalcAnalysis['plan_grand']) }}</strong>
              &nbsp;|&nbsp;
              Accepted quotations total:
              <strong>{{ \App\Models\ClientProject::formatMoney($recalcAnalysis['quotation_grand']) }}</strong>
            </p>
            <p class="admin-form-help" style="margin:6px 0 0;">
              Paid so far: {{ \App\Models\ClientProject::formatMoney($recalcAnalysis['paid_grand']) }}.
              Remaining to allocate: {{ \App\Models\ClientProject::formatMoney($recalcAnalysis['remaining_grand']) }}.
              @if ($recalcAnalysis['unpaid_invoice_count'] > 0)
                {{ $recalcAnalysis['unpaid_invoice_count'] }} unpaid installment invoice(s) will be replaced with new drafts.
              @endif
            </p>
            <button type="button"
                    class="admin-btn admin-btn--primary admin-btn--sm cp-recalc-open"
                    style="margin-top:10px;"
                    data-cp-recalc-open>
              <i class="fa fa-refresh" aria-hidden="true"></i>
              Recalculate Remaining Installments
            </button>
          @endif
        </div>
      </div>
    @elseif ($planIsLocked && $hasQuot)
      <div class="cp-recalc-actions" style="margin-bottom:12px;">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-recalc-open>
          <i class="fa fa-refresh" aria-hidden="true"></i>
          Recalculate Remaining Installments
        </button>
        <span class="admin-form-help">Use this if you updated accepted quotations and need to refresh unpaid installments.</span>
      </div>
    @endif

    <form action="{{ route('admin.client-projects.installments.save', $project) }}"
          method="post"
          id="cp-installments-form"
          data-total="{{ $hiddenPreTax }}"
          data-plan="{{ $paymentPlan }}"
          data-paid-count="{{ $paidInstallmentCount }}"
          data-hst-rate="{{ $effectiveHstRate }}">
      @csrf

      <input type="hidden" name="total_amount" id="cp-total" value="{{ $hiddenPreTax> 0 ? number_format($hiddenPreTax, 2, '.', '') : '' }}">

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label>Total with HST (from accepted quotation)</label>
          @if ($hasQuot)
            <p class="cp-financials-locked-total" id="cp-total-with-hst-display">{{ \App\Models\ClientProject::formatMoney($quotGrand) }}</p>
            <p class="admin-form-help">Pre-tax subtotal (used for installment %): {{ \App\Models\ClientProject::formatMoney($quotSub) }}. Manage quotations on the <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation tab</a>.</p>
          @else
            <p class="cp-financials-locked-total">—</p>
            <p class="admin-form-help">
              @if ($pendingContentQuotations->isNotEmpty())
                Mark a quotation as <strong>Accepted</strong> on the
                <a href="{{ $quotationTabUrl }}" data-cp-jump-tab="quotation">Quotation</a>
                tab to unlock this total.
              @elseif ($acceptedEmptyCount > 0)
                The accepted quotation still needs priced line items.
              @else
                Need at least one <strong>Accepted</strong> quotation with line items.
              @endif
              @if ($paidInstallmentCount> 0)
                Payment totals stay fixed while installments are paid.
              @endif
            </p>
          @endif
        </div>
        <div class="admin-form-group">
          <label for="cp-plan">Payment schedule
            <span class="cp-hint" title="Choose how the client will pay. With 2/3/4 installments, each row below takes a percentage — the dollar amount is calculated for you.">?</span>
          </label>
          <select class="admin-input" id="cp-plan" name="payment_plan" @disabled($planIsLocked)>
            <option value="1" @selected($paymentPlan === 1)>Pay in full at the end</option>
            <option value="2" @selected($paymentPlan === 2)>2 installments</option>
            <option value="3" @selected($paymentPlan === 3)>3 installments</option>
            <option value="4" @selected($paymentPlan === 4)>4 installments</option>
          </select>
          @if ($planIsLocked)
            {{-- Disabled selects don't submit, so mirror the plan value via a hidden input. --}}
            <input type="hidden" name="payment_plan" value="{{ $paymentPlan }}">
            <p class="admin-form-help">The payment plan is locked because one or more installments have already been marked paid.</p>
          @endif
        </div>
      </div>

      <div id="cp-installments-editor" class="cp-installments-editor">
        <div class="cp-installments-remaining" id="cp-installments-remaining">
          Remaining to allocate: <span data-remaining>0</span>%
        </div>
        <div class="cp-installments-rows" id="cp-installments-rows">
          @foreach ($installments as $idx => $inst)
            <div class="cp-installment-row {{ $inst->is_paid ? 'is-paid-row' : '' }}" data-row data-paid="{{ $inst->is_paid ? '1' : '0' }}">
              <div class="cp-installment-label">
                {{ $inst->label ?: 'Installment '.($idx + 1) }}
                @if ($inst->is_paid)
                  <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:6px;">Paid</span>
                @endif
              </div>
              <div class="cp-installment-pct">
                <label>Percentage</label>
                <input type="number" class="admin-input cp-pct-input" name="percentages[]" step="0.01" min="0" max="100"
                       value="{{ number_format((float) $inst->percentage, 2, '.', '') }}"
                       @readonly($inst->is_paid)
                       @if ($inst->is_paid) title="Locked — this installment is already paid." @endif>
                <span>%</span>
              </div>
              <div class="cp-installment-amount">
                <span class="admin-form-help">Amount (incl. 13% HST)</span>
                <span class="cp-amount-preview" data-amount-preview>—</span>
              </div>
            </div>
          @endforeach
        </div>
      </div>

      @error('percentages')
        <p class="admin-form-error">{{ $message }}</p>
      @enderror

      @php
        $savePlanDisabledTitle = $canSavePaymentPlan
            ? null
            : ($pendingContentQuotations->isNotEmpty()
                ? 'Mark at least one quotation as Accepted on the Quotation tab first.'
                : 'Need at least one Accepted quotation with line items before saving the payment plan.');
      @endphp
      <button type="submit"
              class="admin-btn admin-btn--primary"
              @disabled(!$canSavePaymentPlan)
              @if ($savePlanDisabledTitle) title="{{ $savePlanDisabledTitle }}" @endif>
        Save payment plan
      </button>
      @if (! $canSavePaymentPlan)
        <p class="admin-form-help" style="margin-top:8px;">
          Save stays disabled until an <strong>Accepted</strong> quotation with line items is on file.
        </p>
      @endif
    </form>
  </div>

  {{-- INSTALLMENT PAYMENT STATUS ------------------------------------------ --}}
  @if ($installments->isNotEmpty())
    <div class="admin-card">
      <h3>Installments</h3>
      <div class="admin-table-scroll">
      <table class="admin-table cp-installments-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Label</th>
            <th>%</th>
            <th class="cp-col-amount">Amount <span class="admin-form-help cp-incl-hst">(incl. 13% HST)</span></th>
            <th>Status</th>
            <th>Paid on</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach ($installments as $idx => $inst)
            <tr class="{{ $inst->is_paid ? 'cp-row-paid' : '' }}">
              <td>{{ $idx + 1 }}</td>
              <td>{{ $inst->label }}</td>
              <td>{{ number_format((float) $inst->percentage, 2) }}%</td>
              <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($inst->effectiveTotal()) }}</td>
              <td>
                @if ($inst->is_paid)
                  <span class="cp-badge" style="background:#22c55e20;color:#15803d;">Paid</span>
                @else
                  <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Unpaid</span>
                @endif
              </td>
              <td>{{ \App\Models\ClientProject::formatDate($inst->paid_on) }}</td>
              <td>
                <form action="{{ route('admin.client-projects.installments.toggle-paid', [$project, $inst]) }}" method="post" class="cp-inline-form">
                  @csrf
                  @if ($inst->is_paid)
                    <input type="hidden" name="is_paid" value="0">
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Mark unpaid</button>
                  @else
                    <input type="hidden" name="is_paid" value="1">
                    <input class="admin-input" type="date" name="paid_on" value="{{ now()->toDateString() }}" class="cp-paid-date">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Mark paid</button>
                  @endif
                </form>
              </td>
            </tr>
          @endforeach
          @php
            $totalPaidWithHst = $installments->where('is_paid', true)->reduce(
                fn ($carry, $i) => $carry + $i->effectiveTotal(),
                0.0
            );
          @endphp
          <tr class="cp-totals-row">
            <td colspan="3"><strong>Total paid</strong></td>
            <td class="cp-col-amount"><strong>{{ \App\Models\ClientProject::formatMoney($totalPaidWithHst) }}</strong></td>
            <td colspan="3">{{ number_format($project->percentPaid(), 1) }}% of total amount</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  @endif
@endif

{{-- EXPENSES ----------------------------------------------------------- --}}
@php
  $expenseSendHasClient = $project->hasClientContactInfo();
@endphp
<div class="admin-card">
  <h3>Expenses</h3>
  <form action="{{ route('admin.client-projects.expenses.store', $project) }}" method="post" enctype="multipart/form-data" class="cp-finance-form">
    @csrf
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input class="admin-input" type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input class="admin-input" type="text" name="description" required maxlength="500" value="{{ old('description') }}">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="admin-input cp-money-input"
               inputmode="decimal"
               autocomplete="off"
              
               placeholder="0.00"
               value="{{ old('amount') }}" data-cp-money-entry="direct">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add expense</button>
      </div>
    </div>
    <div class="admin-form-group cp-expense-receipt-field">
      <label for="cp-expense-receipt"><i class="fa fa-camera"></i> Receipt (optional)</label>
      <input class="admin-input" type="file" id="cp-expense-receipt" name="receipt" accept="image/*,application/pdf" capture="environment">
      <p class="admin-form-help">Image (auto-converted to WebP) or PDF. On mobile, tap to take a photo directly.</p>
    </div>
    @error('amount') <p class="admin-form-error">{{ $message }}</p> @enderror
    @error('receipt') <p class="admin-form-error">{{ $message }}</p> @enderror
  </form>

  @if ($project->expenses->isNotEmpty())
    <div class="admin-table-scroll cp-expenses-table-wrap">
    <table class="admin-table cp-expenses-table">
      <thead>
        <tr><th class="cp-expense-ref-col">#</th><th>Date</th><th>Description</th><th class="cp-col-amount">Amount</th><th>Receipt</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($project->expenses as $expense)
          @php
            $expenseOnInvoice = $expense->client_project_invoice_id !== null;
            $expenseEditOpen = (int) session('open_expense_edit', 0) === (int) $expense->id;
          @endphp
          <tr>
            <td class="cp-expense-ref-col">{{ $expense->expense_ref }}</td>
            <td>{{ \App\Models\ClientProject::formatDate($expense->occurred_on) }}</td>
            <td>{{ $expense->description }}</td>
            <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($expense->amount) }}</td>
            <td class="cp-expense-receipt-cell">
              @if ($expense->receiptUrl())
                <a href="{{ $expense->receiptUrl() }}" target="_blank" rel="noopener" title="Open receipt">
                  @if ($expense->receiptIsImagePreview())
                    <img src="{{ $expense->receiptUrl() }}" alt="Receipt {{ $expense->expense_ref }}" class="cp-expense-receipt-thumb" width="56" height="56" loading="lazy">
                  @else
                    <span class="cp-expense-receipt-pdf">PDF</span>
                  @endif
                </a>
              @else
                —
              @endif
            </td>
            <td class="cp-expense-actions-cell">
              <div class="cp-expense-actions cp-expense-actions--desktop">
                <button type="button" class="admin-btn admin-btn--sm" data-cp-expense-edit-toggle="{{ $expense->id }}">Edit</button>
                <form action="{{ route('admin.client-projects.expenses.destroy', [$project, $expense]) }}" method="post" onsubmit="return confirm('Delete this expense?');" class="cp-inline-form">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </form>
              </div>
              <div class="admin-actions-dropdown cp-expense-actions--mobile">
                <button type="button" class="admin-actions-dropdown__toggle" aria-label="Expense actions" aria-expanded="false" aria-haspopup="menu">
                  <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
                </button>
                <div class="admin-actions-dropdown__menu">
                  <button type="button" class="admin-actions-dropdown__item" data-cp-expense-edit-toggle="{{ $expense->id }}">
                    <i class="fa fa-pencil" aria-hidden="true"></i> Edit
                  </button>
                  <form action="{{ route('admin.client-projects.expenses.destroy', [$project, $expense]) }}" method="post" onsubmit="return confirm('Delete this expense?');" class="admin-actions-dropdown__form">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="admin-actions-dropdown__item admin-actions-dropdown__item--danger">
                      <i class="fa fa-trash" aria-hidden="true"></i> Delete
                    </button>
                  </form>
                </div>
              </div>
            </td>
          </tr>
          <tr class="cp-expense-edit-row" data-cp-expense-edit-row="{{ $expense->id }}" @if (! $expenseEditOpen) hidden @endif>
            <td colspan="6">
              <div class="cp-expense-edit-panel">
                <h4 class="cp-expense-edit-heading">Edit expense #{{ $expense->expense_ref }}</h4>
                @if ($expenseOnInvoice)
                  <p class="admin-form-help">This expense is on an invoice line — only the receipt file can be added or replaced here. To change the amount or description, adjust the invoice draft first.</p>
                @endif
                <form action="{{ route('admin.client-projects.expenses.update', [$project, $expense]) }}" method="post" enctype="multipart/form-data" class="cp-finance-form">
                  @csrf
                  @method('PUT')
                  @if (! $expenseOnInvoice)
                    <div class="cp-finance-form-grid">
                      <div class="admin-form-group">
                        <label for="cp-expense-edit-date-{{ $expense->id }}">Date</label>
                        <input class="admin-input" type="date" id="cp-expense-edit-date-{{ $expense->id }}" name="occurred_on" required value="{{ old('occurred_on', $expense->occurred_on->toDateString()) }}">
                      </div>
                      <div class="admin-form-group">
                        <label for="cp-expense-edit-desc-{{ $expense->id }}">Description</label>
                        <input class="admin-input" type="text" id="cp-expense-edit-desc-{{ $expense->id }}" name="description" required maxlength="500" value="{{ old('description', $expense->description) }}">
                      </div>
                      <div class="admin-form-group">
                        <label for="cp-expense-edit-amt-{{ $expense->id }}">Amount (CAD)</label>
                        <input type="text" id="cp-expense-edit-amt-{{ $expense->id }}" name="amount" required
                               class="admin-input cp-money-input"
                               inputmode="decimal"
                               autocomplete="off"
                              
                               value="{{ old('amount', number_format((float) $expense->amount, 2, '.', '')) }}" data-cp-money-entry="direct">
                      </div>
                    </div>
                  @endif
                  <div class="admin-form-group cp-expense-receipt-field">
                    <label for="cp-expense-edit-receipt-{{ $expense->id }}">
                      @if ($expenseOnInvoice)
                        Receipt
                      @else
                        Receipt <span class="cp-hint">(optional)</span>
                      @endif
                    </label>
                    <input class="admin-input" type="file" id="cp-expense-edit-receipt-{{ $expense->id }}" name="receipt" accept="image/*,application/pdf" @if ($expenseOnInvoice) required @endif>
                    @if (! $expenseOnInvoice)
                      <p class="admin-form-help">Leave blank to keep the current receipt. Add or replace with an image (stored as WebP) or a PDF — filename <strong>Receipt-{{ $expense->expense_ref }}</strong> with the matching extension.</p>
                    @else
                      <p class="admin-form-help">Image (JPEG, PNG, WebP, AVIF) or PDF — stored as WebP or PDF named <strong>Receipt-{{ $expense->expense_ref }}</strong>.</p>
                    @endif
                  </div>
                  @if ($errors->any() && $expenseEditOpen)
                    @error('occurred_on') <p class="admin-form-error">{{ $message }}</p> @enderror
                    @error('description') <p class="admin-form-error">{{ $message }}</p> @enderror
                    @error('amount') <p class="admin-form-error">{{ $message }}</p> @enderror
                    @error('receipt') <p class="admin-form-error">{{ $message }}</p> @enderror
                  @endif
                  <div class="cp-expense-edit-footer">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save</button>
                    <button type="button" class="admin-btn admin-btn--sm" data-cp-expense-edit-cancel="{{ $expense->id }}">Cancel</button>
                  </div>
                </form>
              </div>
            </td>
          </tr>
        @endforeach
        <tr class="cp-totals-row">
          <td class="cp-expense-ref-col"></td>
          <td colspan="2"><strong>Total expenses</strong></td>
          <td class="cp-col-amount"><strong>{{ \App\Models\ClientProject::formatMoney($project->totalExpensesAmount()) }}</strong></td>
          <td></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>

    <div class="cp-expenses-send-email">
      <h4 class="cp-expenses-send-heading">Send to client</h4>
      <p class="admin-form-help">
        Email the client a table of every expense, any returns/credits, and the <strong>net materials total</strong> (expenses minus returns). Receipt files are attached as WebP or PDF named <strong>Receipt-#</strong> to match the # column when present.
        {{ $project->secondaryClientEmail() ? 'A copy goes to the secondary email if set.' : '' }}
      </p>
      @if (! $expenseSendHasClient)
        <p class="admin-form-help" style="color:#b45309;">
          <i class="fa fa-exclamation-triangle"></i>
          Add the client’s name and email on the
          <a href="{{ route('admin.client-projects.edit', $project) }}">edit project</a> page before sending.
        </p>
      @endif
      <form action="{{ route('admin.client-projects.expenses.send', $project) }}"
            method="post"
            onsubmit="return confirm('Send the expenses summary to {{ e($project->clientEmailRecipientsLabel() ?: 'the client') }}?');">
        @csrf
        <button type="submit"
                class="admin-btn admin-btn--primary admin-btn--sm"
                @disabled(! $expenseSendHasClient)>
          <i class="fa fa-paper-plane"></i>
          Send expenses to client
        </button>
      </form>
    </div>
  @else
    <p class="admin-form-help">No expenses recorded yet.</p>
  @endif
</div>

{{-- RETURNS / CREDITS --------------------------------------------------- --}}
<div class="admin-card">
  <h3>Returns / Credits <span class="cp-hint" title="Money returned from suppliers or credits. These are subtracted from expenses to compute Total Spent.">?</span></h3>
  <form action="{{ route('admin.client-projects.returns.store', $project) }}" method="post" class="cp-finance-form">
    @csrf
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input class="admin-input" type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input class="admin-input" type="text" name="description" required maxlength="500">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="admin-input cp-money-input"
               inputmode="decimal"
               autocomplete="off"
              
               placeholder="0.00" data-cp-money-entry="direct">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add return</button>
      </div>
    </div>
  </form>

  @if ($project->returns->isNotEmpty())
    <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th class="cp-col-amount">Amount</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($project->returns as $return)
          <tr>
            <td>{{ \App\Models\ClientProject::formatDate($return->occurred_on) }}</td>
            <td>{{ $return->description }}</td>
            <td class="cp-col-amount">−{{ \App\Models\ClientProject::formatMoney($return->amount) }}</td>
            <td>
              <form action="{{ route('admin.client-projects.returns.destroy', [$project, $return]) }}" method="post" onsubmit="return confirm('Delete this return?');" class="cp-inline-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
        <tr class="cp-totals-row">
          <td colspan="2"><strong>Total returns</strong></td>
          <td class="cp-col-amount"><strong>−{{ \App\Models\ClientProject::formatMoney($project->totalReturnsAmount()) }}</strong></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>
  @else
    <p class="admin-form-help">No returns recorded yet.</p>
  @endif
</div>

{{-- PROFIT / HST SUMMARY ---------------------------------------------- --}}
<div class="admin-card cp-profit-card">
  <h3>Profit summary
    <span class="cp-hint" title="{{ $isHourly
      ? 'Revenue = rate × logged hours (pre-tax labour). Expenses on Financials track materials reimbursed by the client—they are shown as cash tracked but do not reduce gross/net profit below. HST set aside = 13% of labour revenue.'
      : 'Revenue = sum of pre-tax installment amounts for paid rows. When you mark an installment invoice paid, each row’s amount is updated to match that invoice’s billed subtotal (after discount), so this matches actual billed pre-tax revenue. HST set aside = 13% of the labour share of that paid revenue (materials HST is already paid at the store). If a quotation has no labour split, the full paid amount is treated as labour. With closed hours on Time Tracking, Effective hourly rate = net profit after HST ÷ those hours.' }}">?</span></h3>
  @php
    $revenue = $project->totalRevenueCollected();
    $spent = $project->totalSpent();
    $gross = $project->grossProfit();
    $hst = $project->hstToSave();
    $netAfterHst = $project->netProfitAfterHst();
    $effectiveHourlyFromTime = !$isHourly ? $project->effectiveHourlyRate() : null;
  @endphp
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">{{ $isHourly ? 'Revenue (rate × hours)' : 'Revenue (paid)' }}</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($revenue) }}</div>
    </div>
    <div class="cp-profit-tile">
      @if ($isHourly)
        <div class="cp-profit-label">Total spent (tracked)</div>
        <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($spent) }}</div>
        <p class="admin-form-help" style="margin:6px 0 0;font-size:12px;line-height:1.4;color:#6b7280;">
          Materials reimbursed on client invoices—not deducted from gross or net profit below.
        </p>
      @else
        <div class="cp-profit-label">Total spent (expenses − returns)</div>
        <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($spent) }}</div>
      @endif
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Gross profit</div>
      <div class="cp-profit-value {{ $gross>= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($gross) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">HST to save (13%)</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($hst) }}</div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value {{ $netAfterHst>= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($netAfterHst) }}</div>
    </div>
    @if ($effectiveHourlyFromTime !== null)
      <div class="cp-profit-tile">
        <div class="cp-profit-label">Effective hourly rate</div>
        <div class="cp-profit-value {{ $effectiveHourlyFromTime>= 0 ? 'is-positive' : 'is-negative' }}">
          {{ \App\Models\ClientProject::formatMoney($effectiveHourlyFromTime) }} / h
        </div>
        <p class="admin-form-help" style="margin:6px 0 0;font-size:12px;line-height:1.4;color:#6b7280;">
          Net profit after HST divided by closed hours from Time Tracking.
        </p>
      </div>
    @endif
  </div>
</div>

@if (! $isHourly && $recalcAnalysis && $recalcAnalysis['applicable'] && ! $recalcAnalysis['blocked'])
  @php
    $recalcDefaultRemaining = max(
        1,
        min(
            (int) $recalcAnalysis['max_remaining'],
            (int) ($recalcAnalysis['unpaid_count'] > 0 ? $recalcAnalysis['unpaid_count'] : $recalcAnalysis['max_remaining'])
        )
    );
    if ((float) $recalcAnalysis['remaining_pre_tax'] <= 0.01) {
        $recalcDefaultRemaining = 0;
    }
  @endphp
  <div class="cp-modal-backdrop" id="cp-recalc-modal" hidden
       data-preview-url="{{ route('admin.client-projects.installments.recalculate-preview', $project) }}"
       data-apply-url="{{ route('admin.client-projects.installments.recalculate', $project) }}"
       data-paid-count="{{ (int) $recalcAnalysis['paid_count'] }}"
       data-max-remaining="{{ (int) $recalcAnalysis['max_remaining'] }}"
       data-remaining-pre-tax="{{ number_format((float) $recalcAnalysis['remaining_pre_tax'], 2, '.', '') }}"
       data-quotation-grand="{{ number_format((float) $recalcAnalysis['quotation_grand'], 2, '.', '') }}"
       data-plan-grand="{{ number_format((float) $recalcAnalysis['plan_grand'], 2, '.', '') }}"
       data-paid-grand="{{ number_format((float) $recalcAnalysis['paid_grand'], 2, '.', '') }}"
       data-csrf="{{ csrf_token() }}">
    <div class="cp-modal cp-recalc-modal" role="dialog" aria-labelledby="cp-recalc-title" aria-modal="true">
      <div class="cp-modal-header">
        <h3 id="cp-recalc-title">Recalculate remaining installments</h3>
        <button type="button" class="cp-modal-close" data-cp-recalc-close aria-label="Close">&times;</button>
      </div>
      <div class="cp-modal-body">
        <p class="admin-form-help" style="margin-top:0;">
          New project total (accepted quotations):
          <strong>{{ \App\Models\ClientProject::formatMoney($recalcAnalysis['quotation_grand']) }}</strong>.
          Already paid:
          <strong>{{ \App\Models\ClientProject::formatMoney($recalcAnalysis['paid_grand']) }}</strong>.
          Remaining balance:
          <strong>{{ \App\Models\ClientProject::formatMoney($recalcAnalysis['remaining_grand']) }}</strong>.
        </p>
        <p class="admin-form-help">
          Paid installments and paid invoices stay unchanged. Unpaid installment invoices will be deleted and replaced with new drafts.
        </p>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-recalc-remaining-count">Remaining installments</label>
            <select id="cp-recalc-remaining-count" class="admin-input">
              @for ($n = ($recalcAnalysis['remaining_pre_tax'] > 0.01 ? 1 : 0); $n <= (int) $recalcAnalysis['max_remaining']; $n++)
                <option value="{{ $n }}" @selected($n === $recalcDefaultRemaining)>
                  @if ($n === 0)
                    None (balance already covered by paid installments)
                  @elseif ($n === 1)
                    1 remaining installment (lump sum)
                  @else
                    {{ $n }} remaining installments
                  @endif
                </option>
              @endfor
            </select>
          </div>
          <div class="admin-form-group">
            <label for="cp-recalc-split-mode">How to split the remaining balance</label>
            <select id="cp-recalc-split-mode" class="admin-input">
              <option value="defaults" selected>Standard split (e.g. 30/30/40)</option>
              @if ($recalcAnalysis['unpaid_count'] > 0)
                <option value="keep">Keep current unpaid proportions</option>
              @endif
            </select>
          </div>
        </div>

        <div id="cp-recalc-preview" class="cp-recalc-preview" aria-live="polite">
          <p class="admin-form-help">Choose options above to preview the new breakdown.</p>
        </div>
        <p id="cp-recalc-error" class="admin-form-error" hidden></p>
      </div>
      <div class="cp-modal-footer">
        <button type="button" class="admin-btn admin-btn--ghost" data-cp-recalc-close>Cancel</button>
        <button type="button" class="admin-btn admin-btn--primary" id="cp-recalc-confirm" disabled>
          Confirm recalculation
        </button>
      </div>
    </div>
  </div>
@endif
