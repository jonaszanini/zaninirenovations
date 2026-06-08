@extends('admin.layouts.app')

@section('heading', 'Image creation')

@push('head')
  <link rel="stylesheet" href="{{ asset('css/admin-utilities.css') }}?v={{ @filemtime(public_path('css/admin-utilities.css')) ?: time() }}">
@endpush

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Choose an AI model to generate images. Each model has its own workspace, prompt history, and gallery.
    </p>
  </div>

  <div class="util-tools-grid">
    @foreach ($models as $model)
      @if ($model['available'])
        <a href="{{ $model['route'] }}" class="admin-card util-tool-card">
          <span class="util-tool-card__icon"><i class="fa {{ $model['icon'] }}"></i></span>
          <span class="util-tool-card__body">
            <span class="util-tool-card__name">{{ $model['name'] }}</span>
            <span class="util-tool-card__desc">{{ $model['description'] }}</span>
          </span>
          <span class="util-tool-card__cta" aria-hidden="true"><i class="fa fa-arrow-right"></i></span>
        </a>
      @else
        <div class="admin-card util-tool-card util-tool-card--soon" aria-disabled="true">
          <span class="util-tool-card__icon"><i class="fa {{ $model['icon'] }}"></i></span>
          <span class="util-tool-card__body">
            <span class="util-tool-card__name">{{ $model['name'] }}</span>
            <span class="util-tool-card__desc">{{ $model['description'] }}</span>
          </span>
          <span class="util-tool-card__badge">Coming soon</span>
        </div>
      @endif
    @endforeach
  </div>
@endsection
