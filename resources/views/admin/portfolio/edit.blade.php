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
        <input class="admin-input" type="text" id="title" name="title" value="{{ old('title', $project->title) }}" required maxlength="255">
        @error('title')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea class="admin-input" id="description" name="description" required>{{ old('description', $project->description) }}</textarea>
        @include('admin.partials.portfolio_description_ai', ['hasExistingPhotos' => $project->photos->isNotEmpty()])
        @error('description')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      @include('admin.partials.portfolio_seo_fields')
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
        <p class="admin-form-help" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        @include('admin.partials.portfolio_category_ai')
        <div class="admin-category-checkboxes" id="portfolio-category-checkboxes">
          @foreach ($categories as $cat)
            <label class="admin-checkbox admin-checkbox-tile">
              <input type="checkbox" class="admin-checkbox__input" name="project_category_ids[]" value="{{ $cat->id }}" @checked(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true))>
              <span class="admin-checkbox__mark" aria-hidden="true"></span>
              <span class="admin-checkbox__text">{{ $cat->name }}</span>
            </label>
          @endforeach
        </div>
        @error('project_category_ids')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
        @error('project_category_ids.*')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input class="admin-input" type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', optional($project->completed_on)->format('Y-m-d')) }}" required>
        <p class="admin-form-help">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-form-error">{{ $message }}</div>
        @enderror
      </div>

      @if ($project->photos->isNotEmpty())
        <div class="admin-form-group" id="admin-photo-order-group">
          <div class="admin-form-group__label-row">
            <span class="admin-form-group-label">Photos</span>
            <div class="admin-photo-bulk-actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-photo-bulk-label" id="admin-photo-bulk-before" disabled
                data-url="{{ route('admin.portfolio.photos.bulk-label', $project) }}"
                data-label="Before"
                title="Add Before label to selected photos"
                aria-label="Add Before label to selected photos">
                Before
              </button>
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-photo-bulk-label" id="admin-photo-bulk-during" disabled
                data-url="{{ route('admin.portfolio.photos.bulk-label', $project) }}"
                data-label="During"
                title="Add During label to selected photos"
                aria-label="Add During label to selected photos">
                During
              </button>
              <button type="button" class="admin-btn admin-btn--danger admin-btn--sm" id="admin-photo-bulk-delete" disabled
                data-url="{{ route('admin.portfolio.photos.bulk-destroy', $project) }}"
                title="Delete selected photos"
                aria-label="Delete selected photos">
                <i class="fa fa-trash" aria-hidden="true"></i> Delete
              </button>
            </div>
          </div>
          <p class="admin-form-help">Drag by the grip icon to reorder. The <strong>first photo</strong> is the homepage project cover. Click <strong>Edit</strong> to straighten, rotate, crop, or add a Before or During label (saved as WebP). Select photos with the checkbox, then use <strong>Before</strong>, <strong>During</strong>, <strong>Delete</strong>, or <strong>Apply AI improvements</strong> below.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            @foreach ($project->photos as $photo)
              <li class="admin-photo-sortable-item" data-photo-id="{{ $photo->id }}">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  @if ($loop->first)
                    <span class="admin-photo-cover-badge">Homepage cover</span>
                  @endif
                  <img src="{{ $photo->url() }}" alt="">
                  <label class="admin-photo-select admin-checkbox admin-checkbox--sm">
                    <input type="checkbox" class="admin-checkbox__input js-portfolio-ai-existing" value="{{ $photo->id }}" aria-label="Select photo">
                    <span class="admin-checkbox__mark" aria-hidden="true"></span>
                  </label>
                </div>
                <div class="admin-photo-sortable-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm admin-photo-edit-btn js-edit-photo"
                    title="Edit photo"
                    data-photo-id="{{ $photo->id }}"
                    data-edit-url="{{ route('admin.portfolio.photos.crop', [$project, $photo]) }}"
                    data-replace-url="{{ route('admin.portfolio.photos.replace', [$project, $photo]) }}"
                    data-image-url="{{ $photo->url() }}">
                    Edit
                  </button>
                </div>
              </li>
            @endforeach
          </ul>
          <div id="admin-photo-sequence-fields" aria-hidden="true"></div>
          @error('photo_sequence')
            <div class="admin-form-error">{{ $message }}</div>
          @enderror
        </div>
      @endif

      @php
        $canAdd = 15 - $project->photos->count();
      @endphp
      @if ($canAdd> 0)
        @php
          $portfolioWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
        @endphp
        <div class="admin-portfolio-upload-section">
          @include('admin.partials.local_and_google_photos', [
            'fileInputId' => 'photos',
            'fileInputName' => 'photos[]',
            'fileLabel' => 'Add photos',
            'fileHint' => 'WebP, JPEG, or PNG (10 MB max each). New files are converted to WebP at '.$portfolioWebp['quality'].'% quality, scaled to at most '.$portfolioWebp['max_edge_px'].'px, and compressed to about '.(int) round($portfolioWebp['max_bytes'] / 1024).' KB or less; originals are not kept. Up to '.$canAdd.' more (15 total). After you save, use <strong>Edit</strong> on any thumbnail to straighten, rotate, or crop.',
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
            'allowExisting' => $project->photos->isNotEmpty(),
          ])
        </div>
      @else
        <p class="admin-form-help" id="admin-photo-cap-notice">Maximum of 15 photos reached. Delete one to add more.</p>
        @if (auth()->user()->googlePhotosToken)
          <div class="admin-google-photos-panel admin-google-photos-panel--compact">
            <div class="admin-google-photos-panel__head">
              <div class="admin-google-photos-panel__title">
                <span class="admin-form-group-label">Google Photos</span>
                <span class="admin-google-photos-panel__name">
                  <span class="admin-google-photos-panel__sep" aria-hidden="true">|</span>
                  <span class="admin-google-photos-account">Connected as <strong>{{ auth()->user()->googlePhotosToken->google_email }}</strong></span>
                </span>
              </div>
            </div>
            <div class="admin-google-photos-head">
              <button
                type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-admin-google-disconnect
                data-disconnect-url="{{ route('admin.google-photos.disconnect') }}">Disconnect</button>
            </div>
          </div>
        @endif
        @if ($project->photos->isNotEmpty())
          @include('admin.partials.portfolio_ai_improve', [
            'fileInputId' => 'photos',
            'maxTotal' => $canAdd,
            'allowExisting' => true,
          ])
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
    @php
      $editPhotoWebp = \App\Services\ProjectPhotoWebpStorage::portfolioEncodingSettings();
    @endphp
    <div id="admin-photo-edit-modal" class="admin-modal" hidden
      data-delete-url="{{ route('admin.portfolio.photos.bulk-destroy', $project) }}"
      data-ai-url="{{ route('admin.portfolio.ai-improve-photos') }}">
      <div class="admin-modal-backdrop" data-edit-modal-dismiss tabindex="-1" aria-hidden="true"></div>
      <div class="admin-modal-panel" role="dialog" aria-modal="true" aria-labelledby="admin-photo-edit-title">
        <h2 id="admin-photo-edit-title" class="admin-modal-title">Edit photo</h2>
        <p class="admin-modal-hint">Straighten a tilted shot, rotate 90°, and crop the frame. Pick an aspect ratio below, or use <strong>Free</strong> to resize manually. The file on the server is replaced (WebP). Optionally add a <strong>Before</strong> or <strong>During</strong> label.</p>
        <div class="admin-crop-stage">
          <div class="admin-crop-stage__frame">
            <img id="admin-photo-edit-img" src="" alt="">
          </div>
        </div>
        <div class="admin-crop-toolbar admin-crop-toolbar--rotate">
          <span class="admin-crop-toolbar__label">Straighten &amp; rotate</span>
          <div class="admin-crop-rotate-row">
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="admin-photo-edit-rotate-ccw" disabled title="Rotate 90° counter-clockwise" aria-label="Rotate 90 degrees left">
              <i class="fa fa-undo" aria-hidden="true"></i>
            </button>
            <div class="admin-crop-straighten">
              <input type="range" id="admin-photo-edit-straighten" class="admin-input admin-crop-straighten__slider" min="-15" max="15" step="0.1" value="0" disabled aria-label="Straighten tilt">
              <span id="admin-photo-edit-rotate-value" class="admin-crop-straighten__value" aria-live="polite">0.0°</span>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="admin-photo-edit-rotate-cw" disabled title="Rotate 90° clockwise" aria-label="Rotate 90 degrees right">
              <i class="fa fa-repeat" aria-hidden="true"></i>
            </button>
          </div>
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
              aria-pressed="true">
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
            <span class="admin-checkbox__text">Add <strong>Before</strong> label</span>
          </label>
          <label class="admin-checkbox admin-checkbox--sm" for="admin-photo-crop-label-during">
            <input type="checkbox" class="admin-checkbox__input" id="admin-photo-crop-label-during" value="1">
            <span class="admin-checkbox__mark" aria-hidden="true"></span>
            <span class="admin-checkbox__text">Add <strong>During</strong> label</span>
          </label>
        </div>
        <div class="admin-modal-actions">
          <div class="admin-photo-edit-tools">
            <div class="admin-ai-action-group">
              @include('admin.partials.ai_instruction_edit_link', [
                'instructionKey' => \App\Models\AiInstruction::KEY_PORTFOLIO_PHOTO_PROMPT,
                'instructionLabel' => 'Edit portfolio photo prompt',
              ])
              <button type="button" class="admin-btn admin-btn--primary admin-btn--icon" id="admin-photo-edit-ai" disabled aria-label="Apply AI improvements" title="Apply AI improvements">
                <i class="fa fa-magic" aria-hidden="true"></i>
              </button>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--icon" id="admin-photo-edit-replace-toggle" disabled aria-expanded="false" aria-controls="admin-photo-edit-replace" aria-label="Replace" title="Replace">
              <i class="fa fa-exchange" aria-hidden="true"></i>
            </button>
            <button type="button" class="admin-btn admin-btn--danger admin-btn--icon" id="admin-photo-edit-delete" disabled aria-label="Delete" title="Delete">
              <i class="fa fa-trash" aria-hidden="true"></i>
            </button>
          </div>
          <div class="admin-photo-edit-actions-end">
            <button type="button" class="admin-btn admin-btn--ghost" data-edit-modal-dismiss>Cancel</button>
            <button type="button" class="admin-btn admin-btn--primary" id="admin-photo-edit-apply" disabled>Save</button>
          </div>
        </div>
        <div class="admin-photo-edit-feedback">
          <div id="admin-photo-edit-ai-loading" class="nb2-loading" hidden aria-live="polite">
            <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
            <span>Working…</span>
          </div>
          <p id="admin-photo-edit-tools-status" class="nb2-status" role="status"></p>
        </div>
        <div id="admin-photo-edit-replace" class="admin-photo-edit-replace" hidden>
          <p class="admin-form-help">Upload a new photo or pick one from Google Photos. It replaces this photo and keeps its place in the gallery.</p>
          <div class="admin-form-group">
            <label for="admin-photo-edit-replace-file">Upload</label>
            <input class="admin-input" type="file" id="admin-photo-edit-replace-file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
          </div>
          @include('admin.partials.google_photos_picker', [
            'maxTotal' => 1,
            'fileInputId' => 'admin-photo-edit-replace-file',
            'statusMode' => 'total',
            'formSync' => false,
            'footText' => 'One photo. An upload and a Google Photo cannot be used together.',
          ])
          <button type="button" class="admin-btn admin-btn--primary" id="admin-photo-edit-replace-apply">
            Use this photo
          </button>
        </div>
      </div>
    </div>
  @endif
@endsection

@push('scripts')
  <script>
    @if ($canAdd > 0 || $project->photos->isNotEmpty())
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
      historyKey: @json('portfolio-desc:'.$project->id),
      csrfToken: @json(csrf_token()),
      improveUrl: @json(route('admin.portfolio.ai-improve-description.edit', $project)),
    };
    window.portfolioCategoryAiConfig = {
      titleInputId: 'title',
      descriptionInputId: 'description',
      fileInputId: 'photos',
      csrfToken: @json(csrf_token()),
      categorizeUrl: @json(route('admin.portfolio.ai-categorize.edit', $project)),
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

        var list = document.getElementById('admin-photo-sortable');
        var form = list && list.closest('form');
        var fields = document.getElementById('admin-photo-sequence-fields');
        if (!list || !form || !fields || typeof Sortable === 'undefined') return;

        var deleteBtn = document.getElementById('admin-photo-bulk-delete');
        var labelButtons = document.querySelectorAll('.js-photo-bulk-label');

        function selectedPhotoBoxes() {
          return list.querySelectorAll('.js-portfolio-ai-existing:checked');
        }

        function setBulkActionsDisabled(disabled) {
          if (deleteBtn) deleteBtn.disabled = disabled;
          Array.prototype.forEach.call(labelButtons, function (btn) {
            btn.disabled = disabled;
          });
        }

        function syncPhotoSelection() {
          list.querySelectorAll('.admin-photo-sortable-item').forEach(function (item) {
            var box = item.querySelector('.js-portfolio-ai-existing');
            item.classList.toggle('is-ai-selected', !!(box && box.checked));
          });
          setBulkActionsDisabled(selectedPhotoBoxes().length < 1);
        }

        function applyLabeledPhotoUrls(photos) {
          (photos || []).forEach(function (photo) {
            if (!photo || photo.id == null || !photo.url) return;
            var item = list.querySelector('.admin-photo-sortable-item[data-photo-id="' + photo.id + '"]');
            if (!item) return;
            var thumb = item.querySelector('img');
            if (thumb) thumb.src = photo.url;
            var editBtn = item.querySelector('.js-edit-photo');
            if (editBtn) editBtn.setAttribute('data-image-url', photo.url);
          });
        }

        function clearPhotoSelection() {
          list.querySelectorAll('.js-portfolio-ai-existing:checked').forEach(function (box) {
            box.checked = false;
          });
          syncPhotoSelection();
        }

        function refreshCoverBadge() {
          list.querySelectorAll('.admin-photo-cover-badge').forEach(function (badge) {
            badge.remove();
          });
          var first = list.querySelector('.admin-photo-sortable-item .admin-photo-sortable-thumb');
          if (!first) return;
          var badge = document.createElement('span');
          badge.className = 'admin-photo-cover-badge';
          badge.textContent = 'Homepage cover';
          first.insertBefore(badge, first.firstChild);
        }

        function bumpUploadSlots(freed) {
          if (!freed) return;
          var ai = document.getElementById('portfolio-ai-improve');
          if (ai) {
            var slots = parseInt(ai.getAttribute('data-remaining-slots') || '0', 10);
            ai.setAttribute('data-remaining-slots', String(slots + freed));
          }
          var picker = null;
          document.querySelectorAll('.admin-google-photos').forEach(function (el) {
            if (!picker && !el.closest('#admin-photo-edit-modal')) picker = el;
          });
          if (!picker) return;
          var pickerSlots = parseInt(picker.getAttribute('data-max-total') || '0', 10);
          picker.setAttribute('data-max-total', String(pickerSlots + freed));
          if (picker.googlePhotosPicker && picker.googlePhotosPicker.refreshStatus) {
            picker.googlePhotosPicker.refreshStatus();
          }
        }

        Sortable.create(list, {
          animation: 180,
          handle: '.admin-photo-drag-handle',
          ghostClass: 'admin-photo-sortable-ghost',
          dragClass: 'admin-photo-sortable-drag',
          forceFallback: true,
          onEnd: refreshCoverBadge,
        });

        list.addEventListener('change', function (event) {
          var target = event.target;
          if (!target || !target.classList || !target.classList.contains('js-portfolio-ai-existing')) return;
          syncPhotoSelection();
        });

        if (deleteBtn) {
          deleteBtn.addEventListener('click', function () {
            var boxes = selectedPhotoBoxes();
            var count = boxes.length;
            if (!count || deleteBtn.disabled) return;
            var atCap = !!document.getElementById('admin-photo-cap-notice');
            var message = count === 1
              ? 'Delete this photo? It is removed from storage.'
              : 'Delete ' + count + ' selected photos? They are removed from storage.';
            if (atCap) {
              message += ' This page will refresh afterward so you can add photos again. Save other edits first if you need to keep them.';
            }
            var ask = (window.AdminDialog && window.AdminDialog.confirm)
              ? window.AdminDialog.confirm(message, { title: 'Delete photos', confirmLabel: 'Delete', danger: true })
              : Promise.resolve(window.confirm(message));
            ask.then(function (ok) {
              if (!ok) return;
              var fd = new FormData();
              Array.prototype.forEach.call(boxes, function (box) {
                fd.append('photo_ids[]', box.value);
              });
              setBulkActionsDisabled(true);
              fetch(deleteBtn.getAttribute('data-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest',
                  'X-CSRF-TOKEN': csrf,
                },
                body: fd,
              }).then(function (res) {
                return res.text().then(function (text) {
                  var body = null;
                  if (text) {
                    try { body = JSON.parse(text); } catch (ignore) { body = null; }
                  }
                  if (!res.ok) {
                    var msg = (body && body.message) ? body.message : ('Could not delete the selected photos (HTTP ' + res.status + ').');
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
                if (atCap) {
                  window.location.reload();
                  return;
                }
                Array.prototype.forEach.call(boxes, function (box) {
                  var item = box.closest('.admin-photo-sortable-item');
                  if (item) item.remove();
                });
                refreshCoverBadge();
                bumpUploadSlots(count);
                syncPhotoSelection();
                if (!list.querySelector('.admin-photo-sortable-item')) {
                  var group = document.getElementById('admin-photo-order-group');
                  if (group) group.hidden = true;
                }
              }).catch(function (err) {
                window.alert(err && err.message ? err.message : 'Could not delete the selected photos.');
                syncPhotoSelection();
              });
            });
          });
        }

        Array.prototype.forEach.call(labelButtons, function (labelBtn) {
          labelBtn.addEventListener('click', function () {
            var boxes = selectedPhotoBoxes();
            var count = boxes.length;
            var label = labelBtn.getAttribute('data-label') || '';
            if (!count || labelBtn.disabled || !label) return;
            var message = count === 1
              ? 'Add a ' + label + ' label to this photo? The word is written onto the image.'
              : 'Add a ' + label + ' label to ' + count + ' selected photos? The word is written onto each image.';
            var ask = (window.AdminDialog && window.AdminDialog.confirm)
              ? window.AdminDialog.confirm(message, { title: label + ' label', confirmLabel: 'Add label' })
              : Promise.resolve(window.confirm(message));
            ask.then(function (ok) {
              if (!ok) return;
              var stillChecked = selectedPhotoBoxes();
              if (!stillChecked.length) {
                syncPhotoSelection();
                return;
              }
              var fd = new FormData();
              fd.append('label', label);
              Array.prototype.forEach.call(stillChecked, function (box) {
                fd.append('photo_ids[]', box.value);
              });
              setBulkActionsDisabled(true);
              fetch(labelBtn.getAttribute('data-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                  'Accept': 'application/json',
                  'X-Requested-With': 'XMLHttpRequest',
                  'X-CSRF-TOKEN': csrf,
                },
                body: fd,
              }).then(function (res) {
                return res.text().then(function (text) {
                  var body = null;
                  if (text) {
                    try { body = JSON.parse(text); } catch (ignore) { body = null; }
                  }
                  if (body && body.photos) applyLabeledPhotoUrls(body.photos);
                  if (res.ok) clearPhotoSelection();
                  if (!res.ok) {
                    var msg = (body && body.message) ? body.message : ('Could not add the ' + label + ' label (HTTP ' + res.status + ').');
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
              }).catch(function (err) {
                window.alert(err && err.message ? err.message : 'Could not add the label.');
              }).then(function () {
                syncPhotoSelection();
              });
            });
          });
        });

        form.addEventListener('submit', function () {
          fields.innerHTML = '';
          list.querySelectorAll('.admin-photo-sortable-item').forEach(function (li) {
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
        var modal = document.getElementById('admin-photo-edit-modal');
        var img = document.getElementById('admin-photo-edit-img');
        var applyBtn = document.getElementById('admin-photo-edit-apply');
        var labelBeforeCheckbox = document.getElementById('admin-photo-crop-label-before');
        var labelDuringCheckbox = document.getElementById('admin-photo-crop-label-during');
        var straightenSlider = document.getElementById('admin-photo-edit-straighten');
        var rotateValueEl = document.getElementById('admin-photo-edit-rotate-value');
        var rotateCcwBtn = document.getElementById('admin-photo-edit-rotate-ccw');
        var rotateCwBtn = document.getElementById('admin-photo-edit-rotate-cw');
        var aspectButtons = modal ? modal.querySelectorAll('[data-crop-ar]') : [];
        var maxEdge = {{ (int) $editPhotoWebp['max_edge_px'] }};
        if (!modal || !img || !applyBtn || !aspectButtons.length || typeof Cropper === 'undefined') return;

        if (modal.parentElement !== document.body) {
          document.body.appendChild(modal);
        }

        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        var cropper = null;
        var activeUrl = '';
        var activePhotoId = '';
        var activeReplaceUrl = '';
        var activeItem = null;
        var toolsBusy = false;
        var editDeleteBtn = document.getElementById('admin-photo-edit-delete');
        var editAiBtn = document.getElementById('admin-photo-edit-ai');
        var editAiLoading = document.getElementById('admin-photo-edit-ai-loading');
        var editReplaceToggle = document.getElementById('admin-photo-edit-replace-toggle');
        var editReplacePanel = document.getElementById('admin-photo-edit-replace');
        var editReplaceFile = document.getElementById('admin-photo-edit-replace-file');
        var editReplaceApply = document.getElementById('admin-photo-edit-replace-apply');
        var editToolsStatus = document.getElementById('admin-photo-edit-tools-status');
        var stage = modal.querySelector('.admin-crop-stage');
        var straightenFine = 0;
        var rotationZoom = 1;

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
          aspectButtons.forEach(function (btn) {
            var key = btn.getAttribute('data-crop-ar') || '';
            var isActive = key === mode;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
          });
          cropper.setAspectRatio(aspectRatioValue(mode));
          fitRotationCover(false);
        }

        function resetAspectControls() {
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

        function resetRotateControls() {
          straightenFine = 0;
          rotationZoom = 1;
          if (straightenSlider) {
            straightenSlider.value = '0';
            straightenSlider.disabled = true;
          }
          if (rotateCcwBtn) rotateCcwBtn.disabled = true;
          if (rotateCwBtn) rotateCwBtn.disabled = true;
          updateRotateDisplay();
        }

        function enableRotateControls() {
          if (straightenSlider) straightenSlider.disabled = false;
          if (rotateCcwBtn) rotateCcwBtn.disabled = false;
          if (rotateCwBtn) rotateCwBtn.disabled = false;
          updateRotateDisplay();
        }

        function updateRotateDisplay() {
          if (!rotateValueEl || !cropper) return;
          var deg = cropper.getImageData().rotate || 0;
          rotateValueEl.textContent = deg.toFixed(1) + '°';
        }

        // Extra scale so a rotated photo still covers the crop box (no empty corners).
        // rotationZoom is only the scale this helper added, so manual crop zoom is kept
        // and the frame zooms back out as the angle returns toward a right angle.
        function relativeCoverScale() {
          var canvas = cropper.getCanvasData();
          var image = cropper.getImageData();
          var box = cropper.getCropBoxData();
          var iw = image.width;
          var ih = image.height;
          if (!iw || !ih || !box.width || !box.height) return 1;

          var theta = ((image.rotate || 0) * Math.PI) / 180;
          var cos = Math.cos(theta);
          var sin = Math.sin(theta);
          var cx = canvas.left + (image.left || 0) + iw / 2;
          var cy = canvas.top + (image.top || 0) + ih / 2;
          var pad = 1;
          var corners = [
            [box.left, box.top],
            [box.left + box.width, box.top],
            [box.left, box.top + box.height],
            [box.left + box.width, box.top + box.height]
          ];
          var s = 0;
          for (var i = 0; i < corners.length; i++) {
            var px = corners[i][0] - cx;
            var py = corners[i][1] - cy;
            var lx = Math.abs(px * cos + py * sin) + pad;
            var ly = Math.abs(-px * sin + py * cos) + pad;
            s = Math.max(s, (lx * 2) / iw, (ly * 2) / ih);
          }
          return s;
        }

        function zoomBy(factor) {
          if (!cropper || !factor || Math.abs(factor - 1) < 0.0005) return;
          var canvas = cropper.getCanvasData();
          var box = cropper.getCropBoxData();
          var before = canvas.naturalWidth ? canvas.width / canvas.naturalWidth : 0;
          if (!before) return;
          cropper.zoomTo(before * factor, {
            x: box.left + box.width / 2,
            y: box.top + box.height / 2
          });
          var afterCanvas = cropper.getCanvasData();
          var after = afterCanvas.naturalWidth ? afterCanvas.width / afterCanvas.naturalWidth : before;
          var actual = after / before;
          if (actual > 0 && isFinite(actual)) {
            rotationZoom *= actual;
          }
        }

        function fitRotationCover(allowZoomOut) {
          if (!cropper) return;
          var guard = 0;
          while (guard < 3) {
            guard += 1;
            var s = relativeCoverScale();
            if (!isFinite(s) || s <= 0) return;
            if (s > 1.001) {
              zoomBy(s);
              continue;
            }
            if (allowZoomOut && rotationZoom > 1.001 && s < 0.999) {
              zoomBy(Math.max(s, 1 / rotationZoom));
              continue;
            }
            return;
          }
        }

        // Quarter turns show the whole photo in its new orientation. The slider
        // then zooms that frame just enough to hide the empty corners.
        function refitFullFrame() {
          if (!cropper) return;
          var container = cropper.getContainerData();
          var image = cropper.getImageData();
          var degrees = image.rotate || 0;
          var rad = degrees * Math.PI / 180;
          var absCos = Math.abs(Math.cos(rad));
          var absSin = Math.abs(Math.sin(rad));
          var aabbW = image.naturalWidth * absCos + image.naturalHeight * absSin;
          var aabbH = image.naturalWidth * absSin + image.naturalHeight * absCos;
          if (!aabbW || !aabbH || !container.width || !container.height) return;
          var ratio = Math.min(container.width / aabbW, container.height / aabbH);
          cropper.zoomTo(ratio);
          var canvas = cropper.getCanvasData();
          cropper.setCropBoxData({
            left: canvas.left,
            top: canvas.top,
            width: canvas.width,
            height: canvas.height
          });
          rotationZoom = 1;
        }

        function rotateQuarter(delta) {
          if (!cropper) return;
          cropper.rotate(delta);
          refitFullFrame();
          fitRotationCover(true);
          updateRotateDisplay();
        }

        function sizeCropStagePx() {
          if (!stage) return;
          var vh = window.innerHeight || 640;
          var h = Math.floor(vh * 0.42);
          if (h < 240) h = 240;
          if (h> 440) h = 440;
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
          toolsBusy = false;
          modal.hidden = true;
          destroyCropper();
          clearCropStagePx();
          applyBtn.disabled = true;
          activeUrl = '';
          activePhotoId = '';
          activeReplaceUrl = '';
          activeItem = null;
          resetAspectControls();
          resetRotateControls();
          resetReplaceChoices();
          setToolsEnabled(false);
          setToolsStatus('');
          if (labelBeforeCheckbox) {
            labelBeforeCheckbox.checked = false;
          }
          if (labelDuringCheckbox) {
            labelDuringCheckbox.checked = false;
          }
          document.documentElement.classList.remove('admin-photo-edit-modal-open');
        }

        function openModal() {
          sizeCropStagePx();
          document.documentElement.classList.add('admin-photo-edit-modal-open');
          modal.hidden = false;
        }

        window.addEventListener('resize', function () {
          if (modal.hidden) return;
          sizeCropStagePx();
          if (cropper && typeof cropper.resize === 'function') {
            try { cropper.resize(); } catch (e) { /* ignore */ }
            fitRotationCover(true);
          }
        });

        modal.querySelectorAll('[data-edit-modal-dismiss]').forEach(function (el) {
          el.addEventListener('click', closeModal);
        });

        img.addEventListener('cropend', function () {
          fitRotationCover(false);
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !modal.hidden) {
            closeModal();
          }
        });

        if (straightenSlider) {
          straightenSlider.addEventListener('input', function () {
            if (!cropper) return;
            var nextFine = parseFloat(straightenSlider.value);
            if (isNaN(nextFine)) return;
            var delta = nextFine - straightenFine;
            if (delta !== 0) {
              cropper.rotate(delta);
              straightenFine = nextFine;
              fitRotationCover(true);
            }
            updateRotateDisplay();
          });
        }

        if (rotateCcwBtn) {
          rotateCcwBtn.addEventListener('click', function () {
            rotateQuarter(-90);
          });
        }

        if (rotateCwBtn) {
          rotateCwBtn.addEventListener('click', function () {
            rotateQuarter(90);
          });
        }

        function setToolsStatus(message, isError) {
          if (!editToolsStatus) return;
          editToolsStatus.textContent = message || '';
          editToolsStatus.classList.toggle('is-error', !!isError);
        }

        function setToolsBusy(active, message) {
          toolsBusy = !!active;
          if (editAiLoading) {
            editAiLoading.hidden = !active;
            var label = editAiLoading.querySelector('span');
            if (label && message) label.textContent = message;
          }
          setToolsEnabled(!active && !!activePhotoId);
          if (active) applyBtn.disabled = true;
        }

        function setToolsEnabled(on) {
          var disabled = !on || toolsBusy;
          if (editDeleteBtn) editDeleteBtn.disabled = disabled;
          if (editAiBtn) editAiBtn.disabled = disabled;
          if (editReplaceToggle) editReplaceToggle.disabled = disabled;
          if (editReplaceApply) editReplaceApply.disabled = disabled;
        }

        function editorGoogleRoot() {
          return editReplacePanel ? editReplacePanel.querySelector('.admin-google-photos') : null;
        }

        function resetReplaceChoices() {
          if (editReplaceFile) editReplaceFile.value = '';
          var googleRoot = editorGoogleRoot();
          if (googleRoot && googleRoot.googlePhotosPicker && googleRoot.googlePhotosPicker.clearPickedItems) {
            googleRoot.googlePhotosPicker.clearPickedItems();
          } else if (googleRoot) {
            googleRoot.querySelectorAll('input[data-picker-checkbox]').forEach(function (box) {
              box.checked = false;
            });
          }
          if (editReplacePanel) editReplacePanel.hidden = true;
          if (editReplaceToggle) editReplaceToggle.setAttribute('aria-expanded', 'false');
        }

        function editorErrorMessage(body, status) {
          if (body && body.error) return body.error;
          if (body && body.message) return body.message;
          if (body && body.errors) {
            var keys = Object.keys(body.errors);
            if (keys.length && body.errors[keys[0]] && body.errors[keys[0]][0]) {
              return body.errors[keys[0]][0];
            }
          }
          return 'HTTP ' + status;
        }

        function postForm(url, formData) {
          return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': csrfToken,
            },
            body: formData,
          }).then(function (res) {
            return res.text().then(function (text) {
              var body = null;
              if (text) {
                try { body = JSON.parse(text); } catch (ignore) { body = null; }
              }
              if (!res.ok) throw new Error(editorErrorMessage(body, res.status));
              return body || {};
            });
          });
        }

        function updateThumbnail(url) {
          if (!activeItem) return;
          var thumb = activeItem.querySelector('img');
          if (thumb) thumb.src = url;
          var editBtn = activeItem.querySelector('.js-edit-photo');
          if (editBtn) editBtn.setAttribute('data-image-url', url);
        }

        function refreshGalleryAfterDelete() {
          var list = document.getElementById('admin-photo-sortable');
          if (!list) return;
          list.querySelectorAll('.admin-photo-cover-badge').forEach(function (badge) {
            badge.remove();
          });
          var first = list.querySelector('.admin-photo-sortable-item .admin-photo-sortable-thumb');
          if (first) {
            var badge = document.createElement('span');
            badge.className = 'admin-photo-cover-badge';
            badge.textContent = 'Homepage cover';
            first.insertBefore(badge, first.firstChild);
          }
          var noneSelected = list.querySelectorAll('.js-portfolio-ai-existing:checked').length < 1;
          var pageDelete = document.getElementById('admin-photo-bulk-delete');
          if (pageDelete) pageDelete.disabled = noneSelected;
          document.querySelectorAll('.js-photo-bulk-label').forEach(function (btn) {
            btn.disabled = noneSelected;
          });
          var ai = document.getElementById('portfolio-ai-improve');
          if (ai) {
            var slots = parseInt(ai.getAttribute('data-remaining-slots') || '0', 10);
            ai.setAttribute('data-remaining-slots', String(slots + 1));
          }
          if (!list.querySelector('.admin-photo-sortable-item')) {
            var group = document.getElementById('admin-photo-order-group');
            if (group) group.hidden = true;
          }
        }

        function mountImage(url) {
            destroyCropper();
            applyBtn.disabled = true;
            resetAspectControls();
            resetRotateControls();
            openModal();

            var bust = url + (url.indexOf('?') === -1 ? '?' : '&') + 'editor=' + Date.now();
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
                      cropper.rotateTo(0);
                      straightenFine = 0;
                      if (straightenSlider) straightenSlider.value = '0';
                      applyBtn.disabled = false;
                      enableAspectControls();
                      enableRotateControls();
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
                  alert('Could not open the photo editor. Try refreshing the page, or check that Cropper.js loaded (network / ad blocker).');
                  if (typeof console !== 'undefined' && console.error) {
                    console.error(e);
                  }
                }
              });
            }

            function onImgReady() {
              if (!img.naturalWidth || !img.naturalHeight) {
                closeModal();
                alert('Could not load this image for editing.');
                return;
              }
              initCropperOnce();
            }

            img.onerror = function () {
              img.onerror = null;
              img.onload = null;
              closeModal();
              alert('Could not load this image for editing (broken link or blocked request).');
            };
            img.onload = onImgReady;
            img.src = bust;
        }

        document.querySelectorAll('.js-edit-photo').forEach(function (btn) {
          btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-image-url');
            var postUrl = btn.getAttribute('data-edit-url');
            if (!url || !postUrl) return;
            activeUrl = postUrl;
            activePhotoId = btn.getAttribute('data-photo-id') || '';
            activeReplaceUrl = btn.getAttribute('data-replace-url') || '';
            activeItem = btn.closest('.admin-photo-sortable-item');
            setToolsStatus('');
            resetReplaceChoices();
            setToolsEnabled(true);
            mountImage(url);
          });
        });

        if (editDeleteBtn) {
          editDeleteBtn.addEventListener('click', function () {
            if (!activePhotoId || toolsBusy) return;
            var atCap = !!document.getElementById('admin-photo-cap-notice');
            var message = 'Delete this photo? It is removed from storage.';
            if (atCap) {
              message += ' This page will refresh afterward so you can add photos again. Save other edits first if you need to keep them.';
            }
            var ask = (window.AdminDialog && window.AdminDialog.confirm)
              ? window.AdminDialog.confirm(message, { title: 'Delete photo', confirmLabel: 'Delete', danger: true })
              : Promise.resolve(window.confirm(message));
            ask.then(function (ok) {
              if (!ok) return;
              var fd = new FormData();
              fd.append('photo_ids[]', activePhotoId);
              setToolsBusy(true, 'Deleting…');
              postForm(modal.getAttribute('data-delete-url'), fd).then(function () {
                if (atCap) {
                  window.location.reload();
                  return;
                }
                if (activeItem) activeItem.remove();
                refreshGalleryAfterDelete();
                closeModal();
              }).catch(function (err) {
                setToolsBusy(false);
                setToolsStatus(err.message || 'Could not delete this photo.', true);
              });
            });
          });
        }

        if (editAiBtn) {
          editAiBtn.addEventListener('click', function () {
            if (!activePhotoId || toolsBusy) return;
            var fd = new FormData();
            fd.append('existing_photo_id', activePhotoId);
            setToolsBusy(true, 'Improving with Gemini…');
            setToolsStatus('Applying AI improvements…', false);
            postForm(modal.getAttribute('data-ai-url'), fd).then(function (body) {
              var file = body && body.files && body.files[0] ? body.files[0] : null;
              var nextUrl = file && file.url ? file.url : '';
              if (!nextUrl) throw new Error('AI improve did not return a photo.');
              setToolsBusy(false);
              updateThumbnail(nextUrl);
              setToolsStatus('AI improvement saved. You can still crop it, or close the editor.', false);
              mountImage(nextUrl);
            }).catch(function (err) {
              setToolsBusy(false);
              setToolsStatus(err.message || 'Could not improve this photo.', true);
              if (cropper) applyBtn.disabled = false;
            });
          });
        }

        if (editReplaceToggle && editReplacePanel) {
          editReplaceToggle.addEventListener('click', function () {
            if (toolsBusy) return;
            var open = editReplacePanel.hidden;
            editReplacePanel.hidden = !open;
            editReplaceToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) editReplacePanel.scrollIntoView({ block: 'nearest' });
          });
        }

        if (editReplaceApply) {
          editReplaceApply.addEventListener('click', function () {
            if (!activeReplaceUrl || toolsBusy) return;
            var fd = new FormData();
            var hasFile = editReplaceFile && editReplaceFile.files && editReplaceFile.files.length > 0;
            if (hasFile) fd.append('image', editReplaceFile.files[0]);
            var googleRoot = editorGoogleRoot();
            var payloads = (googleRoot && googleRoot.googlePhotosPicker)
              ? googleRoot.googlePhotosPicker.getCheckedPayloads()
              : [];
            payloads.forEach(function (payload) {
              fd.append('google_picker_items[]', payload);
            });
            if (!hasFile && !payloads.length) {
              setToolsStatus('Upload a photo or pick one from Google Photos.', true);
              return;
            }
            if (hasFile && payloads.length) {
              setToolsStatus('Choose either an upload or one Google Photo.', true);
              return;
            }
            setToolsBusy(true, 'Replacing photo…');
            setToolsStatus('Replacing this photo…', false);
            postForm(activeReplaceUrl, fd).then(function (body) {
              var nextUrl = body && body.url ? body.url : '';
              if (!nextUrl) throw new Error('Replace did not return a photo.');
              setToolsBusy(false);
              resetReplaceChoices();
              updateThumbnail(nextUrl);
              setToolsStatus('Photo replaced. You can still crop it, or close the editor.', false);
              mountImage(nextUrl);
            }).catch(function (err) {
              setToolsBusy(false);
              setToolsStatus(err.message || 'Could not replace this photo.', true);
              if (cropper) applyBtn.disabled = false;
            });
          });
        }

        if (labelBeforeCheckbox && labelDuringCheckbox) {
          labelBeforeCheckbox.addEventListener('change', function () {
            if (labelBeforeCheckbox.checked) labelDuringCheckbox.checked = false;
          });
          labelDuringCheckbox.addEventListener('change', function () {
            if (labelDuringCheckbox.checked) labelBeforeCheckbox.checked = false;
          });
        }

        aspectButtons.forEach(function (btn) {
          btn.addEventListener('click', function () {
            setAspectMode(btn.getAttribute('data-crop-ar') || 'free');
          });
        });

        applyBtn.addEventListener('click', function () {
          if (!cropper || !activeUrl) return;
          var canvas = cropper.getCroppedCanvas({
            maxWidth: maxEdge,
            maxHeight: maxEdge,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
            fillColor: '#ffffff',
          });
          if (!canvas) {
            alert('Could not prepare the edited image. Try adjusting the crop area.');
            return;
          }

          applyBtn.disabled = true;
          canvas.toBlob(function (blob) {
            if (!blob) {
              alert('Could not export the edited image.');
              applyBtn.disabled = false;
              return;
            }

            var fd = new FormData();
            fd.append('image', blob, 'edited.png');
            if (labelBeforeCheckbox && labelBeforeCheckbox.checked) {
              fd.append('label_before', '1');
            }
            if (labelDuringCheckbox && labelDuringCheckbox.checked) {
              fd.append('label_during', '1');
            }

            fetch(activeUrl, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
              },
              body: fd,
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
              alert(err && err.message ? err.message : 'Could not save the edited photo.');
              applyBtn.disabled = false;
            });
          }, 'image/png');
        });
      })();
    </script>
  @endpush
@endif


