{{--
  Opens the client quotation PDF in a new tab (stays on the calculator).
  Expects: $isEdit, $quotation (when editing), optional $linkedCpq.
--}}
@php
  $linkedCpq = $linkedCpq ?? null;
  $pdfProject = ($isEdit ?? false) && isset($quotation) ? ($quotation->project ?? null) : null;
  $canViewPdf = $pdfProject
    && $linkedCpq
    && (int) $linkedCpq->client_project_id === (int) $pdfProject->id
    && $linkedCpq->hasContent();
  if (! ($isEdit ?? false)) {
      $pdfDisabledTitle = '';
  } elseif (! $pdfProject || ! $linkedCpq) {
      $pdfDisabledTitle = 'Link this quotation to a project and save to preview the client PDF.';
  } else {
      $pdfDisabledTitle = 'Save with at least one line item before the PDF is available.';
  }
@endphp
@if ($isEdit ?? false)
  @if ($canViewPdf)
    <a href="{{ route('admin.client-projects.quotation.pdf', [$pdfProject, $linkedCpq]) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="admin-btn admin-btn--ghost admin-btn--sm"
       title="Open the client quotation PDF in a new tab">
      <i class="fa fa-file-pdf-o" aria-hidden="true"></i> View PDF
    </a>
  @else
    <button type="button"
            class="admin-btn admin-btn--ghost admin-btn--sm"
            disabled
            title="{{ $pdfDisabledTitle }}">
      <i class="fa fa-file-pdf-o" aria-hidden="true"></i> View PDF
    </button>
  @endif
@endif
