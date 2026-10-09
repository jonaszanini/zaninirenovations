{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  @param string|null $text  Plain explanation (escaped via {{ }})
  @param string|null $html  HTML explanation (links, <code>, <strong>)
  @param string|null $id    Optional popover id
--}}
@if (filled($html ?? null))
  <x-admin.info-tip :id="$id ?? null">{!! $html !!}</x-admin.info-tip>
@elseif (filled($text ?? null))
  <x-admin.info-tip :id="$id ?? null">{{ $text }}</x-admin.info-tip>
@endif
