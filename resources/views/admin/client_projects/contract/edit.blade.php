@extends('admin.layouts.app')

@section('heading', 'Edit Contract — '.$project->name)

@push('head')
  {{-- TinyMCE 6 community edition, self-hosted via the official CDN.
       This avoids requiring an API key and gives us the full toolbar
       suitable for legal/contract documents. --}}
  <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"
          referrerpolicy="origin"></script>
@endpush

@section('content')
  <div class="admin-card cp-contract-editor-card">
    <div class="cp-contract-editor-toolbar">
      <div>
        <h3 style="margin:0;">Contract editor</h3>
        <p class="admin-form-help" style="margin:4px 0 0;">
          Paste from Word or Google Docs and the formatting will be preserved.
          Use the toolbar for headings, lists, tables and more. The contract
          header (parties) and footer (signatures + entire-agreement clause)
          are added automatically when the PDF is generated — you only edit
          the body here.
        </p>
      </div>
      <a href="{{ route('admin.client-projects.show', $project) }}#contract"
         class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to project
      </a>
    </div>

    <form action="{{ route('admin.client-projects.contract.update', $project) }}"
          method="post"
          id="cp-contract-form">
      @csrf
      @method('PUT')

      <input type="hidden" name="intent" value="draft" id="cp-contract-intent">

      <div class="admin-form-group">
        <textarea id="cp-contract-editor"
                  name="html_content"
                  rows="30">{{ old('html_content', $contract->html_content) }}</textarea>
      </div>

      <div class="cp-contract-editor-actions">
        <a href="{{ route('admin.client-projects.show', $project) }}#contract"
           class="admin-btn admin-btn--ghost">
          Cancel
        </a>
        <button type="submit"
                class="admin-btn admin-btn--ghost"
                data-cp-contract-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                class="admin-btn admin-btn--primary"
                data-cp-contract-intent="close">
          Save &amp; Close
        </button>
      </div>
    </form>
  </div>

  <script>
    (function () {
      // Init TinyMCE on the contract textarea.
      tinymce.init({
        selector: '#cp-contract-editor',
        menubar: 'edit insert view format table tools',
        plugins: 'advlist autolink lists link image table code preview searchreplace fullscreen visualblocks wordcount paste help',
        toolbar:
          'undo redo | blocks fontfamily fontsize | ' +
          'bold italic underline strikethrough forecolor backcolor | ' +
          'alignleft aligncenter alignright alignjustify | ' +
          'bullist numlist outdent indent | link table image | ' +
          'removeformat code preview fullscreen help',
        height: 700,
        branding: false,
        promotion: false,
        paste_data_images: true,
        paste_as_text: false,
        content_style:
          'body { font-family: Arial, Helvetica, sans-serif; font-size: 12pt; color: #1f2937; line-height: 1.55; padding: 16px; } ' +
          'h1, h2, h3, h4 { color: #024768; }',
        // Sync TinyMCE content back to the textarea on every change so
        // standard form submission picks up the latest HTML.
        setup: function (editor) {
          editor.on('change keyup undo redo', function () {
            editor.save();
          });
        }
      });

      // Stamp the right "intent" (draft vs. close) onto the hidden field
      // before the form is submitted, based on which button was clicked.
      var form = document.getElementById('cp-contract-form');
      var intentField = document.getElementById('cp-contract-intent');
      var buttons = form.querySelectorAll('[data-cp-contract-intent]');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-contract-intent') || 'draft';
        });
      });
    })();
  </script>
@endsection
