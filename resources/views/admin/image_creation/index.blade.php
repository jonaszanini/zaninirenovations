@extends('admin.layouts.app')

@section('heading', 'Image creation')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Choose an AI model to generate images. Each model has its own workspace, prompt history, and gallery.
    </p>
  </div>

  <div class="ic-model-grid">
    @foreach ($models as $model)
      @if ($model['available'])
        <a href="{{ $model['route'] }}" class="admin-card ic-model-card">
          <span class="ic-model-card__icon">
            @include('admin.image_creation._model_icon')
          </span>
          <span class="ic-model-card__name">{{ $model['name'] }}</span>
        </a>
      @else
        <div class="admin-card ic-model-card ic-model-card--soon" aria-disabled="true">
          <span class="ic-model-card__icon">
            @include('admin.image_creation._model_icon')
          </span>
          <span class="ic-model-card__name">{{ $model['name'] }}</span>
          <span class="ic-model-card__badge">Coming soon</span>
        </div>
      @endif
    @endforeach
  </div>
@endsection
