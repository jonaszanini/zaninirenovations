{{--
  Label + ⓘ with the last word glued to the tip (nowrap) so two-column
  grids keep inputs aligned when the icon would otherwise wrap alone.

  @param string      $label
  @param string|null $for
  @param string|null $tipText
  @param string|null $tipHtml
  @param string|null $tipLabel
  @param string|null $tipId
  @param bool        $required
  @param string|null $labelId
  @param string      $prefixHtml   Trusted HTML before the lead words (icons)
--}}
@php
  $for = $for ?? '';
  $label = $label ?? '';
  $required = $required ?? false;
  $prefixHtml = $prefixHtml ?? '';
  $labelId = $labelId ?? null;
  $parts = \App\Support\AdminInfoTipInclude::splitLabel(trim(strip_tags((string) $label)));
  $lead = $parts['lead'];
  $last = $parts['last'];
  $forAttr = $for !== '' ? ' for="'.e($for).'"' : '';
  $idAttr = ! empty($labelId) ? ' id="'.e($labelId).'"' : '';
@endphp
<span class="admin-label-with-tip">
  @if ($lead !== '')
    <label{!! $forAttr !!}{!! $idAttr !!}>{!! $prefixHtml !!}{{ $lead }}</label>
  @endif
  <span class="admin-label-with-tip__end">
    <label{!! $forAttr !!}@if ($lead === ''){!! $idAttr !!}@endif>{{ $last }}@if ($required)<span class="cp-required">*</span>@endif</label>
    @include('admin.partials.info_tip', [
      'tipText' => $tipText ?? null,
      'tipHtml' => $tipHtml ?? null,
      'tipLabel' => $tipLabel ?? $label,
      'tipId' => $tipId ?? null,
    ])
  </span>
</span>
