{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  Never read parent-scope $id, $label, $text, or $html — @include inherits
  the parent view, so those collide with form fields and loops.
  Only tipId / tipLabel / tipText / tipHtml are honored.

  @param string|null $tipText   Plain explanation (escaped)
  @param string|null $tipHtml   HTML explanation (links, <code>, <strong>)
  @param string|null $tipId     Optional popover id (NOT $id)
  @param string|null $tipLabel  Field name used as aria-label "About {label}"
--}}
@php
    $resolvedTipId = $tipId ?? null;
    $resolvedTipLabel = $tipLabel ?? null;
    $resolvedTipText = $tipText ?? null;
    $resolvedTipHtml = $tipHtml ?? null;
@endphp
@if (filled($resolvedTipHtml))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{!! $resolvedTipHtml !!}</x-admin.info-tip>
@elseif (filled($resolvedTipText))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{{ $resolvedTipText }}</x-admin.info-tip>
@endif
