{{--
  Document actions that used to live in the old quote toolbar.
  Stays out of the Save / Draft / Cancel row.
  Optional: $purchaseListUrl, $purchaseListTitle, $isEdit, $quotation, $linkedCpq
--}}
@if (! empty($isEdit))
  <nav class="qh-more" aria-label="Quotation documents">
    @if (! empty($purchaseListUrl))
      <a href="{{ $purchaseListUrl }}"
         target="_blank"
         rel="noopener noreferrer"
         class="admin-btn admin-btn--ghost admin-btn--sm"
         data-purchase-list-choice
         data-purchase-list-title="{{ $purchaseListTitle ?? 'Print material list' }}">
        <i class="fa fa-print" aria-hidden="true"></i> {{ $purchaseListTitle ?? 'Print material list' }}
      </a>
    @endif
    @include('admin.partials.quotation_view_pdf')
  </nav>
@endif
