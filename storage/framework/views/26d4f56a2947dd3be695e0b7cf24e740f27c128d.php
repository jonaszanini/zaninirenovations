

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
      <div class="admin-form-group">
        <label for="project_category_id">Category</label>
        <select id="project_category_id" name="project_category_id" required>
          <option value="">Select category</option>
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($cat->id); ?>" <?php if((string) old('project_category_id') === (string) $cat->id): echo 'selected'; endif; ?>><?php echo e($cat->name); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['project_category_id'];
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
        <p class="admin-form-hint">WebP, JPEG, or PNG (10 MB max each). Files are converted to WebP, resized so the longest side is at most <?php echo e((int) config('webp.max_edge_px', 1920)); ?>px, and recompressed for the web; originals are not kept. Display order matches the order you select (first = top).</p>
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
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save project</button>
        <a href="<?php echo e(route('admin.projects.index')); ?>" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\projects\create.blade.php ENDPATH**/ ?>