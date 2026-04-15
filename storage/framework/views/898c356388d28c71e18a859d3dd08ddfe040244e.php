

<?php $__env->startSection('title', 'Edit category'); ?>
<?php $__env->startSection('heading', 'Edit category'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card">
    <form action="<?php echo e(route('admin.categories.update', $category)); ?>" method="post">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>
      <div class="admin-form-group">
        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?php echo e(old('name', $category->name)); ?>" required maxlength="120">
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
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" value="<?php echo e(old('slug', $category->slug)); ?>" required maxlength="80" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" title="Lowercase letters, numbers, and hyphens only">
        <p class="admin-form-hint">Used internally. Change only if you know you need a new identifier.</p>
        <?php $__errorArgs = ['slug'];
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
        <input type="number" id="sort_order" name="sort_order" value="<?php echo e(old('sort_order', $category->sort_order)); ?>" min="0" max="32767">
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
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">Save changes</button>
        <a href="<?php echo e(route('admin.categories.index')); ?>" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\categories\edit.blade.php ENDPATH**/ ?>