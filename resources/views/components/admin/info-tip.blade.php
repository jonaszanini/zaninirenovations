{{--
  Shared admin ⓘ help popover. Canonical API (keep this for merges):

    <x-admin.info-tip>Help text stays in the DOM.</x-admin.info-tip>

  Optional attributes: id, class. Slot may include HTML (links, <code>, <strong>).
  Existing @include('admin.partials.info_tip', ['text' => '…']) still works.
--}}
@props(['text' => null, 'id' => null])

@php
    $body = '';
    if (isset($slot) && is_object($slot) && method_exists($slot, 'isNotEmpty') && $slot->isNotEmpty()) {
        $body = trim((string) $slot);
    } elseif (filled($text)) {
        $body = e($text);
    }
    $tipId = $id ?? ('admin-info-tip-'.str_replace('.', '-', uniqid('t', true)));
@endphp
@if (filled($body))
<span {{ $attributes->class('admin-info-tip-wrap') }}>
  <button type="button"
          class="admin-info-tip"
          aria-label="More info"
          aria-expanded="false"
          aria-controls="{{ $tipId }}">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
  </button>
  <span id="{{ $tipId }}" class="admin-info-tip__bubble" role="tooltip">{!! $body !!}</span>
</span>
@endif
