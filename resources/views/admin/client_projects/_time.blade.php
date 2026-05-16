@php
  $isHourly = $project->isHourly();
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();
  $configuredRate = $isHourly ? (float) ($project->hourly_rate ?? 0) : null;

  // 15-minute slots for the full day (00:00–23:45). Values stay zero-padded
  // HH:MM for DB consistency; labels use 24h without a leading zero on the
  // hour (9:00, 13:00) for easier reading on phones. When end < start,
  // duration spans midnight (e.g. 20:00 → 8:00).
  $timeOptions = [];
  for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $value = sprintf('%02d:%02d', $h, $m);
    $timeOptions[$value] = sprintf('%d:%02d', $h, $m);
  }
@endphp

<div class="admin-card">
  <h3>Log time</h3>
  <form action="{{ route('admin.client-projects.time.store', $project) }}" method="post" class="cp-finance-form" id="cp-time-form" data-cp-time-form>
    @csrf
    <div class="cp-time-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Start time</label>
        <select name="start_time" class="cp-time-select" data-cp-time-start>
          <option value="">—</option>
          @foreach ($timeOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label>End time</label>
        <select name="end_time" class="cp-time-select" data-cp-time-end>
          <option value="">—</option>
          @foreach ($timeOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label>Hours
          <span class="cp-hint" title="Optional. Filled automatically when you pick start + end. Leave empty + leave end blank to start an open entry.">?</span>
        </label>
        <input type="text" name="hours"
               class="cp-hours-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="auto"
               data-cp-time-hours>
      </div>
      <div class="admin-form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="500">
      </div>
    </div>
    <p class="admin-form-help cp-time-help">
      Tip: pick <strong>Start</strong> + <strong>End</strong> and Hours fills in automatically. For a shift past midnight, use a clock time on the next morning for <strong>End</strong> (e.g. start 20:00, end 8:00). Pick only <strong>Start</strong> to log an in-progress entry — you can come back later and add the End time.
    </p>
    @error('hours') <p class="admin-form-error">{{ $message }}</p> @enderror
    @error('end_time') <p class="admin-form-error">{{ $message }}</p> @enderror
    <div class="cp-time-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
    </div>
  </form>

  @if ($project->timeEntries->isNotEmpty())
    <h4 class="cp-time-saved-heading">Saved entries</h4>
    <p class="admin-form-help cp-time-saved-help">Newest at the top. Tap a row to expand; use Edit to change times.</p>
    <div class="cp-time-entries">
      @foreach ($project->timeEntries as $entry)
        @php
          $isOpen = $entry->isOpen();
          $dm = $entry->occurred_on ? \App\Support\EasternStandardTimeDisplay::formatCalendarDayMonth($entry->occurred_on) : '—';
          $st = \App\Models\ClientProjectTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on);
          $et = \App\Models\ClientProjectTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on);
          if ($isOpen) {
            $hoursPart = 'in progress';
          } elseif ($entry->hours !== null) {
            $hoursPart = rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.').' hrs';
          } else {
            $hoursPart = '—';
          }
          $summaryLine = $dm.': '.$st.' - '.$et.' = '.$hoursPart;
        @endphp
        <details class="cp-time-entry {{ $isOpen ? 'cp-time-entry--open' : '' }}" data-cp-entry-row="{{ $entry->id }}">
          <summary class="cp-time-entry__summary">
            <span class="cp-time-entry__summary-text">{{ $summaryLine }}</span>
            @if ($isOpen)
              <span class="cp-time-entry__badge cp-time-entry__badge--progress">In progress</span>
            @endif
          </summary>
          <div class="cp-time-entry__panel">
            @if (filled($entry->note))
              <p class="cp-time-entry__note"><strong>Note:</strong> {{ $entry->note }}</p>
            @endif
            <div class="cp-time-entry__actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-toggle="{{ $entry->id }}">
                {{ $isOpen ? 'Set end time' : 'Edit' }}
              </button>
              <form action="{{ route('admin.client-projects.time.destroy', [$project, $entry]) }}" method="post" onsubmit="return confirm('Delete this time entry?');" class="cp-inline-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </div>
            <div class="cp-time-entry__edit" data-cp-edit-row="{{ $entry->id }}" hidden>
              <form action="{{ route('admin.client-projects.time.update', [$project, $entry]) }}" method="post" class="cp-time-edit-form" data-cp-time-form>
                @csrf
                @method('PUT')
                <div class="cp-time-form-grid">
                  <div class="admin-form-group">
                    <label>Date</label>
                    <input type="date" name="occurred_on" required value="{{ optional($entry->occurred_on)->toDateString() }}">
                  </div>
                  <div class="admin-form-group">
                    <label>Start time</label>
                    <select name="start_time" class="cp-time-select" data-cp-time-start>
                      <option value="">—</option>
                      @foreach ($timeOptions as $val => $label)
                        <option value="{{ $val }}" @selected($entry->start_time === $val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="admin-form-group">
                    <label>End time</label>
                    <select name="end_time" class="cp-time-select" data-cp-time-end>
                      <option value="">—</option>
                      @foreach ($timeOptions as $val => $label)
                        <option value="{{ $val }}" @selected($entry->end_time === $val)>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="admin-form-group">
                    <label>Hours</label>
                    <input type="text" name="hours"
                           class="cp-hours-input"
                           inputmode="decimal"
                           autocomplete="off"
                           placeholder="auto"
                           value="{{ $entry->hours !== null ? rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.') : '' }}"
                           data-cp-time-hours>
                  </div>
                  <div class="admin-form-group">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="500" value="{{ $entry->note }}">
                  </div>
                </div>
                <div class="cp-time-submit cp-time-edit-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-cancel="{{ $entry->id }}">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save changes</button>
                </div>
              </form>
            </div>
          </div>
        </details>
      @endforeach
    </div>
  @else
    <p class="admin-form-help">No time entries recorded yet.</p>
  @endif
</div>

<div class="admin-card cp-profit-card">
  <h3>Time summary</h3>
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total hours (closed)</div>
      <div class="cp-profit-value">{{ number_format($totalHours, 2) }}</div>
    </div>
    @if ($isHourly)
      <div class="cp-profit-tile">
        <div class="cp-profit-label">Configured rate</div>
        <div class="cp-profit-value">
          {{ $configuredRate > 0 ? \App\Models\ClientProject::formatMoney($configuredRate).' / h' : 'Not set' }}
        </div>
      </div>
      <div class="cp-profit-tile cp-profit-tile--highlight">
        <div class="cp-profit-label">Earned (rate × hours)</div>
        <div class="cp-profit-value">{{ \App\Models\ClientProject::formatMoney($project->hourlyAmountEarned()) }}</div>
      </div>
    @endif
    @if ($isHourly)
      <div class="cp-profit-tile">
        <div class="cp-profit-label">Final amount after HST</div>
        <div class="cp-profit-value {{ $project->hourlyFinalAmountAfterHst() >= 0 ? 'is-positive' : 'is-negative' }}">
          {{ \App\Models\ClientProject::formatMoney($project->hourlyFinalAmountAfterHst()) }}
        </div>
      </div>
    @else
      <div class="cp-profit-tile">
        <div class="cp-profit-label">Net profit after HST</div>
        <div class="cp-profit-value {{ $netAfterHst >= 0 ? 'is-positive' : 'is-negative' }}">
          {{ \App\Models\ClientProject::formatMoney($netAfterHst) }}
        </div>
      </div>
    @endif
    @unless ($isHourly)
      <div class="cp-profit-tile cp-profit-tile--highlight">
        <div class="cp-profit-label">Effective hourly rate</div>
        <div class="cp-profit-value">
          {{ $hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h' }}
        </div>
      </div>
    @endunless
  </div>

  @if ($isHourly)
    @php
      $closedForSummary = $project->timeEntries->filter(fn ($e) => $e->hours !== null)->count();
      $hasClientEmail = filled($project->client_email);
      $hasClientName = filled($project->client_name);
      $canSendTimeSummary = $closedForSummary > 0 && $hasClientEmail && $hasClientName;
    @endphp
    <div class="cp-time-summary-email" style="margin-top:20px;padding-top:20px;border-top:1px solid #e5e7eb;">
      <h4 class="cp-time-saved-heading" style="margin-top:0;">Send summary to client</h4>
      <p class="admin-form-help" style="margin-bottom:12px;">
        Email the client a full breakdown: each day worked (start, end, hours, notes), total hours, and amounts before and after 13% HST.
        {{ $project->secondaryClientEmail() ? 'A copy goes to the secondary email if set.' : '' }}
      </p>
      @if (!$hasClientEmail || !$hasClientName)
        <p class="admin-form-help" style="color:#b45309;">
          <i class="fa fa-exclamation-triangle"></i>
          Add the client’s name and email on the
          <a href="{{ route('admin.client-projects.edit', $project) }}">edit project</a> page before sending.
        </p>
      @elseif ($closedForSummary === 0)
        <p class="admin-form-help" style="color:#b45309;">
          <i class="fa fa-exclamation-triangle"></i>
          Close at least one time entry (with hours) before sending — open “in progress” rows are listed separately but not included in totals.
        </p>
      @endif
      <form action="{{ route('admin.client-projects.time-tracking-summary.send', $project) }}"
            method="post"
            onsubmit="return confirm('Send the time tracking summary to {{ e($project->clientEmailRecipientsLabel() ?: 'the client') }}?');">
        @csrf
        <button type="submit"
                class="admin-btn admin-btn--primary admin-btn--sm"
                @disabled(!$canSendTimeSummary)>
          <i class="fa fa-paper-plane"></i>
          Send time summary to client
        </button>
      </form>
    </div>
  @endif
</div>
