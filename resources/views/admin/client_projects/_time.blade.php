@php
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();

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
  <form action="{{ route('admin.client-projects.time.store', $project) }}" method="post" class="cp-finance-form" id="cp-time-form">
    @csrf
    <div class="cp-time-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Start time</label>
        <select id="cp-time-start" class="cp-time-select">
          <option value="">—</option>
          @foreach ($timeOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label>End time</label>
        <select id="cp-time-end" class="cp-time-select">
          <option value="">—</option>
          @foreach ($timeOptions as $val => $label)
            <option value="{{ $val }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="admin-form-group">
        <label>Hours
          <span class="cp-hint" title="Decimal hours allowed — e.g. 4.5 for 4h 30m. Auto-filled when you pick start and end times.">?</span>
        </label>
        <input type="text" id="cp-time-hours" name="hours" required
               class="cp-hours-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="e.g. 4.5">
      </div>
      <div class="admin-form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="500">
      </div>
    </div>
    <p class="admin-form-help cp-time-help">
      Tip: pick <strong>Start</strong> + <strong>End</strong> (15-min steps, 8 AM – 8 PM) and Hours is filled for you, or just type Hours directly.
    </p>
    <div class="cp-time-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
    </div>
  </form>

  @if ($project->timeEntries->isNotEmpty())
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Hours</th><th>Note</th><th></th></tr>
      </thead>
      <tbody>
        @foreach ($project->timeEntries as $entry)
          <tr>
            <td>{{ \App\Models\ClientProject::formatDate($entry->occurred_on) }}</td>
            <td>{{ number_format((float) $entry->hours, 2) }}</td>
            <td>{{ $entry->note ?: '—' }}</td>
            <td>
              <form action="{{ route('admin.client-projects.time.destroy', [$project, $entry]) }}" method="post" onsubmit="return confirm('Delete this time entry?');" class="cp-inline-form">
                @csrf
                @method('DELETE')
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="admin-form-help">No time entries recorded yet.</p>
  @endif
</div>

<div class="admin-card cp-profit-card">
  <h3>Time summary</h3>
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total hours</div>
      <div class="cp-profit-value">{{ number_format($totalHours, 2) }}</div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value {{ $netAfterHst >= 0 ? 'is-positive' : 'is-negative' }}">
        {{ \App\Models\ClientProject::formatMoney($netAfterHst) }}
      </div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Effective hourly rate</div>
      <div class="cp-profit-value">
        {{ $hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h' }}
      </div>
    </div>
  </div>
</div>
