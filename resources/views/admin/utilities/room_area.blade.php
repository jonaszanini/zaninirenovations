@extends('admin.layouts.app')

@section('heading', 'Room Area')
@section('no_sidebar', true)

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  @include('admin.utilities._room_area_openplan3d', [
    'pageTitle' => 'Room Area',
    'backUrl' => route('admin.utilities.index'),
    'backLabel' => 'All utilities',
    'projectsJson' => $projectsJson ?? [],
    'clientProjects' => $clientProjects ?? [],
    'initialRoomId' => $initialRoomId ?? null,
    'assembliesJson' => $assembliesJson ?? [],
    'catalogPickerOptions' => $catalogPickerOptions ?? [],
  ])
@endsection

@push('scripts')
  <script src="{{ asset('js/openplan3d-measurement-bridge.js') }}?v={{ @filemtime(public_path('js/openplan3d-measurement-bridge.js')) ?: time() }}"></script>
@endpush
