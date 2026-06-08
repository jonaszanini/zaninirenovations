@extends('admin.layouts.app')

@section('heading', 'Image creation')

@section('content')
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Generate 16:9 images with Gemini using the same API as blog featured images. Prompts and results are saved below for download, reuse, or deletion.</p>
  </div>

  <div class="admin-card admin-card--wide nb2-prompt-card">
    <h2 class="nb2-card-title">Nano Banana 2</h2>
    <p class="admin-form-help nb2-card-lede">Describe the image you want. Results are stored as WebP at {{ (int) config('webp.quality', 75) }}% quality, about {{ (int) round(config('webp.max_bytes', 102400) / 1024) }}&nbsp;KB.</p>

    <div class="admin-form-group">
      <label for="nb2-prompt">Image prompt</label>
      <textarea id="nb2-prompt" rows="6" maxlength="2000" class="admin-input" placeholder="Describe the scene, style, lighting, and composition you want…"></textarea>
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
      <a href="#" class="nb2-gallery-card__media" target="_blank" rel="noopener" title="Open full image">
        <img src="" alt="" loading="lazy" decoding="async">
      </a>
      <div class="nb2-gallery-card__body">
        <p class="nb2-gallery-card__prompt"></p>
        <p class="nb2-gallery-card__meta"></p>
        <div class="nb2-gallery-card__actions">
          <a href="#" class="admin-btn admin-btn--ghost admin-btn--sm nb2-download-link" download>
            <i class="fa fa-download"></i> Download
          </a>
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-nb2-recreate data-prompt="">
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
      var generateBtn = document.getElementById('nb2-generate-btn');
      var promptEl = document.getElementById('nb2-prompt');
      var loadingEl = document.getElementById('nb2-loading');
      var statusEl = document.getElementById('nb2-status');
      var gridEl = document.getElementById('nb2-gallery-grid');
      var emptyEl = document.getElementById('nb2-gallery-empty');
      var countEl = document.getElementById('nb2-gallery-count');
      var cardTemplate = document.getElementById('nb2-gallery-card-template');
      var generateUrl = @json(route('admin.image-creation.generate'));
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
        var downloadLink = card.querySelector('.nb2-download-link');
        var recreateBtn = card.querySelector('[data-nb2-recreate]');
        var deleteBtn = card.querySelector('[data-nb2-delete]');

        if (mediaLink) mediaLink.href = data.image_url;
        if (img) img.src = data.image_url;
        if (promptNode) {
          promptNode.textContent = truncatePrompt(data.prompt, 120);
          promptNode.title = data.prompt;
        }
        if (metaNode) metaNode.textContent = data.created_at || '';
        if (downloadLink) downloadLink.href = data.download_url;
        if (recreateBtn) recreateBtn.setAttribute('data-prompt', data.prompt);
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
            var prompt = btn.getAttribute('data-prompt') || '';
            if (promptEl) {
              promptEl.value = prompt;
              promptEl.focus();
            }
            setStatus('Prompt loaded. Edit it, then generate again.', false);
          });
        });

        root.querySelectorAll('[data-nb2-delete]').forEach(function (btn) {
          if (btn.getAttribute('data-nb2-bound') === '1') return;
          btn.setAttribute('data-nb2-bound', '1');
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
      }

      if (generateBtn && promptEl) {
        generateBtn.addEventListener('click', function () {
          var prompt = promptEl.value.trim();
          if (!prompt) {
            setStatus('Enter an image prompt first.', true);
            promptEl.focus();
            return;
          }

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
            body: JSON.stringify({ prompt: prompt }),
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

      bindGalleryActions(document);
    })();
  </script>
@endpush
