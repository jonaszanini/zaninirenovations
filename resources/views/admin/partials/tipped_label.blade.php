{{--
  Label + ⓘ. The whole phrase stays in one <label>. The required *
  and the ⓘ sit in the trailing span so they stay on the same line
  as the last word without pulling that word into its own element.

  Order is always: label text, then *, then <small>, then ⓘ.
  Required * is wrapped with the ⓘ so they stay on one line.

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
  $defined = \App\Support\AdminInfoTipInclude::tippedLabelBag((isset($__data) && is_array($__data)) ? $__data : []);
  $for = array_key_exists('for', $defined) ? (string) $defined['for'] : '';
  $label = array_key_exists('label', $defined) ? (string) $defined['label'] : '';
  $required = ! empty($defined['required']);
  $prefixHtml = array_key_exists('prefixHtml', $defined) ? (string) $defined['prefixHtml'] : '';
  $labelId = array_key_exists('labelId', $defined) && filled($defined['labelId']) ? (string) $defined['labelId'] : null;
  $tipText = array_key_exists('tipText', $defined) ? $defined['tipText'] : null;
  $tipHtml = array_key_exists('tipHtml', $defined) ? $defined['tipHtml'] : null;
  $tipLabel = array_key_exists('tipLabel', $defined) ? $defined['tipLabel'] : $label;
  $tipId = array_key_exists('tipId', $defined) ? $defined['tipId'] : null;
  $labelText = trim(strip_tags((string) $label));
  $forAttr = $for !== '' ? ' for="'.e($for).'"' : '';
  $idAttr = $labelId !== null ? ' id="'.e($labelId).'"' : '';
@endphp
<span class="admin-label-with-tip"{!! $idAttr !!}>
  <label{!! $forAttr !!}>{!! $prefixHtml !!}{{ $labelText }}</label>
  <span class="admin-label-with-tip__end">
    @if ($required)
      <span class="cp-required">*</span>
    @endif
    @include('admin.partials.info_tip', [
      'tipText' => $tipText,
      'tipHtml' => $tipHtml,
      'tipLabel' => $tipLabel,
      'tipId' => $tipId,
    ])
  </span>
</span>
