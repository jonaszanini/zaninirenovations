@php
  $isHourly = $project->isHourly();
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();
  $configuredRate = $isHourly ? (float) ($project->hourly_rate ?? 0) : null;

  // Build 15-minute time options from 8:00 AM to 8:00 PM inclusive.
  // Key is 24-hour HH:MM (used as <option value>), label is 12-hour formatted.
  $timeOptions = [];
  for ($minutes = 8 * 60; $minutes <= 20 * 60; $minutes += 15) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $value = sprintf('%02d:%02d', $h, $m);
    $hour12 = $h % 12 === 0 ? 12 : $h % 12;
    $ampm = $h < 12 ? 'AM' : 'PM';
    $timeOptions[$value] = sprintf('%d:%02d %s', $hour12, $m, $ampm);
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
      Tip: pick <strong>Start</strong> + <strong>End</strong> and Hours fills in automatically. Pick only <strong>Start</strong> to log an in-progress entry — you can come back later and add the End time.
    </p>
    @error('hours') <p class="admin-form-error">{{ $message }}</p> @enderror
    @error('end_time') <p class="admin-form-error">{{ $message }}</p> @enderror
    <div class="cp-time-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
    </div>
  </form>

  @if ($project->timeEntries->isNotEmpty())
    <div class="admin-table-scroll">
    <table class="admin-table cp-time-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Start</th>
          <th>End</th>
          <th>Hours</th>
          <th>Note</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @foreach ($project->timeEntries as $entry)
          @php $isOpen = $entry->isOpen(); @endphp
          <tr data-cp-entry-row="{{ $entry->id }}" class="{{ $isOpen ? 'cp-row-open' : '' }}">
            <td data-label="Date">{{ \App\Models\ClientProject::formatDate($entry->occurred_on) }}</td>
            <td data-label="Start">{{ \App\Models\ClientProjectTimeEntry::formatTime($entry->start_time) }}</td>
            <td data-label="End">
              {{ \App\Models\ClientProjectTimeEntry::formatTime($entry->end_time) }}
              @if ($isOpen)
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;margin-left:6px;">In progress</span>
              @endif
            </td>
            <td data-label="Hours">{{ $entry->hours !== null ? number_format((float) $entry->hours, 2) : '—' }}</td>
            <td data-label="Note">{{ $entry->note ?: '—' }}</td>
            <td class="cp-time-actions" data-label="Actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-toggle="{{ $entry->id }}">
                {{ $isOpen ? 'Set end' : 'Edit' }}
              </button>
              <form action="{{ route('admin.client-projects.time.destroy', [$project, $entry]) }}" method="post" onsubmit="return confirm('Delete this time entry?');" class="cp-inline-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
          <tr class="cp-time-edit-row" data-cp-edit-row="{{ $entry->id }}" hidden>
            <td colspan="6">
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
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
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
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value {{ $netAfterHst >= 0 ? 'is-positive' : 'is-negative' }}">
        {{ \App\Models\ClientProject::formatMoney($netAfterHst) }}
      </div>
    </div>
    @unless ($isHourly)
      <div class="cp-profit-tile cp-profit-tile--highlight">
        <div class="cp-profit-label">Effective hourly rate</div>
        <div class="cp-profit-value">
          {{ $hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h' }}
        </div>
      </div>
    @endunless
  </div>
</div>
