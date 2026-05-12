

<?php $__env->startSection('heading', 'Projects'); ?>

<?php $__env->startSection('content'); ?>
  <?php
    $toggleQueryBase = fn (string $listMode) => array_merge(
        \Illuminate\Support\Arr::except(request()->query(), ['page']),
        ['list' => $listMode],
    );
    $isOngoingList = (($filters['list'] ?? 'ongoing') === 'ongoing');
  ?>

  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">Manage every project from quote request to completion. Status changes and emails are logged automatically.</p>
    <a href="<?php echo e(route('admin.client-projects.create')); ?>" class="admin-btn admin-btn--primary">
      <i class="fa fa-plus"></i> New project
    </a>
  </div>

  <div class="cp-projects-scope-bar">
    <div class="cp-projects-scope-head">
      <span class="cp-projects-scope-label">Show</span>
      <div class="cp-projects-scope-toggles">
        <a href="<?php echo e(route('admin.client-projects.index', $toggleQueryBase('ongoing'))); ?>"
           class="cp-projects-scope-chip <?php echo e($isOngoingList ? 'cp-projects-scope-chip--active' : ''); ?>"
           aria-current="<?php echo e($isOngoingList ? 'page' : 'false'); ?>"
           title="Hide Completed and Rejected (focus on work in motion).">
          <i class="fa fa-spinner"></i> Ongoing pipeline
        </a>
        <a href="<?php echo e(route('admin.client-projects.index', $toggleQueryBase('all'))); ?>"
           class="cp-projects-scope-chip <?php echo e(! $isOngoingList ? 'cp-projects-scope-chip--active' : ''); ?>"
           aria-current="<?php echo e(! $isOngoingList ? 'page' : 'false'); ?>"
           title="Include Completed and Rejected with every other status.">
          <i class="fa fa-th-large"></i> All projects
        </a>
      </div>
    </div>
    <p class="cp-projects-scope-hint">
      <i class="fa fa-filter"></i> By default Completed and Rejected are hidden. Use All projects or the Status filter below to surface them.
    </p>
  </div>

  <form method="get" action="<?php echo e(route('admin.client-projects.index')); ?>" class="cp-filters">
    <input type="hidden" name="list" value="<?php echo e($filters['list'] ?? 'ongoing'); ?>">
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
        <p><?php echo e((($filters['list'] ?? 'ongoing') === 'ongoing') && ($filters['status'] ?? '') === ''
          ? 'No ongoing pipeline projects match these filters. Try All projects or adjust search and dates.'
          : 'No projects match these filters.'); ?></p>
        <p class="admin-form-help" style="margin-top:12px;">
          <?php if(($filters['list'] ?? 'ongoing') === 'ongoing' && ($filters['status'] ?? '') === ''): ?>
            Completed and rejected are hidden in the default view.&nbsp;<a href="<?php echo e(route('admin.client-projects.index', $toggleQueryBase('all'))); ?>">Show all projects</a>
            &nbsp;
          <?php endif; ?>
          <a href="<?php echo e(route('admin.client-projects.create')); ?>">Create a new project</a>
        </p>
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

                <?php echo $__env->make('admin.client_projects._contacted_via_display', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
              </td>
              <td>
                <span class="cp-badge" style="background: <?php echo e($statusColors[$project->status] ?? '#6b7280'); ?>20; color: <?php echo e($statusColors[$project->status] ?? '#6b7280'); ?>;">
                  <?php echo e($statusLabels[$project->status] ?? $project->status); ?>

                </span>
              </td>
              <td>
                <?php if($project->showsVisitLayoutInProjectList()): ?>
                  <div><?php echo e(\App\Models\ClientProject::formatDate($project->visit_date)); ?></div>
                  <div class="cp-subtle">Visit</div>
                <?php else: ?>
                  <div><?php echo e(\App\Models\ClientProject::formatDate($project->start_date)); ?></div>
                  <div class="cp-subtle">→ <?php echo e(\App\Models\ClientProject::formatDate($project->end_date)); ?></div>
                <?php endif; ?>
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
              <td><?php echo e(\App\Models\ClientProject::formatDateTime($project->updated_at)); ?></td>
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