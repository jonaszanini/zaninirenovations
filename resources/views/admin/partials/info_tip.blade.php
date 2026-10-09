{{--
  Wrapper around <x-admin.info-tip> for existing @include / view() callers.

  @include is rewritten by AdminInfoTipInclude::rewriteBlade to render() so
  parent Blade scope cannot leak tip* keys (does not rely on IsolatingFactory).
  view() / ->with() still hit this file; resolveFromView() applies the
  text/html guard and reads only the four tip* keys from $__data.
--}}
@php
    $passed = (isset($__data) && is_array($__data)) ? $__data : [];
    $resolved = \App\Support\AdminInfoTipInclude::resolveFromView($passed);
    $resolvedTipId = $resolved['tipId'];
    $resolvedTipLabel = $resolved['tipLabel'];
    $resolvedTipText = $resolved['tipText'];
    $resolvedTipHtml = $resolved['tipHtml'];
    $tipId = $tipLabel = $tipText = $tipHtml = null;
@endphp
@if (filled($resolvedTipHtml))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{!! $resolvedTipHtml !!}</x-admin.info-tip>
@elseif (filled($resolvedTipText))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{{ $resolvedTipText }}</x-admin.info-tip>
@endif
