

<?php $__env->startSection('heading', 'Paving stone quotations'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="<?php echo e(route('admin.quotations.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All quotation types
      </a>
    </div>
    <a href="<?php echo e(route('admin.quotations.paving.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New paving quotation
    </a>
  </div>

  <?php if($quotations->isEmpty()): ?>
    <div class="admin-card" style="text-align:center;padding:36px 24px;">
      <p class="admin-form-help" style="margin:0;">
        No paving quotations yet. Open the calculator to build an Ottawa-aligned estimate from the price database.
      </p>
    </div>
  <?php else: ?>
    <div class="admin-card">
      <div class="admin-table-scroll">
        <table class="admin-table fq-list-table">
          <thead>
            <tr>
              <th>Number</th>
              <th>Client</th>
              <th>Draft</th>
              <th>Final quote</th>
              <th>Linked project</th>
              <th>Updated</th>
              <th style="width: 260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $quotations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><strong><?php echo e($q->quote_number); ?></strong></td>
                <td><?php echo e($q->client_name ?: '—'); ?></td>
                <td><?php echo e($q->is_draft ? 'Yes' : '—'); ?></td>
                <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($q->final_quote_total)); ?></td>
                <td>
                  <?php if($q->project): ?>
                    <a href="<?php echo e(route('admin.client-projects.show', $q->project)); ?>#quotation"><?php echo e($q->project->name); ?></a>
                  <?php else: ?>
                    <span class="admin-form-help">—</span>
                  <?php endif; ?>
                </td>
                <td><span class="admin-form-help"><?php echo e($q->updated_at?->format('d/m/Y')); ?></span></td>
                <td>
                  <div class="fq-list-actions">
                    <a href="<?php echo e(route('admin.quotations.paving.edit', $q)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <form method="post" action="<?php echo e(route('admin.quotations.paving.destroy', $q)); ?>" class="cp-inline-form"
                          onsubmit="return confirm('Delete <?php echo e($q->quote_number); ?>?');">
                      <?php echo csrf_field(); ?>
                      <?php echo method_field('DELETE'); ?>
                      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm"><i class="fa fa-trash"></i></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
      <?php echo e($quotations->links()); ?>

    </div>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/quotations/paving/index.blade.php ENDPATH**/ ?>