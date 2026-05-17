<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Quote request received']); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>
  <p>
    Thank you for your quote request. We have started working on your
    <strong><?php echo e($project->name); ?></strong> quote and will be in touch shortly.
  </p>
  <p>If you need to share any additional information in the meantime, just reply to this email.</p>
  <p style="margin-top:24px;">Best regards,<br>
    <?php echo e($siteName); ?>

  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_quote_received.blade.php ENDPATH**/ ?>