<?php
  use Illuminate\Support\Carbon;

  $clientName = $project->client_name;

  $startFormatted = $project->start_date ? Carbon::parse($project->start_date)->format('F j, Y') : null;
  $endFormatted = $project->end_date ? Carbon::parse($project->end_date)->format('F j, Y') : null;
  $sameDay = $project->start_date && $project->end_date
      && Carbon::parse($project->start_date)->isSameDay(Carbon::parse($project->end_date));

  $rate = $project->hourly_rate !== null
      ? \App\Models\ClientProject::formatMoney((float) $project->hourly_rate)
      : null;

  $invoiceCadence = $sameDay
      ? 'on the same day the work is completed'
      : 'weekly, or upon completion of the project';

  // Build a human-readable single field for the scheduled date so the body
  // flows naturally whether it's a one-day visit or a multi-day project.
  if ($sameDay) {
      $scheduledDate = $startFormatted;
  } elseif ($startFormatted && $endFormatted) {
      $scheduledDate = $startFormatted.' to '.$endFormatted;
  } else {
      $scheduledDate = $startFormatted ?: $endFormatted;
  }
?>

<?php $__env->startComponent('emails.client_projects.layout', ['title' => 'Service confirmation']); ?>
  <p>Dear <?php echo e($clientName); ?>,</p>

  <p>
    I am writing to confirm that your project, <strong><?php echo e($project->name); ?></strong>,
    is officially scheduled. I have reserved the following date for your service:
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

  <?php if($rate): ?>
    <p>
      As we previously discussed, this project will be billed at an hourly rate of
      <strong><?php echo e($rate); ?>/hr + HST</strong>.
    </p>
  <?php else: ?>
    <p>
      As we previously discussed, this project will be billed at the hourly rate we agreed on,
      plus HST.
    </p>
  <?php endif; ?>

  <p>A few things to keep in mind as we prepare for the start date:</p>

  <p style="margin:12px 0;">
    <strong>Materials:</strong> If I am sourcing materials for your project, these will be billed
    at cost plus any applicable delivery or handling fees.
  </p>

  <p style="margin:12px 0;">
    <strong>Insurance:</strong> For your peace of mind, all work is fully covered by my
    $3,000,000.00 liability insurance and performed strictly according to O. Reg. 332/12
    (Ontario Building Code).
  </p>

  <p style="margin:16px 0 8px 0;"><strong>Payment information:</strong></p>
  <p style="margin:0 0 12px 0;">
    Invoices for hourly work are issued <?php echo e($invoiceCadence); ?>.
    I accept payment via:
  </p>
  <ul style="margin:0 0 12px 20px;padding:0;">
    <li style="margin:4px 0;"><strong>e-Transfer:</strong> send to zaninirenovations@gmail.com</li>
    <li style="margin:4px 0;"><strong>Cash:</strong> payable upon receipt of invoice</li>
  </ul>

  <?php if(!empty($calendarLink)): ?>
    <p style="margin:20px 0 8px 0;"><strong>Save the date:</strong></p>
    <p style="margin:0 0 12px 0;">
      Add the scheduled <?php echo e($sameDay ? 'date' : 'dates'); ?> to your calendar with one click:
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
    If you have any questions or need to adjust the schedule before we begin,
    please let me know as soon as possible.
  </p>

  <p style="margin-top:24px;">
    Best regards,<br><br>
    Jonas Zanini<br>
    Owner, <?php echo e($siteName); ?><br>
    (647) 834-7570<br>
    <a href="https://zaninirenovation.ca">zaninirenovation.ca</a>
  </p>
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/emails/client_projects/client_service_confirmation.blade.php ENDPATH**/ ?>