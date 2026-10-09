{{--
  Compact ⓘ click-to-open help. Bubble is a sibling of the button so it
  stays selectable and does not sit in the control's accessible name.
  Pass $text (escaped) or a slot (trusted Blade HTML).
--}}
@props([
    'text' => '',
    'id' => null,
    'label' => 'More information',
])
@php
    $hasSlot = isset($slot) && trim((string) $slot) !== '';
@endphp
@if ($hasSlot || filled($text))
  @php
    $tipId = $id ?: 'admin-info-tip-'.str_replace('.', '', uniqid('', true));
  @endphp
  <span {{ $attributes->except(['text', 'id', 'label'])->class('admin-info-tip-wrap') }}>
    <button
      type="button"
      class="admin-info-tip"
      aria-expanded="false"
      aria-controls="{{ $tipId }}"
      aria-describedby="{{ $tipId }}"
      aria-label="{{ $label }}"
    >
      <i class="fa fa-info-circle" aria-hidden="true"></i>
    </button>
    <span id="{{ $tipId }}" class="admin-info-tip__bubble" role="tooltip">
      @if ($hasSlot)
        {!! $slot !!}
      @else
        {{ $text }}
      @endif
    </span>
  </span>
@endif
