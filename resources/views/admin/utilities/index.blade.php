@extends('admin.layouts.app')

@section('heading')
  Utilities
  <x-admin.info-tip label="Utilities">A growing toolbox of quick calculators for day-to-day jobs. Pick a tool below to get started.</x-admin.info-tip>
@endsection

@section('content')

  <div class="ic-model-grid">
    @foreach ($tools as $tool)
      @if ($tool['available'])
        <a href="{{ $tool['route'] }}" class="admin-card ic-model-card" @if (!empty($tool['target'])) target="{{ $tool['target'] }}" rel="noopener" @endif>
          <span class="ic-model-card__icon">
            @include('admin.utilities._tool_icon_' . $tool['icon'])
          </span>
          <span class="ic-model-card__name">{{ $tool['name'] }}</span>
        </a>
      @else
        <div class="admin-card ic-model-card ic-model-card--soon" aria-disabled="true">
          <span class="ic-model-card__icon">
            @include('admin.utilities._tool_icon_' . $tool['icon'])
          </span>
          <span class="ic-model-card__name">{{ $tool['name'] }}</span>
          <span class="ic-model-card__badge">Coming soon</span>
        </div>
      @endif
    @endforeach
  </div>
@endsection
