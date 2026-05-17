

<?php $__env->startSection('heading', 'New category'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card admin-card--narrow">
    <form action="<?php echo e(route('admin.categories.store')); ?>" method="post">
      <?php echo csrf_field(); ?>
      <div class="admin-form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="120" placeholder="e.g. Kitchen renovation">
        <?php $__errorArgs = ['name'];
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
        <label for="sort_order">Sort order</label>
        <input type="number" id="sort_order" name="sort_order" value="<?php echo e(old('sort_order', 0)); ?>" min="0" max="32767">
        <p class="admin-form-hint">Lower numbers appear first in dropdowns. Default 0.</p>
        <?php $__errorArgs = ['sort_order'];
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
      <p class="admin-form-hint" style="margin-top:-0.5rem;">A URL-friendly slug is created automatically from the name (e.g. “Kitchen renovation” → <code class="admin-code">kitchen-renovation</code>). You can change it when editing.</p>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save category</button>
        <a href="<?php echo e(route('admin.categories.index')); ?>" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\categories\create.blade.php ENDPATH**/ ?>