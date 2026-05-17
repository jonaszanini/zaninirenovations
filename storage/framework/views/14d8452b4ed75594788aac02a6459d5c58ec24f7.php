<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Follow up with client']); ?>
  <p>The quote for <strong><?php echo e($project->name); ?></strong> was sent and it has been 2 days — time to check in with the client for feedback.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;"><?php echo e($project->client_name); ?></td>
    </tr>
    <?php if($project->client_email): ?>
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Email</td>
        <td style="padding:6px 0;"><a href="mailto:<?php echo e($project->client_email); ?>" style="color:#024768;"><?php echo e($project->client_email); ?></a></td>
      </tr>
    <?php endif; ?>
    <?php if($project->client_phone): ?>
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Phone</td>
        <td style="padding:6px 0;"><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone)); ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <p style="margin-top:24px;">
    <a href="<?php echo e(url('/admin/client-projects/'.$project->id)); ?>" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
      Open project in admin
    </a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\admin_quote_follow_up.blade.php ENDPATH**/ ?>