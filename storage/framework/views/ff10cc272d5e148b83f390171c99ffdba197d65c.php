

<?php $__env->startSection('heading', 'Edit client — '.$client->name); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card admin-card--narrow">
    <form action="<?php echo e(route('admin.clients.update', $client)); ?>" method="post">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>

      <div class="admin-form-group">
        <label for="cl-name">Name <span class="cp-required">*</span></label>
        <input type="text" id="cl-name" name="name" value="<?php echo e(old('name', $client->name)); ?>" required maxlength="255"
               class="<?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cl-email">Email</label>
          <input type="email" id="cl-email" name="email" value="<?php echo e(old('email', $client->email)); ?>" maxlength="255"
                 class="<?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
          <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="admin-form-group">
          <label for="cl-phone">Phone</label>
          <input type="text" id="cl-phone" name="phone"
                 value="<?php echo e(\App\Models\ClientProject::formatPhone(old('phone', $client->phone))); ?>"
                 class="cp-phone-input <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                 inputmode="tel" autocomplete="tel" maxlength="14" placeholder="(xxx) xxx-xxxx">
          <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cl-email-secondary">Secondary email</label>
        <input type="email" id="cl-email-secondary" name="email_secondary" value="<?php echo e(old('email_secondary', $client->email_secondary)); ?>" maxlength="255"
               class="<?php $__errorArgs = ['email_secondary'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        <?php $__errorArgs = ['email_secondary'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-group">
        <label for="cl-address">Address</label>
        <input type="text" id="cl-address" name="address" value="<?php echo e(old('address', $client->address)); ?>" maxlength="500"
               class="<?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        <?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-group">
        <label for="cl-notes">Notes</label>
        <textarea id="cl-notes" name="notes" rows="3" maxlength="5000"><?php echo e(old('notes', $client->notes)); ?></textarea>
        <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <p class="admin-form-help">
        Linked to <strong><?php echo e($client->projects()->count()); ?></strong> project(s). Changing details here does not automatically change older project PDFs.
      </p>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-save"></i> Save changes
        </button>
        <a href="<?php echo e(route('admin.clients.index')); ?>" class="admin-btn admin-btn--ghost">Back to list</a>
      </div>
    </form>

    <?php if($client->projects()->exists()): ?>
      <p class="admin-form-help" style="margin-top:24px;color:#b45309;">
        <i class="fa fa-info-circle"></i>
        Delete is disabled while this client is linked to projects. Remove the client link on each project first (edit project → clear directory selection and save).
      </p>
    <?php else: ?>
      <form action="<?php echo e(route('admin.clients.destroy', $client)); ?>" method="post" style="margin-top:20px;"
            onsubmit="return confirm('Delete this client permanently?');">
        <?php echo csrf_field(); ?>
        <?php echo method_field('DELETE'); ?>
        <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">
          <i class="fa fa-trash"></i> Delete client
        </button>
      </form>
    <?php endif; ?>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\clients\edit.blade.php ENDPATH**/ ?>