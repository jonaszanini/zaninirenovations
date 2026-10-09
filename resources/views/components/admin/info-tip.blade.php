{{--
  Compact ⓘ click-to-open help. Reuses .admin-info-tip so admin-dialog.js
  handles Esc / outside click. Pass $text (plain explanation).
--}}
@props([
    'text' => '',
    'id' => null,
])
@if (filled($text))
  @php
    $tipId = $id ?: 'admin-info-tip-'.str_replace('.', '', uniqid('', true));
  @endphp
  <button
    type="button"
    {{ $attributes->except(['text', 'id'])->class('admin-info-tip') }}
    aria-expanded="false"
    aria-controls="{{ $tipId }}"
    aria-label="More information"
  >
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <span id="{{ $tipId }}" class="admin-info-tip__bubble" role="tooltip">{{ $text }}</span>
  </button>
@endif
