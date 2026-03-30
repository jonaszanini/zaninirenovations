<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin sign in — Zanini Renovations</title>
  <link rel="stylesheet" href="<?php echo e(asset('css/font-awesome.min.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/admin.css')); ?>">
</head>
<body class="admin-body">
  <div class="admin-login-page">
    <div class="admin-login-card">
      <div class="admin-login-brand">
        <span>Zanini Renovations</span>
        <strong>Admin</strong>
      </div>
      <h1>Sign in</h1>
      <p class="subtitle">Site administration only.</p>

      <form method="post" action="<?php echo e(route('login')); ?>">
        <?php echo csrf_field(); ?>
        <div class="admin-form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="username">
          <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <div class="admin-error"><?php echo e($message); ?></div>
          <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="admin-form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <div class="admin-form-group">
          <label style="display:flex;align-items:center;gap:0.5rem;text-transform:none;font-weight:500;">
            <input type="checkbox" name="remember" value="1" <?php echo e(old('remember') ? 'checked' : ''); ?>>
            Remember me
          </label>
        </div>
        <button type="submit" class="admin-btn admin-btn--primary" style="width:100%;">Sign in</button>
      </form>

      <a class="back" href="<?php echo e(url('/')); ?>"><i class="fa fa-arrow-left"></i> Back to website</a>
    </div>
  </div>
</body>
</html>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/auth/login.blade.php ENDPATH**/ ?>