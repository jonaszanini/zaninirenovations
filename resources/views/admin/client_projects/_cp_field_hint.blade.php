{{-- Legacy hint include — now the shared info-tip. Pass tipText or tipHtml only. --}}
@php
  $hintText = $tipText ?? null;
  $hintHtml = $tipHtml ?? null;
  $hintId = $tipId ?? null;
  $hintLabel = $tipLabel ?? null;
  $tipText = $tipHtml = $tipId = $tipLabel = null;
@endphp
@include('admin.partials.info_tip', [
  'tipText' => $hintText,
  'tipHtml' => $hintHtml,
  'tipId' => $hintId,
  'tipLabel' => $hintLabel,
])
