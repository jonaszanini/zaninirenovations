

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
      <div class="admin-form-group">
        <label for="category">Category</label>
        <select id="category" name="category" required>
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($slug); ?>" <?php if(old('category', $project->category) === $slug): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['category'];
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
          <p class="admin-form-hint">Set display order (0 = first). Check “Remove” to delete a photo from storage.</p>
          <div class="admin-photo-grid">
            <?php $__currentLoopData = $project->photos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="admin-photo-item">
                <img src="<?php echo e($photo->url()); ?>" alt="">
                <div class="admin-form-group">
                  <label for="order-<?php echo e($photo->id); ?>">Order</label>
                  <input type="number" id="order-<?php echo e($photo->id); ?>" name="photo_order[<?php echo e($photo->id); ?>]" min="0" max="99" value="<?php echo e(old('photo_order.'.$photo->id, $photo->sort_order)); ?>">
                </div>
                <label style="display:flex;align-items:center;gap:0.35rem;font-size:0.8rem;color:var(--admin-muted);cursor:pointer;">
                  <input type="checkbox" name="delete_photos[]" value="<?php echo e($photo->id); ?>"> Remove
                </label>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>
        </div>
      <?php endif; ?>

      <?php
        $canAdd = 10 - $project->photos->count();
      ?>
      <?php if($canAdd > 0): ?>
        <div class="admin-form-group">
          <label for="photos">Add photos (WebP)</label>
          <input type="file" id="photos" name="photos[]" accept=".webp,image/webp" multiple>
          <p class="admin-form-hint">You can add up to <?php echo e($canAdd); ?> more (10 total).</p>
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

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/projects/edit.blade.php ENDPATH**/ ?>