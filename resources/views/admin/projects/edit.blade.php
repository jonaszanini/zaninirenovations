@extends('admin.layouts.app')

@section('title', 'Edit project')
@section('heading', 'Edit project')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.projects.update', $project) }}" method="post" enctype="multipart/form-data">
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
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', $project->categories->pluck('id')->map(fn ($id) => (string) $id)->all());
      @endphp
      <div class="admin-form-group">
        <span class="admin-form-group-label">Categories</span>
        <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        <div class="admin-category-checkboxes">
          @foreach ($categories as $cat)
            <label class="admin-checkbox-tile">
              <input type="checkbox" name="project_category_ids[]" value="{{ $cat->id }}" @checked(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true))>
              <span>{{ $cat->name }}</span>
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
          <p class="admin-form-hint">Drag by the grip icon to reorder. Use rotate to fix orientation (90°). Unchecked photos are saved in this order. Check “Remove” to delete from storage.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            @foreach ($project->photos as $photo)
              <li class="admin-photo-sortable-item" data-photo-id="{{ $photo->id }}">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  <img src="{{ $photo->url() }}" alt="">
                </div>
                <div class="admin-photo-sortable-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° counter-clockwise"
                    data-post-url="{{ route('admin.projects.photos.rotate', [$project, $photo]) }}"
                    data-direction="ccw">
                    <i class="fa fa-undo" aria-hidden="true"></i><span class="admin-sr-only"> Rotate left</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° clockwise"
                    data-post-url="{{ route('admin.projects.photos.rotate', [$project, $photo]) }}"
                    data-direction="cw">
                    <i class="fa fa-repeat" aria-hidden="true"></i><span class="admin-sr-only"> Rotate right</span>
                  </button>
                </div>
                <label class="admin-photo-remove-label">
                  <input type="checkbox" name="delete_photos[]" value="{{ $photo->id }}"> Remove
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
        <div class="admin-form-group">
          <label for="photos">Add photos</label>
          <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
          <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). New files are converted to WebP and scaled so the longest side is at most {{ (int) config('webp.max_edge_px', 1920) }}px; originals are not kept. Up to {{ $canAdd }} more (15 total).</p>
          @error('photos')
            <div class="admin-error">{{ $message }}</div>
          @enderror
          @error('photos.*')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>

        <div class="admin-form-group">
          <span class="admin-form-group-label">Google Photos</span>
          @if (auth()->user()->googlePhotosToken)
            <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">
              Connected as <strong>{{ auth()->user()->googlePhotosToken->google_email }}</strong>.
              <form action="{{ route('admin.google-photos.disconnect') }}" method="post" style="display:inline;margin-left:0.5rem;">
                @csrf
                <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Disconnect</button>
              </form>
            </p>
            <div
              class="admin-google-photos"
              data-google-media-url="{{ route('admin.google-photos.media') }}"
              data-max-total="{{ $canAdd }}"
            >
              <div class="admin-google-photos-toolbar">
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-google-load>Load recent photos</button>
                <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-google-more hidden>Load more</button>
                <span class="admin-google-photos-status" data-google-status></span>
              </div>
              <div class="admin-google-photos-grid" data-google-grid></div>
            </div>
            <p class="admin-form-hint">You can add up to {{ $canAdd }} more photos total (uploads + Google combined).</p>
          @else
            <p class="admin-form-hint" style="margin-top:0;">
              <a class="admin-btn admin-btn--ghost admin-btn--sm" href="{{ route('admin.google-photos.connect') }}">Connect Google Photos</a>
              <span style="margin-left:0.5rem;color:var(--admin-muted);">Required for importing from your library.</span>
            </p>
          @endif
          @error('google_photos_media_ids')
            <div class="admin-error">{{ $message }}</div>
          @enderror
          @error('google_photos_media_ids.*')
            <div class="admin-error">{{ $message }}</div>
          @enderror
        </div>
      @else
        <p class="admin-form-hint">Maximum of 15 photos reached. Remove one to add more.</p>
      @endif

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="{{ route('admin.projects.index') }}" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>
  </div>
@endsection

@if ($project->photos->isNotEmpty())
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
  @endpush
@endif

@push('scripts')
  <script>
    (function () {
      var root = document.querySelector('.admin-google-photos');
      if (!root) return;

      var mediaUrl = root.getAttribute('data-google-media-url');
      var maxTotal = parseInt(root.getAttribute('data-max-total') || '0', 10);
      var btnLoad = root.querySelector('[data-google-load]');
      var btnMore = root.querySelector('[data-google-more]');
      var grid = root.querySelector('[data-google-grid]');
      var status = root.querySelector('[data-google-status]');
      var nextToken = null;

      function selectedCount() {
        return grid.querySelectorAll('input[type="checkbox"][name="google_photos_media_ids[]"]:checked').length;
      }

      function refreshStatus() {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var g = selectedCount();
        status.textContent = 'Selected: ' + g + ' Google + ' + files + ' uploads (max ' + maxTotal + ' more)';
      }

      function enforceMax(checkbox) {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var maxGoogle = Math.max(0, maxTotal - files);
        if (!checkbox.checked) return;
        if (selectedCount() > maxGoogle) {
          checkbox.checked = false;
          alert('Too many Google Photos selected for current upload count (max ' + maxTotal + ' more).');
        }
      }

      function renderItems(items) {
        items.forEach(function (it) {
          if (!it || !it.id) return;
          var url = it.baseUrl ? (it.baseUrl + '=w220-h220-c') : '';
          var label = it.filename || 'Photo';

          var tile = document.createElement('label');
          tile.className = 'admin-google-photo-tile';

          var cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.name = 'google_photos_media_ids[]';
          cb.value = it.id;
          cb.addEventListener('change', function () { enforceMax(cb); refreshStatus(); });

          var thumb = document.createElement('div');
          thumb.className = 'admin-google-photo-thumb';
          if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            img.loading = 'lazy';
            thumb.appendChild(img);
          }

          var cap = document.createElement('div');
          cap.className = 'admin-google-photo-caption';
          cap.textContent = label;

          tile.appendChild(cb);
          tile.appendChild(thumb);
          tile.appendChild(cap);
          grid.appendChild(tile);
        });
      }

      async function loadPage(token) {
        status.textContent = 'Loading…';
        var u = new URL(mediaUrl, window.location.origin);
        if (token) u.searchParams.set('page_token', token);
        var res = await fetch(u.toString(), { headers: { 'Accept': 'application/json' } });
        var data = await res.json().catch(function () { return null; });
        if (!res.ok || !data || data.error) {
          status.textContent = '';
          alert((data && data.error) ? data.error : 'Could not load Google Photos.');
          return;
        }
        renderItems(data.items || []);
        nextToken = data.nextPageToken || null;
        btnMore.hidden = !nextToken;
        status.textContent = '';
        refreshStatus();
      }

      btnLoad.addEventListener('click', function () {
        grid.innerHTML = '';
        nextToken = null;
        btnMore.hidden = true;
        loadPage(null);
      });
      btnMore.addEventListener('click', function () {
        if (nextToken) loadPage(nextToken);
      });

      var fileInput = document.getElementById('photos');
      if (fileInput) {
        fileInput.addEventListener('change', function () {
          grid.querySelectorAll('input[type="checkbox"][name="google_photos_media_ids[]"]:checked').forEach(function (cb) {
            enforceMax(cb);
          });
          refreshStatus();
        });
      }

      refreshStatus();
    })();
  </script>
@endpush
