{{--
  Tap/click information icon. Pass $text (plain explanation).
--}}
@if (filled($text ?? null))
  <button type="button" class="admin-info-tip" aria-expanded="false" aria-label="{{ $text }}">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <span class="admin-info-tip__bubble" role="tooltip">{{ $text }}</span>
  </button>
@endif
