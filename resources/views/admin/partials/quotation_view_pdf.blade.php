{{--
  Opens the client quotation PDF in a new tab (stays on the calculator).
  Expects: $isEdit, $quotation (when editing), optional $linkedCpq.
  Optional: $iconOnly (bool) — icon button with title/aria-label only.
--}}
@php
  $linkedCpq = $linkedCpq ?? null;
  $iconOnly = ! empty($iconOnly);
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
  $pdfBtnClass = 'admin-btn admin-btn--ghost admin-btn--sm'.($iconOnly ? ' admin-btn--icon' : '');
  $pdfLabel = 'View PDF';
@endphp
@if ($isEdit ?? false)
  @if ($canViewPdf)
    <a href="{{ route('admin.client-projects.quotation.pdf', [$pdfProject, $linkedCpq]) }}"
       target="_blank"
       rel="noopener noreferrer"
       class="{{ $pdfBtnClass }}"
       data-qh-view-pdf
       title="{{ $pdfLabel }}"
       @if ($iconOnly) aria-label="{{ $pdfLabel }}" @endif>
      <i class="fa fa-file-pdf-o" aria-hidden="true"></i>@unless ($iconOnly) {{ $pdfLabel }}@endunless
    </a>
  @else
    <button type="button"
            class="{{ $pdfBtnClass }}"
            disabled
            data-qh-view-pdf
            title="{{ $pdfDisabledTitle }}"
            @if ($iconOnly) aria-label="{{ $pdfLabel }}" @endif>
      <i class="fa fa-file-pdf-o" aria-hidden="true"></i>@unless ($iconOnly) {{ $pdfLabel }}@endunless
    </button>
  @endif
@endif
