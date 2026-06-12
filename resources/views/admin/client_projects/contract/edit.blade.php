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
      <h3 class="cp-form-help-flush">Contract editor</h3>
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
        <textarea class="admin-input" id="cp-contract-editor"
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
        @php
          $requiredChecklist = collect($contractDraftingChecklist)->filter(fn ($item) => empty($item['optional']));
          $requiredPresent = $requiredChecklist->where('present', true)->count();
          $requiredTotal = $requiredChecklist->count();
        @endphp
        <div class="cp-contract-context-checklist-wrap">
          <div class="cp-contract-context-checklist-head">
            <span class="cp-contract-context-checklist-title">Included in export</span>
            <span class="cp-contract-context-checklist-summary @if($requiredPresent < $requiredTotal) is-incomplete @endif">
              {{ $requiredPresent }} / {{ $requiredTotal }} required
            </span>
          </div>
          <ul class="cp-contract-context-checklist" aria-label="Contract context checklist">
            @foreach ($contractDraftingChecklist as $item)
              @php
                $state = $item['present']
                  ? 'present'
                  : (!empty($item['optional']) ? 'optional' : 'missing');
              @endphp
              <li class="cp-contract-context-check is-{{ $state }}">
                <span class="cp-contract-context-check__icon" aria-hidden="true">
                  @if ($item['present'])
                    <i class="fa fa-check"></i>
                  @else
                    <i class="fa fa-times"></i>
                  @endif
                </span>
                <span class="cp-contract-context-check__label">{{ $item['label'] }}</span>
                @if (!$item['present'] && !empty($item['optional']))
                  <span class="cp-contract-context-check__hint">optional</span>
                @elseif (!$item['present'])
                  <span class="cp-contract-context-check__hint">not in export</span>
                @endif
              </li>
            @endforeach
          </ul>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:8px;">
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-contract-copy-context">
            <i class="fa fa-clipboard"></i> Copy to clipboard
          </button>
          <span id="cp-contract-copy-feedback" class="admin-form-help" style="margin:0;color:#15803d;display:none;" aria-live="polite">Copied.</span>
        </div>
        <textarea id="cp-contract-context-textarea"
                  readonly
                  rows="10"
                  class="admin-input"
                  style="width:100%;font-family:Consolas,Monaco,'Courier New',monospace;font-size:12px;line-height:1.45;resize:vertical;">{{ $contractDraftingContext }}</textarea>
        <div class="cp-contract-gemini-wrap">
          <div class="admin-ai-action-group">
          <button type="button"
                  class="cp-contract-gemini-btn"
                  id="cp-contract-gemini-draft"
                  data-ai-url="{{ route('admin.client-projects.contract.ai-draft', $project) }}">
            <span class="cp-contract-gemini-btn__logo" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2L13.9 8.3L20 10.2L13.9 12.1L12 18.4L10.1 12.1L4 10.2L10.1 8.3L12 2Z" fill="url(#cp-gemini-grad-a)"/>
                <path d="M19 14L19.8 16.6L22.4 17.4L19.8 18.2L19 20.8L18.2 18.2L15.6 17.4L18.2 16.6L19 14Z" fill="url(#cp-gemini-grad-b)"/>
                <path d="M6 15L6.7 17.1L8.8 17.8L6.7 18.5L6 20.6L5.3 18.5L3.2 17.8L5.3 17.1L6 15Z" fill="url(#cp-gemini-grad-c)"/>
                <defs>
                  <linearGradient id="cp-gemini-grad-a" x1="4" y1="2" x2="20" y2="18" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#4285F4"/>
                    <stop offset="1" stop-color="#9B72CB"/>
                  </linearGradient>
                  <linearGradient id="cp-gemini-grad-b" x1="15" y1="14" x2="23" y2="21" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#1BA1E3"/>
                    <stop offset="1" stop-color="#4285F4"/>
                  </linearGradient>
                  <linearGradient id="cp-gemini-grad-c" x1="3" y1="15" x2="9" y2="21" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#9B72CB"/>
                    <stop offset="1" stop-color="#D96570"/>
                  </linearGradient>
                </defs>
              </svg>
            </span>
            <span class="cp-contract-gemini-btn__text">Generate contract with Gemini</span>
          </button>
          @include('admin.partials.ai_instruction_edit_link', [
            'instructionKey' => \App\Models\AiInstruction::KEY_CONTRACT,
            'instructionLabel' => 'Edit contract generation prompt',
          ])
          <span class="admin-ai-text-history-mount" data-history-mount data-history-key="contract-{{ $project->id }}"></span>
          </div>
          <span id="cp-contract-gemini-status" class="cp-contract-gemini-status" aria-live="polite"></span>
        </div>
      </div>
      <div class="cp-modal-footer">
        <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Close</button>
      </div>
    </div>
  </div>

  @include('admin.partials.ai_text_history_assets')

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

      var geminiBtn = document.getElementById('cp-contract-gemini-draft');
      var geminiStatus = document.getElementById('cp-contract-gemini-status');
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';

      function setGeminiStatus(msg, isError) {
        if (!geminiStatus) return;
        geminiStatus.textContent = msg || '';
        geminiStatus.classList.toggle('is-error', !!isError);
      }

      function editorHasContent() {
        if (typeof tinymce !== 'undefined') {
          var ed = tinymce.get('cp-contract-editor');
          if (ed) {
            var html = (ed.getContent({ format: 'text' }) || '').trim();
            return html.length> 0;
          }
        }
        var raw = document.getElementById('cp-contract-editor');
        return raw && (raw.value || '').trim().length> 0;
      }

      function getEditorHtml() {
        if (typeof tinymce !== 'undefined') {
          var ed = tinymce.get('cp-contract-editor');
          if (ed) {
            return ed.getContent() || '';
          }
        }
        var raw = document.getElementById('cp-contract-editor');
        return raw ? (raw.value || '') : '';
      }

      function applyDraftToEditor(html) {
        if (typeof tinymce !== 'undefined') {
          var ed = tinymce.get('cp-contract-editor');
          if (ed) {
            ed.setContent(html || '');
            ed.save();
            return;
          }
        }
        var raw = document.getElementById('cp-contract-editor');
        if (raw) {
          raw.value = html || '';
        }
      }

      var contractHistoryMount = document.querySelector('.cp-contract-gemini-wrap [data-history-mount]');
      var contractHistory = null;
      if (contractHistoryMount && window.AdminAiTextHistory) {
        contractHistory = window.AdminAiTextHistory.create({
          storageKey: 'contract-ai:' + (contractHistoryMount.getAttribute('data-history-key') || 'default'),
          mountEl: contractHistoryMount,
          title: 'Contract draft versions',
          getText: getEditorHtml,
          setText: applyDraftToEditor,
          formatPreview: function (html) {
            var text = String(html || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            return window.AdminAiTextHistory.defaultPreview(text);
          },
        });
      }

      if (geminiBtn && ta) {
        geminiBtn.addEventListener('click', function () {
          var url = geminiBtn.getAttribute('data-ai-url');
          var context = ta.value || '';
          if (!url || !context.trim()) {
            window.alert('Project details are empty. Fill in the project and quotations first.');
            return;
          }
          if (editorHasContent()) {
            if (!window.confirm('Replace the current contract editor content with the AI draft?')) {
              return;
            }
          }

          if (contractHistory && editorHasContent()) {
            contractHistory.captureOriginalIfNeeded();
          }

          geminiBtn.disabled = true;
          setGeminiStatus('Generating with Gemini…', false);

          fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ context: context }),
          }).then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                throw new Error((body && body.error) ? body.error : ('HTTP ' + res.status));
              }
              return body;
            });
          }).then(function (body) {
            var html = body.html || '';
            if (contractHistory && html) {
              contractHistory.addVersion(html);
            }
            applyDraftToEditor(html);
            closeModal();
            setGeminiStatus('Draft inserted. Use the history icon to restore earlier versions.', false);
            window.setTimeout(function () { setGeminiStatus('', false); }, 4000);
          }).catch(function (err) {
            setGeminiStatus(err && err.message ? err.message : 'Could not generate contract.', true);
          }).finally(function () {
            geminiBtn.disabled = false;
          });
        });
      }

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
