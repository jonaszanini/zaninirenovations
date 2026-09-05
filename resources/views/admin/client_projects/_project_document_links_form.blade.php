@php
  $standalone = (bool) ($standalone ?? true);
  $formId = $formId ?? null;
  $rootId = $rootId ?? 'cp-document-links';
  $oldRows = old('document_links');
  if (! is_array($oldRows) || $oldRows === []) {
      $oldRows = [['url' => '', 'title' => '', 'kind' => 'auto']];
  }
@endphp

@if ($standalone)
<form action="{{ route('admin.client-projects.document-links.store', $project) }}" method="post" class="cp-document-links" id="{{ $rootId }}-form">
  @csrf
@else
<div class="cp-document-links" id="{{ $rootId }}-form">
@endif
  <div class="admin-form-group">
    <span class="cp-document-links-label">
      Document links <span class="admin-form-help cp-inline-help">(optional)</span>
      @include('admin.partials.info_tip', ['text' => 'Paste Word, Excel, or PowerPoint share links (OneDrive, SharePoint, Google Docs, or a direct file URL). Type is detected from the link; you can override it. Add as many as you need.'])
    </span>
  </div>

  <div class="cp-document-links-rows" data-cp-doc-link-rows>
    @foreach ($oldRows as $index => $row)
      @include('admin.client_projects._document_link_row', [
        'index' => (int) $index,
        'row' => is_array($row) ? $row : [],
        'formId' => $standalone ? null : $formId,
      ])
    @endforeach
  </div>

  @error('document_links')
    <p class="admin-form-error">{{ $message }}</p>
  @enderror

  <div class="cp-document-links-actions">
    <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-doc-link-add>
      <i class="fa fa-plus" aria-hidden="true"></i> Add another link
    </button>
    @if ($standalone)
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-link" aria-hidden="true"></i> Save links
      </button>
    @endif
  </div>
@if ($standalone)
</form>
@else
</div>
@endif

<template id="{{ $rootId }}-row-template">
  @include('admin.client_projects._document_link_row', [
    'index' => '__INDEX__',
    'row' => ['url' => '', 'title' => '', 'kind' => 'auto'],
    'formId' => $standalone ? null : $formId,
  ])
</template>

@once
  @push('scripts')
    <script src="{{ asset('js/admin-document-links.js') }}?v={{ @filemtime(public_path('js/admin-document-links.js')) ?: time() }}"></script>
  @endpush
@endonce
@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.AdminDocumentLinks) {
        window.AdminDocumentLinks.init(@json($rootId));
      }
    });
  </script>
@endpush
