@extends('admin.layouts.app')

@section('heading', 'Edit project')

@section('content')
  <div class="cp-edit-project-layout cp-portfolio-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
    <form id="portfolio-edit-form" action="{{ route('admin.portfolio.update', $project) }}" method="post" enctype="multipart/form-data">
      @csrf
      @method('PUT')
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="{{ old('title', $project->title) }}" required maxlength="255">
        @error('title')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required>{{ old('description', $project->description) }}</textarea>
        @include('admin.partials.portfolio_description_ai', ['hasExistingPhotos' => $project->photos->isNotEmpty()])
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', $project->categories->pluck('id')->map(fn ($id) => (string) $id)->all());
      @endphp
      <div class="admin-form-group">
        <div class="admin-form-group__label-row">
          <span class="admin-form-group-label">Categories</span>
          <a href="{{ route('admin.categories.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm" title="Manage portfolio categories">
            <i class="fa fa-cog" aria-hidden="true"></i> Configure
          </a>
        </div>
        <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        <div class="admin-category-checkboxes">
          @foreach ($categories as $cat)
            <label class="admin-checkbox admin-checkbox-tile">
              <input type="checkbox" class="admin-checkbox__input" name="project_category_ids[]" value="{{ $cat->id }}" @checked(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">{{ $cat->name }}</span>
            </label>
          @endforeach
        </div>
        @error('project_category_ids')
          <div class="admin-error">{{ $message }}</div>
        @enderror
        @error('project_category_ids.*')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', optional($project->completed_on)->format('Y-m-d')) }}" required>
        <p class="admin-form-hint">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>

      @if ($project->photos->isNotEmpty())
        <div class="admin-form-group">
          <label>Photos — order &amp; remove</label>
          <p class="admin-form-hint">Drag by the grip icon to reorder. The <strong>first photo</strong> is the homepage project cover. Use rotate to fix orientation (90°) or crop to trim the frame (saved as WebP). Unchecked photos are saved in this order. Check “Remove” to delete from storage.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            @foreach ($project->photos as $photo)
              <li class="admin-photo-sortable-item" data-photo-id="{{ $photo->id }}">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  @if ($loop->first)
                    <span class="admin-photo-cover-badge">Homepage cover</span>
                  @endif
                  <img src="{{ $photo->url() }}" alt="">
                </div>
                <div class="admin-photo-sortable-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-crop-photo"
                    title="Crop photo"
                    data-crop-url="{{ route('admin.portfolio.photos.crop', [$project, $photo]) }}"
                    data-image-url="{{ $photo->url() }}">
                    <i class="fa fa-crop" aria-hidden="true"></i><span class="admin-sr-only"> Crop</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° counter-clockwise"
                    data-post-url="{{ route('admin.portfolio.photos.rotate', [$project, $photo]) }}"
                    data-direction="ccw">
                    <i class="fa fa-undo" aria-hidden="true"></i><span class="admin-sr-only"> Rotate left</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° clockwise"
                    data-post-url="{{ route('admin.portfolio.photos.rotate', [$project, $photo]) }}"
                    data-direction="cw">
                    <i class="fa fa-repeat" aria-hidden="true"></i><span class="admin-sr-only"> Rotate right</span>
                  </button>
                </div>
                <label class="admin-photo-remove-label admin-checkbox admin-checkbox--sm">
                  <input type="checkbox" class="admin-checkbox__input" name="delete_photos[]" value="{{ $photo->id }}">
                  <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  <span class="admin-checkbox__text">Remove</span>
                </label>
              </li>
            @endforeach
          </ul>
          <div id="admin-photo-sequence-fields" aria-hidden="true"></div>
          @error('photo_sequence')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
      @endif

      @php
        $canAdd = 15 - $project->photos->count();
      @endphp
      @if ($canAdd > 0)
        @php
          $portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
        @endphp
        <div class="admin-portfolio-upload-section">
          @include('admin.partials.local_and_google_photos', [
            'fileInputId' => 'photos',
            'fileInputName' => 'photos[]',
            'fileLabel' => 'Add photos',
            'fileHint' => 'WebP, JPEG, or PNG (10 MB max each). New files are converted to WebP at '.$portfolioWebp['quality'].'% quality, scaled to at most '.$portfolioWebp['max_edge_px'].'px, and compressed to about '.(int) round($portfolioWebp['max_bytes'] / 1024).' KB or less; originals are not kept. Up to '.$canAdd.' more (15 total). After you save, use the crop icon on any thumbnail to trim it.',
            'maxTotal' => $canAdd,
            'statusMode' => 'remaining',
            'formSync' => true,
            'googleFootText' => 'You can add up to '.$canAdd.' more photos total (file uploads + picked Google photos).',
            'showPreviewGrid' => true,
            'previewGridId' => 'portfolio-local-preview-grid',
            'fileErrors' => ['photos', 'photos.*'],
          ])

          @include('admin.partials.portfolio_ai_improve', [
            'fileInputId' => 'photos',
            'maxTotal' => $canAdd,
          ])
        </div>
      @else
        <p class="admin-form-hint">Maximum of 15 photos reached. Remove one to add more.</p>
        @if (auth()->user()->googlePhotosToken)
          <div class="admin-google-photos-panel admin-google-photos-panel--compact">
            <div class="admin-google-photos-panel__head">
              <span class="admin-form-group-label">Google Photos</span>
            </div>
            <p class="admin-form-hint admin-google-photos-panel__intro">
              Connected as <strong>{{ auth()->user()->googlePhotosToken->google_email }}</strong>.
              <button
                type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-admin-google-disconnect
                data-disconnect-url="{{ route('admin.google-photos.disconnect') }}"
              >Disconnect</button>
            </p>
          </div>
        @endif
      @endif
    </form>
    </div>

    <aside class="cp-edit-project-actions-aside" role="region" aria-label="Portfolio form actions">
      <button type="submit" form="portfolio-edit-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-check"></i> Save changes
      </button>
      <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost admin-btn--sm">Back to list</a>
    </aside>
  </div>

  @if ($project->photos->isNotEmpty())
    <div id="admin-photo-crop-modal" class="admin-modal" hidden>
      <div class="admin-modal-backdrop" data-crop-modal-dismiss tabindex="-1" aria-hidden="true"></div>
      <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-photo-crop-title">
        <h2 id="admin-photo-crop-title" class="admin-modal-title">Crop photo</h2>
        <p class="admin-modal-hint">Drag the box or corners to adjust the crop. Pick an aspect ratio below, or use <strong>Free</strong> to resize manually. The file on the server is replaced (WebP). Optionally add a <strong>Before</strong> label for before-and-after galleries.</p>
        <div class="admin-crop-stage">
          <img id="admin-photo-crop-img" src="" alt="">
        </div>
        <div class="admin-crop-toolbar">
          <span class="admin-crop-toolbar__label">Aspect ratio</span>
          <div class="admin-crop-ar-picker nb2-ar-picker" role="group" aria-label="Crop aspect ratio">
            <button
              type="button"
              class="nb2-ar-btn admin-crop-ar-btn"
              data-crop-ar="free"
              disabled
              title="Free crop — drag corners freely"
              aria-label="Free aspect ratio"
              aria-pressed="true"
            >
              <span class="nb2-ar-btn__shape admin-crop-ar-free__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">Free</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="1:1" data-ar-w="1" data-ar-h="1" disabled title="Square 1:1" aria-label="Square 1:1" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">1:1</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="4:3" data-ar-w="4" data-ar-h="3" disabled title="Classic 4:3" aria-label="4:3" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">4:3</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="3:2" data-ar-w="3" data-ar-h="2" disabled title="Photo 3:2" aria-label="3:2" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">3:2</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="16:9" data-ar-w="16" data-ar-h="9" disabled title="Widescreen 16:9" aria-label="16:9" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">16:9</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="3:4" data-ar-w="3" data-ar-h="4" disabled title="Portrait 3:4" aria-label="3:4" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">3:4</span>
            </button>
            <button type="button" class="nb2-ar-btn admin-crop-ar-btn" data-crop-ar="9:16" data-ar-w="9" data-ar-h="16" disabled title="Vertical 9:16" aria-label="9:16" aria-pressed="false">
              <span class="nb2-ar-btn__shape" aria-hidden="true"></span>
              <span class="nb2-ar-btn__label">9:16</span>
            </button>
          </div>
        </div>
        <div class="admin-crop-options">
          <label class="admin-checkbox admin-checkbox--sm" for="admin-photo-crop-label-before">
            <input type="checkbox" class="admin-checkbox__input" id="admin-photo-crop-label-before" value="1">
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Add <strong>Before</strong> label (bottom-left badge)</span>
          </label>
        </div>
        <div class="admin-modal-actions">
          <button type="button" class="admin-btn admin-btn--ghost" data-crop-modal-dismiss>Cancel</button>
          <button type="button" class="admin-btn admin-btn--primary" id="admin-photo-crop-apply" disabled>Apply crop</button>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    @if ($canAdd > 0)
    window.portfolioAiImproveConfig = {
      fileInputId: 'photos',
      maxTotal: {{ (int) $canAdd }},
      csrfToken: @json(csrf_token()),
      aiImproveUrl: @json(route('admin.portfolio.ai-improve-photos')),
      saveHint: 'Save changes',
    };
    @endif
    window.portfolioDescriptionAiConfig = {
      descriptionInputId: 'description',
      fileInputId: 'photos',
      csrfToken: @json(csrf_token()),
      improveUrl: @json(route('admin.portfolio.ai-improve-description.edit', $project)),
    };
  </script>
@endpush

@if ($project->photos->isNotEmpty())
  @push('head')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" crossorigin="anonymous">
  @endpush
  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
      (function () {
        var csrf = @json(csrf_token());

        /* Rotate: full form POST (same as normal Laravel) — works even if Sortable CDN fails */
        document.querySelectorAll('.js-rotate-photo').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-post-url');
            var direction = btn.getAttribute('data-direction');
            if (!url || !direction) return;
            if (btn.disabled) return;
            btn.disabled = true;
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = url;
            f.style.display = 'none';
            var token = document.createElement('input');
            token.name = '_token';
            token.value = csrf;
            var dir = document.createElement('input');
            dir.name = 'direction';
            dir.value = direction;
            f.appendChild(token);
            f.appendChild(dir);
            document.body.appendChild(f);
            f.submit();
          });
        });

        var list = document.getElementById('admin-photo-sortable');
        var form = list && list.closest('form');
        var fields = document.getElementById('admin-photo-sequence-fields');
        if (!list || !form || !fields || typeof Sortable === 'undefined') return;

        Sortable.create(list, {
          animation: 180,
          handle: '.admin-photo-drag-handle',
          ghostClass: 'admin-photo-sortable-ghost',
          dragClass: 'admin-photo-sortable-drag',
          forceFallback: true,
        });

        form.addEventListener('submit', function () {
          fields.innerHTML = '';
          list.querySelectorAll('.admin-photo-sortable-item').forEach(function (li) {
            var cb = li.querySelector('input[name="delete_photos[]"]');
            if (cb && cb.checked) return;
            var id = li.getAttribute('data-photo-id');
            if (!id) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'photo_sequence[]';
            input.value = id;
            fields.appendChild(input);
          });
        });
      })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js" crossorigin="anonymous"></script>
    <script>
      (function () {
        var modal = document.getElementById('admin-photo-crop-modal');
        var img = document.getElementById('admin-photo-crop-img');
        var applyBtn = document.getElementById('admin-photo-crop-apply');
        var labelBeforeCheckbox = document.getElementById('admin-photo-crop-label-before');
        var aspectButtons = modal.querySelectorAll('[data-crop-ar]');
        if (!modal || !img || !applyBtn || !aspectButtons.length || typeof Cropper === 'undefined') return;

        /* Keep overlay under <body> so position:fixed always covers the viewport (not the form column). */
        if (modal.parentElement !== document.body) {
          document.body.appendChild(modal);
        }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        var cropper = null;
        var activeUrl = '';
        var activeAspect = 'free';
        var stage = modal.querySelector('.admin-crop-stage');

        function aspectRatioValue(mode) {
          if (mode === 'free') return NaN;
          var parts = String(mode).split(':');
          if (parts.length !== 2) return NaN;
          var w = parseFloat(parts[0]);
          var h = parseFloat(parts[1]);
          if (!w || !h) return NaN;
          return w / h;
        }

        function setAspectMode(mode) {
          if (!cropper) return;
          activeAspect = mode;
          aspectButtons.forEach(function (btn) {
            var key = btn.getAttribute('data-crop-ar') || '';
            var isActive = key === mode;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });
          cropper.setAspectRatio(aspectRatioValue(mode));
        }

        function resetAspectControls() {
          activeAspect = 'free';
          aspectButtons.forEach(function (btn) {
            var isFree = btn.getAttribute('data-crop-ar') === 'free';
            btn.disabled = true;
            btn.classList.toggle('is-active', isFree);
            btn.setAttribute('aria-pressed', isFree ? 'true' : 'false');
          });
        }

        function enableAspectControls() {
          aspectButtons.forEach(function (btn) {
            btn.disabled = false;
          });
          setAspectMode('free');
        }

        function sizeCropStagePx() {
          if (!stage) return;
          var vh = window.innerHeight || 640;
          /* ~half viewport minus room for modal chrome; clamp keeps it usable on short / tall screens */
          var h = Math.floor(vh * 0.48);
          if (h < 260) h = 260;
          if (h > 480) h = 480;
          stage.style.height = h + 'px';
          stage.style.minHeight = h + 'px';
          stage.style.maxHeight = h + 'px';
        }

        function clearCropStagePx() {
          if (!stage) return;
          stage.style.height = '';
          stage.style.minHeight = '';
          stage.style.maxHeight = '';
        }

        function destroyCropper() {
          if (cropper) {
            cropper.destroy();
            cropper = null;
          }
          img.onload = null;
          img.onerror = null;
          img.removeAttribute('src');
        }

        function closeModal() {
          modal.hidden = true;
          destroyCropper();
          clearCropStagePx();
          applyBtn.disabled = true;
          activeUrl = '';
          resetAspectControls();
          if (labelBeforeCheckbox) {
            labelBeforeCheckbox.checked = false;
          }
          document.documentElement.classList.remove('admin-crop-modal-open');
        }

        function openModal() {
          sizeCropStagePx();
          document.documentElement.classList.add('admin-crop-modal-open');
          modal.hidden = false;
        }

        window.addEventListener('resize', function () {
          if (modal.hidden) return;
          sizeCropStagePx();
          if (cropper && typeof cropper.resize === 'function') {
            try {
              cropper.resize();
            } catch (e) { /* ignore */ }
          }
        });

        modal.querySelectorAll('[data-crop-modal-dismiss]').forEach(function (el) {
          el.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
          }
        });

        document.querySelectorAll('.js-crop-photo').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-image-url');
            var postUrl = btn.getAttribute('data-crop-url');
            if (!url || !postUrl) return;
            destroyCropper();
            activeUrl = postUrl;
            applyBtn.disabled = true;
            resetAspectControls();
            openModal();

            var bust = url + (url.indexOf('?') === -1 ? '?' : '&') + 'cropper=' + Date.now();
            var initScheduled = false;

            function initCropperOnce() {
              if (initScheduled) return;
              if (!img.naturalWidth || !img.naturalHeight) return;
              initScheduled = true;
              img.onload = null;
              window.requestAnimationFrame(function () {
                try {
                  cropper = new Cropper(img, {
                    viewMode: 1,
                    autoCropArea: 1,
                    responsive: true,
                    restore: false,
                    ready: function () {
                      applyBtn.disabled = false;
                      enableAspectControls();
                      sizeCropStagePx();
                      try {
                        if (cropper && typeof cropper.resize === 'function') {
                          cropper.resize();
                        }
                      } catch (eResize) { /* ignore */ }
                      window.setTimeout(function () {
                        sizeCropStagePx();
                        try {
                          if (cropper && typeof cropper.resize === 'function') {
                            cropper.resize();
                          }
                        } catch (e2) { /* ignore */ }
                      }, 80);
                    },
                  });
                } catch (e) {
                  closeModal();
                  alert('Could not open the crop tool. Try refreshing the page, or check that Cropper.js loaded (network / ad blocker).');
                  if (typeof console !== 'undefined' && console.error) {
                    console.error(e);
                  }
                }
              });
            }

            function onImgReady() {
              if (!img.naturalWidth || !img.naturalHeight) {
                closeModal();
                alert('Could not load this image for cropping.');
                return;
              }
              initCropperOnce();
            }

            img.onerror = function () {
              img.onerror = null;
              img.onload = null;
              closeModal();
              alert('Could not load this image for cropping (broken link or blocked request).');
            };
            img.onload = onImgReady;
            img.src = bust;
          });
        });

        aspectButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            setAspectMode(btn.getAttribute('data-crop-ar') || 'free');
          });
        });

        applyBtn.addEventListener('click', function () {
          if (!cropper || !activeUrl) return;
          var d = cropper.getData(true);
          applyBtn.disabled = true;
          fetch(activeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
              x: d.x,
              y: d.y,
              width: d.width,
              height: d.height,
              label_before: !!(labelBeforeCheckbox && labelBeforeCheckbox.checked),
            }),
          }).then(function (res) {
            return res.json().then(function (body) {
              if (!res.ok) {
                var msg = (body && body.message) ? body.message : ('HTTP ' + res.status);
                if (body && body.errors && typeof body.errors === 'object') {
                  var keys = Object.keys(body.errors);
                  if (keys.length && body.errors[keys[0]] && body.errors[keys[0]][0]) {
                    msg = body.errors[keys[0]][0];
                  }
                }
                throw new Error(msg);
              }
              return body;
            });
          }).then(function () {
            window.location.reload();
          }).catch(function (err) {
            alert(err && err.message ? err.message : 'Could not crop photo.');
            applyBtn.disabled = false;
          });
        });
      })();
    </script>
  @endpush
@endif


