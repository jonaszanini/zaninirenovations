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
    {{-- Top action bar: Back to project on the LEFT, the form action
         buttons (Cancel / Save Draft / Save & Close) on the RIGHT.
         The action buttons live outside the <form> below and are wired
         to it via the HTML `form="cp-contract-form"` attribute. --}}
    <div class="cp-contract-editor-toolbar">
      <div class="cp-contract-editor-toolbar-left">
        <a href="{{ route('admin.client-projects.show', $project) }}#contract"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
        <button type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                id="cp-contract-open-context-modal"
                aria-haspopup="dialog"
                aria-controls="cp-contract-context-dialog">
          <i class="fa fa-file-text-o"></i> Project &amp; quote details
        </button>
      </div>
      <div class="cp-contract-editor-toolbar-right">
        <a href="{{ route('admin.client-projects.show', $project) }}#contract"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          Cancel
        </a>
        <button type="submit"
                form="cp-contract-form"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-cp-contract-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                form="cp-contract-form"
                class="admin-btn admin-btn--primary admin-btn--sm"
                data-cp-contract-intent="close">
          Save &amp; Close
        </button>
      </div>
    </div>

    <div class="cp-contract-editor-heading">
      <h3 style="margin:0;">Contract editor</h3>
      <p class="admin-form-help" style="margin:4px 0 0;">
        Paste from Word or Google Docs and the formatting will be preserved.
        Use the toolbar for headings, lists, tables and more. The contract
        header (parties) and footer (signatures + entire-agreement clause)
        are added automatically when the PDF is generated — you only edit
        the body here.
      </p>
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

  <div class="cp-modal-backdrop" id="cp-contract-context-modal" hidden>
    <div class="cp-modal" id="cp-contract-context-dialog" role="dialog" aria-modal="true" aria-labelledby="cp-contract-context-title" style="max-width:720px;">
      <div class="cp-modal-header">
        <h3 id="cp-contract-context-title">Project &amp; quotation details</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <div class="cp-modal-body">
        <p class="admin-form-help" style="margin-top:0;">
          All quotations linked to this project (calculator / fence / standard / paving data as JSON, line items, totals), client and site info, Financials installment amounts, schedule, billing, <strong>internal project notes</strong>, and e-transfer instructions — for pasting into an AI assistant to help draft the contract. Not sent to the client.
        </p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;">
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-contract-copy-context">
            <i class="fa fa-clipboard"></i> Copy to clipboard
          </button>
          <span id="cp-contract-copy-feedback" class="admin-form-help" style="margin:0;color:#15803d;display:none;" aria-live="polite">Copied.</span>
        </div>
        <textarea id="cp-contract-context-textarea"
                  readonly
                  rows="22"
                  class="admin-input"
                  style="width:100%;font-family:Consolas,Monaco,'Courier New',monospace;font-size:12px;line-height:1.45;resize:vertical;min-height:280px;">{{ $contractDraftingContext }}</textarea>
      </div>
      <div class="cp-modal-footer">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Close</button>
      </div>
    </div>
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
      // We query the whole document because the top toolbar's submit
      // buttons live outside the form (they use the HTML `form` attribute
      // to associate themselves with it).
      var intentField = document.getElementById('cp-contract-intent');
      var buttons = document.querySelectorAll('[data-cp-contract-intent]');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-contract-intent') || 'draft';
        });
      });
    })();
  </script>
  <script>
    (function () {
      var openBtn = document.getElementById('cp-contract-open-context-modal');
      var modal = document.getElementById('cp-contract-context-modal');
      var ta = document.getElementById('cp-contract-context-textarea');
      var copyBtn = document.getElementById('cp-contract-copy-context');
      var feedback = document.getElementById('cp-contract-copy-feedback');
      if (!openBtn || !modal) return;

      function openModal() {
        modal.hidden = false;
        document.body.classList.add('cp-modal-open');
        setTimeout(function () {
          if (ta) {
            ta.focus();
            ta.select();
          }
        }, 0);
      }

      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
      }

      openBtn.addEventListener('click', openModal);
      modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.matches && e.target.matches('[data-cp-modal-close]'))) {
          closeModal();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) {
          closeModal();
        }
      });

      if (copyBtn && ta) {
        copyBtn.addEventListener('click', function () {
          var text = ta.value;

          function showOk() {
            if (feedback) {
              feedback.style.display = '';
              setTimeout(function () {
                if (feedback) {
                  feedback.style.display = 'none';
                }
              }, 2000);
            }
          }

          function showErr() {
            window.alert('Could not copy to clipboard. Select the text in the box and copy manually (Ctrl+C).');
          }

          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showOk).catch(function () {
              try {
                ta.select();
                if (document.execCommand('copy')) {
                  showOk();
                } else {
                  showErr();
                }
              } catch (err) {
                showErr();
              }
            });
          } else {
            try {
              ta.select();
              if (document.execCommand('copy')) {
                showOk();
              } else {
                showErr();
              }
            } catch (err2) {
              showErr();
            }
          }
        });
      }
    })();
  </script>
@endsection
