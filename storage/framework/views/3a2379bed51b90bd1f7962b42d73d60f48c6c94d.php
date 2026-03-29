<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?php echo $__env->yieldContent('title', 'Admin'); ?> — Zanini Renovations</title>
  <link rel="stylesheet" href="<?php echo e(asset('css/font-awesome.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
  <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="admin-body">
  <div class="admin-app">
    <aside class="admin-sidebar">
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
      </ul>
      <div class="admin-sidebar-footer">
        <div><?php echo e(auth()->user()->email); ?></div>
        <form action="<?php echo e(route('admin.logout')); ?>" method="post">
          <?php echo csrf_field(); ?>
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" style="margin-top:0.5rem;width:100%;">Log out</button>
        </form>
      </div>
    </aside>
    <div class="admin-main">
      <header class="admin-topbar">
        <h1><?php echo $__env->yieldContent('heading'); ?></h1>
      </header>
      <main class="admin-content">
        <?php if(session('success')): ?>
          <div class="admin-flash admin-flash--success"><?php echo e(session('success')); ?></div>
        <?php endif; ?>
        <?php echo $__env->yieldContent('content'); ?>
      </main>
    </div>
  </div>
  <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/layouts/app.blade.php ENDPATH**/ ?>