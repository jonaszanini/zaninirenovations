@php
  $photos = $project->photos ?? collect();
  $galleryId = $galleryId ?? 'cp-project-photo-gallery';
  $editable = (bool) ($editable ?? false);
@endphp

@if ($photos->isNotEmpty())
  <section class="cp-project-photos" aria-label="Project photos">
    @if ($showHeading ?? true)
      <h3 class="cp-project-photos-heading">Photos</h3>
    @endif
    <div class="cp-project-photos-grid" id="{{ $galleryId }}-grid">
      @foreach ($photos as $index => $photo)
        <figure class="cp-project-photos-item">
          <button type="button"
                  class="cp-project-photos-thumb"
                  data-cp-photo-index="{{ $index }}"
                  aria-label="View photo {{ $index + 1 }} of {{ $photos->count() }}">
            <img src="{{ $photo->url() }}" alt="Project photo {{ $index + 1 }}" loading="lazy" width="160" height="120">
          </button>
          @if ($editable)
            <form action="{{ route('admin.client-projects.photos.destroy', [$project, $photo]) }}"
                  method="post"
                  class="cp-project-photos-delete"
                  onsubmit="return confirm('Remove this photo?');">
              @csrf
              @method('DELETE')
              <button type="submit" class="admin-btn admin-btn--ghost admin-btn--xs" title="Remove photo">
                <i class="fa fa-trash" aria-hidden="true"></i>
              </button>
            </form>
          @endif
        </figure>
      @endforeach
    </div>
  </section>

  <div class="cp-photo-lightbox" id="{{ $galleryId }}-lightbox" hidden role="dialog" aria-modal="true" aria-label="Photo viewer">
    <button type="button" class="cp-photo-lightbox-backdrop" data-cp-lightbox-close aria-label="Close"></button>
    <div class="cp-photo-lightbox-inner">
      <button type="button" class="cp-photo-lightbox-close" data-cp-lightbox-close aria-label="Close">
        <i class="fa fa-times" aria-hidden="true"></i>
      </button>
      <button type="button" class="cp-photo-lightbox-nav cp-photo-lightbox-nav--prev" data-cp-lightbox-prev aria-label="Previous photo">
        <i class="fa fa-chevron-left" aria-hidden="true"></i>
      </button>
      <figure class="cp-photo-lightbox-figure">
        <img src="" alt="" class="cp-photo-lightbox-img" data-cp-lightbox-img>
        <figcaption class="cp-photo-lightbox-caption" data-cp-lightbox-caption></figcaption>
      </figure>
      <button type="button" class="cp-photo-lightbox-nav cp-photo-lightbox-nav--next" data-cp-lightbox-next aria-label="Next photo">
        <i class="fa fa-chevron-right" aria-hidden="true"></i>
      </button>
    </div>
  </div>

  @push('scripts')
    <script>
      (function () {
        var galleryId = @json($galleryId);
        var urls = @json($photos->map(fn ($p) => $p->url())->values());
        if (!urls.length) return;

        var lightbox = document.getElementById(galleryId + '-lightbox');
        var grid = document.getElementById(galleryId + '-grid');
        if (!lightbox || !grid) return;

        var imgEl = lightbox.querySelector('[data-cp-lightbox-img]');
        var captionEl = lightbox.querySelector('[data-cp-lightbox-caption]');
        var prevBtn = lightbox.querySelector('[data-cp-lightbox-prev]');
        var nextBtn = lightbox.querySelector('[data-cp-lightbox-next]');
        var current = 0;

        function render() {
          if (!imgEl) return;
          imgEl.src = urls[current];
          imgEl.alt = 'Project photo ' + (current + 1) + ' of ' + urls.length;
          if (captionEl) {
            captionEl.textContent = (current + 1) + ' / ' + urls.length;
          }
          if (prevBtn) prevBtn.disabled = current <= 0;
          if (nextBtn) nextBtn.disabled = current >= urls.length - 1;
        }

        function openAt(index) {
          current = Math.max(0, Math.min(index, urls.length - 1));
          render();
          lightbox.hidden = false;
          document.body.classList.add('cp-modal-open');
        }

        function close() {
          lightbox.hidden = true;
          document.body.classList.remove('cp-modal-open');
          if (imgEl) imgEl.src = '';
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
            if (current > 0) {
              current -= 1;
              render();
            }
          });
        }

        if (nextBtn) {
          nextBtn.addEventListener('click', function () {
            if (current < urls.length - 1) {
              current += 1;
              render();
            }
          });
        }

        document.addEventListener('keydown', function (e) {
          if (lightbox.hidden) return;
          if (e.key === 'Escape') {
            close();
          } else if (e.key === 'ArrowLeft' && current > 0) {
            current -= 1;
            render();
          } else if (e.key === 'ArrowRight' && current < urls.length - 1) {
            current += 1;
            render();
          }
        });
      })();
    </script>
  @endpush
@endif
