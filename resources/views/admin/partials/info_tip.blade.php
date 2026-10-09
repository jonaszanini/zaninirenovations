{{--
<<<<<<< HEAD
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
=======
  Wrapper around <x-admin.info-tip> for existing @include callers.

  @param string|null $text  Plain explanation (escaped via {{ }})
  @param string|null $html  HTML explanation (links, <code>, <strong>)
  @param string|null $id    Optional popover id
--}}
@if (filled($html ?? null))
  <x-admin.info-tip :id="$id ?? null">{!! $html !!}</x-admin.info-tip>
@elseif (filled($text ?? null))
  <x-admin.info-tip :id="$id ?? null">{{ $text }}</x-admin.info-tip>
>>>>>>> 27ea98bd (Add shared admin info-tip and convert help paragraphs.)
@endif
