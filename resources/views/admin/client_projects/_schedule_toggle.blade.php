{{--
  Schedule toggle + expandable start/end dates (UI-only checkbox; dates submit when expanded).
  @param string $scheduleToggleId
  @param string $expandableId
  @param string $warningId
  @param string $startDateId
  @param string $endDateId
  @param bool $expandOpen
  @param bool $scheduleChecked
  @param string|null $startDateValue
  @param string|null $endDateValue
  @param bool $slowExpand — 1s open/close animation (create project)
--}}
@php
  $expandOpen = $expandOpen ?? false;
  $scheduleChecked = $scheduleChecked ?? false;
  $startDateValue = $startDateValue ?? null;
  $endDateValue = $endDateValue ?? null;
  $slowExpand = $slowExpand ?? false;
  $scheduleHint = 'Optional. Used to show the project on the Project Calendar page and to send an Add to Google Calendar email.';
@endphp
<label class="cp-visit-toggle" for="{{ $scheduleToggleId }}">
  <input type="checkbox"
    id="{{ $scheduleToggleId }}"
    @checked($scheduleChecked)>
  <span class="cp-visit-toggle__control" aria-hidden="true"></span>
  <span class="cp-visit-toggle__label cp-label-with-hint">
    Schedule
    @include('admin.client_projects._cp_field_hint', ['text' => $scheduleHint])
  </span>
</label>
<div id="{{ $expandableId }}"
  class="cp-visit-expandable @if ($slowExpand) cp-visit-expandable--slow @endif @if ($expandOpen) is-open @endif"
  aria-hidden="{{ $expandOpen ? 'false' : 'true' }}">
  <div class="cp-visit-expandable__inner">
    <div id="{{ $warningId }}" class="admin-flash admin-flash--warning cp-schedule-date-warning admin-flash--js-hidden admin-flash--compact-top" role="alert"></div>
    <div class="admin-form-grid-2 cp-schedule-dates">
      <div class="admin-form-group">
        <label for="{{ $startDateId }}">Start date</label>
        <input type="date" id="{{ $startDateId }}" name="start_date" value="{{ $startDateValue }}">
        @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
      <div class="admin-form-group">
        <label for="{{ $endDateId }}">End date</label>
        <input type="date" id="{{ $endDateId }}" name="end_date" value="{{ $endDateValue }}">
        @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
        <p class="admin-form-help">Same-day start &amp; end is allowed for small projects.</p>
      </div>
    </div>
  </div>
</div>
