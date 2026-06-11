<div class="cp-time-entries cp-helper-entries">
  @foreach ($project->helperTimeEntries as $entry)
    @php
      $isOpen = $entry->isOpen();
      $isPaid = $entry->isPaid();
      $isExpense = $entry->isExpense();
      $helperName = $entry->helper?->name ?? 'Helper';
      $detailLine = $entry->summaryDetailLine();
      $canSelectForPay = ($showPayCheckboxes ?? false) && $entry->isPayable() && ! $isPaid;
      $canSelectForAck = ($showAckCheckboxes ?? false)
        && $isPaid
        && $entry->payment
        && ! $entry->payment->isAcknowledged();
      $editHelpers = $assignedHelpers;
      if ($entry->helper && ! $editHelpers->contains('id', $entry->helper_id)) {
        $editHelpers = $editHelpers->push($entry->helper)->sortBy('name')->values();
      }
      $editHourlyRate = $entry->hourly_rate !== null
        ? number_format((float) $entry->hourly_rate, 2, '.', '')
        : number_format((float) ($entry->helper?->hourly_rate ?? 0), 2, '.', '');
      $editExpenseAmount = $entry->expense_amount !== null
        ? number_format((float) $entry->expense_amount, 2, '.', '')
        : '';
    @endphp
    <details class="cp-time-entry cp-helper-entry {{ $isOpen ? 'cp-time-entry--open' : '' }} {{ $isPaid ? 'cp-helper-entry--paid' : '' }} {{ $isExpense ? 'cp-helper-entry--expense' : '' }}" data-cp-entry-row="{{ $entry->id }}">
      <summary class="cp-time-entry__summary cp-helper-entry__summary">
        <div class="cp-helper-entry__summary-main">
          <div class="cp-helper-entry__summary-top">
            @if ($canSelectForAck)
              <label class="admin-checkbox admin-checkbox--block cp-helper-entry__select" onclick="event.stopPropagation();">
                <input type="checkbox"
                       name="payment_ids[]"
                       value="{{ $entry->payment->id }}"
                       class="admin-checkbox__input"
                       @if ($ackFormId ?? null) form="{{ $ackFormId }}" @endif
                       data-cp-helper-ack-check
                       data-payment-id="{{ $entry->payment->id }}"
                       aria-label="Select {{ $helperName }} entry for acknowledgement">
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text cp-helper-entry__name">{{ $helperName }}</span>
              </label>
            @elseif ($canSelectForPay)
              <label class="admin-checkbox admin-checkbox--block cp-helper-entry__select" onclick="event.stopPropagation();">
                <input type="checkbox"
                       name="entry_ids[]"
                       value="{{ $entry->id }}"
                       class="admin-checkbox__input"
                       @if ($payFormId ?? null) form="{{ $payFormId }}" @endif
                       data-cp-helper-entry-check
                       data-helper-id="{{ $entry->helper_id }}"
                       aria-label="Select {{ $helperName }} entry for payment">
                <span class="admin-checkbox__mark" aria-hidden="true"></span>
                <span class="admin-checkbox__text cp-helper-entry__name">{{ $helperName }}</span>
              </label>
            @else
              <span class="cp-helper-entry__name">{{ $helperName }}</span>
            @endif
            @if ($isExpense)
              <span class="cp-helper-entry__type-badge">Reimbursement</span>
            @endif
          </div>
          <div class="cp-helper-entry__summary-detail">{{ $detailLine }}</div>
        </div>
        @if ($isOpen)
          <span class="cp-time-entry__badge cp-time-entry__badge--progress">In progress</span>
        @elseif ($isPaid)
          <span class="cp-time-entry__badge cp-helper-entry__badge--paid">
            Paid · {{ $entry->payment?->paymentMethodLabel() }}
          </span>
        @endif
      </summary>
      <div class="cp-time-entry__panel">
        @if (! $isExpense && filled($entry->note))
          <p class="cp-time-entry__note"><strong>Note:</strong> {{ $entry->note }}</p>
        @endif
        @if ($isPaid && $entry->payment)
          <p class="cp-time-entry__note">
            <strong>Payment:</strong>
            {{ \App\Models\ClientProject::formatMoney((float) $entry->lineAmount()) }}
            (batch total {{ \App\Models\ClientProject::formatMoney((float) $entry->payment->total_amount) }})
            on {{ \App\Models\ClientProject::formatDate($entry->payment->paid_at) }}
            via {{ $entry->payment->paymentMethodLabel() }}.
            @if ($entry->payment->isAcknowledged())
              Acknowledged {{ \App\Models\ClientProject::formatDateTime($entry->payment->acknowledged_at) }}.
            @else
              <span class="cp-helper-entry__pending-ack">Awaiting helper confirmation.</span>
              <form action="{{ route('admin.client-projects.helper-payments.acknowledge', [$project, $entry->payment]) }}"
                    method="post"
                    class="cp-inline-form"
                    style="display:inline;">
                @csrf
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Mark acknowledged</button>
              </form>
            @endif
          </p>
        @endif
        @unless ($isPaid)
          <div class="cp-time-entry__actions">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-toggle="{{ $entry->id }}">
              {{ $isOpen ? 'Set end time' : 'Edit' }}
            </button>
            <form action="{{ route('admin.client-projects.helper-time.destroy', [$project, $entry]) }}" method="post" onsubmit="return confirm('Delete this helper entry?');" class="cp-inline-form">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
            </form>
          </div>
          <div class="cp-time-entry__edit" data-cp-edit-row="{{ $entry->id }}" hidden>
            @if ($isExpense)
              <form action="{{ route('admin.client-projects.helper-time.update', [$project, $entry]) }}" method="post" class="cp-time-edit-form">
                @csrf
                @method('PUT')
                <div class="cp-helper-time-form-grid">
                  <div class="admin-form-group col-3">
                    <label>Helper</label>
                    <select class="admin-input" name="helper_id" required>
                      @foreach ($editHelpers as $helper)
                        <option value="{{ $helper->id }}" @selected($entry->helper_id === $helper->id)>{{ $helper->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="admin-form-group col-2">
                    <label>Date</label>
                    <input class="admin-input" type="date" name="occurred_on" required value="{{ optional($entry->occurred_on)->toDateString() }}">
                  </div>
                  <div class="admin-form-group col-3">
                    <label>Reason</label>
                    <input class="admin-input" type="text" name="expense_description" maxlength="500" required value="{{ $entry->expense_description }}">
                  </div>
                  <div class="admin-form-group col-2">
                    <label>Amount</label>
                    <input type="text" name="expense_amount" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off" required value="{{ $editExpenseAmount }}">
                  </div>
                </div>
                <div class="cp-time-submit cp-time-edit-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-cancel="{{ $entry->id }}">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save changes</button>
                </div>
              </form>
            @else
              <form action="{{ route('admin.client-projects.helper-time.update', [$project, $entry]) }}" method="post" class="cp-time-edit-form" data-cp-time-form>
                @csrf
                @method('PUT')
                <div class="cp-helper-time-form-grid">
                  <div class="admin-form-group col-3">
                    <label>Helper</label>
                    <select class="admin-input" name="helper_id" required>
                      @foreach ($editHelpers as $helper)
                        <option value="{{ $helper->id }}" @selected($entry->helper_id === $helper->id)>{{ $helper->name }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="admin-form-group col-2">
                    <label>Date</label>
                    <input class="admin-input" type="date" name="occurred_on" required value="{{ optional($entry->occurred_on)->toDateString() }}">
                  </div>
                  <div class="admin-form-group col-2">
                    <label>Start time</label>
                    @include('admin.client_projects._cp_time_input', [
                      'name' => 'start_time',
                      'role' => 'start',
                      'value' => $entry->start_time,
                    ])
                  </div>
                  <div class="admin-form-group col-2">
                    <label>End time</label>
                    @include('admin.client_projects._cp_time_input', [
                      'name' => 'end_time',
                      'role' => 'end',
                      'value' => $entry->end_time,
                    ])
                  </div>
                  <div class="admin-form-group col-2">
                    <label>Note</label>
                    <input class="admin-input" type="text" name="note" maxlength="500" value="{{ $entry->note }}">
                  </div>
                  <div class="admin-form-group col-1">
                    <label>Hours</label>
                    <input type="text" name="hours" class="admin-input cp-hours-input" inputmode="decimal" autocomplete="off" placeholder="auto"
                           value="{{ $entry->hours !== null ? rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.') : '' }}"
                           data-cp-time-hours>
                  </div>
                  <div class="admin-form-group col-2">
                    <label class="cp-label-with-hint">
                      Hourly rate
                      @include('admin.client_projects._cp_field_hint', [
                        'text' => 'Defaults from the helper profile; change here for this day only.',
                      ])
                    </label>
                    <input type="text" name="hourly_rate" class="admin-input cp-money-input" inputmode="decimal" autocomplete="off"
                           value="{{ $editHourlyRate }}" data-cp-money-entry="direct">
                  </div>
                </div>
                <div class="cp-time-submit cp-time-edit-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-cancel="{{ $entry->id }}">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save changes</button>
                </div>
              </form>
            @endif
          </div>
        @endunless
      </div>
    </details>
  @endforeach
</div>
