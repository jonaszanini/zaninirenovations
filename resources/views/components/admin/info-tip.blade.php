{{--
  <x-admin.info-tip text="…" label="more info" />
  Also accepts tipText / tipLabel (same as admin.partials.info_tip).
--}}
@props([
    'text' => null,
    'label' => 'More info',
    'tipText' => null,
    'tipLabel' => null,
])
@php
    $resolvedTipText = $tipText ?? $text ?? null;
    $resolvedTipLabel = $tipLabel ?? $label ?? 'More info';
@endphp
@if (filled($resolvedTipText))
  <button type="button" {{ $attributes->class('admin-info-tip') }} aria-expanded="false" aria-label="{{ $resolvedTipLabel }}">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <span class="admin-info-tip__bubble" role="tooltip">{{ $resolvedTipText }}</span>
  </button>
@endif
