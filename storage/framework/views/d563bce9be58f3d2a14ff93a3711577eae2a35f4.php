<?php
  $cvStyleKey = $project->contactedViaStyleKey();
?>
<div class="cp-subtle <?php echo e($lineClass ?? ''); ?>">
  Contacted via:
  <?php if($cvStyleKey !== null): ?>
    <span class="cp-contacted-via-value cp-contacted-via-value--<?php echo e($cvStyleKey); ?>"><?php echo e($project->contactedViaDisplay()); ?></span>
  <?php else: ?>
    <?php echo e($project->contactedViaDisplay()); ?>

  <?php endif; ?>
</div>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_contacted_via_display.blade.php ENDPATH**/ ?>