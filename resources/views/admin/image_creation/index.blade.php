@extends('admin.layouts.app')

@section('heading', 'AI Image')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Generate new images from prompts or edit existing photos with AI. Each workflow has its own history and gallery.
    </p>
  </div>

  <div class="ic-model-grid">
    @foreach ($workflows as $workflow)
      @if ($workflow['available'])
        <a href="{{ $workflow['route'] }}" class="admin-card ic-model-card">
          <span class="ic-model-card__icon">
            <i class="fa {{ $workflow['icon'] }}" aria-hidden="true"></i>
          </span>
          <span class="ic-model-card__name">{{ $workflow['name'] }}</span>
          <span class="ic-model-card__desc">{{ $workflow['description'] }}</span>
        </a>
      @else
        <div class="admin-card ic-model-card ic-model-card--soon" aria-disabled="true">
          <span class="ic-model-card__icon">
            <i class="fa {{ $workflow['icon'] }}" aria-hidden="true"></i>
          </span>
          <span class="ic-model-card__name">{{ $workflow['name'] }}</span>
          <span class="ic-model-card__badge">Coming soon</span>
        </div>
      @endif
    @endforeach
  </div>
@endsection
