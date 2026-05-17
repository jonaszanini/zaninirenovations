<?php $__env->startComponent('emails.client_projects.layout', ['title' => $project->status === \App\Models\ClientProject::STATUS_VISIT_SCHEDULED ? 'Visit / quote prep' : 'Quote still pending']); ?>
  <p><strong>Reminder:</strong>
    <?php if($project->status === \App\Models\ClientProject::STATUS_VISIT_SCHEDULED): ?>
      this project is in <strong>Visit scheduled</strong> — follow up after the site visit and prepare/send the quote when ready.
    <?php else: ?>
      a quote still needs to be prepared and sent for this project.
    <?php endif; ?>
  </p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Project</td>
      <td style="padding:6px 0;"><strong><?php echo e($project->name); ?></strong></td>
    </tr>
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
    <?php if($project->client_address): ?>
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">Address</td>
        <td style="padding:6px 0;"><?php echo e($project->client_address); ?></td>
      </tr>
    <?php endif; ?>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Created</td>
      <td style="padding:6px 0;"><?php echo e(\App\Models\ClientProject::formatDateTime($project->created_at)); ?></td>
    </tr>
  </table>

  <?php if($project->notes): ?>
    <p style="margin-top:16px;"><strong>Notes:</strong></p>
    <p style="white-space:pre-wrap;background:#f9fafb;padding:12px;border-radius:6px;border:1px solid #e5e7eb;"><?php echo e($project->notes); ?></p>
  <?php endif; ?>

  <p style="margin-top:24px;">
    <a href="<?php echo e(url('/admin/client-projects/'.$project->id)); ?>" style="display:inline-block;background:#024768;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;font-weight:600;">
      Open project in admin
    </a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\admin_quote_reminder.blade.php ENDPATH**/ ?>