

<?php $__env->startSection('heading', 'Email / SMS log'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">
      Audit trail of every email and SMS sent by the system. Newest first.
      Each row records the recipient, what was sent, and whether the
      delivery attempt succeeded.
    </p>
  </div>

  <form method="get" action="<?php echo e(route('admin.communications.index')); ?>" class="cp-filters">
    <div class="cp-filter">
      <label for="comm-filter-q">Search</label>
      <input type="text" id="comm-filter-q" name="q" value="<?php echo e($filters['q']); ?>"
             placeholder="Client, project, recipient, subject…">
    </div>
    <div class="cp-filter">
      <label for="comm-filter-channel">Channel</label>
      <select id="comm-filter-channel" name="channel">
        <option value="">All channels</option>
        <option value="email" <?php if($filters['channel'] === 'email'): echo 'selected'; endif; ?>>Email</option>
        <option value="sms" <?php if($filters['channel'] === 'sms'): echo 'selected'; endif; ?>>SMS</option>
      </select>
    </div>
    <div class="cp-filter">
      <label for="comm-filter-reason">Reason</label>
      <select id="comm-filter-reason" name="reason">
        <option value="">All reasons</option>
        <?php $__currentLoopData = $reasonOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($key); ?>" <?php if($filters['reason'] === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>
    <div class="cp-filter">
      <label for="comm-filter-direction">Audience</label>
      <select id="comm-filter-direction" name="direction">
        <option value="">All</option>
        <option value="to_client" <?php if($filters['direction'] === 'to_client'): echo 'selected'; endif; ?>>To client</option>
        <option value="to_admin" <?php if($filters['direction'] === 'to_admin'): echo 'selected'; endif; ?>>To admin (internal)</option>
      </select>
    </div>
    <div class="cp-filter cp-filter--actions">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
      <a href="<?php echo e(route('admin.communications.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
    </div>
  </form>

  <?php if($logs->isEmpty()): ?>
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No communications match these filters yet.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table cp-table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Project</th>
            <th>Type</th>
            <th>Reason</th>
            <th>Description</th>
            <th>Recipient</th>
            <th>Status</th>
            <th>Date &amp; time</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $isSms = $log->channel === \App\Models\ClientCommunicationLog::CHANNEL_SMS;
              $isAdminBound = $log->direction === \App\Models\ClientCommunicationLog::DIRECTION_TO_ADMIN;
              $isFailed = $log->isFailed();
              $reasonColor = $log->reasonColor();
              $projectUrl = $log->client_project_id
                ? route('admin.client-projects.show', $log->client_project_id)
                : null;
            ?>
            <tr class="<?php echo \Illuminate\Support\Arr::toCssClasses(['cp-comm-row-failed' => $isFailed]) ?>">
              <td>
                <strong><?php echo e($log->clientNameLabel()); ?></strong>
                <?php if($isAdminBound): ?>
                  <div class="cp-subtle"><i class="fa fa-user-secret" aria-hidden="true"></i> Internal</div>
                <?php endif; ?>
              </td>
              <td>
                <?php if($projectUrl): ?>
                  <a href="<?php echo e($projectUrl); ?>"><?php echo e($log->projectNameLabel()); ?></a>
                <?php else: ?>
                  <?php echo e($log->projectNameLabel()); ?>

                <?php endif; ?>
              </td>
              <td>
                <span class="cp-badge" style="background: <?php echo e($isSms ? '#10b98120' : '#3b82f620'); ?>; color: <?php echo e($isSms ? '#10b981' : '#3b82f6'); ?>;">
                  <i class="fa fa-<?php echo e($isSms ? 'comment' : 'envelope'); ?>" aria-hidden="true"></i>
                  <?php echo e($log->channelLabel()); ?>

                </span>
              </td>
              <td>
                <span class="cp-badge" style="background: <?php echo e($reasonColor); ?>20; color: <?php echo e($reasonColor); ?>;">
                  <?php echo e($log->reasonLabel()); ?>

                </span>
              </td>
              <td>
                <?php if($log->subject): ?>
                  <div><strong><?php echo e($log->subject); ?></strong></div>
                <?php endif; ?>
                <div><?php echo e($log->description); ?></div>
                <?php if($log->error): ?>
                  <div class="cp-subtle" style="color:#b45309;">
                    <i class="fa fa-exclamation-triangle"></i> <?php echo e($log->error); ?>

                  </div>
                <?php endif; ?>
              </td>
              <td>
                <div><?php echo e($log->recipient); ?></div>
                <?php if($log->cc): ?>
                  <div class="cp-subtle">cc: <?php echo e($log->cc); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if($isFailed): ?>
                  <span class="cp-badge" style="background:#ef444420; color:#ef4444;">
                    <i class="fa fa-times-circle"></i> Failed
                  </span>
                <?php else: ?>
                  <span class="cp-badge" style="background:#22c55e20; color:#22c55e;">
                    <i class="fa fa-check-circle"></i> Sent
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php echo e(\App\Models\ClientProject::formatDateTime($log->sent_at)); ?>

              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
    <div class="admin-pagination">
      <?php echo e($logs->links('pagination.admin')); ?>

    </div>
  <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\communications\index.blade.php ENDPATH**/ ?>