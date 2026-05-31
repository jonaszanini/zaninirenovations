@extends('admin.layouts.app')

@section('heading', 'Utilities')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      A growing toolbox of quick calculators for day-to-day jobs. Pick a tool below to get started.
    </p>
  </div>

  <div class="util-tools-grid">
    @foreach ($tools as $tool)
      @if ($tool['available'])
        <a href="{{ $tool['route'] }}" class="admin-card util-tool-card">
          <span class="util-tool-card__icon"><i class="fa {{ $tool['icon'] }}"></i></span>
          <span class="util-tool-card__body">
            <span class="util-tool-card__name">{{ $tool['name'] }}</span>
            <span class="util-tool-card__desc">{{ $tool['description'] }}</span>
          </span>
          <span class="util-tool-card__cta" aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        </a>
      @else
        <div class="admin-card util-tool-card util-tool-card--soon" aria-disabled="true">
          <span class="util-tool-card__icon"><i class="fa {{ $tool['icon'] }}"></i></span>
          <span class="util-tool-card__body">
            <span class="util-tool-card__name">{{ $tool['name'] }}</span>
            <span class="util-tool-card__desc">{{ $tool['description'] }}</span>
          </span>
          <span class="util-tool-card__badge">Coming soon</span>
        </div>
      @endif
    @endforeach
  </div>
@endsection
