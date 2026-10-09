{{--
  Tap/click information icon.

  Prefer $tipText / $tipLabel so @include does not inherit a parent $text or
  $label (form_field / checkbox loops). $text remains a fallback.
--}}
@php
    $resolvedTipText = $tipText ?? $text ?? null;
    $resolvedTipLabel = $tipLabel ?? 'More info';
@endphp
@if (filled($resolvedTipText))
  <button type="button" class="admin-info-tip" aria-expanded="false" aria-label="{{ $resolvedTipLabel }}">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <span class="admin-info-tip__bubble" role="tooltip">{{ $resolvedTipText }}</span>
  </button>
@endif
