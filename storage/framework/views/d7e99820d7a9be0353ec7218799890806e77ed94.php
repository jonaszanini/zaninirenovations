<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Work begins tomorrow']); ?>
  <p><strong>Reminder:</strong> this project is scheduled to <strong>begin tomorrow</strong>.</p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px;font-size:14px;">
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Project</td>
      <td style="padding:6px 0;"><strong><?php echo e($project->name); ?></strong></td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Client</td>
      <td style="padding:6px 0;"><?php echo e($project->client_name); ?></td>
    </tr>
    <tr>
      <td style="padding:6px 12px 6px 0;color:#6b7280;">Start date</td>
      <td style="padding:6px 0;"><strong><?php echo e($startFormatted); ?></strong></td>
    </tr>
    <?php if($project->end_date): ?>
      <tr>
        <td style="padding:6px 12px 6px 0;color:#6b7280;">End date</td>
        <td style="padding:6px 0;"><?php echo e(\App\Support\EasternStandardTimeDisplay::formatCalendarLongDateWithWeekday($project->end_date)); ?></td>
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
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\admin_commencement_eve.blade.php ENDPATH**/ ?>