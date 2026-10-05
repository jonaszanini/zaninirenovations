{{--
  Shared "Pick on calendar" control + modal for project start/end dates.

  Required: $startDateId, $endDateId
  Optional:
    $excludeProjectId   omit this project from busy days
    $submitFormId       form to submit after "Use these dates" (legacy)
    $saveUrl            URL that stores only the chosen start/end dates
    $scheduleToggleId   checkbox to turn on before filling dates
    $hint               help text beside the button
    $hideButton         modal-only (rare)
--}}
@php
  $startDateId = $startDateId ?? 'cp-start-date';
  $endDateId = $endDateId ?? 'cp-end-date';
  $hint = $hint ?? 'See other projects\' booked days, click two days (start, then end), then Use these dates.';
  $hideButton = ! empty($hideButton);
@endphp

@unless ($hideButton)
  <div class="cp-schedule-pick-row">
    <button type="button"
            class="admin-btn admin-btn--outline admin-btn--sm"
            data-schedule-date-picker
            data-start-input="#{{ $startDateId }}"
            data-end-input="#{{ $endDateId }}"
            data-events-url="{{ route('admin.client-projects.calendar.picker-events') }}"
            @if (! empty($excludeProjectId)) data-exclude-id="{{ $excludeProjectId }}" @endif
            @if (! empty($submitFormId)) data-submit-form="#{{ $submitFormId }}" @endif
            @if (! empty($saveUrl)) data-save-url="{{ $saveUrl }}" @endif
            @if (! empty($scheduleToggleId)) data-schedule-toggle="#{{ $scheduleToggleId }}" @endif>
      <i class="fa fa-calendar" aria-hidden="true"></i> Pick on calendar
    </button>
    <span class="admin-form-help cp-schedule-pick-hint">{{ $hint }}</span>
  </div>
@endunless

@once
  <div class="cp-modal-backdrop cp-date-picker-backdrop" id="cp-date-picker-modal" hidden>
    <div class="cp-modal cp-date-picker-modal" role="dialog" aria-labelledby="cp-date-picker-title" aria-modal="true">
      <div class="cp-modal-header">
        <h3 id="cp-date-picker-title">Pick start &amp; end dates</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <div class="cp-modal-body cp-date-picker-body">
        <div class="cp-date-picker-selection">
          <div>
            <span class="cp-date-picker-selection-label">Start</span>
            <strong id="cp-date-picker-start">—</strong>
          </div>
          <span class="cp-date-picker-selection-arrow" aria-hidden="true">→</span>
          <div>
            <span class="cp-date-picker-selection-label">End</span>
            <strong id="cp-date-picker-end">—</strong>
          </div>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-date-picker-clear" id="cp-date-picker-clear">
            Clear
          </button>
        </div>
        <div id="cp-date-picker-conflict" class="admin-flash admin-flash--warning cp-date-picker-conflict admin-flash--js-hidden" role="alert"></div>
        <div id="cp-date-picker-calendar" class="cp-date-picker-calendar"></div>
      </div>
      <div class="cp-modal-footer">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
        <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-date-picker-confirm" disabled>
          <i class="fa fa-check"></i> Use these dates
        </button>
      </div>
    </div>
  </div>
@endonce

@once
  @push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
  @endpush
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="{{ asset('js/admin-schedule-date-picker.js') }}?v={{ @filemtime(public_path('js/admin-schedule-date-picker.js')) ?: time() }}"></script>
  @endpush
@endonce
