{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  Reset tip* immediately so leftover parent-scope values cannot leak into
  the component (or a nested include). Callers must pass the keys they
  want — typically all four, with unused ones set to null.
--}}
@php
    $resolvedTipId = $tipId ?? null;
    $resolvedTipLabel = $tipLabel ?? null;
    $resolvedTipText = $tipText ?? null;
    $resolvedTipHtml = $tipHtml ?? null;
    $tipId = $tipLabel = $tipText = $tipHtml = null;
@endphp
@if (filled($resolvedTipHtml))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{!! $resolvedTipHtml !!}</x-admin.info-tip>
@elseif (filled($resolvedTipText))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{{ $resolvedTipText }}</x-admin.info-tip>
@endif
