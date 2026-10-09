{{--
  Wrapper around <x-admin.info-tip> for existing @include callers.

  IsolatingFactory drops parent-scope merge for this view, so only keys
  passed on the include (or view() data) exist here. Legacy text/html
  keys throw outside production (see AdminInfoTipInclude).
--}}
@php
    $passed = (isset($__data) && is_array($__data)) ? $__data : [];
    $resolvedTipId = array_key_exists('tipId', $passed) ? $passed['tipId'] : null;
    $resolvedTipLabel = array_key_exists('tipLabel', $passed) ? $passed['tipLabel'] : null;
    $resolvedTipText = array_key_exists('tipText', $passed) ? $passed['tipText'] : null;
    $resolvedTipHtml = array_key_exists('tipHtml', $passed) ? $passed['tipHtml'] : null;
    $tipId = $tipLabel = $tipText = $tipHtml = null;
@endphp
@if (filled($resolvedTipHtml))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{!! $resolvedTipHtml !!}</x-admin.info-tip>
@elseif (filled($resolvedTipText))
  <x-admin.info-tip :id="$resolvedTipId" :label="$resolvedTipLabel">{{ $resolvedTipText }}</x-admin.info-tip>
@endif
