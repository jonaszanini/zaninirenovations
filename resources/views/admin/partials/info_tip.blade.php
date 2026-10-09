{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  Never read $id or $label — @include inherits the parent view scope, so those
  collide with form_field / checkbox / _qty_lw input ids and loop labels.
  Pass tipId / tipLabel explicitly, or use <x-admin.info-tip> directly.

  @param string|null $text      Plain explanation (escaped)
  @param string|null $html      HTML explanation (links, <code>, <strong>)
  @param string|null $tipId     Optional popover id (NOT $id)
  @param string|null $tipLabel  Field name used as aria-label "About {label}"
--}}
@php
    $resolvedTipId = $tipId ?? null;
    $resolvedTipLabel = $tipLabel ?? null;
@endphp
@if (filled($html ?? null))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{!! $html !!}</x-admin.info-tip>
@elseif (filled($text ?? null))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{{ $text }}</x-admin.info-tip>
@endif
