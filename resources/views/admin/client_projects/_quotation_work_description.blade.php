@if (filled($previewWorkDesc ?? ''))
  @php
    $previewWorkDescIsHtml = \App\Support\QuotationWorkDescriptionHtml::isHtml((string) $previewWorkDesc);
  @endphp
  <details class="cp-quotation-work-description">
    <summary class="cp-quotation-work-description__summary admin-chevron-summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="cp-quotation-work-description-title">Description of the work</span>
    </summary>
    @if ($previewWorkDescIsHtml)
      <div class="cp-quotation-work-description-body cp-quotation-work-description-body--rich">{!! $previewWorkDesc !!}</div>
    @else
      <div class="cp-quotation-work-description-body">{!! nl2br(e($previewWorkDesc)) !!}</div>
    @endif
  </details>
@endif
