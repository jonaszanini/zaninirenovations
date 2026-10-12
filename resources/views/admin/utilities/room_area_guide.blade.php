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
      <p class="util-ra-guide__lede">Six steps to measure a room on site and send it to a quote. Each step shows the real toolbar controls so you can match them by sight. Tools you do not see in the editor yet are coming later — drawing, doors, wall math, and room-to-quote already work as they do today.</p>

      <ol class="util-ra-guide__steps">
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Open Room Area from a project</h2>
            <p>On the job, open <strong>Utilities</strong> and tap <strong>Room Area</strong>. That full-page editor is the one to use on site. You can also open it from All utilities, then pick or create a project. Use <strong>Guide</strong> any time you want this walkthrough.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">Header</span>
            <span class="util-ra-guide__chip-row" data-ra-guide-chip="guide">
              <span class="admin-btn admin-btn--ghost util-ra-full-header__help util-ra-guide__sample">
                <i class="fa fa-question-circle" aria-hidden="true"></i>
                <span>Guide</span>
              </span>
            </span>
          </div>
        </li>
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Calibrate scale or type wall lengths</h2>
            <p>If you have a floor-plan photo, upload it, tap the two ends of a known wall, and type that length. Or skip the photo and type walls in the <strong>Measure</strong> panel — <code>12' 6"</code>, <code>12.5</code>, or <code>150"</code> all work. Use <strong>Add rect</strong> for a simple rectangle. <strong>R90</strong> / <strong>L90</strong> turn the next wall.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">Measure</span>
            <span class="util-ra-guide__chip-row" data-ra-guide-chip="measure">
              <span class="admin-btn admin-btn--ghost util-ra-side__tab is-active util-ra-guide__sample">Measure</span>
              <span class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-guide__sample" data-ra-guide-chip="calibrate">
                <i class="fa fa-arrows-h" aria-hidden="true"></i>
              </span>
            </span>
            <span class="util-ra-guide__chip-row" data-ra-guide-chip="r90">
              <span class="admin-btn admin-btn--ghost util-ra-type-walls__dir is-active util-ra-guide__sample">R90</span>
              <span class="admin-btn admin-btn--ghost util-ra-type-walls__dir util-ra-guide__sample" data-ra-guide-chip="l90">L90</span>
              <span class="admin-btn admin-btn--ghost util-ra-type-walls__rect-add util-ra-guide__sample" data-ra-guide-chip="add-rect">Add rect</span>
            </span>
          </div>
        </li>
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Close the shape and add doors and windows</h2>
            <p>Close the room when the outline meets. Open <strong>Objects</strong> and drop doors and windows onto walls; they snap in place. Use <strong>Walls</strong> only if you want thick-wall drawing — quotes still use the inside.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">Objects / Walls</span>
            <span class="util-ra-guide__chip-row util-ra-guide__chip-row--dock">
              <span class="admin-btn admin-btn--ghost util-ra-palette__toggle util-ra-guide__sample" data-ra-guide-chip="objects">
                <span>Objects</span>
                <i class="fa fa-chevron-down" aria-hidden="true"></i>
              </span>
              <span class="admin-btn admin-btn--ghost util-ra-palette__toggle util-ra-guide__sample" data-ra-guide-chip="walls">
                <span>Walls</span>
                <i class="fa fa-chevron-down" aria-hidden="true"></i>
              </span>
            </span>
          </div>
        </li>
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Check Math</h2>
            <p>Open the <strong>Math</strong> tab. Floor (sq ft), perimeter, baseboard (doors removed), and wall area (doors and windows removed) update live. Set ceiling height there if the room is not 8 ft.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">Panel tabs</span>
            <span class="util-ra-guide__chip-row" data-ra-guide-chip="math">
              <span class="admin-btn admin-btn--ghost util-ra-side__tab util-ra-guide__sample">Measure</span>
              <span class="admin-btn admin-btn--ghost util-ra-side__tab is-active util-ra-guide__sample">Math</span>
              <span class="admin-btn admin-btn--ghost util-ra-side__tab util-ra-guide__sample">Photos</span>
              <span class="admin-btn admin-btn--ghost util-ra-side__tab util-ra-guide__sample">Rooms</span>
            </span>
          </div>
        </li>
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Save rooms, then quote</h2>
            <p>Name the room in the header and tap <strong>Save</strong>. Add more rooms, then <strong>Quote</strong> on a row or <strong>Quote all</strong> to send measurements into flooring, tile, paint, stairs, or basement quotes.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">Save / Quote</span>
            <span class="util-ra-guide__chip-row">
              <span class="admin-btn admin-btn--primary util-ra-full-header__save util-ra-guide__sample" data-ra-guide-chip="save">
                <i class="fa fa-save" aria-hidden="true"></i>
                <span>Save</span>
              </span>
              <span class="admin-btn admin-btn--ghost admin-btn--sm util-ra-guide__sample" data-ra-guide-chip="quote">
                <i class="fa fa-file-text-o" aria-hidden="true"></i>
                <span>Quote</span>
              </span>
              <span class="admin-btn admin-btn--outline admin-btn--sm util-ra-guide__sample" data-ra-guide-chip="quote-all">
                <i class="fa fa-file-text-o" aria-hidden="true"></i>
                <span>Quote all</span>
              </span>
            </span>
          </div>
        </li>
        <li class="util-ra-guide__step">
          <div class="util-ra-guide__copy">
            <h2>Zoom, pan, Fit, and Panel</h2>
            <p>Pinch or use <strong>+ / −</strong> to zoom. Drag empty canvas or use two fingers to pan. <strong>Fit</strong> shows the whole drawing. <strong>Panel</strong> shows or hides Measure, Math, Photos, and Rooms. On a phone the panel is the bottom sheet.</p>
          </div>
          <div class="util-ra-guide__samples" data-ra-guide-samples aria-hidden="true">
            <span class="util-ra-guide__chip-label">View</span>
            <span class="util-ra-guide__chip-row util-ra-zoom-controls" data-ra-guide-chip="zoom">
              <span class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-guide__sample">
                <i class="fa fa-minus" aria-hidden="true"></i>
              </span>
              <span class="util-ra-zoom-label">100%</span>
              <span class="admin-btn admin-btn--ghost util-ra-icon-btn util-ra-guide__sample">
                <i class="fa fa-plus" aria-hidden="true"></i>
              </span>
              <span class="admin-btn admin-btn--ghost util-ra-full-header__fit util-ra-guide__sample" data-ra-guide-chip="fit">Fit</span>
              <span class="admin-btn admin-btn--ghost util-ra-full-header__panel util-ra-guide__sample" data-ra-guide-chip="panel">
                <i class="fa fa-sliders" aria-hidden="true"></i>
                <span>Panel</span>
              </span>
            </span>
          </div>
        </li>
      </ol>
    </article>
  </div>
@endsection
