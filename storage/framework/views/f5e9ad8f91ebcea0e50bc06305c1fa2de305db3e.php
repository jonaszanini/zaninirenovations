<?php
  $typeLower = strtolower($quotation->typeLabel());
?>

<?php $__env->startComponent('emails.client_projects.layout', ['title' => $quotation->typeLabel().' – '.$project->name]); ?>
  <p>Hi <?php echo e($project->client_name); ?>,</p>

  <p>
    It was a pleasure discussing your <strong><?php echo e($project->name); ?></strong> with you recently.
    Please find the detailed <?php echo e($typeLower); ?> attached as a PDF for your review.
  </p>

  <p>
    This proposal outlines the scope of the work. All planned work is designed to
    meet or exceed the standards set by the Ontario Building Code (O.Reg. 332/12)
    to ensure the safety and longevity of your home.
  </p>

  <p>
    If you have any questions regarding it or would like to adjust any of the
    details, please feel free to reach out. I am happy to hop on a quick call or
    respond via email.
  </p>

  <p>I look forward to the possibility of working together to bring this project to life.</p>

  <p style="margin-top:24px;">
    Best regards,<br>
    Jonas Zanini<br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_quotation.blade.php ENDPATH**/ ?>