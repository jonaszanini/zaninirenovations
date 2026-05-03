<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Thank you']); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>
  <p>
    Thank you for trusting us with your <strong><?php echo e($project->name); ?></strong> project.
    It was a pleasure working with you to transform your space.
  </p>
  <p>
    If you are happy with the results, please take a moment to leave us a review on Google.
    Your feedback helps our local business grow and means a lot to our team.
  </p>
  <p style="margin:24px 0;">
    <a href="<?php echo e($reviewUrl); ?>" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;font-weight:600;">
      Leave a Google review
    </a>
  </p>
  <p style="font-size:13px;color:#6b7280;">Or copy and paste this link into your browser:<br>
    <a href="<?php echo e($reviewUrl); ?>" style="color:#024768;"><?php echo e($reviewUrl); ?></a>
  </p>
  <p style="margin-top:24px;">Best regards,<br>
    Jonas Zanini
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/emails/client_projects/client_thank_you.blade.php ENDPATH**/ ?>