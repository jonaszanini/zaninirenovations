@php
  use App\Models\ClientProjectHelperPayment;

  $assignedHelperIds = $project->helpers->pluck('id')->map(fn ($id) => (int) $id)->all();
  $assignedHelpers = $project->helpers->sortBy('name')->values();
  $paymentMethods = ClientProjectHelperPayment::paymentMethodOptions();

  $timeOptions = [];
  for ($minutes = 0; $minutes < 24 * 60; $minutes += 15) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $value = sprintf('%02d:%02d', $h, $m);
    $timeOptions[$value] = sprintf('%d:%02d', $h, $m);
  }
  $timeOptions['23:59'] = '23:59';

  $unpaidClosed = $project->helperTimeEntries
      ->filter(fn ($e) => ! $e->isPaid() && $e->hours !== null && ! $e->isOpen())
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
      <div class="cp-time-submit">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save project helpers</button>
      </div>
    </form>

    @if ($assignedHelpers->isEmpty())
      <p class="admin-form-help" style="margin-top:16px;">Assign at least one helper above before logging time.</p>
    @else
      <h4 class="cp-time-saved-heading" style="margin-top:24px;">Log helper time</h4>
      <form action="{{ route('admin.client-projects.helper-time.store', $project) }}"
            method="post"
            class="cp-finance-form"
            data-cp-time-form>
        @csrf
        <div class="cp-helper-time-form-grid">
          <div class="admin-form-group col-3">
            <label>Helper</label>
            <select name="helper_id" required>
              @foreach ($assignedHelpers as $helper)
                <option value="{{ $helper->id }}" @selected(old('helper_id') == $helper->id)>{{ $helper->name }}</option>
              @endforeach
            </select>
            @error('helper_id') <p class="admin-form-error">{{ $message }}</p> @enderror
          </div>
          <div class="admin-form-group col-2">
            <label>Date</label>
            <input type="date" name="occurred_on" required value="{{ old('occurred_on', now()->toDateString()) }}">
          </div>
          <div class="admin-form-group col-2">
            <label>Start time</label>
            <select name="start_time" class="cp-time-select" data-cp-time-start>
              <option value="">—</option>
              @foreach ($timeOptions as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group col-2">
            <label>End time</label>
            <select name="end_time" class="cp-time-select" data-cp-time-end>
              <option value="">—</option>
              @foreach ($timeOptions as $val => $label)
                <option value="{{ $val }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group col-2">
            <label>Note (optional)</label>
            <input type="text" name="note" maxlength="500" value="{{ old('note') }}">
          </div>
          <div class="admin-form-group col-1">
            <label>Hours</label>
            <input type="text" name="hours" class="cp-hours-input" inputmode="decimal" autocomplete="off" placeholder="auto" data-cp-time-hours>
          </div>
        </div>
        @error('hours') <p class="admin-form-error">{{ $message }}</p> @enderror
        @error('end_time') <p class="admin-form-error">{{ $message }}</p> @enderror
        <div class="cp-time-submit">
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add helper time entry</button>
        </div>
      </form>
    @endif
  @endif

  @if ($project->helperTimeEntries->isNotEmpty())
    <h4 class="cp-time-saved-heading" style="margin-top:24px;">Helper entries</h4>
    <p class="admin-form-help cp-time-saved-help">Select unpaid closed days below, then mark as paid. Paid days are locked and recorded as project expenses.</p>

    @if ($unpaidClosed->isNotEmpty())
      <form action="{{ route('admin.client-projects.helper-time.mark-paid', $project) }}"
            method="post"
            class="cp-helper-pay-form"
            id="cp-helper-pay-form"
            data-cp-helper-pay-form
            onsubmit="return confirm('Mark selected days as paid, add a project expense, and email the helper?');">
        @csrf
        <div class="cp-helper-pay-toolbar">
          <div class="admin-form-group">
            <label>Payment method</label>
            <select name="payment_method" required>
              @foreach ($paymentMethods as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="admin-form-group">
            <label>Paid on</label>
            <input type="date" name="paid_on" value="{{ now()->toDateString() }}">
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
          'timeOptions' => $timeOptions,
          'showPayCheckboxes' => $unpaidClosed->isNotEmpty(),
          'payFormId' => $unpaidClosed->isNotEmpty() ? 'cp-helper-pay-form' : null,
        ])
  @elseif ($activeHelpers->isNotEmpty() && $assignedHelpers->isNotEmpty())
    <p class="admin-form-help" style="margin-top:16px;">No helper time entries recorded yet.</p>
  @endif
</div>
