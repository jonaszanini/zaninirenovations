

<?php $__env->startSection('heading', 'New project'); ?>

<?php $__env->startSection('content'); ?>
  <div class="admin-card admin-card--narrow">
    <form action="<?php echo e(route('admin.client-projects.store')); ?>" method="post">
      <?php echo csrf_field(); ?>

      <div class="admin-form-group">
        <label for="cp-name">Project name <span class="cp-required">*</span></label>
        <input type="text" id="cp-name" name="name" value="<?php echo e(old('name')); ?>" required maxlength="255"
               class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('name')]) ?>">
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-section">
        <h3>Client information</h3>

        <?php echo $__env->make('admin.client_projects._client_directory_picker', [
          'clientsSearchUrl' => $clientsSearchUrl,
          'initialClientForPicker' => null,
        ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <div class="admin-form-group">
          <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
          <input type="text" id="cp-client-name" name="client_name" value="<?php echo e(old('client_name')); ?>" required maxlength="255"
                 class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_name')]) ?>">
          <?php $__errorArgs = ['client_name'];
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
            <label for="cp-client-email">Email</label>
            <input type="email" id="cp-client-email" name="client_email" value="<?php echo e(old('client_email')); ?>" maxlength="255"
                   class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_email')]) ?>">
            <?php $__errorArgs = ['client_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php
              // Checkbox value is preserved on validation failure. On first
              // render there is no "old" value, so default to checked.
              $sendClientEmail = old('send_client_email', '1') === '1';
            ?>
            <label class="cp-checkbox-inline" for="cp-send-client-email" style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;font-weight:400;">
              <input type="hidden" name="send_client_email" value="0">
              <input type="checkbox"
                     id="cp-send-client-email"
                     name="send_client_email"
                     value="1"
                     <?php if($sendClientEmail): echo 'checked'; endif; ?>>
              <span>Send automated &ldquo;quote request received&rdquo; email to the client</span>
            </label>
          </div>
          <div class="admin-form-group">
            <label for="cp-client-phone">Phone number</label>
            <input type="text"
                   id="cp-client-phone"
                   name="client_phone"
                   class="<?php echo \Illuminate\Support\Arr::toCssClasses(['cp-phone-input', 'is-invalid' => $errors->has('client_phone')]) ?>"
                   inputmode="tel"
                   autocomplete="tel"
                   maxlength="14"
                   placeholder="(xxx) xxx-xxxx"
                   value="<?php echo e(\App\Models\ClientProject::formatPhone(old('client_phone'))); ?>">
            <?php $__errorArgs = ['client_phone'];
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
          <label for="cp-client-email-secondary">Secondary email <small class="admin-form-help cp-inline-help">(optional — CC'd on every client email)</small></label>
          <input type="email" id="cp-client-email-secondary" name="client_email_secondary"
                 value="<?php echo e(old('client_email_secondary')); ?>" maxlength="255"
                 placeholder="spouse@example.com"
                 class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_email_secondary')]) ?>">
          <?php $__errorArgs = ['client_email_secondary'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>

        <div class="admin-form-group">
          <label for="cp-client-address">Address</label>
          <input type="text" id="cp-client-address" name="client_address" value="<?php echo e(old('client_address')); ?>" maxlength="500"
                 class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_address')]) ?>">
          <?php $__errorArgs = ['client_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Billing</h3>
        <?php
          $billingType = old('billing_type', \App\Models\ClientProject::BILLING_FIXED);
        ?>
        <div class="admin-form-group cp-billing-choice">
          <label class="cp-billing-option">
            <input type="radio" name="billing_type" value="<?php echo e(\App\Models\ClientProject::BILLING_FIXED); ?>"
                   data-cp-billing
                   <?php if($billingType === \App\Models\ClientProject::BILLING_FIXED): echo 'checked'; endif; ?>>
            <span>
              <strong>Fixed price</strong>
              <small>One total amount, paid in 1–4 installments.</small>
            </span>
          </label>
          <label class="cp-billing-option">
            <input type="radio" name="billing_type" value="<?php echo e(\App\Models\ClientProject::BILLING_HOURLY); ?>"
                   data-cp-billing
                   <?php if($billingType === \App\Models\ClientProject::BILLING_HOURLY): echo 'checked'; endif; ?>>
            <span>
              <strong>Hourly rate</strong>
              <small>Set a rate per hour — earnings = rate × logged hours.</small>
            </span>
          </label>
        </div>
        <?php $__errorArgs = ['billing_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

        <div class="admin-form-group cp-hourly-rate-group" data-cp-hourly-rate
             style="<?php echo e($billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;'); ?>">
          <label for="cp-hourly-rate">Hourly rate (CAD / hour) <span class="cp-required">*</span></label>
          <input type="text" id="cp-hourly-rate" name="hourly_rate"
                 class="cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 placeholder="0.00"
                 value="<?php echo e(old('hourly_rate')); ?>">
          <?php $__errorArgs = ['hourly_rate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          <p class="admin-form-help">You can adjust this later from the project's Financials tab.</p>
        </div>
      </div>

      <div class="admin-form-group">
        <label for="cp-notes">Notes</label>
        <textarea id="cp-notes" name="notes" rows="4" maxlength="5000"><?php echo e(old('notes')); ?></textarea>
        <?php $__errorArgs = ['notes'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary">
          <i class="fa fa-check"></i> Create project
        </button>
        <a href="<?php echo e(route('admin.client-projects.index')); ?>" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>

      <p class="admin-form-help cp-form-footer" data-cp-fixed-only
         style="<?php echo e($billingType === \App\Models\ClientProject::BILLING_HOURLY ? 'display:none;' : ''); ?>">
        Status will be set to <strong>Quote Pending</strong>. You'll receive a daily email reminder until the quote is sent.
      </p>
      <p class="admin-form-help cp-form-footer" data-cp-hourly-only
         style="<?php echo e($billingType === \App\Models\ClientProject::BILLING_HOURLY ? '' : 'display:none;'); ?>">
        Status will be set to <strong>Proposal Accepted</strong>. No email is sent to the client yet &mdash; once
        you schedule the dates you'll be able to send a service confirmation from the project page.
      </p>
    </form>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/create.blade.php ENDPATH**/ ?>