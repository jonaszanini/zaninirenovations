@extends('admin.layouts.app')

@section('heading', 'Modify image')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Upload one or more photos (from your PC or Google Photos) and describe what to change. The same prompt is applied to every selected photo. Results are saved at full resolution (no WebP).</p>
    <a href="{{ route('admin.image-creation.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> AI Image
    </a>
  </div>

  <div class="admin-card nb2-prompt-card">
    <h2 class="nb2-card-title">Modify image</h2>
    <p class="admin-form-help nb2-card-lede">JPEG, PNG, or WebP up to {{ (int) round($inlineMaxMb * 4) }}&nbsp;MB each. Files up to {{ $inlineMaxMb }}&nbsp;MB are sent inline to Gemini; larger uploads use the File API automatically.</p>

    @include('admin.partials.local_and_google_photos', [
      'fileInputId' => 'aim-source-images',
      'fileInputName' => 'source_images[]',
      'fileLabel' => 'Source photos',
      'fileHint' => 'Select one or more photos from your computer. The same edit prompt below will be applied to each photo you include (uploads and checked Google Photos).',
      'maxTotal' => $maxSources,
      'statusMode' => 'total',
      'formSync' => false,
      'googleFootText' => 'Combined total cannot exceed '.$maxSources.' (file uploads + picked Google photos).',
      'showPreviewGrid' => true,
      'previewGridId' => 'aim-local-preview-grid',
      'fileErrors' => ['source_images', 'source_images.*'],
    ])

    <div class="admin-form-group aim-prompt-group">
      <label for="aim-prompt">Edit instructions</label>
      <textarea id="aim-prompt" rows="5" maxlength="{{ (int) $promptMaxLength }}" class="admin-input" placeholder="Describe what to change, e.g. “Change the background to a modern backyard patio at sunset.”"></textarea>
    </div>

    <div class="nb2-generate-row">
      <button type="button" id="aim-modify-btn" class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
        <span>Apply changes</span>
      </button>
      <div class="admin-ai-action-group">
        <button type="button" id="aim-improve-btn" class="admin-btn admin-btn--ghost admin-btn--lg">
          <i class="fa fa-magic" aria-hidden="true"></i>
          <span>Improve prompt</span>
        </button>
        @include('admin.partials.ai_instruction_edit_link', [
          'instructionKey' => \App\Models\AiInstruction::KEY_IMAGE_MODIFY_PROMPT,
          'instructionLabel' => 'Edit image modify prompt improvement',
        ])
        <span class="admin-ai-text-history-mount" data-history-mount></span>
      </div>
      <div id="aim-loading" class="nb2-loading" hidden aria-live="polite">
        <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
        <span id="aim-loading-text">Editing with Gemini…</span>
      </div>
    </div>

    <p id="aim-status" class="nb2-status" role="status"></p>
  </div>

  <section class="nb2-gallery-section" aria-labelledby="aim-gallery-heading">
    <div class="nb2-gallery-header">
      <h2 id="aim-gallery-heading" class="nb2-section-title">Modified images</h2>
      <p class="nb2-gallery-count" id="aim-gallery-count">{{ $images->total() }} {{ \Illuminate\Support\Str::plural('image', $images->total()) }}</p>
    </div>

    @if ($images->isEmpty())
      <div class="admin-table-wrap" id="aim-gallery-empty">
        <div class="admin-empty">
          <p>No edited images yet. Choose source photos, describe the changes, and click Apply changes.</p>
        </div>
      </div>
    @endif

    <div class="portfolio-admin-grid nb2-gallery-grid" id="aim-gallery-grid" @if($images->isEmpty()) hidden @endif>
      @foreach ($images as $image)
        @include('admin.image_creation._modify_gallery_item', ['image' => $image])
      @endforeach
    </div>

    @if ($images->hasPages())
      <div class="admin-pagination" id="aim-gallery-pagination">
        {{ $images->links('pagination.admin') }}
      </div>
    @endif
  </section>

  <template id="aim-gallery-card-template">
    <article class="nb2-gallery-card" data-aim-card data-image-id="">
      <a href="#" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open edited image">
        <img src="" alt="" loading="lazy" decoding="async">
      </a>
      <div class="nb2-gallery-card__body">
        <p class="nb2-gallery-card__prompt"></p>
        <p class="nb2-gallery-card__meta"></p>
        <div class="nb2-gallery-card__actions">
          <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm aim-download-original" download hidden>
            <i class="fa fa-download"></i> Edited
          </a>
          <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm aim-download-source" download hidden>
            <i class="fa fa-file-image-o"></i> Source
          </a>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-aim-reuse-prompt data-prompt="">
            <i class="fa fa-refresh"></i> Reuse prompt
          </button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-aim-delete data-delete-url="">
            <i class="fa fa-trash"></i> Delete
          </button>
        </div>
      </div>
    </article>
  </template>
@endsection

@include('admin.partials.ai_text_history_assets')

@push('scripts')
  <script>
    (function () {
      var modifyBtn = document.getElementById('aim-modify-btn');
      var improveBtn = document.getElementById('aim-improve-btn');
      var fileInput = document.getElementById('aim-source-images');
      var googleRoot = document.querySelector('.admin-google-photos');
      var promptEl = document.getElementById('aim-prompt');
      var loadingEl = document.getElementById('aim-loading');
      var loadingTextEl = document.getElementById('aim-loading-text');
      var statusEl = document.getElementById('aim-status');
      var gridEl = document.getElementById('aim-gallery-grid');
      var emptyEl = document.getElementById('aim-gallery-empty');
      var countEl = document.getElementById('aim-gallery-count');
      var cardTemplate = document.getElementById('aim-gallery-card-template');
      var modifyUrl = @json(route('admin.image-creation.modify.store'));
      var improveUrl = @json(route('admin.image-creation.improve-prompt'));
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
      var galleryCount = {{ (int) $images->total() }};
      var maxSources = {{ (int) $maxSources }};
      var promptHistoryMount = document.querySelector('.admin-ai-action-group [data-history-mount]');
      var promptHistory = null;
      if (promptHistoryMount && window.AdminAiTextHistory && promptEl) {
        promptHistory = window.AdminAiTextHistory.create({
          storageKey: 'image-modify-prompt',
          mountEl: promptHistoryMount,
          title: 'Edit prompt versions',
          getText: function () { return promptEl.value; },
          setText: function (text) {
            promptEl.value = text;
            promptEl.focus();
          },
        });
      }

      function setStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.classList.toggle('is-error', !!isError);
      }

      function errorFromBody(body, status) {
        if (body && body.error) return body.error;
        if (body && body.errors) {
          var keys = Object.keys(body.errors);
          if (keys.length && body.errors[keys[0]] && body.errors[keys[0]][0]) {
            return body.errors[keys[0]][0];
          }
        }
        if (body && body.message) return body.message;
        if (status === 504 || status === 502) {
          return 'The server timed out while editing. Each photo is processed separately; if this persists, try again or use fewer photos.';
        }
        return 'HTTP ' + status;
      }

      function fetchJsonPost(url, formData) {
        return fetch(url, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
          },
          body: formData,
        }).then(function (res) {
          return res.text().then(function (text) {
            var body = null;
            if (text) {
              try {
                body = JSON.parse(text);
              } catch (ignore) {
                if (!res.ok) {
                  throw new Error(errorFromBody(null, res.status));
                }
                throw new Error('Server returned an unexpected response (HTTP ' + res.status + ').');
              }
            }
            if (!res.ok) {
              throw new Error(errorFromBody(body, res.status));
            }
            return body;
          });
        });
      }

      function googleJobLabel(payload, index) {
        try {
          var data = JSON.parse(payload);
          if (data && data.filename) return data.filename;
        } catch (ignore) {}
        return 'Google photo ' + (index + 1);
      }

      function buildModifyJobs(prompt) {
        var jobs = [];
        if (fileInput && fileInput.files) {
          Array.prototype.forEach.call(fileInput.files, function (file, index) {
            jobs.push({
              label: file.name || ('Upload ' + (index + 1)),
              buildFormData: function () {
                var fd = new FormData();
                fd.append('prompt', prompt);
                fd.append('source_images[]', file);
                return fd;
              },
            });
          });
        }
        googlePayloads().forEach(function (payload, index) {
          jobs.push({
            label: googleJobLabel(payload, index),
            buildFormData: function () {
              var fd = new FormData();
              fd.append('prompt', prompt);
              fd.append('google_picker_items[]', payload);
              return fd;
            },
          });
        });
        return jobs;
      }

      function setLoading(active, message) {
        if (loadingEl) loadingEl.hidden = !active;
        if (loadingTextEl && message) loadingTextEl.textContent = message;
        if (modifyBtn) modifyBtn.disabled = !!active;
        if (improveBtn) improveBtn.disabled = !!active;
      }

      function updateGalleryCount() {
        if (!countEl) return;
        var label = galleryCount === 1 ? 'image' : 'images';
        countEl.textContent = galleryCount + ' ' + label;
      }

      function truncatePrompt(text, limit) {
        if (!text || text.length <= limit) return text || '';
        return text.slice(0, limit - 1) + '…';
      }

      function uploadCount() {
        return fileInput && fileInput.files ? fileInput.files.length : 0;
      }

      function googleCheckedCount() {
        if (!googleRoot || !googleRoot.googlePhotosPicker) return 0;
        return googleRoot.googlePhotosPicker.getCheckedCount();
      }

      function googlePayloads() {
        if (!googleRoot || !googleRoot.googlePhotosPicker) return [];
        return googleRoot.googlePhotosPicker.getCheckedPayloads();
      }

      function totalSourceCount() {
        return uploadCount() + googleCheckedCount();
      }

      function buildCardFromTemplate(data) {
        if (!cardTemplate || !gridEl) return null;
        var fragment = cardTemplate.content.cloneNode(true);
        var card = fragment.querySelector('[data-aim-card]');
        if (!card) return null;

        card.setAttribute('data-image-id', String(data.id));
        var mediaLink = card.querySelector('.nb2-gallery-card__media');
        var img = card.querySelector('img');
        var promptNode = card.querySelector('.nb2-gallery-card__prompt');
        var metaNode = card.querySelector('.nb2-gallery-card__meta');
        var downloadOriginal = card.querySelector('.aim-download-original');
        var downloadSource = card.querySelector('.aim-download-source');
        var reuseBtn = card.querySelector('[data-aim-reuse-prompt]');
        var deleteBtn = card.querySelector('[data-aim-delete]');

        if (mediaLink) mediaLink.href = data.image_url;
        if (img) img.src = data.image_url;
        if (promptNode) {
          promptNode.textContent = truncatePrompt(data.prompt, 120);
          promptNode.title = data.prompt;
        }
        if (metaNode) metaNode.textContent = data.meta || data.created_at || '';
        if (downloadOriginal) {
          if (data.download_original_url) {
            downloadOriginal.href = data.download_original_url;
            downloadOriginal.hidden = false;
          } else {
            downloadOriginal.hidden = true;
          }
        }
        if (downloadSource) {
          if (data.download_source_url) {
            downloadSource.href = data.download_source_url;
            downloadSource.hidden = false;
          } else {
            downloadSource.hidden = true;
          }
        }
        if (reuseBtn) reuseBtn.setAttribute('data-prompt', data.prompt || '');
        if (deleteBtn) deleteBtn.setAttribute('data-delete-url', data.delete_url);

        return card;
      }

      function prependGalleryCard(data) {
        if (!gridEl) return;
        var card = buildCardFromTemplate(data);
        if (!card) return;

        if (emptyEl) emptyEl.hidden = true;
        gridEl.hidden = false;
        gridEl.insertBefore(card, gridEl.firstChild);
        galleryCount += 1;
        updateGalleryCount();
      }

      function bindGalleryActions(root) {
        if (!root) return;

        root.querySelectorAll('[data-aim-reuse-prompt]').forEach(function (btn) {
          if (btn.getAttribute('data-aim-bound') === '1') return;
          btn.setAttribute('data-aim-bound', '1');
          btn.addEventListener('click', function () {
            if (promptEl) {
              promptEl.value = btn.getAttribute('data-prompt') || '';
              promptEl.focus();
            }
            setStatus('Edit prompt loaded. Choose source photos if needed, then apply changes.', false);
          });
        });

        root.querySelectorAll('[data-aim-delete]').forEach(function (btn) {
          if (btn.getAttribute('data-aim-bound') === '1') return;
          btn.setAttribute('data-aim-bound', '1');
          btn.addEventListener('click', function () {
            var deleteUrl = btn.getAttribute('data-delete-url');
            if (!deleteUrl) return;
            if (!window.confirm('Delete this image from storage and history?')) return;

            btn.disabled = true;
            fetch(deleteUrl, {
              method: 'DELETE',
              credentials: 'same-origin',
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
              },
            }).then(function (res) {
              return res.json().then(function (body) {
                if (!res.ok) {
                  throw new Error((body && body.error) ? body.error : ('HTTP ' + res.status));
                }
                return body;
              });
            }).then(function () {
              var card = btn.closest('[data-aim-card]');
              if (card) card.remove();
              galleryCount = Math.max(0, galleryCount - 1);
              updateGalleryCount();
              if (galleryCount === 0) {
                if (emptyEl) emptyEl.hidden = false;
                if (gridEl) gridEl.hidden = true;
              }
              setStatus('Image deleted.', false);
            }).catch(function (err) {
              btn.disabled = false;
              setStatus(err.message || 'Could not delete image.', true);
            });
          });
        });
      }

      if (improveBtn) {
        improveBtn.addEventListener('click', function () {
          var prompt = promptEl ? promptEl.value.trim() : '';
          if (!prompt) {
            setStatus('Enter edit instructions first, then click Improve prompt.', true);
            if (promptEl) promptEl.focus();
            return;
          }

          if (promptHistory) {
            promptHistory.captureOriginalIfNeeded();
          }

          setLoading(true, 'Improving prompt with Gemini…');
          setStatus('', false);

          fetch(improveUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ prompt: prompt }),
          }).then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                throw new Error(errorFromBody(body, res.status));
              }
              return body;
            });
          }).then(function (body) {
            if (promptEl && body.prompt) {
              if (promptHistory) {
                promptHistory.addVersion(body.prompt);
              }
              promptEl.value = body.prompt;
              promptEl.focus();
            }
            var note = 'Prompt improved. Use the history icon to restore earlier versions.';
            if (body.why_better) {
              var why = body.why_better;
              if (why.length> 240) why = why.slice(0, 239) + '…';
              note += ' ' + why;
            }
            setStatus(note, false);
          }).catch(function (err) {
            setStatus(err.message || 'Could not improve prompt.', true);
          }).finally(function () {
            setLoading(false, 'Editing with Gemini…');
          });
        });
      }

      if (modifyBtn) {
        modifyBtn.addEventListener('click', async function () {
          var prompt = promptEl ? promptEl.value.trim() : '';
          var total = totalSourceCount();

          if (total < 1) {
            setStatus('Choose at least one source photo (upload or Google Photos).', true);
            if (fileInput) fileInput.click();
            return;
          }
          if (total> maxSources) {
            setStatus('Too many photos selected (max ' + maxSources + ').', true);
            return;
          }
          if (!prompt) {
            setStatus('Describe what you want changed in the photo(s).', true);
            if (promptEl) promptEl.focus();
            return;
          }

          var jobs = buildModifyJobs(prompt);
          var failures = [];
          var successCount = 0;

          setLoading(true, 'Editing photo 1 of ' + jobs.length + '…');
          setStatus('Processing ' + jobs.length + ' photo' + (jobs.length === 1 ? '' : 's') + ' one at a time…', false);

          for (var i = 0; i < jobs.length; i++) {
            var job = jobs[i];
            var progress = 'Editing photo ' + (i + 1) + ' of ' + jobs.length + '…';
            setLoading(true, progress);
            setStatus(progress, false);

            try {
              var body = await fetchJsonPost(modifyUrl, job.buildFormData());
              (body.images || []).forEach(function (item) {
                prependGalleryCard(item);
                successCount += 1;
              });
              bindGalleryActions(gridEl);
              (body.failures || []).forEach(function (f) {
                failures.push(f);
              });
            } catch (err) {
              failures.push({
                label: job.label,
                error: err && err.message ? err.message : 'Image edit failed.',
              });
            }
          }

          var msg;
          if (successCount === 0 && failures.length) {
            msg = failures.map(function (f) {
              return (f.label || 'Photo') + ': ' + (f.error || 'failed');
            }).join('; ');
            setStatus(msg, true);
          } else if (failures.length) {
            msg = successCount + ' edited, ' + failures.length + ' failed. ';
            msg += failures.map(function (f) {
              return (f.label || 'Photo') + ': ' + (f.error || 'failed');
            }).join('; ');
            setStatus(msg, true);
          } else {
            msg = successCount === 1 ? '1 image edited and saved.' : successCount + ' images edited and saved.';
            setStatus(msg, false);
          }

          setLoading(false, 'Editing with Gemini…');
        });
      }

      bindGalleryActions(document);
    })();
  </script>
@endpush
