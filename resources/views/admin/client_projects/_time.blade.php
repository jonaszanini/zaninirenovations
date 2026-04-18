@php
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();
@endphp

<div class="admin-card">
  <h3>Log time</h3>
  <form action="{{ route('admin.client-projects.time.store', $project) }}" method="post" class="cp-finance-form">
    @csrf
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="{{ now()->toDateString() }}">
      </div>
      <div class="admin-form-group">
        <label>Hours <span class="cp-hint" title="Decimal hours allowed — e.g. 4.5 for 4h 30m.">?</span></label>
        <input type="number" name="hours" required step="0.01" min="0.01" max="24" placeholder="e.g. 4.5">
      </div>
      <div class="admin-form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="500">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
      </div>
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
