

<?php $__env->startSection('heading', 'Fence Quotations'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar fq-list-toolbar">
    <div class="admin-toolbar-group">
      <a href="<?php echo e(route('admin.quotations.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> All quotation types
      </a>
      <a href="<?php echo e(route('admin.quotations.fence.config')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-cog"></i> Configuration
      </a>
    </div>
    <a href="<?php echo e(route('admin.quotations.fence.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New fence quotation
    </a>
  </div>

  <?php if($quotations->isEmpty()): ?>
    <div class="admin-card" style="text-align:center;padding:36px 24px;">
      <p class="admin-form-help" style="margin:0;">
        No fence quotations yet. Click <strong>New fence quotation</strong> to open the calculator.
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
              <th>Height</th>
              <th>Linear ft</th>
              <th>Gates</th>
              <th>Total</th>
              <th>Linked project</th>
              <th>Updated</th>
              <th style="width: 260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $quotations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $q): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $totalFt = collect($q->sides ?? [])->sum(fn ($s) => (float) ($s['length_ft'] ?? 0));
              ?>
              <tr>
                <td><strong><?php echo e($q->quote_number); ?></strong></td>
                <td><?php echo e($q->client_name); ?></td>
                <td><?php echo e(rtrim(rtrim(number_format((float) $q->fence_height_ft, 2), '0'), '.')); ?>'</td>
                <td><?php echo e(rtrim(rtrim(number_format($totalFt, 2), '0'), '.')); ?></td>
                <td><?php echo e($q->gate_count); ?></td>
                <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($q->grand_total)); ?></td>
                <td>
                  <?php if($q->project): ?>
                    <a href="<?php echo e(route('admin.client-projects.show', $q->project)); ?>#quotation">
                      <?php echo e($q->project->name); ?>

                    </a>
                  <?php else: ?>
                    <span class="admin-form-help">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="admin-form-help"><?php echo e($q->updated_at?->format('d/m/Y')); ?></span>
                </td>
                <td>
                  <div class="fq-list-actions">
                    <a href="<?php echo e(route('admin.quotations.fence.edit', $q)); ?>"
                       class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-pencil"></i> Edit
                    </a>
                    <a href="<?php echo e(route('admin.quotations.fence.pdf', $q)); ?>"
                       target="_blank" rel="noopener"
                       class="admin-btn admin-btn--ghost admin-btn--sm">
                      <i class="fa fa-file-pdf-o"></i> PDF
                    </a>
                    <form method="post"
                          action="<?php echo e(route('admin.quotations.fence.destroy', $q)); ?>"
                          class="cp-inline-form"
                          onsubmit="return confirm('Delete fence quotation <?php echo e($q->quote_number); ?>? This cannot be undone.');">
                      <?php echo csrf_field(); ?>
                      <?php echo method_field('DELETE'); ?>
                      <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
                        <i class="fa fa-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:16px;">
      <?php echo e($quotations->links()); ?>

    </div>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/quotations/fence/index.blade.php ENDPATH**/ ?>