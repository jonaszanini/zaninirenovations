@extends('admin.layouts.app')

@section('heading', 'Room Area guide')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="util-ra-guide" data-ra-guide>
    <header class="util-ra-full-header util-ra-guide__header">
      <a href="{{ $backUrl }}" class="admin-btn admin-btn--ghost util-ra-full-header__back" data-ra-guide-back>
        <i class="fa fa-arrow-left" aria-hidden="true"></i>
        Back to editor
      </a>
      <h1 class="util-ra-guide__title">Room Area guide</h1>
    </header>

    <article class="util-ra-guide__body admin-card">
      <p class="util-ra-guide__lede">Six steps to measure a room on site and send it to a quote. Tools you do not see here yet are coming later — drawing, doors, wall math, and room-to-quote already work as they do today.</p>

      <ol class="util-ra-guide__steps">
        <li>
          <h2>Open Room Area from a project</h2>
          <p>On the job, open <strong>Utilities</strong> and tap <strong>Room Area</strong>. That full-page editor is the one to use on site. You can also open it from All utilities, then pick or create a project.</p>
        </li>
        <li>
          <h2>Calibrate scale or type wall lengths</h2>
          <p>If you have a floor-plan photo, upload it, tap the two ends of a known wall, and type that length. Or skip the photo and type walls in the Measure panel — <code>12' 6"</code>, <code>12.5</code>, or <code>150"</code> all work. Use <strong>Add rect</strong> for a simple rectangle.</p>
        </li>
        <li>
          <h2>Close the shape and add doors and windows</h2>
          <p>Close the room when the outline meets. Open <strong>Objects</strong> and drop doors and windows onto walls; they snap in place. Use <strong>Walls</strong> only if you want thick-wall drawing — quotes still use the inside.</p>
        </li>
        <li>
          <h2>Check Math</h2>
          <p>Open the <strong>Math</strong> tab. Floor (sq ft), perimeter, baseboard (doors removed), and wall area (doors and windows removed) update live. Set ceiling height there if the room is not 8 ft.</p>
        </li>
        <li>
          <h2>Save rooms, then quote</h2>
          <p>Name the room in the header and tap <strong>Save</strong>. Add more rooms, then <strong>Quote</strong> on a row or <strong>Quote all</strong> to send measurements into flooring, tile, paint, stairs, or basement quotes.</p>
        </li>
        <li>
          <h2>Zoom, pan, Fit, and Panel</h2>
          <p>Pinch or use <strong>+ / −</strong> to zoom. Drag empty canvas or use two fingers to pan. <strong>Fit</strong> shows the whole drawing. <strong>Panel</strong> shows or hides Measure, Math, Photos, and Rooms. On a phone the panel is the bottom sheet.</p>
        </li>
      </ol>
    </article>
  </div>
@endsection
