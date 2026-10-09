{{--
  Label as a sibling of the info-tip — never nest the tip inside <label>.

  Never read parent-scope $for / $labelHtml / $labelId / $tipText / $tipHtml /
  $tipLabel — @include inherits the parent view. Pass lw* keys only.

  @param string|null $lwFor         label[for]
  @param string      $lwLabelText   Visible label (escaped)
  @param string|null $lwLabelHtml   Trusted label HTML instead of $lwLabelText
  @param string|null $lwTipText     Plain tip (escaped)
  @param string|null $lwTipHtml     HTML tip
  @param string|null $lwTipLabel    aria-label "About {tipLabel}"
  @param string|null $lwFilledKey   Optional client-filled marker
  @param array       $lwFilledKeys
  @param string|null $lwLabelId
--}}
@php
  $lwFor = $lwFor ?? null;
  $lwLabelText = $lwLabelText ?? '';
  $lwLabelHtml = $lwLabelHtml ?? null;
  $lwLabelId = $lwLabelId ?? null;
  $lwTipText = $lwTipText ?? null;
  $lwTipHtml = $lwTipHtml ?? null;
  $lwTipLabel = $lwTipLabel ?? trim(strip_tags((string) $lwLabelText));
  $lwFilledKey = $lwFilledKey ?? null;
  $lwFilledKeys = $lwFilledKeys ?? [];
@endphp
<span class="admin-label-with-tip">
  <label @if (! empty($lwFor)) for="{{ $lwFor }}" @endif @if (! empty($lwLabelId)) id="{{ $lwLabelId }}" @endif>
    @if (! empty($lwFilledKey))
      @include('admin.quotations.basement._client_filled', ['key' => $lwFilledKey, 'filledKeys' => $lwFilledKeys])
    @endif
    @if (! empty($lwLabelHtml))
      {!! $lwLabelHtml !!}
    @else
      {{ $lwLabelText }}
    @endif
  </label>
  @include('admin.partials.info_tip', [
    'tipText' => $lwTipText,
    'tipHtml' => $lwTipHtml,
    'tipLabel' => $lwTipLabel,
  ])
</span>
