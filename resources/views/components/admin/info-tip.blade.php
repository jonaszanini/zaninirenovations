{{--
  Shared admin ⓘ help popover. Canonical API (keep this path for merges):

    <x-admin.info-tip label="Board style">Help text stays in the DOM.</x-admin.info-tip>

  $label becomes aria-label="About {label}". Slot is trusted Blade HTML
  (links, <code>, <strong>). Plain $text is escaped.

  Place the tip as a sibling of <label for="…"> — never inside it — so the
  ⓘ does not pollute the input's accessible name. Headings may wrap the tip.
--}}
@props(['text' => null, 'id' => null, 'label' => null])

@php
    $body = '';
    if (isset($slot) && is_object($slot) && method_exists($slot, 'isNotEmpty') && $slot->isNotEmpty()) {
        $body = trim((string) $slot);
    } elseif (filled($text)) {
        $body = e($text);
    }
    $tipId = $id ?: ('admin-info-tip-'.str_replace('.', '', uniqid('', true)));
    $ariaLabel = filled($label) ? ('About '.$label) : 'More info';
@endphp
@if (filled($body))
<span {{ $attributes->class('admin-info-tip-wrap') }}>
  <button type="button"
          class="admin-info-tip"
          aria-label="{{ $ariaLabel }}"
          aria-expanded="false"
          aria-controls="{{ $tipId }}"
          aria-describedby="{{ $tipId }}">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
  </button>
  <span id="{{ $tipId }}" class="admin-info-tip__bubble" role="tooltip">{!! $body !!}</span>
</span>
@endif
