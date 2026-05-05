

<?php $__env->startSection('heading', $project->name); ?>

<?php $__env->startPush('head'); ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
<?php $__env->stopPush(); ?>

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
          <?php if($project->secondaryClientEmail()): ?>
            <span title="Secondary email — CC'd on every client email"><i class="fa fa-envelope-o"></i> <a href="mailto:<?php echo e($project->secondaryClientEmail()); ?>"><?php echo e($project->secondaryClientEmail()); ?></a></span>
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
        <div class="cp-updated">Updated <?php echo e(\App\Models\ClientProject::formatDateTime($project->updated_at)); ?></div>
      </div>
    </div>

    <?php if (! ($isRejected)): ?>
      
      <form action="<?php echo e(route('admin.client-projects.status.update', $project)); ?>" method="post" class="cp-status-form" id="cp-status-form" data-current-status="<?php echo e($project->status); ?>">
        <?php echo csrf_field(); ?>
        <div class="cp-status-form-head">
          <label for="cp-status-select" class="cp-status-form-label">Change status</label>
          <span class="cp-hint" title="Workflow: Visit scheduled → Quote Pending → Quote Sent → Proposal Accepted → Scheduled (contract signed + 1st installment paid, waiting for start date) → In Progress → Completed. Time tracking, quotation, contract, invoice, and financials unlock from Proposal Accepted onward.">?</span>
          <span class="cp-status-form-help">Pick a status to save it instantly. Type a note first if you want it logged with the change.</span>
        </div>
        <div class="cp-status-controls">
          <select id="cp-status-select" name="status" class="admin-input cp-status-select">
            <?php $__currentLoopData = $statusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($key); ?>" <?php if($project->status === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </select>
          <input type="text" id="cp-status-note" name="note" placeholder="Optional note about this status change…"
                 maxlength="2000" class="admin-input cp-status-note">
          
          <button type="submit" class="admin-btn admin-btn--primary cp-status-save">
            <i class="fa fa-save"></i> Save status
          </button>
        </div>
      </form>
      <script>
        (function () {
          var form = document.getElementById('cp-status-form');
          if (!form) return;
          var select = form.querySelector('#cp-status-select');
          var note = form.querySelector('#cp-status-note');
          if (!select) return;

          // Hide the Save button now that we know JS is on.
          form.classList.add('cp-status-form--auto');

          var submitting = false;
          select.addEventListener('change', function () {
            if (submitting) return;
            if (select.value === form.getAttribute('data-current-status')) return;

            submitting = true;
            select.disabled = true;
            if (note) note.disabled = true;
            // Re-add the disabled select's value so it still posts.
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'status';
            hidden.value = select.value;
            form.appendChild(hidden);
            form.submit();
          });
        })();
      </script>
    <?php else: ?>
      <p class="cp-final-note"><i class="fa fa-ban"></i> This project is marked as <strong>Rejected</strong>. No further status changes are possible.</p>
    <?php endif; ?>

    <div class="cp-header-actions">
      <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>" class="admin-btn admin-btn--ghost admin-btn--sm">
        <i class="fa fa-pencil"></i> Edit details
      </a>
      <?php if($calendarLink): ?>
        <a href="<?php echo e($calendarLink); ?>" target="_blank" rel="noopener noreferrer" class="cp-calendar-link cp-calendar-link--btn">
          <i class="fa fa-calendar" aria-hidden="true"></i>
          <span>Add to Google Calendar</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  
  <div class="cp-tabs" role="tablist">
    <button type="button" class="cp-tab is-active" data-tab="overview" role="tab">Overview</button>
    <button type="button" class="cp-tab" data-tab="schedule" role="tab">Calendar</button>
    <button type="button" class="cp-tab" data-tab="time" role="tab">Time Tracking</button>
    <button type="button" class="cp-tab" data-tab="quotation" role="tab">Quotation</button>
    <button type="button" class="cp-tab" data-tab="contract" role="tab">Contract</button>
    <button type="button" class="cp-tab" data-tab="invoice" role="tab">Invoice</button>
    <button type="button" class="cp-tab" data-tab="financials" role="tab">Financials</button>
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
        <dt>Secondary email</dt><dd><?php echo e($project->secondaryClientEmail() ?: '—'); ?></dd>
        <dt>Phone</dt><dd><?php echo e(\App\Models\ClientProject::formatPhone($project->client_phone) ?: '—'); ?></dd>
        <dt>Address</dt><dd><?php echo e($project->client_address ?: '—'); ?></dd>
        <dt>Created</dt><dd><?php echo e(\App\Models\ClientProject::formatDateTime($project->created_at)); ?></dd>
        <dt>Status changed</dt><dd><?php echo e(\App\Models\ClientProject::formatDateTime($project->status_changed_at)); ?></dd>
        <?php if($project->visit_required || $project->visit_date || $project->visit_done): ?>
          <dt>Visit</dt>
          <dd>
            <?php if($project->visit_done): ?>
              Done
              <?php if($project->visit_date): ?>
                (<?php echo e(\App\Models\ClientProject::formatDate($project->visit_date)); ?>)
              <?php endif; ?>
            <?php elseif($project->visit_required): ?>
              <?php echo e($project->visit_date ? \App\Models\ClientProject::formatDate($project->visit_date) : 'Required — date not set yet'); ?>

            <?php elseif($project->visit_date): ?>
              <?php echo e(\App\Models\ClientProject::formatDate($project->visit_date)); ?>

            <?php endif; ?>
          </dd>
        <?php endif; ?>
      </dl>

      <?php if($project->notes): ?>
        <h3 style="margin-top:24px;">Notes</h3>
        <div class="cp-notes-box"><?php echo e($project->notes); ?></div>
      <?php endif; ?>
    </div>
  </section>

  
  <section class="cp-tab-panel" data-panel="schedule" id="schedule">
    <?php
      $datesSet = $project->start_date && $project->end_date;
      $hasContact = $project->hasClientContactInfo();
      $canSendDates = !$isRejected && $datesSet && $hasContact;
      $lastSentDates = \App\Models\ClientCommunicationLog::query()
        ->where('client_project_id', $project->id)
        ->where('reason', \App\Models\ClientCommunicationLog::REASON_SCHEDULE_DATES_MANUAL)
        ->where('status', \App\Models\ClientCommunicationLog::STATUS_SENT)
        ->latest('sent_at')
        ->first();
      $lastSentRecipients = $lastSentDates
        ? trim($lastSentDates->recipient.($lastSentDates->cc ? ', '.$lastSentDates->cc : ''), ', ')
        : null;
      $sendDatesConfirm = $lastSentDates
        ? 'Re-send the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?'
        : 'Email the project dates to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?';
    ?>

    <form id="cp-schedule-dates-form" action="<?php echo e(route('admin.client-projects.update', $project)); ?>" method="post" class="cp-schedule-form">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>
      
      <input type="hidden" name="name" value="<?php echo e($project->name); ?>">
      <input type="hidden" name="client_name" value="<?php echo e($project->client_name); ?>">
      <input type="hidden" name="client_email" value="<?php echo e($project->client_email); ?>">
      <input type="hidden" name="client_phone" value="<?php echo e($project->client_phone); ?>">
      <input type="hidden" name="client_address" value="<?php echo e($project->client_address); ?>">
      <input type="hidden" name="notes" value="<?php echo e($project->notes); ?>">

      <?php
        $cpShowVisitDateVal = old('visit_date', optional($project->visit_date)?->toDateString());
        $cpShowVisitExpandOpen = (bool) old('visit_required', $project->visit_required)
          || (bool) old('visit_done', $project->visit_done)
          || filled($cpShowVisitDateVal)
          || $errors->has('visit_date');
      ?>
      <div class="admin-card cp-visit-card">
        <h3>Visit date</h3>
        <p class="admin-form-help">Use this when the client wants an on-site visit before work is scheduled. Turn on <strong>Visit required</strong> to set a date. Check <strong>Visit done</strong> after the visit — the project list will then show job start/end dates again.</p>
        <input type="hidden" name="visit_required" value="0">
        <div class="admin-form-group cp-visit-toggle">
          <label class="cp-checkbox-label">
            <input type="checkbox" name="visit_required" value="1" id="cp-visit-required"
              <?php if(old('visit_required', $project->visit_required)): echo 'checked'; endif; ?>>
            <span>Visit required</span>
          </label>
        </div>
        <div id="cp-schedule-visit-expandable" class="cp-visit-expandable <?php if($cpShowVisitExpandOpen): ?> is-open <?php endif; ?>"
             aria-hidden="<?php echo e($cpShowVisitExpandOpen ? 'false' : 'true'); ?>">
          <div class="cp-visit-expandable__inner">
            <div class="admin-form-group cp-visit-date">
              <label for="cp-visit-date">Visit date</label>
              <input type="date" id="cp-visit-date" name="visit_date"
                value="<?php echo e($cpShowVisitDateVal); ?>">
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
            <div class="admin-form-group cp-visit-toggle">
              <label class="cp-checkbox-label">
                <input type="checkbox" name="visit_done" value="1" id="cp-visit-done"
                  <?php if(old('visit_done', $project->visit_done)): echo 'checked'; endif; ?>>
                <span>Visit done</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="admin-card">
        <h3>Start &amp; End dates</h3>
        <p class="admin-form-help">Saving both dates schedules the project on the Project Calendar and emails you a Google Calendar link.</p>
        <div id="cp-schedule-date-warning" class="admin-flash admin-flash--warning" style="display:none;margin-bottom:14px;" role="alert"></div>

        <div class="cp-schedule-pick-row">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="cp-open-date-picker">
            <i class="fa fa-calendar"></i> Pick on calendar
          </button>
          <span class="admin-form-help cp-schedule-pick-hint">See other projects' booked days and click two days (start, then end) to fill the dates below.</span>
        </div>

        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-start">Start date</label>
            <input type="date" id="cp-start" name="start_date" value="<?php echo e(old('start_date', optional($project->start_date)->toDateString())); ?>">
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
            <label for="cp-end">End date</label>
            <input type="date" id="cp-end" name="end_date" value="<?php echo e(old('end_date', optional($project->end_date)->toDateString())); ?>">
            <?php $__errorArgs = ['end_date'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <p class="admin-form-help">Same-day start &amp; end is allowed.</p>
          </div>
        </div>

        <?php if($project->start_date && $project->end_date): ?>
          <div class="cp-schedule-summary cp-schedule-summary--with-action">
            <span class="cp-schedule-summary__dates">
              <strong>Scheduled:</strong>
              <?php echo e(\App\Models\ClientProject::formatDate($project->start_date)); ?>

              <span class="cp-schedule-summary__arrow" aria-hidden="true">→</span>
              <?php echo e(\App\Models\ClientProject::formatDate($project->end_date)); ?>

            </span>
            <?php if($calendarLink): ?>
              <a href="<?php echo e($calendarLink); ?>" target="_blank" rel="noopener noreferrer" class="cp-calendar-link">
                <i class="fa fa-calendar" aria-hidden="true"></i>
                <span>Add to Google Calendar</span>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (! ($isRejected)): ?>
          <?php if(!$datesSet): ?>
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Save both dates first to enable <strong>Send dates to client</strong>.
            </p>
          <?php elseif(!$hasContact): ?>
            <p class="admin-form-help cp-send-dates-note cp-send-dates-note--warn">
              <i class="fa fa-exclamation-triangle"></i>
              Missing client contact info: <?php echo e(implode(', ', $project->missingClientContactFields())); ?>.
              <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project details</a> to fix.
            </p>
          <?php endif; ?>

          <?php if($lastSentDates): ?>
            <p class="admin-form-help cp-send-dates-note">
              <i class="fa fa-check-circle" style="color:#10b981;"></i>
              Dates last emailed on
              <strong><?php echo e(\App\Models\ClientProject::formatDateTime($lastSentDates->sent_at)); ?></strong>
              to <?php echo e($lastSentRecipients ?: $project->clientEmailRecipientsLabel()); ?>.
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <div class="cp-schedule-actions">
          <button type="submit" class="admin-btn admin-btn--primary">Save dates</button>
          <?php if (! ($isRejected)): ?>
            
            <button type="submit"
                    form="cp-send-dates-form"
                    class="admin-btn admin-btn--primary cp-send-dates-btn"
                    <?php if(!$canSendDates): echo 'disabled'; endif; ?>
                    title="Email <?php echo e($project->clientEmailRecipientsLabel() ?: 'the client'); ?> the scheduled dates.">
              <i class="fa fa-paper-plane"></i>
              <?php echo e($lastSentDates ? 'Re-send dates to client' : 'Send dates to client'); ?>

            </button>
          <?php endif; ?>
        </div>
      </div>
    </form>

    
    <?php if (! ($isRejected)): ?>
      <form id="cp-send-dates-form"
            action="<?php echo e(route('admin.client-projects.schedule-dates.send', $project)); ?>"
            method="post"
            class="cp-send-dates-form"
            onsubmit="return confirm('<?php echo e($sendDatesConfirm); ?>');">
        <?php echo csrf_field(); ?>
      </form>
    <?php endif; ?>

    
    <?php if($project->billing_type === \App\Models\ClientProject::BILLING_HOURLY && !$isRejected): ?>
      <div class="admin-card cp-service-confirmation-card">
        <h3>Service confirmation</h3>
        <p class="admin-form-help">
          Hourly-billing projects skip the quotation flow because the rate was agreed verbally.
          Once the dates are saved above, send the client a confirmation email with the scheduled
          <?php echo e($project->start_date && $project->end_date && \Illuminate\Support\Carbon::parse($project->start_date)->isSameDay(\Illuminate\Support\Carbon::parse($project->end_date)) ? 'date' : 'dates'); ?>,
          hourly rate, materials policy, and payment instructions.
        </p>

        <?php
          $datesSet = $project->start_date && $project->end_date;
          $hasContact = $project->hasClientContactInfo();
          $alreadySent = (bool) $project->service_confirmation_sent_at;
          $canSend = $datesSet && $hasContact;
        ?>

        <?php if(!$datesSet): ?>
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Save both the start and end dates before you can send the confirmation.
          </p>
        <?php elseif(!$hasContact): ?>
          <p class="admin-form-help" style="color:#b45309;">
            <i class="fa fa-exclamation-triangle"></i>
            Missing client contact info: <?php echo e(implode(', ', $project->missingClientContactFields())); ?>.
            <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project details</a> to fix.
          </p>
        <?php endif; ?>

        <?php if($alreadySent): ?>
          <p class="admin-form-help">
            <i class="fa fa-check-circle" style="color:#10b981;"></i>
            Last sent on
            <strong><?php echo e(\App\Models\ClientProject::formatDateTime($project->service_confirmation_sent_at)); ?></strong>.
          </p>
        <?php endif; ?>

        <form action="<?php echo e(route('admin.client-projects.service-confirmation.send', $project)); ?>"
              method="post"
              class="cp-schedule-form"
              onsubmit="return confirm('<?php echo e($alreadySent ? 'Re-send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?' : 'Send the service confirmation email to '.($project->clientEmailRecipientsLabel() ?: 'the client').'?'); ?>');">
          <?php echo csrf_field(); ?>
          <button type="submit"
                  class="admin-btn admin-btn--primary"
                  <?php if(!$canSend): echo 'disabled'; endif; ?>>
            <i class="fa fa-paper-plane"></i>
            <?php echo e($alreadySent ? 'Re-send service confirmation' : 'Send service confirmation'); ?>

          </button>
        </form>
      </div>
    <?php endif; ?>

    
    <div class="cp-modal-backdrop cp-date-picker-backdrop" id="cp-date-picker-modal" hidden>
      <div class="cp-modal cp-date-picker-modal" role="dialog" aria-labelledby="cp-date-picker-title" aria-modal="true">
        <div class="cp-modal-header">
          <h3 id="cp-date-picker-title">Pick start &amp; end dates</h3>
          <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
        </div>
        <div class="cp-modal-body cp-date-picker-body">
          <p class="admin-form-help cp-date-picker-help">
            Click a day to set the <strong>start</strong>, then click another day to set the <strong>end</strong>
            (or drag across multiple days). Coloured blocks are other projects already scheduled.
          </p>
          <div class="cp-date-picker-selection">
            <div>
              <span class="cp-date-picker-selection-label">Start</span>
              <strong id="cp-date-picker-start">—</strong>
            </div>
            <span class="cp-date-picker-selection-arrow" aria-hidden="true">→</span>
            <div>
              <span class="cp-date-picker-selection-label">End</span>
              <strong id="cp-date-picker-end">—</strong>
            </div>
            <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm cp-date-picker-clear" id="cp-date-picker-clear">
              Clear
            </button>
          </div>
          <div id="cp-date-picker-conflict" class="admin-flash admin-flash--warning cp-date-picker-conflict" style="display:none;" role="alert"></div>
          <div id="cp-date-picker-calendar" class="cp-date-picker-calendar"></div>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" id="cp-date-picker-confirm" disabled>
            <i class="fa fa-check"></i> Use these dates
          </button>
        </div>
      </div>
    </div>
  </section>

  
  <section class="cp-tab-panel" data-panel="time" id="time">
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

  
  <section class="cp-tab-panel" data-panel="invoice" id="invoice">
    <?php echo $__env->make('admin.client_projects._invoice', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
  </section>

  
  <section class="cp-tab-panel" data-panel="financials" id="financials">
    <?php if(!$isAccepted): ?>
      <div class="admin-card cp-locked">
        <h3>Financials are locked</h3>
        <p>Financial details become available once the project status is <strong>Proposal Accepted</strong> or later.</p>
      </div>
    <?php else: ?>
      <?php echo $__env->make('admin.client_projects._financials', ['project' => $project], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <?php endif; ?>
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
              <th>Event</th>
              <th>Note</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $project->statusHistory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e($entry->changedAtDisplay()); ?></td>
                <td>
                  <?php if($entry->isEvent()): ?>
                    <span class="cp-badge" style="background:#e5e7eb;color:#4b5563;">Event</span>
                  <?php else: ?>
                    <span class="cp-badge" style="background:#dbeafe;color:#1e40af;">
                      <?php echo e($entry->fromLabel()); ?> → <?php echo e($entry->toLabel()); ?>

                    </span>
                  <?php endif; ?>
                </td>
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
  <script>
    (function () {
      var form = document.getElementById('cp-schedule-dates-form');
      if (!form) return;
      var start = form.querySelector('[name="start_date"]');
      var end = form.querySelector('[name="end_date"]');
      var warn = document.getElementById('cp-schedule-date-warning');
      var visitCb = document.getElementById('cp-visit-required');
      var visitDate = document.getElementById('cp-visit-date');
      var visitDoneCb = document.getElementById('cp-visit-done');
      var visitExpand = document.getElementById('cp-schedule-visit-expandable');

      function syncVisitExpandable() {
        if (!visitExpand) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        var hasDate = visitDate && visitDate.value && String(visitDate.value).trim() !== '';
        var open = required || done || hasDate;
        visitExpand.classList.toggle('is-open', open);
        visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
      }

      function syncVisitFields() {
        if (!visitDate) return;
        var required = visitCb ? visitCb.checked : false;
        var done = visitDoneCb ? visitDoneCb.checked : false;
        visitDate.readOnly = !!done;
        visitDate.disabled = !done && !required;
        visitDate.classList.toggle('cp-visit-date-input--locked', !!done || !required);
        syncVisitExpandable();
      }

      function checkScheduleDates() {
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
        start.addEventListener('change', checkScheduleDates);
        end.addEventListener('change', checkScheduleDates);
      }
      if (visitCb) visitCb.addEventListener('change', syncVisitFields);
      if (visitDoneCb) visitDoneCb.addEventListener('change', syncVisitFields);
      if (visitDate) visitDate.addEventListener('change', syncVisitExpandable);
      if (visitDate) visitDate.addEventListener('input', syncVisitExpandable);
      syncVisitFields();

      form.addEventListener('submit', function (e) {
        if (visitDate && visitCb && !visitCb.checked && !(visitDoneCb && visitDoneCb.checked)) {
          visitDate.disabled = false;
          visitDate.readOnly = false;
          visitDate.value = '';
        }
        if (!checkScheduleDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    })();
  </script>

  
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script>
    (function () {
      var openBtn = document.getElementById('cp-open-date-picker');
      var modal = document.getElementById('cp-date-picker-modal');
      if (!openBtn || !modal) return;

      var calendarEl = document.getElementById('cp-date-picker-calendar');
      var startLabel = document.getElementById('cp-date-picker-start');
      var endLabel = document.getElementById('cp-date-picker-end');
      var clearBtn = document.getElementById('cp-date-picker-clear');
      var confirmBtn = document.getElementById('cp-date-picker-confirm');
      var conflictBox = document.getElementById('cp-date-picker-conflict');
      var startInput = document.getElementById('cp-start');
      var endInput = document.getElementById('cp-end');

      var BUSY_EVENTS = <?php echo json_encode($scheduleEvents, 15, 512) ?>;
      var calendar = null;
      var selection = { start: null, end: null };

      function fmtIso(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
      }
      function parseIso(iso) {
        // Build a local-date (avoid UTC drift you'd get from `new Date('2026-06-10')`).
        var parts = iso.split('-');
        return new Date(+parts[0], +parts[1] - 1, +parts[2]);
      }
      function fmtDisplay(iso) {
        if (!iso) return '—';
        var d = parseIso(iso);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
      }
      function addDaysIso(iso, days) {
        var d = parseIso(iso);
        d.setDate(d.getDate() + days);
        return fmtIso(d);
      }

      function rangeOverlapsBusy(startIso, endIso) {
        if (!startIso || !endIso) return null;
        var startMs = parseIso(startIso).getTime();
        var endMs = parseIso(endIso).getTime();
        for (var i = 0; i < BUSY_EVENTS.length; i++) {
          var ev = BUSY_EVENTS[i];
          var evStart = parseIso(ev.start).getTime();
          // Stored end is exclusive (FullCalendar all-day), make it inclusive.
          var evEnd = parseIso(addDaysIso(ev.end, -1)).getTime();
          if (startMs <= evEnd && endMs >= evStart) {
            return ev;
          }
        }
        return null;
      }

      function refreshSelectionDisplay() {
        startLabel.textContent = fmtDisplay(selection.start);
        endLabel.textContent = fmtDisplay(selection.end);
        confirmBtn.disabled = !(selection.start && selection.end);

        if (calendar) {
          var sources = calendar.getEventSources();
          for (var i = 0; i < sources.length; i++) {
            if (sources[i].id === 'cp-selection') sources[i].remove();
          }
          if (selection.start && selection.end) {
            calendar.addEventSource({
              id: 'cp-selection',
              events: [{
                id: 'cp-selection',
                start: selection.start,
                end: addDaysIso(selection.end, 1),
                allDay: true,
                display: 'background',
                color: '#2563eb',
              }]
            });
          }
        }

        var conflict = rangeOverlapsBusy(selection.start, selection.end);
        if (conflict) {
          conflictBox.style.display = 'block';
          conflictBox.innerHTML = 'Heads up: this range overlaps <strong>' +
            (conflict.title || 'another project') + '</strong>. You can still confirm, but check the calendar first.';
        } else {
          conflictBox.style.display = 'none';
          conflictBox.textContent = '';
        }
      }

      function setSelection(startIso, endIso) {
        if (startIso && endIso && startIso > endIso) {
          var tmp = startIso; startIso = endIso; endIso = tmp;
        }
        selection.start = startIso || null;
        selection.end = endIso || null;
        refreshSelectionDisplay();
      }

      function handleDayClick(iso) {
        // No selection yet, or both dates already set → start fresh.
        if (!selection.start || (selection.start && selection.end)) {
          setSelection(iso, null);
          return;
        }
        // Have a start, no end → set the end (auto-swap if click is earlier).
        setSelection(selection.start, iso);
      }

      function initCalendar() {
        if (calendar || !calendarEl || typeof FullCalendar === 'undefined') return;

        // Seed initial date from the existing inputs (if any) so the calendar
        // opens on the right month.
        var initialDate = startInput && startInput.value ? startInput.value : new Date();

        calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          initialDate: initialDate,
          firstDay: 0,
          height: 'auto',
          headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
          selectable: true,
          unselectAuto: false,
          longPressDelay: 150,
          eventDisplay: 'block',
          events: BUSY_EVENTS,
          eventDidMount: function (info) {
            if (info.event.extendedProps && info.event.extendedProps.status) {
              info.el.setAttribute('title', info.event.title + ' — ' + info.event.extendedProps.status);
            }
          },
          // We intentionally do NOT use `dateClick` here: with `selectable: true`,
          // a single-day click ALSO fires `select`, and the two handlers race
          // (each overwriting the other's state). Routing everything through
          // `select` keeps a single source of truth.
          select: function (info) {
            // FullCalendar's `end` is exclusive for all-day selections.
            var startIso = fmtIso(info.start);
            var endIso = fmtIso(new Date(info.end.getTime() - 24 * 60 * 60 * 1000));
            if (startIso === endIso) {
              // Single-day click → behaves like the 2-step "click start, click end" flow.
              handleDayClick(startIso);
            } else {
              // Drag across multiple days → take the whole range at once.
              setSelection(startIso, endIso);
            }
            calendar.unselect();
          },
        });
        calendar.render();
      }

      function openModal() {
        // Pre-seed selection from current input values when the modal opens.
        if (startInput && startInput.value && endInput && endInput.value) {
          selection.start = startInput.value;
          selection.end = endInput.value;
        } else if (startInput && startInput.value) {
          selection.start = startInput.value;
          selection.end = null;
        } else {
          selection.start = null;
          selection.end = null;
        }

        modal.hidden = false;
        document.body.classList.add('cp-modal-open');

        // Defer init so the modal has real dimensions when the calendar lays out.
        setTimeout(function () {
          initCalendar();
          if (calendar) {
            if (selection.start) calendar.gotoDate(selection.start);
            calendar.updateSize();
          }
          refreshSelectionDisplay();
        }, 0);
      }

      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
      }

      openBtn.addEventListener('click', openModal);
      modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.matches && e.target.matches('[data-cp-modal-close]'))) {
          closeModal();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) closeModal();
      });

      clearBtn.addEventListener('click', function () {
        setSelection(null, null);
      });

      confirmBtn.addEventListener('click', function () {
        if (!selection.start || !selection.end) return;
        if (startInput) {
          startInput.value = selection.start;
          startInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (endInput) {
          endInput.value = selection.end;
          endInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closeModal();
      });
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/show.blade.php ENDPATH**/ ?>