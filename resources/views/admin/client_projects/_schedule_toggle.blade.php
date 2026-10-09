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
  @param int|null $excludeProjectId — omit this project from calendar busy days
  @param string|null $submitFormId — form to save immediately after picking (show page)
--}}
@php
  $expandOpen = $expandOpen ?? false;
  $scheduleChecked = $scheduleChecked ?? false;
  $startDateValue = $startDateValue ?? null;
  $endDateValue = $endDateValue ?? null;
  $slowExpand = $slowExpand ?? false;
  $scheduleHint = 'Optional. Used to show the project on the Project Calendar page and to send an Add to Google Calendar email.';
@endphp
<span class="admin-label-with-tip">
<label class="cp-visit-toggle" for="{{ $scheduleToggleId }}">
  <input type="checkbox"
    id="{{ $scheduleToggleId }}"
    @checked($scheduleChecked)>
  <span class="cp-visit-toggle__control" aria-hidden="true"></span>
  <span class="cp-visit-toggle__label">Schedule</span>
</label>
@include('admin.client_projects._cp_field_hint', ['tipText' => $scheduleHint, 'tipLabel' => 'Schedule'])
</span>
<div id="{{ $expandableId }}"
  class="cp-visit-expandable @if ($slowExpand) cp-visit-expandable--slow @endif @if ($expandOpen) is-open @endif"
  aria-hidden="{{ $expandOpen ? 'false' : 'true' }}">
  <div class="cp-visit-expandable__inner">
    <div id="{{ $warningId }}" class="admin-flash admin-flash--warning cp-schedule-date-warning admin-flash--js-hidden admin-flash--compact-top" role="alert"></div>
    @include('admin.partials.schedule_date_picker', [
      'startDateId' => $startDateId,
      'endDateId' => $endDateId,
      'scheduleToggleId' => $scheduleToggleId,
      'excludeProjectId' => $excludeProjectId ?? null,
      'submitFormId' => $submitFormId ?? null,
      'hint' => $pickerHint ?? 'See other projects\' booked days, click two days (start, then end), then Use these dates.',
    ])
    <div class="admin-form-grid-2 cp-schedule-dates">
      <div class="admin-form-group">
        <label for="{{ $startDateId }}">Start date</label>
        <input class="admin-input" type="date" id="{{ $startDateId }}" name="start_date" value="{{ $startDateValue }}">
        @error('start_date') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
      <div class="admin-form-group">
        @include('admin.partials.tipped_label', [
          'label' => 'End date',
          'for' => $endDateId,
          'tipText' => 'Same-day start & end is allowed for small projects.',
          'tipLabel' => 'End date',
        ])
        <input class="admin-input" type="date" id="{{ $endDateId }}" name="end_date" value="{{ $endDateValue }}">
        @error('end_date') <p class="admin-form-error">{{ $message }}</p> @enderror
      </div>
    </div>
  </div>
</div>
