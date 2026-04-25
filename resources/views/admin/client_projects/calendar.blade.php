@extends('admin.layouts.app')

@section('heading', 'Project Calendar')

@push('head')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">All projects with scheduled start and end dates. Click any event to open the project. Rejected projects are hidden.</p>
    <a href="{{ route('admin.client-projects.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-list"></i> Back to list
    </a>
  </div>

  <div class="cp-calendar-legend">
    @foreach ($statusLabels as $key => $label)
      @if ($key !== \App\Models\ClientProject::STATUS_REJECTED)
        <span class="cp-legend-item">
          <span class="cp-legend-swatch" style="background: {{ $statusColors[$key] }};"></span>
          {{ $label }}
        </span>
      @endif
    @endforeach
  </div>

  <div class="admin-card cp-calendar-card">
    <div id="cp-calendar"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('cp-calendar');
      if (!el || typeof FullCalendar === 'undefined') return;

      var events = @json($events);

      // Two layouts: desktop = single row; mobile = three stacked rows
      // (view buttons on top, title in the middle, prev/today/next at bottom).
      // We swap the toolbar config when crossing the breakpoint.
      var DESKTOP_TOOLBAR = {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listMonth'
      };
      // Spaces (not commas) make prev / today / next sit in their own groups
      // so we can `justify-content: space-between` them on mobile.
      var MOBILE_TOOLBAR = {
        left: 'prev today next',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listMonth'
      };
      var MOBILE_BREAKPOINT = 700;
      function pickToolbar() {
        return window.innerWidth <= MOBILE_BREAKPOINT ? MOBILE_TOOLBAR : DESKTOP_TOOLBAR;
      }

      var calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: pickToolbar(),
        firstDay: 0,
        height: 'auto',
        events: events,
        eventDisplay: 'block',
        eventClick: function (info) {
          if (info.event.url) {
            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
          }
        },
        eventDidMount: function (info) {
          if (info.event.extendedProps && info.event.extendedProps.status) {
            info.el.setAttribute('title', info.event.title + ' — ' + info.event.extendedProps.status);
          }
        },
        windowResize: function () {
          var next = pickToolbar();
          var current = calendar.getOption('headerToolbar');
          // Only swap when the layout actually changes — avoids re-rendering
          // the toolbar on every pixel of resize.
          if (!current || current.left !== next.left) {
            calendar.setOption('headerToolbar', next);
          }
        }
      });
      calendar.render();
    });
  </script>
@endsection
