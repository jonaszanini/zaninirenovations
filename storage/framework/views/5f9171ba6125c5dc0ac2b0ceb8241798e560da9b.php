

<?php $__env->startSection('heading', 'Clients'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <form method="get" action="<?php echo e(route('admin.clients.index')); ?>" class="cp-clients-toolbar-search">
      <input type="search"
             name="q"
             value="<?php echo e($q); ?>"
             placeholder="Search name, email, phone…"
             class="admin-input"
             style="max-width:320px;">
      <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-search"></i> Search
      </button>
    </form>
    <a href="<?php echo e(route('admin.clients.create')); ?>" class="admin-btn admin-btn--primary admin-btn--sm">
      <i class="fa fa-plus"></i> New client
    </a>
  </div>

  <div class="admin-card">
    <div class="admin-table-scroll">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th style="width:100px;">Projects</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><strong><?php echo e($client->name); ?></strong></td>
              <td><?php echo e($client->email ?: '—'); ?></td>
              <td><?php echo e($client->phone ? \App\Models\ClientProject::formatPhone($client->phone) : '—'); ?></td>
              <td><?php echo e($client->projects_count); ?></td>
              <td>
                <a href="<?php echo e(route('admin.clients.edit', $client)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
                  <i class="fa fa-pencil"></i> Edit
                </a>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="5" class="admin-form-help">No clients yet. Add one or create a project — existing project contacts can be imported via migration.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <?php if($clients->hasPages()): ?>
      <div style="margin-top:16px;"><?php echo e($clients->links()); ?></div>
    <?php endif; ?>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\clients\index.blade.php ENDPATH**/ ?>