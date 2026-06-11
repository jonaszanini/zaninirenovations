@php
  use App\Models\ClientProjectHelperPayment;

  $assignedHelperIds = $project->helpers->pluck('id')->map(fn ($id) => (int) $id)->all();
  $assignedHelpers = $project->helpers->sortBy('name')->values();
  $paymentMethods = ClientProjectHelperPayment::paymentMethodOptions();

  $unpaidClosed = $project->helperTimeEntries
      ->filter(fn ($e) => $e->isPayable() && ! $e->isPaid())
      ->values();

  $closedTimeEntries = $project->helperTimeEntries
      ->filter(fn ($e) => ! $e->isExpense() && $e->hours !== null && ! $e->isOpen());

  $expenseEntries = $project->helperTimeEntries
      ->filter(fn ($e) => $e->isExpense() && (float) ($e->expense_amount ?? 0)>= 0.01);

  $helperTotalHours = round((float) $closedTimeEntries->sum(fn ($e) => (float) $e->hours), 2);
  $helperTotalLabour = round((float) $closedTimeEntries->sum(fn ($e) => $e->lineAmount()), 2);
  $helperTotalReimbursements = round((float) $expenseEntries->sum(fn ($e) => $e->lineAmount()), 2);
  $helperAmountPaid = round((float) $project->helperPayments->sum('total_amount'), 2);
  $helperAmountToBePaid = round((float) $unpaidClosed->sum(fn ($e) => $e->lineAmount()), 2);

  $helperOpenCount = $project->helperTimeEntries->filter(fn ($e) => $e->isOpen())->count();

  $pendingAckPayments = $project->helperPayments
      ->filter(fn ($p) => ! $p->isAcknowledged())
      ->values();
@endphp

<div class="admin-card cp-helper-card" id="cp-helper-time">
  <div class="cp-helper-card__head">
    <h3>Helper time tracking</h3>
    <a href="{{ route('admin.helpers.index', ['return_project' => $project->id]) }}"
       class="admin-btn admin-btn--ghost admin-btn--sm cp-helper-config-btn"
       title="Manage helpers">
      <i class="fa fa-cog"></i>
      <span class="cp-helper-config-btn__label">Manage helpers</span>
    </a>
  </div>

  @if ($activeHelpers->isEmpty())
    <p class="admin-form-help">
      No helpers registered yet.
      <a href="{{ route('admin.helpers.create', ['return_project' => $project->id]) }}">Add a helper</a>
      to log their time on this project.
    </p>
  @else
    <form action="{{ route('admin.client-projects.helpers.sync', $project) }}" method="post" class="cp-helper-assign-form">
      @csrf
      <h4 class="cp-time-saved-heading">Helpers on this project</h4>
      <p class="admin-form-help cp-time-saved-help">Select who may log time here. Rates are set under Manage helpers.</p>
      <div class="cp-helper-assign-row">
        <div class="cp-helper-assign-grid">
          @foreach ($activeHelpers as $helper)
            <label class="admin-checkbox admin-checkbox--block admin-checkbox-tile cp-helper-assign-option">
              <input type="checkbox"
                     class="admin-checkbox__input"
                     name="helper_ids[]"
                     value="{{ $helper->id }}"
                     @checked(in_array($helper->id, $assignedHelperIds, true))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text cp-helper-assign-option__body">
                <strong>{{ $helper->name }}</strong>
                <span class="cp-helper-assign-option__meta">
                  {{ \App\Models\ClientProject::formatMoney((float) $helper->hourly_rate) }}/h
                  @if (filled($helper->email))
                    · {{ $helper->email }}
                  @endif
                </span>
              </span>
            </label>
          @endforeach
        </div>
        <div class="cp-helper-assign-row__action">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save Helpers</button>
        </div>
      </div>
    </form>

    @if ($assignedHelpers->isEmpty())
      <p class="admin-form-help" style="margin-top:16px;">Assign at least one helper above before logging time.</p>
    @else
      <h4 class="cp-time-saved-heading" style="margin-top:24px;">Log helper time</h4>
      <form action="{{ route('admin.client-projects.helper-time.store', $project) }}"
            method="post"
            class="cp-finance-form cp-helper-panel cp-helper-log-form"
            data-cp-time-form>
        @csrf
        <div class="cp-helper-time-form-grid">
          <div class="admin-form-group col-3">
            <label>Helper</label>
            <select class="admin-input" name="helper_id" required>
              @foreach ($assignedHelpers as $helper)
                <option value="{{ $helper->id }}" @selected(old('helper_id') == $helper->id)>{{ $helper->name }}</option>
              @endforeach
            </select>
            @error('helper_id') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group col-2">
            <label>Date</label>
            <input class="admin-input" type="date" name="occurred_on" required value="{{ old('occurred_on', now()->toDateString()) }}">
          </div>
          <div class="admin-form-group col-2">
            <label>Start time</label>
            @include('admin.client_projects._cp_time_input', ['name' => 'start_time', 'role' => 'start', 'value' => old('start_time')])
          </div>
          <div class="admin-form-group col-2">
            <label>End time</label>
            @include('admin.client_projects._cp_time_input', ['name' => 'end_time', 'role' => 'end', 'value' => old('end_time')])
          </div>
          <div class="admin-form-group col-2">
            <label>Note (optional)</label>
            <input class="admin-input" type="text" name="note" maxlength="500" value="{{ old('note') }}">
          </div>
          <div class="admin-form-group col-1">
            <label>Hours</label>
            <input type="text" name="hours" class="admin-input cp-hours-input" inputmode="decimal" autocomplete="off" placeholder="auto" data-cp-time-hours>
          </div>
        </div>
        @error('hours') <p class="admin-form-error">{{ $message }}</p> @enderror
        @error('end_time') <p class="admin-form-error">{{ $message }}</p> @enderror
        <div class="cp-helper-log-form__submit">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add helper time entry</button>
        </div>
      </form>

      <h4 class="cp-time-saved-heading" style="margin-top:20px;">Log other payment</h4>
      <p class="admin-form-help cp-time-saved-help">Reimburse the helper for meals, materials they bought, Uber, etc.</p>
      <form action="{{ route('admin.client-projects.helper-time.store', $project) }}"
            method="post"
            class="cp-finance-form cp-helper-panel cp-helper-expense-form">
        @csrf
        <input type="hidden" name="entry_type" value="expense">
        <div class="cp-helper-time-form-grid">
          <div class="admin-form-group col-3">
            <label>Helper</label>
            <select class="admin-input" name="helper_id" required>
              @foreach ($assignedHelpers as $helper)
                <option value="{{ $helper->id }}" @selected(old('helper_id') == $helper->id)>{{ $helper->name }}</option>
              @endforeach
            </select>
            @error('helper_id') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group col-2">
            <label>Date</label>
            <input class="admin-input" type="date" name="occurred_on" required value="{{ old('occurred_on', now()->toDateString()) }}">
          </div>
          <div class="admin-form-group col-3">
            <label>Reason</label>
            <input class="admin-input" type="text" name="expense_description" maxlength="500" required
                   value="{{ old('expense_description') }}"
                   placeholder="e.g. Lunch, screws &amp; anchors, Uber to site">
            @error('expense_description') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group col-2">
            <label>Amount</label>
            <input type="text" name="expense_amount" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" required
                   value="{{ old('expense_amount') }}" placeholder="0.00">
            @error('expense_amount') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
        </div>
        <div class="cp-helper-log-form__submit">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add reimbursement entry</button>
        </div>
      </form>
    @endif
  @endif

  @if ($project->helperTimeEntries->isNotEmpty())
    <div class="cp-helper-summary">
      <h4 class="cp-time-saved-heading">Helper labour summary</h4>
      <div class="cp-profit-grid cp-helper-summary__grid">
        <div class="cp-profit-tile">
          <div class="cp-profit-label">Total hours worked</div>
          <div class="cp-profit-value">{{ number_format($helperTotalHours, 2) }}</div>
        </div>
        <div class="cp-profit-tile">
          <div class="cp-profit-label">Labour total (rate × hours)</div>
          <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($helperTotalLabour) }}</div>
        </div>
        <div class="cp-profit-tile">
          <div class="cp-profit-label">Other reimbursements</div>
          <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($helperTotalReimbursements) }}</div>
        </div>
        <div class="cp-profit-tile">
          <div class="cp-profit-label">Amount paid</div>
          <div class="cp-profit-value is-positive">{{ \App\Models\ClientProject::formatMoney($helperAmountPaid) }}</div>
        </div>
        <div class="cp-profit-tile cp-profit-tile--highlight">
          <div class="cp-profit-label">Amount to be paid</div>
          <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($helperAmountToBePaid) }}</div>
        </div>
      </div>
      @if ($helperOpenCount> 0)
        <p class="admin-form-help cp-helper-summary__note">
          {{ $helperOpenCount }} in-progress {{ \Illuminate\Support\Str::plural('entry', $helperOpenCount) }} not included — close them with an end time to count toward totals.
        </p>
      @endif
    </div>

    <h4 class="cp-time-saved-heading" style="margin-top:24px;">Helper entries</h4>
    <p class="admin-form-help cp-time-saved-help">
      Select unpaid entries below, then mark as paid. Paid entries are locked and recorded as project expenses.
      @if ($pendingAckPayments->isNotEmpty())
        Use the checkboxes on days awaiting helper confirmation to mark acknowledged in bulk.
      @endif
    </p>

    @if ($pendingAckPayments->isNotEmpty())
      <form action="{{ route('admin.client-projects.helper-payments.acknowledge-bulk', $project) }}"
            method="post"
            class="cp-helper-ack-form"
            id="cp-helper-ack-form"
            data-cp-helper-ack-form
            onsubmit="return confirm('Mark selected payments as acknowledged by the helper?');">
        @csrf
        <div class="cp-helper-pay-toolbar cp-helper-panel cp-helper-ack-toolbar">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-cp-helper-ack-submit disabled>
            Mark selected as acknowledged
          </button>
        </div>
        @error('payment_ids') <p class="admin-form-error">{{ $message }}</p> @enderror
      </form>
    @endif

    @if ($unpaidClosed->isNotEmpty())
      <form action="{{ route('admin.client-projects.helper-time.mark-paid', $project) }}"
            method="post"
            class="cp-helper-pay-form"
            id="cp-helper-pay-form"
            data-cp-helper-pay-form
            onsubmit="return confirm('Mark selected days as paid, add a project expense, and email the helper?');">
        @csrf
        <div class="cp-helper-pay-toolbar cp-helper-panel">
          <div class="admin-form-group">
            <label>Payment method</label>
            <select class="admin-input" name="payment_method" required>
              @foreach ($paymentMethods as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group">
            <label>Paid on</label>
            <input class="admin-input" type="date" name="paid_on" value="{{ now()->toDateString() }}">
          </div>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-cp-helper-pay-submit disabled>
            Mark selected as paid
          </button>
        </div>
        @error('entry_ids') <p class="admin-form-error">{{ $message }}</p> @enderror
      </form>
    @endif

        @include('admin.client_projects._helper_time_entries', [
          'project' => $project,
          'assignedHelpers' => $assignedHelpers,
          'showPayCheckboxes' => $unpaidClosed->isNotEmpty(),
          'payFormId' => $unpaidClosed->isNotEmpty() ? 'cp-helper-pay-form' : null,
          'showAckCheckboxes' => $pendingAckPayments->isNotEmpty(),
          'ackFormId' => $pendingAckPayments->isNotEmpty() ? 'cp-helper-ack-form' : null,
        ])
  @elseif ($activeHelpers->isNotEmpty() && $assignedHelpers->isNotEmpty())
    <p class="admin-form-help" style="margin-top:16px;">No helper time entries recorded yet.</p>
  @endif
</div>
