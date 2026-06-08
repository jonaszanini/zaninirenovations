@extends('admin.layouts.app')

@section('heading', 'Modify image')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Upload a photo and describe what to change. Gemini returns a full-resolution edited image — saved as the original format (no WebP conversion).</p>
    <a href="{{ route('admin.image-creation.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> AI Image
    </a>
  </div>

  <div class="admin-card admin-card--wide nb2-prompt-card">
    <h2 class="nb2-card-title">Modify image</h2>
    <p class="admin-form-help nb2-card-lede">JPEG, PNG, or WebP up to {{ (int) round($inlineMaxMb * 4) }}&nbsp;MB. Files up to {{ $inlineMaxMb }}&nbsp;MB are sent inline; larger uploads use the Gemini File API automatically.</p>

    <div class="admin-form-group">
      <label for="aim-source-image">Source photo</label>
      <div class="aim-upload-zone" id="aim-upload-zone">
        <input type="file" id="aim-source-image" class="aim-upload-input" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
        <div class="aim-upload-placeholder" id="aim-upload-placeholder">
          <i class="fa fa-cloud-upload" aria-hidden="true"></i>
          <span>Click or drop a photo here</span>
        </div>
        <div class="aim-upload-preview" id="aim-upload-preview" hidden>
          <img id="aim-preview-img" src="" alt="Source preview">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="aim-clear-image">
            <i class="fa fa-times"></i> Remove
          </button>
        </div>
      </div>
    </div>

    <div class="admin-form-group">
      <label for="aim-prompt">Edit instructions</label>
      <textarea id="aim-prompt" rows="5" maxlength="2000" class="admin-input" placeholder="Describe what to change, e.g. “Change the background to a modern backyard patio at sunset.”"></textarea>
    </div>

    <div class="nb2-generate-row">
      <button type="button" id="aim-modify-btn" class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-pencil-square-o" aria-hidden="true"></i>
        <span>Apply changes</span>
      </button>
      <div id="aim-loading" class="nb2-loading" hidden aria-live="polite">
        <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
        <span>Editing with Gemini…</span>
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
          <p>No edited images yet. Upload a photo, describe the changes, and click Apply changes.</p>
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

@push('scripts')
  <script>
    (function () {
      var modifyBtn = document.getElementById('aim-modify-btn');
      var fileInput = document.getElementById('aim-source-image');
      var promptEl = document.getElementById('aim-prompt');
      var loadingEl = document.getElementById('aim-loading');
      var statusEl = document.getElementById('aim-status');
      var gridEl = document.getElementById('aim-gallery-grid');
      var emptyEl = document.getElementById('aim-gallery-empty');
      var countEl = document.getElementById('aim-gallery-count');
      var cardTemplate = document.getElementById('aim-gallery-card-template');
      var uploadZone = document.getElementById('aim-upload-zone');
      var uploadPlaceholder = document.getElementById('aim-upload-placeholder');
      var uploadPreview = document.getElementById('aim-upload-preview');
      var previewImg = document.getElementById('aim-preview-img');
      var clearImageBtn = document.getElementById('aim-clear-image');
      var modifyUrl = @json(route('admin.image-creation.modify.store'));
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
      var galleryCount = {{ (int) $images->total() }};

      function setStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.classList.toggle('is-error', !!isError);
      }

      function setLoading(active) {
        if (loadingEl) loadingEl.hidden = !active;
        if (modifyBtn) modifyBtn.disabled = !!active;
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

      function showPreview(file) {
        if (!file || !previewImg || !uploadPreview || !uploadPlaceholder) return;
        var reader = new FileReader();
        reader.onload = function (e) {
          previewImg.src = e.target.result;
          uploadPlaceholder.hidden = true;
          uploadPreview.hidden = false;
        };
        reader.readAsDataURL(file);
      }

      function clearPreview() {
        if (fileInput) fileInput.value = '';
        if (previewImg) previewImg.removeAttribute('src');
        if (uploadPreview) uploadPreview.hidden = true;
        if (uploadPlaceholder) uploadPlaceholder.hidden = false;
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
            setStatus('Edit prompt loaded. Upload a source photo if needed, then apply changes.', false);
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

      if (fileInput) {
        fileInput.addEventListener('change', function () {
          if (fileInput.files && fileInput.files[0]) {
            showPreview(fileInput.files[0]);
          }
        });
      }

      if (clearImageBtn) {
        clearImageBtn.addEventListener('click', clearPreview);
      }

      if (uploadZone && fileInput) {
        uploadZone.addEventListener('dragover', function (e) {
          e.preventDefault();
          uploadZone.classList.add('is-dragover');
        });
        uploadZone.addEventListener('dragleave', function () {
          uploadZone.classList.remove('is-dragover');
        });
        uploadZone.addEventListener('drop', function (e) {
          e.preventDefault();
          uploadZone.classList.remove('is-dragover');
          if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]) {
            fileInput.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
          }
        });
        uploadZone.addEventListener('click', function (e) {
          if (e.target === clearImageBtn || (clearImageBtn && clearImageBtn.contains(e.target))) return;
          fileInput.click();
        });
      }

      if (modifyBtn) {
        modifyBtn.addEventListener('click', function () {
          var prompt = promptEl ? promptEl.value.trim() : '';
          if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            setStatus('Choose a source photo first.', true);
            if (fileInput) fileInput.click();
            return;
          }
          if (!prompt) {
            setStatus('Describe what you want changed in the photo.', true);
            if (promptEl) promptEl.focus();
            return;
          }

          var formData = new FormData();
          formData.append('prompt', prompt);
          formData.append('source_image', fileInput.files[0]);

          setLoading(true);
          setStatus('', false);

          fetch(modifyUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          }).then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                throw new Error((body && body.error) ? body.error : ('HTTP ' + res.status));
              }
              return body;
            });
          }).then(function (body) {
            prependGalleryCard(body);
            bindGalleryActions(gridEl);
            setStatus('Image edited and saved to history.', false);
          }).catch(function (err) {
            setStatus(err.message || 'Image edit failed.', true);
          }).finally(function () {
            setLoading(false);
          });
        });
      }

      bindGalleryActions(document);
    })();
  </script>
@endpush
