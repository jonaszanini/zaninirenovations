

<?php $__env->startSection('title', 'Categories'); ?>
<?php $__env->startSection('heading', 'Categories'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">These labels appear on the Latest projects section of the public site. Slugs are for internal use and stable links.</p>
    <a href="<?php echo e(route('admin.categories.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New category
    </a>
  </div>

  <?php if($categories->isEmpty()): ?>
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No categories yet.</p>
        <a href="<?php echo e(route('admin.categories.create')); ?>" class="admin-btn admin-btn--primary">Add a category</a>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Order</th>
            <th>Name</th>
            <th>Slug</th>
            <th>Projects</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><?php echo e($category->sort_order); ?></td>
              <td><strong><?php echo e($category->name); ?></strong></td>
              <td><code class="admin-code"><?php echo e($category->slug); ?></code></td>
              <td><?php echo e($category->projects_count); ?></td>
              <td style="white-space:nowrap;">
                <a href="<?php echo e(route('admin.categories.edit', $category)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="<?php echo e(route('admin.categories.destroy', $category)); ?>" method="post" style="display:inline;" onsubmit="return confirm('Delete this category? This cannot be undone.');">
                  <?php echo csrf_field(); ?>
                  <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm" <?php if($category->projects_count > 0): ?> disabled title="Reassign projects first" <?php endif; ?>>Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\categories\index.blade.php ENDPATH**/ ?>