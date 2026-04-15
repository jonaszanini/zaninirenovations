<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo $__env->yieldContent('title', 'Admin'); ?> — Zanini Renovations</title>
  <link rel="shortcut icon" href="<?php echo e(asset('images/favicon.png')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/font-awesome.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
  <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="admin-body">
  <div class="admin-app" id="admin-app">
    <div class="admin-nav-backdrop" id="admin-nav-backdrop" aria-hidden="true" hidden></div>
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
      <div class="admin-sidebar-brand">
        <a href="<?php echo e(route('admin.projects.index')); ?>">Zanini Renovations</a>
        <span>Admin</span>
      </div>
      <ul class="admin-nav">
        <li>
          <a href="<?php echo e(route('admin.projects.index')); ?>" class="<?php echo e(request()->routeIs('admin.projects.*') ? 'is-active' : ''); ?>">
            <i class="fa fa-folder-open"></i>
            Latest projects
          </a>
        </li>
        <li>
          <a href="<?php echo e(route('admin.categories.index')); ?>" class="<?php echo e(request()->routeIs('admin.categories.*') ? 'is-active' : ''); ?>">
            <i class="fa fa-tags"></i>
            Categories
          </a>
        </li>
      </ul>
      <div class="admin-sidebar-footer">
        <div class="admin-sidebar-user"><?php echo e(auth()->user()->email); ?></div>
        <form action="<?php echo e(route('admin.logout')); ?>" method="post">
          <?php echo csrf_field(); ?>
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm admin-sidebar-logout">Log out</button>
        </form>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <div class="admin-topbar-inner">
          <button type="button" class="admin-menu-toggle" id="admin-menu-toggle" aria-label="Open navigation menu" aria-expanded="false" aria-controls="admin-sidebar">
            <i class="fa fa-bars admin-menu-icon-open" aria-hidden="true"></i>
            <i class="fa fa-times admin-menu-icon-close" aria-hidden="true"></i>
          </button>
          <h1><?php echo $__env->yieldContent('heading'); ?></h1>
        </div>
      </header>
      <main class="admin-content">
        <?php if(session('success')): ?>
          <div class="admin-flash admin-flash--success"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php if(session('error')): ?>
          <div class="admin-flash admin-flash--error"><?php echo e(session('error')); ?></div>
        <?php endif; ?>
        <?php echo $__env->yieldContent('content'); ?>
      </main>
    </div>
  </div>
  <script>
    (function () {
      var app = document.getElementById('admin-app');
      var toggle = document.getElementById('admin-menu-toggle');
      var backdrop = document.getElementById('admin-nav-backdrop');
      if (!app || !toggle || !backdrop) return;

      var mq = window.matchMedia('(max-width: 900px)');

      function setOpen(open) {
        app.classList.toggle('admin-nav-open', open);
        document.body.classList.toggle('admin-nav-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        backdrop.hidden = !open;
        backdrop.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function closeNav() {
        setOpen(false);
      }

      toggle.addEventListener('click', function () {
        setOpen(!app.classList.contains('admin-nav-open'));
      });

      backdrop.addEventListener('click', closeNav);

      document.querySelectorAll('.admin-nav a').forEach(function (link) {
        link.addEventListener('click', function () {
          if (mq.matches) closeNav();
        });
      });

      mq.addEventListener('change', function (e) {
        if (!e.matches) closeNav();
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && app.classList.contains('admin-nav-open')) {
          closeNav();
          toggle.focus();
        }
      });
    })();
  </script>
  <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\layouts\app.blade.php ENDPATH**/ ?>