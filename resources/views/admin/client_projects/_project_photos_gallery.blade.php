@php
  $photos = $project->photos ?? collect();
  $galleryId = $galleryId ?? 'cp-project-photo-gallery';
  $editable = (bool) ($editable ?? false);
  $mediaItems = $photos->map(fn ($p) => [
    'url' => $p->url(),
    'type' => $p->mediaType(),
    'name' => $p->displayName(),
  ])->values();
@endphp

@if ($photos->isNotEmpty())
  <section class="cp-project-photos" aria-label="Project photos and files">
    @if ($showHeading ?? true)
      <h3 class="cp-project-photos-heading">Photos &amp; files</h3>
    @endif
    <div class="cp-project-photos-grid" id="{{ $galleryId }}-grid">
      @foreach ($photos as $index => $photo)
        <figure class="cp-project-photos-item">
          <button type="button"
                  class="cp-project-photos-thumb cp-project-photos-thumb--{{ $photo->mediaType() }}"
                  data-cp-photo-index="{{ $index }}"
                  aria-label="Open {{ $photo->displayName() }} ({{ $index + 1 }} of {{ $photos->count() }})">
            @if ($photo->isImage())
              <img src="{{ $photo->url() }}" alt="{{ $photo->displayName() }}" loading="lazy" width="160" height="120">
            @elseif ($photo->isVideo())
              <span class="cp-project-photos-thumb-media">
                <i class="fa fa-play-circle" aria-hidden="true"></i>
                <span class="cp-project-photos-thumb-label">Video</span>
              </span>
            @else
              <span class="cp-project-photos-thumb-media cp-project-photos-thumb-media--pdf">
                <i class="fa fa-file-pdf-o" aria-hidden="true"></i>
                <span class="cp-project-photos-thumb-label">PDF</span>
              </span>
            @endif
          </button>
          @if ($editable)
            <form action="{{ route('admin.client-projects.photos.destroy', [$project, $photo]) }}"
                  method="post"
                  class="cp-project-photos-delete"
                  onsubmit="return confirm('Remove this file?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--ghost admin-btn--xs" title="Remove file">
                <i class="fa fa-trash" aria-hidden="true"></i>
              </button>
            </form>
          @endif
        </figure>
      @endforeach
    </div>
  </section>

  <div class="cp-photo-lightbox" id="{{ $galleryId }}-lightbox" hidden role="dialog" aria-modal="true" aria-label="File viewer">
    <button type="button" class="cp-photo-lightbox-backdrop" data-cp-lightbox-close aria-label="Close"></button>
    <div class="cp-photo-lightbox-inner">
      <button type="button" class="cp-photo-lightbox-close" data-cp-lightbox-close aria-label="Close">
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
      <button type="button" class="cp-photo-lightbox-nav cp-photo-lightbox-nav--prev" data-cp-lightbox-prev aria-label="Previous">
        <i class="fa fa-chevron-left" aria-hidden="true"></i>
      </button>
      <figure class="cp-photo-lightbox-figure">
        <img src="" alt="" class="cp-photo-lightbox-img" data-cp-lightbox-img hidden>
        <video class="cp-photo-lightbox-video" data-cp-lightbox-video controls playsinline hidden></video>
        <div class="cp-photo-lightbox-pdf" data-cp-lightbox-pdf hidden>
          <p class="cp-photo-lightbox-pdf-name" data-cp-lightbox-pdf-name></p>
          <a href="#" class="admin-btn admin-btn--primary" data-cp-lightbox-pdf-link target="_blank" rel="noopener">
            <i class="fa fa-external-link" aria-hidden="true"></i> Open PDF
          </a>
        </div>
        <figcaption class="cp-photo-lightbox-caption" data-cp-lightbox-caption></figcaption>
      </figure>
      <button type="button" class="cp-photo-lightbox-nav cp-photo-lightbox-nav--next" data-cp-lightbox-next aria-label="Next">
        <i class="fa fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  @push('scripts')
    <script>
      (function () {
        var galleryId = @json($galleryId);
        var items = @json($mediaItems);
        if (!items.length) return;

        var lightbox = document.getElementById(galleryId + '-lightbox');
        var grid = document.getElementById(galleryId + '-grid');
        if (!lightbox || !grid) return;

        if (lightbox.parentElement !== document.body) {
          document.body.appendChild(lightbox);
        }

        var imgEl = lightbox.querySelector('[data-cp-lightbox-img]');
        var videoEl = lightbox.querySelector('[data-cp-lightbox-video]');
        var pdfWrap = lightbox.querySelector('[data-cp-lightbox-pdf]');
        var pdfName = lightbox.querySelector('[data-cp-lightbox-pdf-name]');
        var pdfLink = lightbox.querySelector('[data-cp-lightbox-pdf-link]');
        var captionEl = lightbox.querySelector('[data-cp-lightbox-caption]');
        var prevBtn = lightbox.querySelector('[data-cp-lightbox-prev]');
        var nextBtn = lightbox.querySelector('[data-cp-lightbox-next]');
        var current = 0;

        function hideAllMedia() {
          if (imgEl) { imgEl.hidden = true; imgEl.removeAttribute('src'); }
          if (videoEl) {
            videoEl.hidden = true;
            videoEl.pause();
            videoEl.removeAttribute('src');
            videoEl.load();
          }
          if (pdfWrap) pdfWrap.hidden = true;
        }

        function render() {
          var item = items[current];
          if (!item) return;
          hideAllMedia();

          if (item.type === 'video' && videoEl) {
            videoEl.src = item.url;
            videoEl.hidden = false;
          } else if (item.type === 'pdf' && pdfWrap && pdfLink) {
            pdfWrap.hidden = false;
            if (pdfName) pdfName.textContent = item.name || 'PDF document';
            pdfLink.href = item.url;
          } else if (imgEl) {
            imgEl.src = item.url;
            imgEl.alt = item.name || ('File ' + (current + 1));
            imgEl.hidden = false;
          }

          if (captionEl) {
            captionEl.textContent = (item.name ? item.name + ' — ' : '') + (current + 1) + ' / ' + items.length;
          }
          if (prevBtn) prevBtn.disabled = current <= 0;
          if (nextBtn) nextBtn.disabled = current>= items.length - 1;
        }

        function openAt(index) {
          current = Math.max(0, Math.min(index, items.length - 1));
          render();
          lightbox.hidden = false;
          document.body.classList.add('cp-modal-open');
        }

        function close() {
          lightbox.hidden = true;
          document.body.classList.remove('cp-modal-open');
          hideAllMedia();
        }

        grid.addEventListener('click', function (e) {
          var btn = e.target.closest('[data-cp-photo-index]');
          if (!btn) return;
          var idx = parseInt(btn.getAttribute('data-cp-photo-index'), 10);
          if (isNaN(idx)) return;
          openAt(idx);
        });

        lightbox.querySelectorAll('[data-cp-lightbox-close]').forEach(function (el) {
          el.addEventListener('click', close);
        });

        if (prevBtn) {
          prevBtn.addEventListener('click', function () {
            if (current> 0) {
              current -= 1;
              render();
            }
          });
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', function () {
            if (current < items.length - 1) {
              current += 1;
              render();
            }
          });
        }

        document.addEventListener('keydown', function (e) {
          if (lightbox.hidden) return;
          if (e.key === 'Escape') {
            close();
          } else if (e.key === 'ArrowLeft' && current> 0) {
            current -= 1;
            render();
          } else if (e.key === 'ArrowRight' && current < items.length - 1) {
            current += 1;
            render();
          }
        });
      })();
    </script>
  @endpush
@endif
