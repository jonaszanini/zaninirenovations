@extends('admin.layouts.app')

@section('heading', $pageTitle)

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  @include('admin.utilities._room_area_tool', [
    'projectsJson' => $projectsJson,
    'clientProjects' => $clientProjects,
    'fullPage' => true,
    'pageTitle' => $pageTitle,
    'backUrl' => $backUrl,
    'backLabel' => 'Back to project',
    'initialRoomId' => $initialRoomId,
    'lockClientProjectId' => $clientProject->id,
    'clientProject' => $clientProject,
  ])
@endsection

@push('scripts')
  <script src="https://unpkg.com/konva@9/konva.min.js"></script>
  <script src="{{ asset('js/room-area-lib.js') }}?v={{ @filemtime(public_path('js/room-area-lib.js')) ?: time() }}"></script>
  <script src="{{ asset('js/admin-utilities-room-area.js') }}?v={{ @filemtime(public_path('js/admin-utilities-room-area.js')) ?: time() }}"></script>
@endpush
