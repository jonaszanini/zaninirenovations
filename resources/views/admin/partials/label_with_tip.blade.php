{{--
  Label as a sibling of the info-tip — never nest the tip inside <label>.

  @param string      $for         label[for]
  @param string      $labelText   Visible label (escaped)
  @param string|null $labelHtml   Trusted label HTML instead of $labelText
  @param string|null $tipText     Plain tip (escaped)
  @param string|null $tipHtml     HTML tip
  @param string|null $tipLabel    aria-label "About {tipLabel}"
  @param string|null $filledKey   Optional client-filled marker
  @param array       $filledKeys
  @param string|null $labelId
--}}
@php
  $for = $for ?? null;
  $labelText = $labelText ?? '';
  $tipLabel = $tipLabel ?? trim(strip_tags($labelText));
  $filledKey = $filledKey ?? null;
  $filledKeys = $filledKeys ?? [];
@endphp
<span class="admin-label-with-tip">
  <label @if (! empty($for)) for="{{ $for }}" @endif @if (! empty($labelId)) id="{{ $labelId }}" @endif>
    @if (! empty($filledKey))
      @include('admin.quotations.basement._client_filled', ['key' => $filledKey, 'filledKeys' => $filledKeys])
    @endif
    @if (! empty($labelHtml))
      {!! $labelHtml !!}
    @else
      {{ $labelText }}
    @endif
  </label>
  @include('admin.partials.info_tip', [
    'tipText' => $tipText ?? null,
    'tipHtml' => $tipHtml ?? null,
    'tipLabel' => $tipLabel,
  ])
</span>
