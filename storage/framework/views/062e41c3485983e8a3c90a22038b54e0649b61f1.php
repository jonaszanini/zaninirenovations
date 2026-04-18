<?php
  $isHourly = $project->isHourly();
  $totalHours = $project->totalHours();
  $netAfterHst = $project->netProfitAfterHst();
  $hourlyRate = $project->effectiveHourlyRate();
  $configuredRate = $isHourly ? (float) ($project->hourly_rate ?? 0) : null;

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
  <form action="<?php echo e(route('admin.client-projects.time.store', $project)); ?>" method="post" class="cp-finance-form" id="cp-time-form" data-cp-time-form>
    <?php echo csrf_field(); ?>
    <div class="cp-time-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="<?php echo e(now()->toDateString()); ?>">
      </div>
      <div class="admin-form-group">
        <label>Start time</label>
        <select name="start_time" class="cp-time-select" data-cp-time-start>
          <option value="">—</option>
          <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label>End time</label>
        <select name="end_time" class="cp-time-select" data-cp-time-end>
          <option value="">—</option>
          <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
      </div>
      <div class="admin-form-group">
        <label>Hours
          <span class="cp-hint" title="Optional. Filled automatically when you pick start + end. Leave empty + leave end blank to start an open entry.">?</span>
        </label>
        <input type="text" name="hours"
               class="cp-hours-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="auto"
               data-cp-time-hours>
      </div>
      <div class="admin-form-group">
        <label>Note (optional)</label>
        <input type="text" name="note" maxlength="500">
      </div>
    </div>
    <p class="admin-form-help cp-time-help">
      Tip: pick <strong>Start</strong> + <strong>End</strong> and Hours fills in automatically. Pick only <strong>Start</strong> to log an in-progress entry — you can come back later and add the End time.
    </p>
    <?php $__errorArgs = ['hours'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    <?php $__errorArgs = ['end_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    <div class="cp-time-submit">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add time entry</button>
    </div>
  </form>

  <?php if($project->timeEntries->isNotEmpty()): ?>
    <table class="admin-table cp-time-table">
      <thead>
        <tr>
          <th>Date</th>
          <th>Start</th>
          <th>End</th>
          <th>Hours</th>
          <th>Note</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $project->timeEntries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php $isOpen = $entry->isOpen(); ?>
          <tr data-cp-entry-row="<?php echo e($entry->id); ?>" class="<?php echo e($isOpen ? 'cp-row-open' : ''); ?>">
            <td data-label="Date"><?php echo e(\App\Models\ClientProject::formatDate($entry->occurred_on)); ?></td>
            <td data-label="Start"><?php echo e(\App\Models\ClientProjectTimeEntry::formatTime($entry->start_time)); ?></td>
            <td data-label="End">
              <?php echo e(\App\Models\ClientProjectTimeEntry::formatTime($entry->end_time)); ?>

              <?php if($isOpen): ?>
                <span class="cp-badge" style="background:#f59e0b20;color:#b45309;margin-left:6px;">In progress</span>
              <?php endif; ?>
            </td>
            <td data-label="Hours"><?php echo e($entry->hours !== null ? number_format((float) $entry->hours, 2) : '—'); ?></td>
            <td data-label="Note"><?php echo e($entry->note ?: '—'); ?></td>
            <td class="cp-time-actions" data-label="Actions">
              <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-toggle="<?php echo e($entry->id); ?>">
                <?php echo e($isOpen ? 'Set end' : 'Edit'); ?>

              </button>
              <form action="<?php echo e(route('admin.client-projects.time.destroy', [$project, $entry])); ?>" method="post" onsubmit="return confirm('Delete this time entry?');" class="cp-inline-form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
          <tr class="cp-time-edit-row" data-cp-edit-row="<?php echo e($entry->id); ?>" hidden>
            <td colspan="6">
              <form action="<?php echo e(route('admin.client-projects.time.update', [$project, $entry])); ?>" method="post" class="cp-time-edit-form" data-cp-time-form>
                <?php echo csrf_field(); ?>
                <?php echo method_field('PUT'); ?>
                <div class="cp-time-form-grid">
                  <div class="admin-form-group">
                    <label>Date</label>
                    <input type="date" name="occurred_on" required value="<?php echo e(optional($entry->occurred_on)->toDateString()); ?>">
                  </div>
                  <div class="admin-form-group">
                    <label>Start time</label>
                    <select name="start_time" class="cp-time-select" data-cp-time-start>
                      <option value="">—</option>
                      <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($val); ?>" <?php if($entry->start_time === $val): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                  </div>
                  <div class="admin-form-group">
                    <label>End time</label>
                    <select name="end_time" class="cp-time-select" data-cp-time-end>
                      <option value="">—</option>
                      <?php $__currentLoopData = $timeOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($val); ?>" <?php if($entry->end_time === $val): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                  </div>
                  <div class="admin-form-group">
                    <label>Hours</label>
                    <input type="text" name="hours"
                           class="cp-hours-input"
                           inputmode="decimal"
                           autocomplete="off"
                           placeholder="auto"
                           value="<?php echo e($entry->hours !== null ? rtrim(rtrim(number_format((float) $entry->hours, 2, '.', ''), '0'), '.') : ''); ?>"
                           data-cp-time-hours>
                  </div>
                  <div class="admin-form-group">
                    <label>Note</label>
                    <input type="text" name="note" maxlength="500" value="<?php echo e($entry->note); ?>">
                  </div>
                </div>
                <div class="cp-time-submit cp-time-edit-actions">
                  <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-edit-cancel="<?php echo e($entry->id); ?>">Cancel</button>
                  <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save changes</button>
                </div>
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
      <div class="cp-profit-label">Total hours (closed)</div>
      <div class="cp-profit-value"><?php echo e(number_format($totalHours, 2)); ?></div>
    </div>
    <?php if($isHourly): ?>
      <div class="cp-profit-tile">
        <div class="cp-profit-label">Configured rate</div>
        <div class="cp-profit-value">
          <?php echo e($configuredRate > 0 ? \App\Models\ClientProject::formatMoney($configuredRate).' / h' : 'Not set'); ?>

        </div>
      </div>
      <div class="cp-profit-tile cp-profit-tile--highlight">
        <div class="cp-profit-label">Earned (rate × hours)</div>
        <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($project->hourlyAmountEarned())); ?></div>
      </div>
    <?php endif; ?>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value <?php echo e($netAfterHst >= 0 ? 'is-positive' : 'is-negative'); ?>">
        <?php echo e(\App\Models\ClientProject::formatMoney($netAfterHst)); ?>

      </div>
    </div>
    <?php if (! ($isHourly)): ?>
      <div class="cp-profit-tile cp-profit-tile--highlight">
        <div class="cp-profit-label">Effective hourly rate</div>
        <div class="cp-profit-value">
          <?php echo e($hourlyRate === null ? 'N/A' : \App\Models\ClientProject::formatMoney($hourlyRate).' / h'); ?>

        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_time.blade.php ENDPATH**/ ?>