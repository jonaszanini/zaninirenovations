

<?php $__env->startSection('heading', 'Project Calendar'); ?>

<?php $__env->startPush('head'); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-toolbar">
    <p class="admin-toolbar-lede">All projects with scheduled start and end dates. Click any event to open the project. Rejected projects are hidden.</p>
    <a href="<?php echo e(route('admin.client-projects.index')); ?>" class="admin-btn admin-btn--ghost">
      <i class="fa fa-list"></i> Back to list
    </a>
  </div>

  <div class="cp-calendar-legend">
    <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php if($key !== \App\Models\ClientProject::STATUS_REJECTED): ?>
        <span class="cp-legend-item">
          <span class="cp-legend-swatch" style="background: <?php echo e($statusColors[$key]); ?>;"></span>
          <?php echo e($label); ?>

        </span>
      <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
  </div>

  <div class="admin-card cp-calendar-card">
    <div id="cp-calendar"></div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var el = document.getElementById('cp-calendar');
      if (!el || typeof FullCalendar === 'undefined') return;

      var events = <?php echo json_encode($events, 15, 512) ?>;

      var calendar = new FullCalendar.Calendar(el, {
        initialView: 'dayGridMonth',
        headerToolbar: {
          left: 'prev,next today',
          center: 'title',
          right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        firstDay: 1,
        height: 'auto',
        events: events,
        eventDisplay: 'block',
        eventClick: function (info) {
          if (info.event.url) {
            info.jsEvent.preventDefault();
            window.location.href = info.event.url;
          }
        },
        eventDidMount: function (info) {
          if (info.event.extendedProps && info.event.extendedProps.status) {
            info.el.setAttribute('title', info.event.title + ' — ' + info.event.extendedProps.status);
          }
        }
      });
      calendar.render();
    });
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/calendar.blade.php ENDPATH**/ ?>