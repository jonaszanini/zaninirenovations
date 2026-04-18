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

      var calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        firstDay: 1,
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
        }
      });
      calendar.render();
    });
  </script>
@endsection
