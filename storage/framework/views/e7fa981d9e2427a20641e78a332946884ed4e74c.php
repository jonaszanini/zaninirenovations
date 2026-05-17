<?php
  use Illuminate\Support\Carbon;

  $clientName = $project->client_name;
  $startFormatted = $project->start_date ? \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($project->start_date) : null;
  $endFormatted = $project->end_date ? \App\Support\EasternStandardTimeDisplay::formatCalendarLongDate($project->end_date) : null;
  $sameDay = $project->start_date && $project->end_date
      && Carbon::parse($project->start_date)->isSameDay(Carbon::parse($project->end_date));

  if ($sameDay) {
      $scheduledDate = $startFormatted;
  } elseif ($startFormatted && $endFormatted) {
      $scheduledDate = $startFormatted.' to '.$endFormatted;
  } else {
      $scheduledDate = $startFormatted ?: $endFormatted;
  }
?>

<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Project schedule']); ?>
  <p>Dear <?php echo e($clientName); ?>,</p>

  <p>
    Just a quick note with the scheduled <?php echo e($sameDay ? 'date' : 'dates'); ?> for your project,
    <strong><?php echo e($project->name); ?></strong>:
  </p>

  <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
    <strong>Scheduled <?php echo e($sameDay ? 'date' : 'dates'); ?>:</strong>
    <?php echo e($scheduledDate); ?>

  </p>

  <?php if(filled($project->client_address)): ?>
    <p style="margin:12px 0 12px 16px;border-left:3px solid #024768;padding:8px 12px;background:#f9fafb;">
      <strong>Service address:</strong>
      <?php echo e($project->client_address); ?>

    </p>
  <?php endif; ?>

  <?php if(!empty($calendarLink)): ?>
    <p style="margin:20px 0 8px 0;"><strong>Add to your calendar:</strong></p>
    <p style="margin:0 0 12px 0;">
      You can add the <?php echo e($sameDay ? 'date' : 'dates'); ?> to Google Calendar with one click:
    </p>
    <p style="margin:12px 0;">
      <a href="<?php echo e($calendarLink); ?>"
         target="_blank"
         rel="noopener noreferrer"
         style="display:inline-block;padding:10px 18px;background:#024768;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
        Add to Google Calendar
      </a>
    </p>
  <?php endif; ?>

  <p>
    If you need to adjust the schedule, please reply to this email or give me a call as soon as possible.
  </p>

  <p style="margin-top:24px;">
    Best regards,<br><br>
    Jonas Zanini<br>
    Owner, <?php echo e($siteName); ?><br>
    (647) 834-7570<br>
    <a href="https://zaninirenovations.ca">zaninirenovations.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\emails\client_projects\client_schedule_dates.blade.php ENDPATH**/ ?>