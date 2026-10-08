@extends('admin.layouts.app')

@section('heading', 'Room Area')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Draw room outlines on a grid, calibrate scale from a known wall length, and compute square footage.
      Save rooms under a project and optionally link it to a client job.
    </p>
    <a href="{{ route('admin.utilities.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All utilities
    </a>
  </div>

  @include('admin.utilities._room_area_tool', [
    'projectsJson' => $projectsJson,
    'clientProjects' => $clientProjects,
    'fullPage' => false,
  ])
@endsection

@push('scripts')
  <script src="https://unpkg.com/konva@9/konva.min.js"></script>
  <script src="{{ asset('js/admin-utilities-room-area.js') }}?v={{ @filemtime(public_path('js/admin-utilities-room-area.js')) ?: time() }}"></script>
@endpush
