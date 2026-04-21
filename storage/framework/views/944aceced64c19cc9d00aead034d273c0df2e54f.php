

<?php $__env->startSection('heading', 'Projects'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage every project from quote request to completion. Status changes and emails are logged automatically.</p>
    <a href="<?php echo e(route('admin.client-projects.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  <form method="get" action="<?php echo e(route('admin.client-projects.index')); ?>" class="cp-filters">
    <div class="cp-filter">
      <label for="cp-filter-q">Search</label>
      <input type="text" id="cp-filter-q" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Project or client name">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-status">Status</label>
      <select id="cp-filter-status" name="status">
        <option value="">All statuses</option>
        <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($key); ?>" <?php if($filters['status'] === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>
    </div>
    <div class="cp-filter">
      <label for="cp-filter-from">From</label>
      <input type="date" id="cp-filter-from" name="from" value="<?php echo e($filters['from']); ?>">
    </div>
    <div class="cp-filter">
      <label for="cp-filter-to">To</label>
      <input type="date" id="cp-filter-to" name="to" value="<?php echo e($filters['to']); ?>">
    </div>
    <div class="cp-filter cp-filter--actions">
      <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Apply</button>
      <a href="<?php echo e(route('admin.client-projects.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Reset</a>
    </div>
  </form>

  <?php if($projects->isEmpty()): ?>
    <div class="admin-table-wrap">
      <div class="admin-empty">
        <p>No projects match these filters.</p>
        <a href="<?php echo e(route('admin.client-projects.create')); ?>" class="admin-btn admin-btn--primary">Create your first project</a>
      </div>
    </div>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table cp-table">
        <thead>
          <tr>
            <th>Client</th>
            <th>Project</th>
            <th>Status</th>
            <th>Dates</th>
            <th class="cp-col-amount">Total</th>
            <th class="cp-col-amount">Paid</th>
            <th>Last updated</th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $projects; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $project): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $url = route('admin.client-projects.show', $project); ?>
            <tr class="cp-row-link" data-href="<?php echo e($url); ?>" tabindex="0" role="link" aria-label="Open project <?php echo e($project->name); ?>">
              <td>
                <strong><?php echo e($project->client_name); ?></strong>
                <?php if($project->client_email): ?>
                  <div class="cp-subtle"><?php echo e($project->client_email); ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php echo e($project->name); ?>

              </td>
              <td>
                <span class="cp-badge" style="background: <?php echo e($statusColors[$project->status] ?? '#6b7280'); ?>20; color: <?php echo e($statusColors[$project->status] ?? '#6b7280'); ?>;">
                  <?php echo e($statusLabels[$project->status] ?? $project->status); ?>

                </span>
              </td>
              <td>
                <div><?php echo e(\App\Models\ClientProject::formatDate($project->start_date)); ?></div>
                <div class="cp-subtle">→ <?php echo e(\App\Models\ClientProject::formatDate($project->end_date)); ?></div>
              </td>
              <td class="cp-col-amount">
                <?php if($project->isHourly()): ?>
                  <?php
                    $hourlyTotal = round((float) ($project->hours_logged ?? 0) * (float) ($project->hourly_rate ?? 0), 2);
                  ?>
                  <?php echo e(\App\Models\ClientProject::formatMoney($hourlyTotal)); ?>

                  <div class="cp-subtle"><?php echo e(number_format((float) ($project->hours_logged ?? 0), 2)); ?> h @ <?php echo e(\App\Models\ClientProject::formatMoney((float) ($project->hourly_rate ?? 0))); ?>/h</div>
                <?php else: ?>
                  <?php echo e(\App\Models\ClientProject::formatMoney($project->total_amount)); ?>

                <?php endif; ?>
              </td>
              <td class="cp-col-amount"><?php echo e(number_format($project->percentPaid(), 0)); ?>%</td>
              <td><?php echo e(optional($project->updated_at)->format('d/m/Y H:i')); ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody>
      </table>
    </div>
    <div class="admin-pagination">
      <?php echo e($projects->links('pagination.admin')); ?>

    </div>
  <?php endif; ?>

  
  <script>
    (function () {
      var rows = document.querySelectorAll('.cp-row-link[data-href]');
      if (!rows.length) return;

      function shouldIgnore(target) {
        // Don't hijack clicks on real interactive elements inside the row
        // (links, buttons, inputs, selects). Today there are none, but this
        // keeps the row safe if any get added later.
        return !!target.closest('a, button, input, select, textarea, label');
      }

      rows.forEach(function (row) {
        var url = row.getAttribute('data-href');

        row.addEventListener('click', function (ev) {
          if (shouldIgnore(ev.target)) return;
          if (ev.ctrlKey || ev.metaKey || ev.shiftKey) {
            window.open(url, '_blank');
          } else {
            window.location.href = url;
          }
        });

        // Middle-click (auxclick, button === 1) → open in new tab.
        row.addEventListener('auxclick', function (ev) {
          if (ev.button !== 1 || shouldIgnore(ev.target)) return;
          ev.preventDefault();
          window.open(url, '_blank');
        });

        // Keyboard: Enter / Space activates the row when it has focus.
        row.addEventListener('keydown', function (ev) {
          if (ev.key !== 'Enter' && ev.key !== ' ') return;
          if (shouldIgnore(ev.target)) return;
          ev.preventDefault();
          window.location.href = url;
        });
      });
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/index.blade.php ENDPATH**/ ?>