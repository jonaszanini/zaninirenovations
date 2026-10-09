{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  @param string|null $text   Plain explanation (escaped)
  @param string|null $html   HTML explanation (links, <code>, <strong>)
  @param string|null $id     Optional popover id
  @param string|null $label  Field name used as aria-label "About {label}"
--}}
@if (filled($html ?? null))
  <x-admin.info-tip :id="$id ?? null" :label="$label ?? null">{!! $html !!}</x-admin.info-tip>
@elseif (filled($text ?? null))
  <x-admin.info-tip :id="$id ?? null" :label="$label ?? null">{{ $text }}</x-admin.info-tip>
@endif
