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
      <h4 class="cp-time-saved-heading">Helpers on this project
        @include('admin.partials.info_tip', [
          'tipText' => 'Select who may log time here. Rates are set under Manage helpers.',
          'tipLabel' => 'Helpers on this project',
        ])
      </h4>
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

      <h4 class="cp-time-saved-heading" style="margin-top:20px;">Log other payment
        @include('admin.partials.info_tip', [
          'tipText' => 'Reimburse the helper for meals, materials they bought, Uber, etc.',
          'tipLabel' => 'Log other payment',
        ])
      </h4>
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
                   value="{{ old('expense_amount') }}" placeholder="0.00" data-cp-money-entry="direct">
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
      @php
        // Build a JS-safe map of entry_id => line amount so the "Remaining" counter can react to checkbox changes.
        $entryAmounts = $unpaidClosed->mapWithKeys(fn ($e) => [$e->id => round((float) $e->lineAmount(), 2)])->toJson();
      @endphp
      <form action="{{ route('admin.client-projects.helper-time.mark-paid', $project) }}"
            method="post"
            class="cp-helper-pay-form"
            id="cp-helper-pay-form"
            data-cp-helper-pay-form
            data-entry-amounts="{{ $entryAmounts }}">
        @csrf
        <div class="cp-helper-pay-toolbar cp-helper-panel" id="cp-helper-pay-toolbar">

          {{-- Split builder --}}
          <div class="cp-split-builder" id="cp-split-builder">
            <div class="cp-split-builder__head">
              <span class="cp-split-builder__label">Payment method(s)</span>
              <span class="cp-split-builder__remaining" id="cp-split-remaining">
                Remaining: <strong id="cp-split-remaining-val">—</strong>
              </span>
            </div>

            <div id="cp-split-rows">
              {{-- First split row (always present) --}}
              <div class="cp-split-row" data-split-row>
                <select class="admin-input cp-split-row__method" name="splits[0][method]" required>
                  <option value="" disabled selected>Select method…</option>
                  @foreach ($paymentMethods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
                <input class="admin-input cp-money-input cp-split-row__amount" type="text" inputmode="decimal" autocomplete="off"
                       name="splits[0][amount]" placeholder="0.00" required data-cp-money-entry="direct">
                <button type="button" class="cp-split-row__remove" title="Remove" onclick="cpRemoveSplitRow(this)" disabled>
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>

            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-split-add-btn" id="cp-split-add-btn"
                    onclick="cpAddSplitRow()">
              <i class="fa fa-plus"></i> Add payment method
            </button>
          </div>

          <div class="admin-form-group" style="margin-top:12px;">
            <label>Paid on</label>
            <input class="admin-input" type="date" name="paid_on" value="{{ now()->toDateString() }}">
          </div>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm" data-cp-helper-pay-submit disabled>
            Mark selected as paid
          </button>
        </div>
        @error('entry_ids') <p class="admin-form-error">{{ $message }}</p> @enderror
        @error('splits') <p class="admin-form-error">{{ $message }}</p> @enderror
      </form>

      <style>
        .cp-split-builder { margin-bottom: 4px; }
        .cp-split-builder__head { display: flex; align-items: center; gap: 16px; margin-bottom: 8px; }
        .cp-split-builder__label { font-size: 13px; font-weight: 600; color: #374151; }
        .cp-split-builder__remaining { font-size: 13px; color: #6b7280; }
        .cp-split-builder__remaining strong { color: #374151; }
        .cp-split-builder__remaining.is-balanced strong { color: #16a34a; }
        .cp-split-builder__remaining.is-over strong { color: #dc2626; }
        #cp-split-rows { display: flex; flex-direction: column; gap: 8px; }
        .cp-split-row { display: flex; align-items: center; gap: 8px; }
        .cp-split-row__method { flex: 1; min-width: 120px; }
        .cp-split-row__amount { width: 120px; }
        .cp-split-row__remove {
          background: none; border: 1px solid #e5e7eb; border-radius: 6px;
          color: #9ca3af; padding: 5px 8px; cursor: pointer; line-height: 1;
          transition: color .15s, border-color .15s;
        }
        .cp-split-row__remove:not(:disabled):hover { color: #ef4444; border-color: #ef4444; }
        .cp-split-row__remove:disabled { opacity: .35; cursor: default; }
        .cp-split-add-btn { margin-top: 4px; font-size: 12px; }
      </style>

      <script>
        (function () {
          var splitIdx = 1; // next index (row 0 is already rendered)
          var maxSplits = 5;
          var paymentMethods = @json($paymentMethods);

          // Build entry-amount map from the form's data attribute.
          var entryAmounts = JSON.parse(
            document.getElementById('cp-helper-pay-form').dataset.entryAmounts || '{}'
          );

          // ── Remaining counter ──────────────────────────────────────────────────
          function getSelectedTotal() {
            var total = 0;
            document.querySelectorAll('input[name="entry_ids[]"]:checked').forEach(function (cb) {
              total += entryAmounts[cb.value] || 0;
            });
            return Math.round(total * 100) / 100;
          }

          function getSplitsTotal() {
            var total = 0;
            document.querySelectorAll('#cp-split-rows .cp-split-row__amount').forEach(function (inp) {
              var v = window.cpMoneyMask ? window.cpMoneyMask.parse(inp.value) : parseFloat(String(inp.value || '').replace(/,/g, ''));
              if (!isNaN(v)) total += v;
            });
            return Math.round(total * 100) / 100;
          }

          function formatMoney(n) {
            return 'CA$' + n.toFixed(2);
          }

          function updateRemaining() {
            var selected = getSelectedTotal();
            var entered  = getSplitsTotal();
            var rem      = Math.round((selected - entered) * 100) / 100;
            var el       = document.getElementById('cp-split-remaining');
            var valEl    = document.getElementById('cp-split-remaining-val');

            if (selected === 0) {
              valEl.textContent = '—';
              el.className = 'cp-split-builder__remaining';
              return;
            }

            valEl.textContent = formatMoney(rem);
            el.className = 'cp-split-builder__remaining' +
              (Math.abs(rem) <= 0.01 ? ' is-balanced' : (rem < -0.01 ? ' is-over' : ''));
          }

          function autoFillFirstRow() {
            var selected = getSelectedTotal();
            if (selected <= 0) return;
            // Only auto-fill when there's just one split row and its amount is empty.
            var rows = document.querySelectorAll('#cp-split-rows .cp-split-row');
            if (rows.length === 1) {
              var amtInput = rows[0].querySelector('.cp-split-row__amount');
              if (!amtInput.value) {
                amtInput.value = selected.toFixed(2);
                if (window.cpMoneyMask) window.cpMoneyMask.wire(amtInput);
                updateRemaining();
              }
            }
          }

          // ── Add / remove rows ─────────────────────────────────────────────────
          window.cpAddSplitRow = function () {
            var rows = document.querySelectorAll('#cp-split-rows .cp-split-row');
            if (rows.length >= maxSplits) return;

            var opts = '<option value="" disabled selected>Select method\u2026</option>' +
              Object.entries(paymentMethods).map(function (kv) {
                return '<option value="' + kv[0] + '">' + kv[1] + '</option>';
              }).join('');

            var div = document.createElement('div');
            div.className = 'cp-split-row';
            div.setAttribute('data-split-row', '');
            div.innerHTML =
              '<select class="admin-input cp-split-row__method" name="splits[' + splitIdx + '][method]" required>' + opts + '</select>' +
              '<input class="admin-input cp-money-input cp-split-row__amount" type="text" inputmode="decimal" autocomplete="off" name="splits[' + splitIdx + '][amount]" placeholder="0.00" required data-cp-money-entry="direct">' +
              '<button type="button" class="cp-split-row__remove" title="Remove" onclick="cpRemoveSplitRow(this)"><i class="fa fa-times"></i></button>';

            document.getElementById('cp-split-rows').appendChild(div);
            splitIdx++;

            var amtInput = div.querySelector('.cp-split-row__amount');
            if (window.cpMoneyMask) window.cpMoneyMask.wire(amtInput);

            // Auto-suggest the remaining amount.
            var rem = Math.round((getSelectedTotal() - getSplitsTotal()) * 100) / 100;
            if (rem > 0.005) {
              amtInput.value = rem.toFixed(2);
              if (window.cpMoneyMask) window.cpMoneyMask.wire(amtInput);
              updateRemaining();
            }

            updateRemoveButtons();
            updateAddButton();
            amtInput.focus();
          };

          window.cpRemoveSplitRow = function (btn) {
            var row = btn.closest('[data-split-row]');
            row.parentNode.removeChild(row);
            updateRemaining();
            updateRemoveButtons();
            updateAddButton();
          };

          function updateRemoveButtons() {
            var rows = document.querySelectorAll('#cp-split-rows .cp-split-row');
            rows.forEach(function (r) {
              r.querySelector('.cp-split-row__remove').disabled = rows.length === 1;
            });
          }

          function updateAddButton() {
            var rows = document.querySelectorAll('#cp-split-rows .cp-split-row');
            document.getElementById('cp-split-add-btn').disabled = rows.length >= maxSplits;
          }

          // ── Confirm dialog ────────────────────────────────────────────────────
          // Intercept the pay-form submit and show the styled AdminDialog instead
          // of the native confirm(). We handle both the mismatch alert and the
          // final "proceed" confirmation through the same path.
          document.getElementById('cp-helper-pay-form').addEventListener('submit', function (e) {
            if (this.dataset.dialogBypass === '1') return; // already confirmed, let submit through
            e.preventDefault();
            e.stopImmediatePropagation();

            var form = this;
            var selected = getSelectedTotal();
            var entered  = getSplitsTotal();
            var diff     = Math.abs(selected - entered);

            if (diff > 0.01) {
              window.AdminDialog.alert(
                'The split amounts (' + formatMoney(entered) + ') don\'t match the selected total (' + formatMoney(selected) + '). Please adjust the amounts before submitting.',
                { title: 'Amount mismatch' }
              );
              return;
            }

            window.AdminDialog.confirm(
              'Mark selected days as paid, add a project expense, and email the helper?',
              { title: 'Mark as paid' }
            ).then(function (ok) {
              if (!ok) return;
              form.dataset.dialogBypass = '1';
              if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
              } else {
                HTMLFormElement.prototype.submit.call(form);
              }
              delete form.dataset.dialogBypass;
            });
          }, true);


          // ── Wire up events ────────────────────────────────────────────────────
          document.addEventListener('change', function (e) {
            if (e.target.matches('input[name="entry_ids[]"]')) {
              autoFillFirstRow();
              updateRemaining();
            }
            if (e.target.matches('#cp-split-rows .cp-split-row__method, #cp-split-rows .cp-split-row__amount')) {
              updateRemaining();
            }
          });
          document.addEventListener('input', function (e) {
            if (e.target.matches('#cp-split-rows .cp-split-row__amount')) {
              updateRemaining();
            }
          });

          updateRemaining();
          updateRemoveButtons();
          updateAddButton();
        })();
      </script>
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
