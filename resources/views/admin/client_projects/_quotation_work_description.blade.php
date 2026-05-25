@if (filled($previewWorkDesc ?? ''))
  <details class="cp-quotation-work-description">
    <summary class="cp-quotation-work-description__summary admin-chevron-summary">
      <span class="admin-chevron-down" aria-hidden="true"><i class="fa fa-chevron-down"></i></span>
      <span class="cp-quotation-work-description-title">Description of the work</span>
    </summary>
    <div class="cp-quotation-work-description-body">{!! nl2br(e($previewWorkDesc)) !!}</div>
  </details>
@endif
