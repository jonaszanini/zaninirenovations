

<?php $__env->startSection('heading', 'Edit project'); ?>

<?php $__env->startSection('content'); ?>
  <div class="cp-edit-project-layout">
    <div class="admin-card admin-card--narrow cp-edit-project-form-card">
      <form id="cp-edit-project-form" action="<?php echo e(route('admin.client-projects.update', $project)); ?>" method="post">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

      <div class="admin-form-group">
        <label for="cp-name">Project name <span class="cp-required">*</span></label>
        <input type="text" id="cp-name" name="name" value="<?php echo e(old('name', $project->name)); ?>" required maxlength="255"
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

      <div class="admin-form-group">
        <label for="cp-contacted-via">Contact via <span class="cp-required">*</span></label>
        <select id="cp-contacted-via" name="contacted_via" required class="<?php echo \Illuminate\Support\Arr::toCssClasses(['admin-input', 'is-invalid' => $errors->has('contacted_via')]) ?>">
          <?php if(old('contacted_via', $project->contacted_via) === null || old('contacted_via', $project->contacted_via) === ''): ?>
            <option value="" disabled selected>Select…</option>
          <?php endif; ?>
          <?php $__currentLoopData = \App\Models\ClientProject::contactedViaOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>" <?php if(old('contacted_via', $project->contacted_via) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['contacted_via'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>

      <div class="admin-form-group">
        <label for="cp-conversation-on">Conversation on</label>
        <select id="cp-conversation-on" name="conversation_on" class="<?php echo \Illuminate\Support\Arr::toCssClasses(['admin-input', 'is-invalid' => $errors->has('conversation_on')]) ?>">
          <option value="" <?php if(old('conversation_on', $project->conversation_on) === null || old('conversation_on', $project->conversation_on) === ''): echo 'selected'; endif; ?>>— Not set —</option>
          <?php $__currentLoopData = \App\Models\ClientProject::contactedViaOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($value); ?>" <?php if(old('conversation_on', $project->conversation_on) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <p class="admin-form-help">If the thread moved (e.g. they emailed first but you now chat on SMS or WhatsApp), pick where you usually follow up.</p>
        <?php $__errorArgs = ['conversation_on'];
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
          'initialClientForPicker' => $initialClientForPicker ?? null,
        ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>

        <div class="admin-form-group">
          <label for="cp-client-name">Client name <span class="cp-required">*</span></label>
          <input type="text" id="cp-client-name" name="client_name" value="<?php echo e(old('client_name', $project->client_name)); ?>" required maxlength="255"
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
            <input type="email" id="cp-client-email" name="client_email" value="<?php echo e(old('client_email', $project->client_email)); ?>" maxlength="255"
                   class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('client_email')]) ?>">
            <?php $__errorArgs = ['client_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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
                   value="<?php echo e(\App\Models\ClientProject::formatPhone(old('client_phone', $project->client_phone))); ?>">
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
                 value="<?php echo e(old('client_email_secondary', $project->client_email_secondary)); ?>" maxlength="255"
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
          <input type="text" id="cp-client-address" name="client_address" value="<?php echo e(old('client_address', $project->client_address)); ?>" maxlength="500"
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

        <div class="admin-form-section" style="margin-top:1rem;padding-top:1rem;border-top:1px dashed #e5e7eb;">
          <h3 style="font-size:1rem;">Invoice billing <span class="admin-form-help cp-inline-help">(optional)</span></h3>
          <p class="admin-form-help">When the payer is a company or a different legal entity, enter the name and address that should appear in the <strong>Bill to</strong> block on invoices and receipts. Day-to-day contact (name, email, phone above) stays the same for emails and SMS.</p>
          <div class="admin-form-group">
            <label for="cp-invoice-billing-name">Legal billing name</label>
            <input type="text" id="cp-invoice-billing-name" name="invoice_billing_name"
                   value="<?php echo e(old('invoice_billing_name', $project->invoice_billing_name)); ?>" maxlength="255"
                   placeholder="e.g. ABC Renovations Inc."
                   class="<?php echo \Illuminate\Support\Arr::toCssClasses(['is-invalid' => $errors->has('invoice_billing_name')]) ?>">
            <?php $__errorArgs = ['invoice_billing_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="admin-form-group">
            <label for="cp-invoice-billing-address">Billing address</label>
            <textarea id="cp-invoice-billing-address" name="invoice_billing_address" rows="3" maxlength="2000"
                      class="admin-input" placeholder="Registered office or mailing address for AP"><?php echo e(old('invoice_billing_address', $project->invoice_billing_address)); ?></textarea>
            <?php $__errorArgs = ['invoice_billing_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <p class="admin-form-help">Leave blank to use the client address above on PDFs.</p>
          </div>
        </div>
      </div>

      <?php
        $cpEditVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
        $cpEditVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
          || (bool) old('visit_done', $project->visit_done)
          || filled($cpEditVisitDateVal)
          || $errors->has('visit_date');
      ?>
      <div class="admin-form-section">
        <h3>Visit date</h3>
        <p class="admin-form-help">On-site visit before quoting. Enable only when the client has asked for a visit.</p>
        <input type="hidden" name="visit_required" value="0">
        <div class="admin-form-group">
          <label class="cp-checkbox-label">
            <input type="checkbox" name="visit_required" value="1" id="cp-edit-visit-required"
              <?php if(old('visit_required', $project->visit_required)): echo 'checked'; endif; ?>>
            <span>Visit required</span>
          </label>
        </div>
        <div id="cp-edit-visit-expandable" class="cp-visit-expandable <?php if($cpEditVisitExpandOpen): ?> is-open <?php endif; ?>"
             aria-hidden="<?php echo e($cpEditVisitExpandOpen ? 'false' : 'true'); ?>">
          <div class="cp-visit-expandable__inner">
            <div class="admin-form-group cp-visit-date">
              <label for="cp-edit-visit-date">Visit date</label>
              <input type="date" id="cp-edit-visit-date" name="visit_date"
                value="<?php echo e($cpEditVisitDateVal); ?>">
              <?php $__errorArgs = ['visit_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <input type="hidden" name="visit_done" value="0">
            <div class="admin-form-group">
              <label class="cp-checkbox-label">
                <input type="checkbox" name="visit_done" value="1" id="cp-edit-visit-done"
                  <?php if(old('visit_done', $project->visit_done)): echo 'checked'; endif; ?>>
                <span>Visit done</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Schedule <span class="admin-form-help cp-inline-help" title="Optional. Used to show the project on the Project Calendar page and to send an 'Add to Google Calendar' email.">?</span></h3>
        <div id="cp-edit-date-warning" class="admin-flash admin-flash--warning" style="display:none;margin-bottom:14px;" role="alert"></div>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start-date">Start date</label>
            <input type="date" id="cp-start-date" name="start_date" value="<?php echo e(old('start_date', optional($project->start_date)->toDateString())); ?>">
            <?php $__errorArgs = ['start_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
          </div>
          <div class="admin-form-group">
            <label for="cp-end-date">End date</label>
            <input type="date" id="cp-end-date" name="end_date" value="<?php echo e(old('end_date', optional($project->end_date)->toDateString())); ?>">
            <?php $__errorArgs = ['end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <p class="admin-form-help">Same-day start &amp; end is allowed for small projects.</p>
          </div>
        </div>
      </div>

      <div class="admin-form-section">
        <h3>Billing</h3>
        <?php
          $billingType = old('billing_type', $project->billing_type ?? \App\Models\ClientProject::BILLING_FIXED);
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
                 value="<?php echo e(old('hourly_rate', $project->hourly_rate ? number_format((float) $project->hourly_rate, 2, '.', '') : '')); ?>">
          <?php $__errorArgs = ['hourly_rate'];
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
        <label for="cp-notes">Notes</label>
        <textarea id="cp-notes" name="notes" rows="4" maxlength="5000"><?php echo e(old('notes', $project->notes)); ?></textarea>
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
        <button type="submit" class="admin-btn admin-btn--primary"><i class="fa fa-check"></i> Save changes</button>
        <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>" class="admin-btn admin-btn--ghost">Cancel</a>
      </div>
    </form>
    </div>

    <aside class="cp-edit-project-actions-aside" role="region" aria-label="Project form actions">
      <button type="submit" form="cp-edit-project-form" class="admin-btn admin-btn--primary admin-btn--sm">
        <i class="fa fa-check"></i> Save changes
      </button>
      <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">Cancel</a>
    </aside>
  </div>

  <div class="admin-card cp-danger-card">
    <h3 class="cp-danger-title">Danger zone</h3>
    <p class="admin-form-help">Deleting a project permanently removes its history, financials, and time entries.</p>
    <form action="<?php echo e(route('admin.client-projects.destroy', $project)); ?>" method="post"
          onsubmit="return confirm('Delete this project and all of its history, financials, and time entries? This cannot be undone.');">
      <?php echo csrf_field(); ?>
      <?php echo method_field('DELETE'); ?>
      <button type="submit" class="admin-btn admin-btn--danger">Delete project</button>
    </form>
  </div>
  <script>
    (function () {
      var form = document.getElementById('cp-edit-project-form');
      if (!form) return;
      var start = document.getElementById('cp-start-date');
      var end = document.getElementById('cp-end-date');
      var warn = document.getElementById('cp-edit-date-warning');
      var visitCb = document.getElementById('cp-edit-visit-required');
      var visitDate = document.getElementById('cp-edit-visit-date');
      var visitDoneCb = document.getElementById('cp-edit-visit-done');
      var visitExpand = document.getElementById('cp-edit-visit-expandable');

      function syncEditVisitExpandable() {
        if (!visitExpand) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        var hasDate = visitDate && visitDate.value && String(visitDate.value).trim() !== '';
        var open = required || done || hasDate;
        visitExpand.classList.toggle('is-open', open);
        visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function syncEditVisitFields() {
        if (!visitDate) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        visitDate.readOnly = !!done;
        visitDate.disabled = !done && !required;
        visitDate.classList.toggle('cp-visit-date-input--locked', !!done || !required);
        syncEditVisitExpandable();
      }

      function checkEditDates() {
        if (!start || !end || !warn) return true;
        if (!start.value || !end.value) {
          warn.style.display = 'none';
          warn.textContent = '';
          return true;
        }
        if (start.value > end.value) {
          warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
          warn.style.display = 'block';
          return false;
        }
        warn.style.display = 'none';
        warn.textContent = '';
        return true;
      }

      if (start && end && warn) {
        start.addEventListener('change', checkEditDates);
        end.addEventListener('change', checkEditDates);
      }
      if (visitCb) visitCb.addEventListener('change', syncEditVisitFields);
      if (visitDoneCb) visitDoneCb.addEventListener('change', syncEditVisitFields);
      if (visitDate) visitDate.addEventListener('change', syncEditVisitExpandable);
      if (visitDate) visitDate.addEventListener('input', syncEditVisitExpandable);
      syncEditVisitFields();

      form.addEventListener('submit', function (e) {
        if (visitDate && visitCb && !visitCb.checked && !(visitDoneCb && visitDoneCb.checked)) {
          visitDate.disabled = false;
          visitDate.readOnly = false;
          visitDate.value = '';
        }
        if (!checkEditDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views\admin\client_projects\edit.blade.php ENDPATH**/ ?>