@extends('admin.layouts.app')

@section('heading', 'New project')

@section('content')
  <div class="admin-card">
    <form action="{{ route('admin.portfolio.store') }}" method="post" enctype="multipart/form-data">
      @csrf
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="{{ old('title') }}" required maxlength="255">
        @error('title')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required>{{ old('description') }}</textarea>
        @error('description')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      @php
        $selectedCategoryIds = old('project_category_ids', []);
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
        <input type="date" id="completed_on" name="completed_on" value="{{ old('completed_on', now()->toDateString()) }}" required>
        <p class="admin-form-hint">When the work was finished (used to order projects on the site — newest completion first).</p>
        @error('completed_on')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-group">
        <label for="photos">Photos</label>
        <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
        <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP, resized so the longest side is at most {{ (int) config('webp.max_edge_px', 1920) }}px, and recompressed for the web; originals are not kept. Up to 15 photos per project. Display order matches the order you select (first = top). To crop a photo after it is saved, edit the project and use the crop button on the thumbnail.</p>
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
          <div
            class="admin-google-photos"
            data-picker-create-url="{{ route('admin.google-photos.picker-sessions.create') }}"
            data-picker-media-url-template="{{ route('admin.google-photos.picker-sessions.media', ['sessionId' => '__SESSION__']) }}"
            data-max-total="15"
          >
            <div class="admin-google-photos-head">
              <span class="admin-google-photos-account">Connected as <strong>{{ auth()->user()->googlePhotosToken->google_email }}</strong></span>
              <button
                type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-admin-google-disconnect
                data-disconnect-url="{{ route('admin.google-photos.disconnect') }}"
              >Disconnect</button>
            </div>
            <p class="admin-form-hint" style="margin:0 0 0.5rem;">Open Google Photos Picker, choose photos (you can open your album there and select all), then import the picked results here.</p>
            <div class="admin-google-photos-toolbar">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-google-open-picker>Open Google Photos Picker</button>
              <span class="admin-google-photos-status" data-google-status></span>
              <span class="admin-google-photos-hint" data-google-hint hidden></span>
            </div>
            <div class="admin-google-photos-grid" data-google-grid></div>
            <div data-google-picked-fields></div>
          </div>
          <p class="admin-form-hint">Combined total cannot exceed 15 (file uploads + picked Google photos).</p>
        @else
          <p class="admin-form-hint" style="margin-top:0;">
            <a class="admin-btn admin-btn--ghost admin-btn--sm" href="{{ route('admin.google-photos.connect') }}">Connect Google Photos</a>
            <span style="margin-left:0.5rem;color:var(--admin-muted);">Required to open Google Photos Picker.</span>
          </p>
        @endif
        @error('google_picker_items')
          <div class="admin-error">{{ $message }}</div>
        @enderror
        @error('google_picker_items.*')
          <div class="admin-error">{{ $message }}</div>
        @enderror
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save project</button>
        <a href="{{ route('admin.portfolio.index') }}" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
@endsection

@push('scripts')
  <script>
    (function () {
      var root = document.querySelector('.admin-google-photos');
      if (!root) return;

      var createSessionUrl = root.getAttribute('data-picker-create-url');
      var mediaUrlTemplate = root.getAttribute('data-picker-media-url-template');
      var maxTotal = parseInt(root.getAttribute('data-max-total') || '15', 10);
      var btnOpen = root.querySelector('[data-google-open-picker]');
      var grid = root.querySelector('[data-google-grid]');
      var status = root.querySelector('[data-google-status]');
      var hintEl = root.querySelector('[data-google-hint]');
      var pickedFields = root.querySelector('[data-google-picked-fields]');

      function selectedCount() {
        return grid.querySelectorAll('input[type="checkbox"][data-picker-checkbox]:checked').length;
      }

      function pickedTileCount() {
        return grid.querySelectorAll('.admin-google-photo-tile').length;
      }

      function pickerThumbUrl(baseUrl) {
        if (!baseUrl || typeof baseUrl !== 'string') return '';
        var t = baseUrl.trim();
        if (t.indexOf('=') !== -1) return t;
        return t + '=w240-h240-c';
      }

      function refreshStatus() {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var picked = pickedTileCount();
        var checked = selectedCount();
        status.textContent =
          picked + ' from Google (' + checked + ' checked for import) · ' + files + ' uploads · max ' + maxTotal + ' total';
      }

      function applyDefaultChecks() {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var maxGoogle = Math.max(0, maxTotal - files);
        grid.querySelectorAll('input[data-picker-checkbox]').forEach(function (c) {
          c.checked = true;
        });
        while (selectedCount() > maxGoogle) {
          var list = grid.querySelectorAll('input[data-picker-checkbox]:checked');
          if (!list.length) break;
          list[list.length - 1].checked = false;
        }
        refreshStatus();
      }

      function enforceMax(checkbox) {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var maxGoogle = Math.max(0, maxTotal - files);
        if (!checkbox.checked) return;
        if (selectedCount() > maxGoogle) {
          checkbox.checked = false;
          alert('Too many Google Photos selected for current upload count (max ' + maxTotal + ' total).');
        }
      }

      function renderItems(items) {
        grid.innerHTML = '';
        items.forEach(function (it) {
          if (!it || !it.baseUrl) return;
          var url = pickerThumbUrl(it.baseUrl);
          var label = it.filename || 'Photo';

          var tile = document.createElement('label');
          tile.className = 'admin-google-photo-tile';
          tile.setAttribute('data-picker-payload', JSON.stringify({
            id: it.id || '',
            baseUrl: it.baseUrl || '',
            mimeType: it.mimeType || '',
            filename: it.filename || ''
          }));

          var thumb = document.createElement('div');
          thumb.className = 'admin-google-photo-thumb';

          var cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.className = 'admin-google-photo-tile-check';
          cb.setAttribute('data-picker-checkbox', '1');
          cb.addEventListener('change', function () { enforceMax(cb); refreshStatus(); });
          thumb.appendChild(cb);

          var img = document.createElement('img');
          img.src = url;
          img.alt = '';
          img.loading = 'lazy';
          img.decoding = 'async';
          img.referrerPolicy = 'no-referrer';
          img.addEventListener('error', function () {
            thumb.classList.add('is-fallback');
          });
          thumb.appendChild(img);

          var fallback = document.createElement('div');
          fallback.className = 'admin-google-photo-thumb-fallback';
          fallback.textContent = 'Preview unavailable (still imports if checked)';
          thumb.appendChild(fallback);

          var cap = document.createElement('div');
          cap.className = 'admin-google-photo-caption';
          cap.textContent = label;

          tile.appendChild(thumb);
          tile.appendChild(cap);
          grid.appendChild(tile);
        });
        applyDefaultChecks();
      }

      function parseSecondsDuration(value, fallbackMs) {
        if (!value || typeof value !== 'string' || !value.endsWith('s')) return fallbackMs;
        var n = parseFloat(value.slice(0, -1));
        if (!isFinite(n) || n <= 0) return fallbackMs;
        return Math.round(n * 1000);
      }

      async function fetchPickedMedia(sessionId, maxItems) {
        var mediaUrl = (mediaUrlTemplate || '').replace('__SESSION__', encodeURIComponent(sessionId));
        if (maxItems && maxItems > 0) {
          var sep = mediaUrl.indexOf('?') === -1 ? '?' : '&';
          mediaUrl += sep + 'max_items=' + encodeURIComponent(String(maxItems));
        }
        if (!mediaUrl || mediaUrl === mediaUrlTemplate) throw new Error('Picker media URL is not configured.');
        var res = await fetch(mediaUrl, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        var data = await res.json().catch(function () { return null; });
        if (!res.ok || !data || data.error) throw new Error((data && data.error) ? data.error : 'Could not read picked media.');
        return data;
      }

      async function openPicker(popup, allowedSlots) {
        status.textContent = 'Starting picker…';
        if (hintEl) {
          hintEl.hidden = true;
          hintEl.textContent = '';
        }
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? (csrfMeta.getAttribute('content') || '') : '';
        if (!csrfToken) throw new Error('Missing CSRF token. Reload the page and try again.');
        var res = await fetch(createSessionUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken,
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ max_item_count: allowedSlots })
        });
        var data = await res.json().catch(function () { return null; });
        if (!res.ok || !data || data.error) {
          throw new Error((data && data.error) ? data.error : ('Could not open Google Photos picker (HTTP ' + res.status + ').'));
        }
        var pickerUrl = data.pickerUriAutoclose || data.pickerUri;
        var sessionId = data.id;
        if (!pickerUrl || !sessionId) throw new Error('Picker session response was incomplete.');
        if (!popup || popup.closed) throw new Error('Popup blocked. Allow popups for this site and try again.');
        popup.location.href = pickerUrl;

        status.textContent = 'Waiting for selection…';
        var pollEvery = parseSecondsDuration(data.pollingConfig && data.pollingConfig.pollInterval, 2000);
        var serverTimeoutMs = parseSecondsDuration(data.pollingConfig && data.pollingConfig.timeoutIn, 120000);
        var started = Date.now();
        var deadline = started + serverTimeoutMs;
        var pickerClosedAt = null;
        var postCloseBudgetMs = 240000;

        while (Date.now() < deadline) {
          await new Promise(function (resolve) { setTimeout(resolve, pollEvery); });
          if (popup && popup.closed && pickerClosedAt === null) {
            pickerClosedAt = Date.now();
            deadline = Math.max(deadline, pickerClosedAt + postCloseBudgetMs);
            status.textContent = 'Finishing up with Google Photos… (albums can take a bit)';
          }
          var picked;
          try {
            picked = await fetchPickedMedia(sessionId, allowedSlots);
          } catch (ignore) {
            continue;
          }
          if (picked.ready) {
            renderItems(picked.items || []);
            status.textContent = '';
            if (hintEl && (picked.items || []).length === 0) {
              hintEl.textContent = 'No items were selected in Google Photos Picker.';
              hintEl.hidden = false;
            }
            refreshStatus();
            return;
          }
        }
        status.textContent = '';
        if (hintEl) {
          hintEl.textContent = 'Google Photos did not finish returning your picks in time. That often happens if you browse a long time in an album before choosing. Try again, confirm your selection in the picker, or pick fewer photos at once.';
          hintEl.hidden = false;
        }
        refreshStatus();
      }

      btnOpen.addEventListener('click', async function () {
        btnOpen.disabled = true;
        try {
          var fileInput = document.getElementById('photos');
          var files = fileInput && fileInput.files ? fileInput.files.length : 0;
          var allowedSlots = Math.max(0, maxTotal - files);
          if (allowedSlots < 1) throw new Error('No free slots left for Google photos. Remove selected uploads first.');
          var popup = window.open('', '_blank');
          if (!popup) throw new Error('Popup blocked. Allow popups for this site and try again.');
          popup.document.write('Loading Google Photos Picker...');
          await openPicker(popup, allowedSlots);
        } catch (e) {
          status.textContent = '';
          alert(e && e.message ? e.message : 'Could not open Google Photos Picker.');
        } finally {
          btnOpen.disabled = false;
          refreshStatus();
        }
      });

      var fileInput = document.getElementById('photos');
      if (fileInput) {
        fileInput.addEventListener('change', function () {
          grid.querySelectorAll('input[type="checkbox"][data-picker-checkbox]:checked').forEach(function (cb) {
            enforceMax(cb);
          });
          refreshStatus();
        });
      }

      var form = root.closest('form');
      if (form && pickedFields) {
        form.addEventListener('submit', function () {
          pickedFields.innerHTML = '';
          grid.querySelectorAll('.admin-google-photo-tile').forEach(function (tile) {
            var cb = tile.querySelector('input[type="checkbox"][data-picker-checkbox]');
            if (!cb || !cb.checked) return;
            var payload = tile.getAttribute('data-picker-payload') || '';
            if (!payload) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'google_picker_items[]';
            input.value = payload;
            pickedFields.appendChild(input);
          });
        });
      }

      refreshStatus();
    })();
  </script>
@endpush


