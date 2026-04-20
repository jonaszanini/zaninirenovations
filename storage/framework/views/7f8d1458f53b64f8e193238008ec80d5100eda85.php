

<?php $__env->startSection('heading', $project->name); ?>

<?php $__env->startSection('content'); ?>
  <?php
    $statusLabels = \App\Models\ClientProject::statusLabels();
    $statusColors = \App\Models\ClientProject::statusColors();
    $isCompleted = $project->isCompleted();
    $isRejected = $project->isRejected();
    $isAccepted = $project->isAccepted();
  ?>

  
  <div class="admin-card cp-header-card">
    <div class="cp-header-row">
      <div class="cp-header-title">
        <div class="cp-client"><?php echo e($project->client_name); ?></div>
        <h2><?php echo e($project->name); ?></h2>
        <div class="cp-header-meta">
          <?php if($project->client_phone): ?>
            <span><i class="fa fa-phone"></i> <?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone)); ?></span>
          <?php endif; ?>
          <?php if($project->client_email): ?>
            <span><i class="fa fa-envelope"></i> <a href="mailto:<?php echo e($project->client_email); ?>"><?php echo e($project->client_email); ?></a></span>
          <?php endif; ?>
          <?php if($project->client_address): ?>
            <span>
              <i class="fa fa-map-marker"></i>
              <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e(urlencode($project->client_address)); ?>"
                 target="_blank"
                 rel="noopener noreferrer"
                 title="Open in Google Maps"><?php echo e($project->client_address); ?></a>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="cp-header-status">
        <span class="cp-badge cp-badge--lg" style="background: <?php echo e($project->statusColor()); ?>20; color: <?php echo e($project->statusColor()); ?>;">
          <?php echo e($project->statusLabel()); ?>

        </span>
        <div class="cp-updated">Updated <?php echo e(optional($project->updated_at)->format('d/m/Y H:i')); ?></div>
      </div>
    </div>

    <?php if (! ($isRejected)): ?>
      <form action="<?php echo e(route('admin.client-projects.status.update', $project)); ?>" method="post" class="cp-status-form">
        <?php echo csrf_field(); ?>
        <label for="cp-status-select">Change status</label>
        <select id="cp-status-select" name="status">
          <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($key); ?>" <?php if($project->status === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <input type="text" name="note" placeholder="Optional note…" maxlength="2000" class="cp-status-note">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Save status</button>
        <span class="cp-hint" title="Proposal Accepted will automatically move the project to In Progress and unlock the financial & time tracking tabs.">?</span>
      </form>
    <?php else: ?>
      <p class="cp-final-note"><i class="fa fa-ban"></i> This project is marked as <strong>Rejected</strong>. No further status changes are possible.</p>
    <?php endif; ?>

    <div class="cp-header-actions">
      <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit details
      </a>
      <?php if($calendarLink): ?>
        <a href="<?php echo e($calendarLink); ?>" target="_blank" rel="noopener noreferrer" class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-calendar"></i> Add to Google Calendar
        </a>
      <?php endif; ?>
      <a href="<?php echo e(route('admin.client-projects.index')); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-arrow-left"></i> Back to list
      </a>
    </div>
  </div>

  
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
    <button type="button" class="cp-tab" data-tab="quotation" role="tab">Quotation</button>
    <button type="button" class="cp-tab" data-tab="contract" role="tab">Contract</button>
    <button type="button" class="cp-tab" data-tab="history" role="tab">History</button>
  </div>

  
  <section class="cp-tab-panel is-active" data-panel="overview">
    <?php if($isCompleted): ?>
      <?php echo $__env->make('admin.client_projects._final_summary', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php endif; ?>

    <div class="admin-card">
      <h3>Details</h3>
      <dl class="cp-dl">
        <dt>Project name</dt><dd><?php echo e($project->name); ?></dd>
        <dt>Client</dt><dd><?php echo e($project->client_name); ?></dd>
        <dt>Email</dt><dd><?php echo e($project->client_email ?: '—'); ?></dd>
        <dt>Phone</dt><dd><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone) ?: '—'); ?></dd>
        <dt>Address</dt><dd><?php echo e($project->client_address ?: '—'); ?></dd>
        <dt>Created</dt><dd><?php echo e($project->created_at->format('d/m/Y H:i')); ?></dd>
        <dt>Status changed</dt><dd><?php echo e($project->status_changed_at ? $project->status_changed_at->format('d/m/Y H:i') : '—'); ?></dd>
      </dl>

      <?php if($project->notes): ?>
        <h3 style="margin-top:24px;">Notes</h3>
        <div class="cp-notes-box"><?php echo e($project->notes); ?></div>
      <?php endif; ?>
    </div>
  </section>

  
  <section class="cp-tab-panel" data-panel="schedule">
    <div class="admin-card">
      <h3>Start &amp; End dates</h3>
      <p class="admin-form-help">Saving both dates schedules the project on the Project Calendar and emails you a Google Calendar link.</p>
      <form action="<?php echo e(route('admin.client-projects.update', $project)); ?>" method="post" class="cp-schedule-form">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>
        
        <input type="hidden" name="name" value="<?php echo e($project->name); ?>">
        <input type="hidden" name="client_name" value="<?php echo e($project->client_name); ?>">
        <input type="hidden" name="client_email" value="<?php echo e($project->client_email); ?>">
        <input type="hidden" name="client_phone" value="<?php echo e($project->client_phone); ?>">
        <input type="hidden" name="client_address" value="<?php echo e($project->client_address); ?>">
        <input type="hidden" name="notes" value="<?php echo e($project->notes); ?>">

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start">Start date</label>
            <input type="date" id="cp-start" name="start_date" value="<?php echo e(optional($project->start_date)->toDateString()); ?>">
          </div>
          <div class="admin-form-group">
            <label for="cp-end">End date</label>
            <input type="date" id="cp-end" name="end_date" value="<?php echo e(optional($project->end_date)->toDateString()); ?>">
            <p class="admin-form-help">Same-day start &amp; end is allowed.</p>
          </div>
        </div>

        <button type="submit" class="admin-btn admin-btn--primary">Save dates</button>
      </form>

      <?php if($project->start_date && $project->end_date): ?>
        <div class="cp-schedule-summary">
          <strong>Scheduled:</strong>
          <?php echo e(\App\Models\ClientProject::formatDate($project->start_date)); ?>

          →
          <?php echo e(\App\Models\ClientProject::formatDate($project->end_date)); ?>

          <?php if($calendarLink): ?>
            ·
            <a href="<?php echo e($calendarLink); ?>" target="_blank" rel="noopener noreferrer">Add to Google Calendar</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  
  <section class="cp-tab-panel" data-panel="financials">
    <?php if(!$isAccepted): ?>
      <div class="admin-card cp-locked">
        <h3>Financials are locked</h3>
        <p>Financial details become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    <?php else: ?>
      <?php echo $__env->make('admin.client_projects._financials', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php endif; ?>
  </section>

  
  <section class="cp-tab-panel" data-panel="time">
    <?php if(!$isAccepted): ?>
      <div class="admin-card cp-locked">
        <h3>Time tracking is locked</h3>
        <p>Time entries become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    <?php else: ?>
      <?php echo $__env->make('admin.client_projects._time', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php endif; ?>
  </section>

  
  <section class="cp-tab-panel" data-panel="quotation" id="quotation">
    <?php echo $__env->make('admin.client_projects._quotation', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
  </section>

  
  <section class="cp-tab-panel" data-panel="contract" id="contract">
    <?php echo $__env->make('admin.client_projects._contract', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
  </section>

  
  <section class="cp-tab-panel" data-panel="history">
    <div class="admin-card">
      <h3>Status history</h3>
      <?php if($project->statusHistory->isEmpty()): ?>
        <p class="admin-form-help">No history yet.</p>
      <?php else: ?>
        <div class="admin-table-scroll">
        <table class="admin-table cp-history-table">
          <thead>
            <tr>
              <th>When</th>
              <th>From</th>
              <th>To</th>
              <th>Note</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $project->statusHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e($entry->changed_at->format('d/m/Y H:i')); ?></td>
                <td><?php echo e($entry->fromLabel()); ?></td>
                <td><strong><?php echo e($entry->toLabel()); ?></strong></td>
                <td><?php echo e($entry->note ?: '—'); ?></td>
                <td><?php echo e($entry->user?->email ?? 'system'); ?></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
        </div>
      <?php endif; ?>
    </div>
  </section>

  
  <script>
    (function () {
      var tabs = document.querySelectorAll('.cp-tab');
      var panels = document.querySelectorAll('.cp-tab-panel');
      if (!tabs.length) return;

      function show(key) {
        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t.getAttribute('data-tab') === key);
        });
        panels.forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
        });
        // remember last tab for a refresh within the same page session
        try { sessionStorage.setItem('cpActiveTab-<?php echo e($project->id); ?>', key); } catch (e) {}
      }

      tabs.forEach(function (t) {
        t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
      });

      // Priority: explicit URL hash (e.g. /show#contract) wins over the
      // session-remembered tab. This lets the controller redirect a user
      // back to the right tab after a save/email action.
      var hashTab = (window.location.hash || '').replace('#', '');
      if (hashTab && document.querySelector('.cp-tab[data-tab="' + hashTab + '"]')) {
        show(hashTab);
      } else {
        try {
          var saved = sessionStorage.getItem('cpActiveTab-<?php echo e($project->id); ?>');
          if (saved) show(saved);
        } catch (e) {}
      }
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/show.blade.php ENDPATH**/ ?>