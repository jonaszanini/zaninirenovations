@php
  $tipId = $tipId ?? 'tip';
  $label = $label ?? 'About this tool';
  $text = $text ?? '';
@endphp
<span class="util-ra-help">
  <button
    type="button"
    class="util-ra-help-q"
    data-ra-help-pop="{{ $tipId }}"
    aria-expanded="false"
    aria-controls="ra-help-{{ $tipId }}"
    aria-label="{{ $label }}">ⓘ</button>
  <div class="util-ra-help-pop" id="ra-help-{{ $tipId }}" data-ra-help-pop-panel="{{ $tipId }}" hidden role="region" aria-label="{{ $label }}">
    {{ $text }}
  </div>
</span>
