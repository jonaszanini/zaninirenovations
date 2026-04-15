

<?php $__env->startSection('title', 'Edit project'); ?>
<?php $__env->startSection('heading', 'Edit project'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card">
    <form action="<?php echo e(route('admin.projects.update', $project)); ?>" method="post" enctype="multipart/form-data">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>
      <div class="admin-form-group">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?php echo e(old('title', $project->title)); ?>" required maxlength="255">
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
        <textarea id="description" name="description" required><?php echo e(old('description', $project->description)); ?></textarea>
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
        $selectedCategoryIds = old('project_category_ids', $project->categories->pluck('id')->map(fn ($id) => (string) $id)->all());
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
        <input type="date" id="completed_on" name="completed_on" value="<?php echo e(old('completed_on', optional($project->completed_on)->format('Y-m-d'))); ?>" required>
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

      <?php if($project->photos->isNotEmpty()): ?>
        <div class="admin-form-group">
          <label>Photos — order &amp; remove</label>
          <p class="admin-form-hint">Drag by the grip icon to reorder. Use rotate to fix orientation (90°). Unchecked photos are saved in this order. Check “Remove” to delete from storage.</p>
          <ul id="admin-photo-sortable" class="admin-photo-sortable-list">
            <?php $__currentLoopData = $project->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li class="admin-photo-sortable-item" data-photo-id="<?php echo e($photo->id); ?>">
                <span class="admin-photo-drag-handle" title="Drag to reorder"><i class="fa fa-bars"></i></span>
                <div class="admin-photo-sortable-thumb">
                  <img src="<?php echo e($photo->url()); ?>" alt="">
                </div>
                <div class="admin-photo-sortable-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° counter-clockwise"
                    data-post-url="<?php echo e(route('admin.projects.photos.rotate', [$project, $photo])); ?>"
                    data-direction="ccw">
                    <i class="fa fa-undo" aria-hidden="true"></i><span class="admin-sr-only"> Rotate left</span>
                  </button>
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm js-rotate-photo"
                    title="Rotate 90° clockwise"
                    data-post-url="<?php echo e(route('admin.projects.photos.rotate', [$project, $photo])); ?>"
                    data-direction="cw">
                    <i class="fa fa-repeat" aria-hidden="true"></i><span class="admin-sr-only"> Rotate right</span>
                  </button>
                </div>
                <label class="admin-photo-remove-label">
                  <input type="checkbox" name="delete_photos[]" value="<?php echo e($photo->id); ?>"> Remove
                </label>
              </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
          <div id="admin-photo-sequence-fields" aria-hidden="true"></div>
          <?php $__errorArgs = ['photo_sequence'];
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
      <?php endif; ?>

      <?php
        $canAdd = 10 - $project->photos->count();
      ?>
      <?php if($canAdd > 0): ?>
        <div class="admin-form-group">
          <label for="photos">Add photos</label>
          <input type="file" id="photos" name="photos[]" accept=".webp,.jpg,.jpeg,.png,image/webp,image/jpeg,image/png" multiple>
          <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). New files are converted to WebP and scaled so the longest side is at most <?php echo e((int) config('webp.max_edge_px', 1920)); ?>px; originals are not kept. Up to <?php echo e($canAdd); ?> more (10 total).</p>
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
      <?php else: ?>
        <p class="admin-form-hint">Maximum of 10 photos reached. Remove one to add more.</p>
      <?php endif; ?>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="<?php echo e(route('admin.projects.index')); ?>" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php if($project->photos->isNotEmpty()): ?>
  <?php $__env->startPush('scripts'); ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
      (function () {
        var csrf = <?php echo json_encode(csrf_token(), 15, 512) ?>;

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
  <?php $__env->stopPush(); ?>
<?php endif; ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/projects/edit.blade.php ENDPATH**/ ?>