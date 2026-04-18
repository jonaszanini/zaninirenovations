<?php
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();

  // Build 15-minute time options from 8:00 AM to 8:00 PM inclusive.
  // Key is 24-hour HH:MM (used as <option value>), label is 12-hour formatted.
  $timeOptions = [];
  for ($minutes = 8 * 60; $minutes <= 20 * 60; $minutes += 15) {
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    $value = sprintf('%02d:%02d', $h, $m);
    $hour12 = $h % 12 === 0 ? 12 : $h % 12;
    $ampm = $h < 12 ? 'AM' : 'PM';
    $timeOptions[$value] = sprintf('%d:%02d %s', $hour12, $m, $ampm);
  }
?>

<div class="admin-card">
  <h3>Log time</h3>
  <form action="<?php echo e(route('admin.client-projects.time.store', $project)); ?>" method="post" class="cp-finance-form" id="cp-time-form">
    <?php echo csrf_field(); ?>
    <div class="cp-time-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="<?php echo e(now()->toDateString()); ?>">
      </div>
      <div class="admin-form-group">
        <label>Start time</label>
        <select id="cp-time-start" class="cp-time-select">
          <option value="">—</option>
          <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label>End time</label>
        <select id="cp-time-end" class="cp-time-select">
          <option value="">—</option>
          <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label>Hours
          <span class="cp-hint" title="Decimal hours allowed — e.g. 4.5 for 4h 30m. Auto-filled when you pick start and end times.">?</span>
        </label>
        <input type="text" id="cp-time-hours" name="hours" required
               class="cp-hours-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="e.g. 4.5">
      </div>
      <div class="admin-form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="500">
      </div>
    </div>
    <p class="admin-form-help cp-time-help">
      Tip: pick <strong>Start</strong> + <strong>End</strong> (15-min steps, 8 AM – 8 PM) and Hours is filled for you, or just type Hours directly.
    </p>
    <div class="cp-time-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
    </div>
  </form>

  <?php if($project->timeEntries->isNotEmpty()): ?>
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Hours</th><th>Note</th><th></th></tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $project->timeEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><?php echo e(\App\Models\ClientProject::formatDate($entry->occurred_on)); ?></td>
            <td><?php echo e(number_format((float) $entry->hours, 2)); ?></td>
            <td><?php echo e($entry->note ?: '—'); ?></td>
            <td>
              <form action="<?php echo e(route('admin.client-projects.time.destroy', [$project, $entry])); ?>" method="post" onsubmit="return confirm('Delete this time entry?');" class="cp-inline-form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="admin-form-help">No time entries recorded yet.</p>
  <?php endif; ?>
</div>

<div class="admin-card cp-profit-card">
  <h3>Time summary</h3>
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total hours</div>
      <div class="cp-profit-value"><?php echo e(number_format($totalHours, 2)); ?></div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value <?php echo e($netAfterHst >= 0 ? 'is-positive' : 'is-negative'); ?>">
        <?php echo e(\App\Models\ClientProject::formatMoney($netAfterHst)); ?>

      </div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Effective hourly rate</div>
      <div class="cp-profit-value">
        <?php echo e($hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h'); ?>

      </div>
    </div>
  </div>
</div>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_time.blade.php ENDPATH**/ ?>