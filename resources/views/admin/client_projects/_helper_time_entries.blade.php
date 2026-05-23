<div class="cp-time-entries cp-helper-entries">
  @foreach ($project->helperTimeEntries as $entry)
    @php
      $isOpen = $entry->isOpen();
      $isPaid = $entry->isPaid();
      $dm = $entry->occurred_on ? \App\Support\EasternStandardTimeDisplay::formatCalendarDayMonth($entry->occurred_on) : '—';
      $st = \App\Models\ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on);
      $et = \App\Models\ClientProjectHelperTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on);
      if ($isOpen) {
        $hoursPart = 'in progress';
      } elseif ($entry->hours !== null) {
        $hoursPart = rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.').' hrs';
      } else {
        $hoursPart = '—';
      }
      $helperName = $entry->helper?->name ?? 'Helper';
      $lineAmount = $entry->lineAmount();
      $summaryLine = $helperName.' · '.$dm.': '.$st.' - '.$et.' = '.$hoursPart;
      if (! $isOpen && $entry->hours !== null) {
        $summaryLine .= ' ('.\App\Models\ClientProject::formatMoney($lineAmount).')';
      }
      $editHelpers = $assignedHelpers;
      if ($entry->helper && ! $editHelpers->contains('id', $entry->helper_id)) {
        $editHelpers = $editHelpers->push($entry->helper)->sortBy('name')->values();
      }
    @endphp
    <details class="cp-time-entry cp-helper-entry {{ $isOpen ? 'cp-time-entry--open' : '' }} {{ $isPaid ? 'cp-helper-entry--paid' : '' }}" data-cp-entry-row="{{ $entry->id }}">
      <summary class="cp-time-entry__summary">
        @if ($showPayCheckboxes && ! $isPaid && ! $isOpen && $entry->hours !== null)
          <label class="cp-helper-entry__check" onclick="event.stopPropagation();">
            <input type="checkbox"
                   name="entry_ids[]"
                   value="{{ $entry->id }}"
                   @if ($payFormId) form="{{ $payFormId }}" @endif
                   data-cp-helper-entry-check
                   data-helper-id="{{ $entry->helper_id }}">
          </label>
        @endif
        <span class="cp-time-entry__summary-text">{{ $summaryLine }}</span>
        @if ($isOpen)
          <span class="cp-time-entry__badge cp-time-entry__badge--progress">In progress</span>
        @elseif ($isPaid)
          <span class="cp-time-entry__badge cp-helper-entry__badge--paid">
            Paid · {{ $entry->payment?->paymentMethodLabel() }}
          </span>
        @endif
      </summary>
      <div class="cp-time-entry__panel">
        @if (filled($entry->note))
          <p class="cp-time-entry__note"><strong>Note:</strong> {{ $entry->note }}</p>
        @endif
        @if ($isPaid && $entry->payment)
          <p class="cp-time-entry__note">
            <strong>Payment:</strong>
            {{ \App\Models\ClientProject::formatMoney((float) $entry->payment->total_amount) }}
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
            <form action="{{ route('admin.client-projects.helper-time.destroy', [$project, $entry]) }}" method="post" onsubmit="return confirm('Delete this helper time entry?');" class="cp-inline-form">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
            </form>
          </div>
          <div class="cp-time-entry__edit" data-cp-edit-row="{{ $entry->id }}" hidden>
            <form action="{{ route('admin.client-projects.helper-time.update', [$project, $entry]) }}" method="post" class="cp-time-edit-form" data-cp-time-form>
              @csrf
              @method('PUT')
              <div class="cp-helper-time-form-grid">
                <div class="admin-form-group col-3">
                  <label>Helper</label>
                  <select name="helper_id" required>
                    @foreach ($editHelpers as $helper)
                      <option value="{{ $helper->id }}" @selected($entry->helper_id === $helper->id)>{{ $helper->name }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="admin-form-group col-2">
                  <label>Date</label>
                  <input type="date" name="occurred_on" required value="{{ optional($entry->occurred_on)->toDateString() }}">
                </div>
                <div class="admin-form-group col-2">
                  <label>Start time</label>
                  <select name="start_time" class="cp-time-select" data-cp-time-start>
                    <option value="">—</option>
                    @foreach ($timeOptions as $val => $label)
                      <option value="{{ $val }}" @selected($entry->start_time === $val)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="admin-form-group col-2">
                  <label>End time</label>
                  <select name="end_time" class="cp-time-select" data-cp-time-end>
                    <option value="">—</option>
                    @foreach ($timeOptions as $val => $label)
                      <option value="{{ $val }}" @selected($entry->end_time === $val)>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
                <div class="admin-form-group col-2">
                  <label>Note</label>
                  <input type="text" name="note" maxlength="500" value="{{ $entry->note }}">
                </div>
                <div class="admin-form-group col-1">
                  <label>Hours</label>
                  <input type="text" name="hours" class="cp-hours-input" inputmode="decimal" autocomplete="off" placeholder="auto"
                         value="{{ $entry->hours !== null ? rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.') : '' }}"
                         data-cp-time-hours>
                </div>
              </div>
              <div class="cp-time-submit cp-time-edit-actions">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-cancel="{{ $entry->id }}">Cancel</button>
                <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save changes</button>
              </div>
            </form>
          </div>
        @endunless
      </div>
    </details>
  @endforeach
</div>
