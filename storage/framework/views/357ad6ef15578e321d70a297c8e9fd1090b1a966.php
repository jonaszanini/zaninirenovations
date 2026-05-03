<?php if($paginator->hasPages()): ?>
  <nav class="admin-pagination-nav" role="navigation" aria-label="Pagination">
    <?php if($paginator->onFirstPage()): ?>
      <span class="is-disabled">Previous</span>
    <?php else: ?>
      <a href="<?php echo e($paginator->previousPageUrl()); ?>" rel="prev">Previous</a>
    <?php endif; ?>

    <?php $__currentLoopData = $elements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $element): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php if(is_string($element)): ?>
        <span class="is-disabled"><?php echo e($element); ?></span>
      <?php endif; ?>

      <?php if(is_array($element)): ?>
        <?php $__currentLoopData = $element; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page => $url): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if($page == $paginator->currentPage()): ?>
            <span aria-current="page"><?php echo e($page); ?></span>
          <?php else: ?>
            <a href="<?php echo e($url); ?>"><?php echo e($page); ?></a>
          <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

    <?php if($paginator->hasMorePages()): ?>
      <a href="<?php echo e($paginator->nextPageUrl()); ?>" rel="next">Next</a>
    <?php else: ?>
      <span class="is-disabled">Next</span>
    <?php endif; ?>
  </nav>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/pagination/admin.blade.php ENDPATH**/ ?>