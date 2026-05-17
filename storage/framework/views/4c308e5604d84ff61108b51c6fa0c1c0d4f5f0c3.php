<?php
  $cvStyleKey = $project->contactedViaStyleKey();
  $coStyleKey = $project->conversationOnStyleKey();
?>
<div class="cp-subtle <?php echo e($lineClass ?? ''); ?>">
  Contact via:
  <?php if($cvStyleKey !== null): ?>
    <span class="cp-contacted-via-value cp-contacted-via-value--<?php echo e($cvStyleKey); ?>"><?php echo e($project->contactedViaDisplay()); ?></span>
  <?php else: ?>
    <?php echo e($project->contactedViaDisplay()); ?>

  <?php endif; ?>
</div>
<?php if(filled($project->conversation_on)): ?>
  <div class="cp-subtle <?php echo e($lineClass ?? ''); ?>" style="margin-top:0.35rem;">
    Conversation on:
    <?php if($coStyleKey !== null): ?>
      <span class="cp-contacted-via-value cp-contacted-via-value--<?php echo e($coStyleKey); ?>"><?php echo e($project->conversationOnDisplay()); ?></span>
    <?php else: ?>
      <?php echo e($project->conversationOnDisplay()); ?>

    <?php endif; ?>
  </div>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\client_projects\_contacted_via_display.blade.php ENDPATH**/ ?>