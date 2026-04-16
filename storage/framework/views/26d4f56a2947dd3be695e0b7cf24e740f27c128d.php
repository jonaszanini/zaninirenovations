<?php $__env->startSection('title', 'New project'); ?>
<?php $__env->startSection('heading', 'New project'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card">
    <form action="<?php echo e(route('admin.projects.store')); ?>" method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?php echo e(old('title')); ?>" required maxlength="255">
        <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="admin-form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" required><?php echo e(old('description')); ?></textarea>
        <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <?php
        $selectedCategoryIds = old('project_category_ids', []);
      ?>
      <div class="admin-form-group">
        <span class="admin-form-group-label">Categories</span>
        <p class="admin-form-hint" style="margin-top:0;margin-bottom:0.65rem;">Select all that apply (e.g. tile and wood).</p>
        <div class="admin-category-checkboxes">
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <label class="admin-checkbox-tile">
              <input type="checkbox" name="project_category_ids[]" value="<?php echo e($cat->id); ?>" <?php if(in_array((string) $cat->id, array_map('strval', (array) $selectedCategoryIds), true)): echo 'checked'; endif; ?>>
              <span><?php echo e($cat->name); ?></span>
            </label>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php $__errorArgs = ['project_category_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['project_category_ids.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="admin-form-group">
        <label for="completed_on">Completed on</label>
        <input type="date" id="completed_on" name="completed_on" value="<?php echo e(old('completed_on', now()->toDateString())); ?>" required>
        <p class="admin-form-hint">When the work was finished (used to order projects on the site — newest completion first).</p>
        <?php $__errorArgs = ['completed_on'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="admin-form-group">
        <label for="photos">Photos</label>
        <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
        <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP, resized so the longest side is at most <?php echo e((int) config('webp.max_edge_px', 1920)); ?>px, and recompressed for the web; originals are not kept. Up to 15 photos per project. Display order matches the order you select (first = top).</p>
        <?php $__errorArgs = ['photos'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['photos.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-group">
        <span class="admin-form-group-label">Google Photos</span>
        <?php if(auth()->user()->googlePhotosToken): ?>
          <div
            class="admin-google-photos"
            data-picker-create-url="<?php echo e(route('admin.google-photos.picker-sessions.create')); ?>"
            data-picker-media-url-template="<?php echo e(route('admin.google-photos.picker-sessions.media', ['sessionId' => '__SESSION__'])); ?>"
            data-max-total="15"
          >
            <div class="admin-google-photos-head">
              <span class="admin-google-photos-account">Connected as <strong><?php echo e(auth()->user()->googlePhotosToken->google_email); ?></strong></span>
              <button
                type="button"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-admin-google-disconnect
                data-disconnect-url="<?php echo e(route('admin.google-photos.disconnect')); ?>"
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
        <?php else: ?>
          <p class="admin-form-hint" style="margin-top:0;">
            <a class="admin-btn admin-btn--ghost admin-btn--sm" href="<?php echo e(route('admin.google-photos.connect')); ?>">Connect Google Photos</a>
            <span style="margin-left:0.5rem;color:var(--admin-muted);">Required to open Google Photos Picker.</span>
          </p>
        <?php endif; ?>
        <?php $__errorArgs = ['google_picker_items'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <?php $__errorArgs = ['google_picker_items.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
          <div class="admin-error"><?php echo e($message); ?></div>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save project</button>
        <a href="<?php echo e(route('admin.projects.index')); ?>" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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

      function refreshStatus() {
        var fileInput = document.getElementById('photos');
        var files = fileInput && fileInput.files ? fileInput.files.length : 0;
        var g = selectedCount();
        status.textContent = 'Selected: ' + g + ' Google + ' + files + ' uploads (max ' + maxTotal + ' total)';
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
          var url = it.baseUrl ? (it.baseUrl + '=w220-h220-c') : '';
          var label = it.filename || 'Photo';

          var tile = document.createElement('label');
          tile.className = 'admin-google-photo-tile';
          tile.setAttribute('data-picker-payload', JSON.stringify({
            id: it.id || '',
            baseUrl: it.baseUrl || '',
            mimeType: it.mimeType || '',
            filename: it.filename || ''
          }));

          var cb = document.createElement('input');
          cb.type = 'checkbox';
          cb.setAttribute('data-picker-checkbox', '1');
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

      function parseSecondsDuration(value, fallbackMs) {
        if (!value || typeof value !== 'string' || !value.endsWith('s')) return fallbackMs;
        var n = parseFloat(value.slice(0, -1));
        if (!isFinite(n) || n <= 0) return fallbackMs;
        return Math.round(n * 1000);
      }

      async function fetchPickedMedia(sessionId) {
        var mediaUrl = (mediaUrlTemplate || '').replace('__SESSION__', encodeURIComponent(sessionId));
        if (!mediaUrl || mediaUrl === mediaUrlTemplate) throw new Error('Picker media URL is not configured.');
        var res = await fetch(mediaUrl, {
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        var data = await res.json().catch(function () { return null; });
        if (!res.ok || !data || data.error) throw new Error((data && data.error) ? data.error : 'Could not read picked media.');
        return data;
      }

      async function openPicker(popup) {
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
          body: JSON.stringify({ max_item_count: maxTotal })
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
        var timeoutMs = parseSecondsDuration(data.pollingConfig && data.pollingConfig.timeoutIn, 120000);
        var started = Date.now();
        while (Date.now() - started < timeoutMs) {
          await new Promise(function (resolve) { setTimeout(resolve, pollEvery); });
          var picked = await fetchPickedMedia(sessionId);
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
        throw new Error('Timed out waiting for Google Photos Picker selection.');
      }

      btnOpen.addEventListener('click', async function () {
        btnOpen.disabled = true;
        try {
          var popup = window.open('', '_blank');
          if (!popup) throw new Error('Popup blocked. Allow popups for this site and try again.');
          popup.document.write('Loading Google Photos Picker...');
          await openPicker(popup);
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
<?php $__env->stopPush(); ?>


<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\projects\create.blade.php ENDPATH**/ ?>