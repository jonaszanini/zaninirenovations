

<?php $__env->startSection('title', 'Latest projects'); ?>
<?php $__env->startSection('heading', 'Latest projects'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage portfolio projects and photos (WebP, JPEG, or PNG — stored as WebP, max edge <?php echo e((int) config('webp.max_edge_px', 1920)); ?>px; up to 10 per project).</p>
    <a href="<?php echo e(route('admin.projects.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  <?php if($projects->isEmpty()): ?>
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No projects yet.</p>
        <a href="<?php echo e(route('admin.projects.create')); ?>" class="admin-btn admin-btn--primary">Add your first project</a>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Title</th>
            <th>Category</th>
            <th>Photos</th>
            <th>Updated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr>
              <td><strong><?php echo e($project->title); ?></strong></td>
              <td><span class="admin-badge"><?php echo e($project->categoryLabel()); ?></span></td>
              <td><?php echo e($project->photos_count); ?> / 10</td>
              <td><?php echo e($project->updated_at->format('M j, Y')); ?></td>
              <td style="white-space:nowrap;">
                <a href="<?php echo e(route('admin.projects.edit', $project)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Edit</a>
                <form action="<?php echo e(route('admin.projects.destroy', $project)); ?>" method="post" style="display:inline;" onsubmit="return confirm('Delete this project and all photos?');">
                  <?php echo csrf_field(); ?>
                  <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
    <div class="admin-pagination">
      <?php echo e($projects->links('pagination.admin')); ?>

    </div>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\projects\index.blade.php ENDPATH**/ ?>