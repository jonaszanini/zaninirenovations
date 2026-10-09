@extends('admin.layouts.app')

@section('heading')
  Nano Banana 2
  <x-admin.info-tip>Generate images with Gemini (Nano Banana 2). Choose aspect ratio and resolution, then download the full original or an optimized WebP copy from history. The API returns a full-resolution original; a compressed WebP copy is saved for fast gallery preview ({{ (int) config('webp.quality', 75) }}% quality, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB).</x-admin.info-tip>
@endsection

@section('content')
  <div class="admin-toolbar">
    <a href="{{ route('admin.image-creation.index') }}" class="admin-btn admin-btn--ghost">
      <i class="fa fa-arrow-left"></i> All models
    </a>
  </div>

  <div class="admin-card nb2-prompt-card">
    <h2 class="nb2-card-title">
      Nano Banana 2
      <x-admin.info-tip>Describe the image you want. The API returns a full-resolution original; a compressed WebP copy is saved for fast gallery preview ({{ (int) config('webp.quality', 75) }}% quality, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB).</x-admin.info-tip>
    </h2>

    <div class="admin-form-group">
      <label for="nb2-prompt">Image prompt</label>
      <textarea id="nb2-prompt" rows="6" maxlength="2000" class="admin-input" placeholder="Describe the scene, style, lighting, and composition you want…"></textarea>
    </div>

    <div class="admin-form-group">
      <span class="admin-form-label" id="nb2-ar-label">Aspect ratio</span>
      <div class="nb2-ar-picker" role="group" aria-labelledby="nb2-ar-label">
        @foreach ($nb2Options['aspectRatios'] as $aspectRatio)
          @php
            [$arW, $arH] = array_map('intval', explode(':', $aspectRatio, 2));
          @endphp
          <button
            type="button"
            class="nb2-ar-btn"
            data-nb2-ar="{{ $aspectRatio }}"
            data-ar-w="{{ $arW }}"
            data-ar-h="{{ $arH }}"
            aria-pressed="{{ $aspectRatio === $nb2Options['defaults']['aspectRatio'] ? 'true' : 'false' }}">
            <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
            <span class="nb2-ar-btn__label">{{ $aspectRatio }}</span>
          </button>
        @endforeach
      </div>
    </div>

    <div class="admin-form-group">
      <label for="nb2-image-size">Image size</label>
      <select id="nb2-image-size" class="admin-input"></select>
      <p class="admin-form-help" id="nb2-size-help">Pixel dimensions update when you change aspect ratio.</p>
    </div>

    <div class="nb2-generate-row">
      <button type="button" id="nb2-generate-btn" class="admin-btn admin-btn--primary admin-btn--lg">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span>Generate image</span>
      </button>
      <div id="nb2-loading" class="nb2-loading" hidden aria-live="polite">
        <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
        <span>Generating with Gemini…</span>
      </div>
    </div>

    <p id="nb2-status" class="nb2-status" role="status"></p>
  </div>

  <section class="nb2-gallery-section" aria-labelledby="nb2-gallery-heading">
    <div class="nb2-gallery-header">
      <h2 id="nb2-gallery-heading" class="nb2-section-title">Image history</h2>
      <p class="nb2-gallery-count" id="nb2-gallery-count">{{ $images->total() }} {{ \Illuminate\Support\Str::plural('image', $images->total()) }}</p>
    </div>

    @if ($images->isEmpty())
      <div class="admin-table-wrap" id="nb2-gallery-empty">
        <div class="admin-empty">
          <p>No generated images yet. Enter a prompt above and click Generate image.</p>
        </div>
      </div>
    @endif

    <div class="portfolio-admin-grid nb2-gallery-grid" id="nb2-gallery-grid" @if($images->isEmpty()) hidden @endif>
      @foreach ($images as $image)
        @include('admin.image_creation._gallery_item', ['image' => $image])
      @endforeach
    </div>

    @if ($images->hasPages())
      <div class="admin-pagination" id="nb2-gallery-pagination">
        {{ $images->links('pagination.admin') }}
      </div>
    @endif
  </section>

  <template id="nb2-gallery-card-template">
    <article class="nb2-gallery-card" data-nb2-card data-image-id="">
      <a href="#" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open WebP preview">
        <img src="" alt="" loading="lazy" decoding="async">
      </a>
      <div class="nb2-gallery-card__body">
        <p class="nb2-gallery-card__prompt"></p>
        <p class="nb2-gallery-card__meta"></p>
        <div class="nb2-gallery-card__actions">
          <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm nb2-download-original" download hidden>
            <i class="fa fa-download"></i> Original
          </a>
          <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm nb2-download-webp" download hidden>
            <i class="fa fa-file-image-o"></i> WebP
          </a>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-nb2-recreate data-prompt="" data-aspect-ratio="" data-image-size="">
            <i class="fa fa-refresh"></i> Recreate
          </button>
          <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" data-nb2-delete data-delete-url="">
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
      var nb2Options = @json($nb2Options);
      var generateBtn = document.getElementById('nb2-generate-btn');
      var promptEl = document.getElementById('nb2-prompt');
      var sizeSelectEl = document.getElementById('nb2-image-size');
      var sizeHelpEl = document.getElementById('nb2-size-help');
      var loadingEl = document.getElementById('nb2-loading');
      var statusEl = document.getElementById('nb2-status');
      var gridEl = document.getElementById('nb2-gallery-grid');
      var emptyEl = document.getElementById('nb2-gallery-empty');
      var countEl = document.getElementById('nb2-gallery-count');
      var cardTemplate = document.getElementById('nb2-gallery-card-template');
      var arButtons = Array.prototype.slice.call(document.querySelectorAll('[data-nb2-ar]'));
      var generateUrl = @json(route('admin.image-creation.generate'));
      var csrfMeta = document.querySelector('meta[name="csrf-token"]');
      var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
      var galleryCount = {{ (int) $images->total() }};
      var selectedAspectRatio = nb2Options.defaults.aspectRatio;
      var selectedImageSize = nb2Options.defaults.imageSize;

      function setStatus(message, isError) {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.classList.toggle('is-error', !!isError);
      }

      function setLoading(active) {
        if (loadingEl) loadingEl.hidden = !active;
        if (generateBtn) generateBtn.disabled = !!active;
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

      function getSizesForAspect(aspectRatio) {
        return nb2Options.sizesByAspect[aspectRatio] || [];
      }

      function populateSizeDropdown(aspectRatio, preferredSize) {
        if (!sizeSelectEl) return;

        var sizes = getSizesForAspect(aspectRatio);
        var html = '';
        var hasPreferred = false;

        sizes.forEach(function (option) {
          var selected = option.value === preferredSize;
          if (selected) hasPreferred = true;
          html += '<option value="' + option.value + '"' + (selected ? ' selected' : '') + '>' + option.label + '</option>';
        });

        sizeSelectEl.innerHTML = html;

        if (!hasPreferred && sizes.length) {
          sizeSelectEl.value = sizes[0].value;
        }

        selectedImageSize = sizeSelectEl.value;

        var active = sizes.find(function (item) { return item.value === selectedImageSize; });
        if (sizeHelpEl && active) {
          sizeHelpEl.textContent = 'Approximate output: ' + active.width + '×' + active.height + ' pixels.';
        }
      }

      function setAspectRatio(aspectRatio, preferredSize) {
        selectedAspectRatio = aspectRatio;

        arButtons.forEach(function (btn) {
          var isActive = btn.getAttribute('data-nb2-ar') === aspectRatio;
          btn.classList.toggle('is-active', isActive);
          btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        populateSizeDropdown(aspectRatio, preferredSize || selectedImageSize);
      }

      function getSelectedParams() {
        return {
          aspect_ratio: selectedAspectRatio,
          image_size: sizeSelectEl ? sizeSelectEl.value : selectedImageSize,
        };
      }

      function applyRecreateParams(prompt, aspectRatio, imageSize) {
        if (promptEl) {
          promptEl.value = prompt || '';
        }
        setAspectRatio(aspectRatio || nb2Options.defaults.aspectRatio, imageSize || nb2Options.defaults.imageSize);
      }

      function buildCardFromTemplate(data) {
        if (!cardTemplate || !gridEl) return null;
        var fragment = cardTemplate.content.cloneNode(true);
        var card = fragment.querySelector('[data-nb2-card]');
        if (!card) return null;

        card.setAttribute('data-image-id', String(data.id));
        var mediaLink = card.querySelector('.nb2-gallery-card__media');
        var img = card.querySelector('img');
        var promptNode = card.querySelector('.nb2-gallery-card__prompt');
        var metaNode = card.querySelector('.nb2-gallery-card__meta');
        var downloadOriginal = card.querySelector('.nb2-download-original');
        var downloadWebp = card.querySelector('.nb2-download-webp');
        var recreateBtn = card.querySelector('[data-nb2-recreate]');
        var deleteBtn = card.querySelector('[data-nb2-delete]');

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
        if (downloadWebp) {
          if (data.download_webp_url) {
            downloadWebp.href = data.download_webp_url;
            downloadWebp.hidden = false;
          } else {
            downloadWebp.hidden = true;
          }
        }
        if (recreateBtn) {
          recreateBtn.setAttribute('data-prompt', data.prompt || '');
          recreateBtn.setAttribute('data-aspect-ratio', data.aspect_ratio || nb2Options.defaults.aspectRatio);
          recreateBtn.setAttribute('data-image-size', data.image_size || nb2Options.defaults.imageSize);
        }
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

        root.querySelectorAll('[data-nb2-recreate]').forEach(function (btn) {
          if (btn.getAttribute('data-nb2-bound') === '1') return;
          btn.setAttribute('data-nb2-bound', '1');
          btn.addEventListener('click', function () {
            applyRecreateParams(
              btn.getAttribute('data-prompt') || '',
              btn.getAttribute('data-aspect-ratio') || nb2Options.defaults.aspectRatio,
              btn.getAttribute('data-image-size') || nb2Options.defaults.imageSize
            );
            if (promptEl) promptEl.focus();
            setStatus('Prompt and generation settings loaded. Edit if needed, then generate again.', false);
          });
        });

        root.querySelectorAll('[data-nb2-delete]').forEach(function (btn) {
          if (btn.getAttribute('data-nb2-bound') === '1') return;
          btn.setAttribute('data-nb2-bound', '1');
          btn.addEventListener('click', function () {
            var deleteUrl = btn.getAttribute('data-delete-url');
            if (!deleteUrl) return;
            window.AdminDialog.confirm('Delete this image from storage and history?', { title: 'Delete image', danger: true }).then(function (ok) {
              if (!ok) return;

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
              var card = btn.closest('[data-nb2-card]');
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
        });
      }

      arButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          setAspectRatio(btn.getAttribute('data-nb2-ar') || nb2Options.defaults.aspectRatio);
        });
      });

      if (sizeSelectEl) {
        sizeSelectEl.addEventListener('change', function () {
          selectedImageSize = sizeSelectEl.value;
          populateSizeDropdown(selectedAspectRatio, selectedImageSize);
        });
      }

      if (generateBtn && promptEl) {
        generateBtn.addEventListener('click', function () {
          var prompt = promptEl.value.trim();
          if (!prompt) {
            setStatus('Enter an image prompt first.', true);
            promptEl.focus();
            return;
          }

          var params = getSelectedParams();
          setLoading(true);
          setStatus('', false);

          fetch(generateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
              prompt: prompt,
              aspect_ratio: params.aspect_ratio,
              image_size: params.image_size,
            }),
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
            setStatus('Image generated and saved to history.', false);
          }).catch(function (err) {
            setStatus(err.message || 'Image generation failed.', true);
          }).finally(function () {
            setLoading(false);
          });
        });
      }

      setAspectRatio(nb2Options.defaults.aspectRatio, nb2Options.defaults.imageSize);
      bindGalleryActions(document);
    })();
  </script>
@endpush
