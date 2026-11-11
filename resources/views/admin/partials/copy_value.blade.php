{{--
  Linked contact value with a copy button.

  Params:
    value      string — plain text to show and copy
    href       string|null — action link (mailto:, tel:, maps URL)
    copyLabel  string — aria-label / title, e.g. "Copy email"
    external   bool — open href in a new tab (maps)
    class      string — extra classes on the wrap
--}}
@php
  $value = trim((string) ($value ?? ''));
  $href = isset($href) ? trim((string) $href) : '';
  $copyLabel = trim((string) ($copyLabel ?? 'Copy'));
  $external = (bool) ($external ?? false);
  $class = trim((string) ($class ?? ''));
  $wrapClass = trim('cl-copy-field'.($class !== '' ? ' '.$class : ''));
@endphp
@if ($value !== '')
  <span class="{{ $wrapClass }}">
    @if ($href !== '')
      <a href="{{ $href }}"
         class="cl-copy-field__value admin-client-link"
         @if ($external) target="_blank" rel="noopener" @endif>{{ $value }}</a>
    @else
      <span class="cl-copy-field__value">{{ $value }}</span>
    @endif
    <button type="button"
            class="cl-copy-btn"
            data-copy-value="{{ $value }}"
            aria-label="{{ $copyLabel }}"
            title="{{ $copyLabel }}">
      <i class="fa fa-copy" aria-hidden="true"></i>
    </button>
  </span>
@endif
