@php
  $isHourly = $project->isHourly();
  $installments = $project->installments;
  $totalAmount = (float) ($project->total_amount ?? 0);
  $paymentPlan = (int) ($project->payment_plan ?? 1);
  $paidInstallmentCount = $installments->where('is_paid', true)->count();
  $planIsLocked = $paidInstallmentCount > 0;
  $hourlyRateConfigured = (float) ($project->hourly_rate ?? 0);

  // The stored `total_amount` and each installment's `amount` are
  // pre-tax (profit / HST set-aside calculations depend on that).
  // For the client-facing "what they actually pay" figure we multiply
  // by (1 + HST_RATE) on display only.
  $hstRate = \App\Models\ClientProject::HST_RATE;
  $withTax = 1 + $hstRate;
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
                 class="cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 placeholder="0.00"
                 value="{{ $hourlyRateConfigured > 0 ? number_format($hourlyRateConfigured, 2, '.', '') : '' }}">
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
    <form action="{{ route('admin.client-projects.installments.save', $project) }}"
          method="post"
          id="cp-installments-form"
          data-total="{{ $totalAmount }}"
          data-plan="{{ $paymentPlan }}"
          data-paid-count="{{ $paidInstallmentCount }}"
          data-hst-rate="{{ $hstRate }}">
      @csrf

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cp-total">Total project amount (CAD) <span class="cp-required">*</span></label>
          <input type="text" id="cp-total" name="total_amount" required
                 class="cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 placeholder="0.00"
                 value="{{ old('total_amount', $totalAmount > 0 ? number_format($totalAmount, 2, '.', '') : '') }}">
          @error('total_amount') <p class="admin-form-error">{{ $message }}</p> @enderror
        </div>
        <div class="admin-form-group">
          <label for="cp-plan">Payment schedule
            <span class="cp-hint" title="Choose how the client will pay. With 2/3/4 installments, each row below takes a percentage — the dollar amount is calculated for you.">?</span>
          </label>
          <select id="cp-plan" name="payment_plan" @disabled($planIsLocked)>
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
                <input type="number" class="cp-pct-input" name="percentages[]" step="0.01" min="0" max="100"
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

      <button type="submit" class="admin-btn admin-btn--primary">Save payment plan</button>
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
              <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney((float) $inst->amount * $withTax) }}</td>
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
                    <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="cp-paid-date">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Mark paid</button>
                  @endif
                </form>
              </td>
            </tr>
          @endforeach
          <tr class="cp-totals-row">
            <td colspan="3"><strong>Total paid</strong></td>
            <td class="cp-col-amount"><strong>{{ \App\Models\ClientProject::formatMoney($project->totalRevenueCollected() * $withTax) }}</strong></td>
            <td colspan="3">{{ number_format($project->percentPaid(), 1) }}% of total amount</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  @endif
@endif

{{-- EXPENSES ----------------------------------------------------------- --}}
<div class="admin-card">
  <h3>Expenses</h3>
  <form action="{{ route('admin.client-projects.expenses.store', $project) }}" method="post" class="cp-finance-form">
    @csrf
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input type="text" name="description" required maxlength="500">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="cp-money-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="0.00">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add expense</button>
      </div>
    </div>
    @error('amount') <p class="admin-form-error">{{ $message }}</p> @enderror
  </form>

  @if ($project->expenses->isNotEmpty())
    <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th class="cp-col-amount">Amount</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($project->expenses as $expense)
          <tr>
            <td>{{ \App\Models\ClientProject::formatDate($expense->occurred_on) }}</td>
            <td>{{ $expense->description }}</td>
            <td class="cp-col-amount">{{ \App\Models\ClientProject::formatMoney($expense->amount) }}</td>
            <td>
              <form action="{{ route('admin.client-projects.expenses.destroy', [$project, $expense]) }}" method="post" onsubmit="return confirm('Delete this expense?');" class="cp-inline-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
        <tr class="cp-totals-row">
          <td colspan="2"><strong>Total expenses</strong></td>
          <td class="cp-col-amount"><strong>{{ \App\Models\ClientProject::formatMoney($project->totalExpensesAmount()) }}</strong></td>
          <td></td>
        </tr>
      </tbody>
    </table>
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
        <input type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input type="text" name="description" required maxlength="500">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="cp-money-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="0.00">
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
  <h3>Profit summary <span class="cp-hint" title="Revenue = paid installments (fixed) or rate × logged hours (hourly). HST set aside at 13% of revenue (Canadian HST).">?</span></h3>
  @php
    $revenue = $project->totalRevenueCollected();
    $spent = $project->totalSpent();
    $gross = $project->grossProfit();
    $hst = $project->hstToSave();
    $netAfterHst = $project->netProfitAfterHst();
  @endphp
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">{{ $isHourly ? 'Revenue (rate × hours)' : 'Revenue (paid)' }}</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($revenue) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total spent (expenses − returns)</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($spent) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Gross profit</div>
      <div class="cp-profit-value {{ $gross >= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($gross) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">HST to save (13%)</div>
      <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($hst) }}</div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value {{ $netAfterHst >= 0 ? 'is-positive' : 'is-negative' }}">{{ \App\Models\ClientProject::formatMoney($netAfterHst) }}</div>
    </div>
  </div>
</div>
