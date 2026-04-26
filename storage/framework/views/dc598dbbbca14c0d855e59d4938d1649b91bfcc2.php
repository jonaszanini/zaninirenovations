

<?php $__env->startSection('heading', 'Quotations'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card fq-hub-intro">
    <h3 style="margin:0 0 8px;">Standalone quotations</h3>
    <p class="admin-form-help" style="margin:0;">
      These calculators generate quotes that don't have to be tied to a client project.
      You can still link a saved estimate to an existing project later to add it as a
      line item on that project's Quotation.
    </p>
  </div>

  <div class="fq-hub-grid">
    
    <a href="<?php echo e(route('admin.quotations.fence.index')); ?>" class="fq-hub-card">
      <div class="fq-hub-card-icon" aria-hidden="true">
        <i class="fa fa-th-large"></i>
      </div>
      <div class="fq-hub-card-body">
        <h4>Fence</h4>
        <p>
          Calculate materials and labour for 6' and 8' pressure-treated wood fences
          against the Ontario Building Code structural rules.
        </p>
        <span class="fq-hub-card-cta">Open calculator <i class="fa fa-arrow-right"></i></span>
      </div>
    </a>

    <a href="<?php echo e(route('admin.quotations.standard.index')); ?>" class="fq-hub-card">
      <div class="fq-hub-card-icon" aria-hidden="true">
        <i class="fa fa-table"></i>
      </div>
      <div class="fq-hub-card-body">
        <h4>Standard</h4>
        <p>
          Build a quote from material line items (with optional 13% HST) plus hourly labour.
          Save standalone or link the total into a project quotation.
        </p>
        <span class="fq-hub-card-cta">Open standard quotation <i class="fa fa-arrow-right"></i></span>
      </div>
    </a>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/quotations/index.blade.php ENDPATH**/ ?>