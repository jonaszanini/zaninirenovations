

<?php
  $isHourly = $project->isHourly();
  $isAccepted = $project->isAccepted();
  $invoices = $project->invoices;

  $missing = $project->missingClientContactFields();
  $hasContactInfo = empty($missing);
?>

<?php if($isHourly): ?>
  
  <?php
    $hourlyRate = round((float) ($project->hourly_rate ?? 0), 2);

    // Time entries with duration set (open entries without end_time are
    // ignored until closed out).
    $closedEntries = $project->timeEntries()
        ->whereNotNull('hours')
        ->get();

    $totalHours = round((float) $closedEntries->sum('hours'), 2);
    $unbilledEntries = $closedEntries->whereNull('client_project_invoice_id');
    $unbilledHours = round((float) $unbilledEntries->sum('hours'), 2);
    $billedHours = round($totalHours - $unbilledHours, 2);

    $unbilledSubtotal = round($unbilledHours * $hourlyRate, 2);
    $unbilledTotal = round($unbilledSubtotal * (1 + \App\Models\ClientProjectInvoice::HST_RATE), 2);

    $canCreateFromHours = $unbilledEntries->isNotEmpty() && $hourlyRate > 0;
  ?>

  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>Invoices</h3>
        <p class="admin-form-help" style="margin:6px 0 0;">
          This project is billed by the hour. Log time on the
          <a href="#time" data-cp-jump-tab="time">Time Tracking</a> tab, then create
          an invoice here whenever you're ready to bill the client for unbilled hours.
          You can also raise a manual invoice for anything not tied to tracked time
          (e.g. materials, delivery fees). Once you mark an invoice as paid, a receipt
          is generated and emailed to the client automatically.
        </p>
      </div>

      <div class="cp-contract-actions">
        <?php if($canCreateFromHours): ?>
          <form action="<?php echo e(route('admin.client-projects.invoices.create', $project)); ?>" method="post" class="cp-inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="mode" value="hourly">
            <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm"
                    title="Snapshot <?php echo e($unbilledEntries->count()); ?> unbilled time entr<?php echo e($unbilledEntries->count() === 1 ? 'y' : 'ies'); ?> into a new draft invoice.">
              <i class="fa fa-clock-o"></i> Create invoice from hours
            </button>
          </form>
        <?php else: ?>
          <button type="button" class="admin-btn admin-btn--primary admin-btn--sm" disabled
                  title="<?php echo e($hourlyRate <= 0 ? 'Set the hourly rate on the project first.' : 'No unbilled hours to invoice — log time on the Time Tracking tab first.'); ?>">
            <i class="fa fa-clock-o"></i> Create invoice from hours
          </button>
        <?php endif; ?>

        <form action="<?php echo e(route('admin.client-projects.invoices.create', $project)); ?>" method="post" class="cp-inline-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="mode" value="manual">
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Create a manual invoice not tied to tracked hours (e.g. materials).">
            <i class="fa fa-plus"></i> New manual invoice
          </button>
        </form>
      </div>
    </div>

    <?php if(!$hasContactInfo): ?>
      <div class="cp-contract-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div>
          <strong>Can't email invoices yet.</strong>
          Missing client info: <strong><?php echo e(implode(', ', $missing)); ?></strong>.
          <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project details to fix.</a>
        </div>
      </div>
    <?php endif; ?>

    
    <div class="cp-hourly-summary">
      <div class="cp-hourly-summary-tile">
        <div class="cp-hourly-summary-label">Hours logged</div>
        <div class="cp-hourly-summary-value"><?php echo e(number_format($totalHours, 2)); ?> h</div>
        <?php if($hourlyRate > 0): ?>
          <div class="cp-hourly-summary-sub">@ <?php echo e(\App\Models\ClientProject::formatMoney($hourlyRate)); ?>/h</div>
        <?php endif; ?>
      </div>
      <div class="cp-hourly-summary-tile">
        <div class="cp-hourly-summary-label">Already invoiced</div>
        <div class="cp-hourly-summary-value"><?php echo e(number_format($billedHours, 2)); ?> h</div>
      </div>
      <div class="cp-hourly-summary-tile cp-hourly-summary-tile--highlight">
        <div class="cp-hourly-summary-label">Unbilled</div>
        <div class="cp-hourly-summary-value"><?php echo e(number_format($unbilledHours, 2)); ?> h</div>
        <?php if($unbilledHours > 0 && $hourlyRate > 0): ?>
          <div class="cp-hourly-summary-sub">
            <?php echo e(\App\Models\ClientProject::formatMoney($unbilledSubtotal)); ?> + HST =
            <strong><?php echo e(\App\Models\ClientProject::formatMoney($unbilledTotal)); ?></strong>
          </div>
        <?php endif; ?>
      </div>
    </div>

    
    <?php if($invoices->isEmpty()): ?>
      <p class="admin-form-help" style="margin-top:18px;">
        <i class="fa fa-info-circle"></i>
        No invoices yet. When you're ready to bill, click <strong>Create invoice from hours</strong>
        to snapshot the unbilled entries into a draft, then review and send.
      </p>
    <?php else: ?>
      <p class="admin-form-help" style="margin-top:14px; margin-bottom:6px;">
        <i class="fa fa-info-circle"></i>
        HST (13%) is added to each invoice once, on top of the pre-tax subtotal.
      </p>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST (13%)</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $invoices->sortByDesc('id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
                $entryCount = $invoice->timeEntries->count();
              ?>
              <tr>
                <td>
                  <?php echo e($invoice->installment_description ?: 'Hourly work'); ?>

                  <?php if($entryCount > 0): ?>
                    <div class="admin-form-help" style="margin-top:2px;">
                      <i class="fa fa-clock-o"></i>
                      <?php echo e($entryCount); ?> time entr<?php echo e($entryCount === 1 ? 'y' : 'ies'); ?> snapshotted
                    </div>
                  <?php endif; ?>
                  <?php if($invoice->isHstExempt()): ?>
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($invoice->subtotal)); ?></td>
                <td class="cp-col-amount">
                  <?php if($invoice->isHstExempt()): ?>
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  <?php else: ?>
                    <?php echo e(\App\Models\ClientProject::formatMoney($invoice->hst)); ?>

                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($invoice->total)); ?></strong></td>
                <td>
                  <span class="cp-badge" style="background: <?php echo e($statusColor); ?>20; color: <?php echo e($statusColor); ?>;">
                    <?php echo e($statusLabel); ?>

                    <span style="font-weight:400;opacity:.8;">· <?php echo e($invoice->invoice_number); ?></span>
                  </span>
                </td>
                <td>
                  <?php echo $__env->make('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => null,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<?php elseif(!$isAccepted): ?>
  <div class="admin-card cp-locked">
    <h3>Invoices are locked</h3>
    <p>Invoices become available once the project status is <strong>Proposal Accepted</strong> or later. Lock in the payment plan on the Financials tab first.</p>
  </div>

<?php else: ?>
  
  <?php
    $installments = $project->installments;
    $installmentInvoices = $invoices->whereNotNull('installment_number')->keyBy('installment_number');
    $manualInvoices = $invoices->whereNull('installment_number')->values();
  ?>

  
  
  
  <div class="admin-card cp-contract-card">
    <div class="cp-contract-header">
      <div class="cp-contract-meta">
        <h3>Invoices</h3>
        <p class="admin-form-help" style="margin:6px 0 0;">
          One invoice per installment from your <a href="#financials" data-cp-jump-tab="financials">Payment plan</a>.
          When you mark an invoice as paid, a receipt is generated and emailed to the client automatically,
          and the matching installment on the Financials tab is marked paid for you.
        </p>
      </div>

      <div class="cp-contract-actions">
        
        <form action="<?php echo e(route('admin.client-projects.invoices.create', $project)); ?>" method="post" class="cp-inline-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="mode" value="manual">
          <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm" title="Create an invoice not tied to a specific installment (e.g. extra work, materials).">
            <i class="fa fa-plus"></i> New manual invoice
          </button>
        </form>
      </div>
    </div>

    <?php if(!$hasContactInfo): ?>
      <div class="cp-contract-warning">
        <i class="fa fa-exclamation-triangle"></i>
        <div>
          <strong>Can't email invoices yet.</strong>
          Missing client info: <strong><?php echo e(implode(', ', $missing)); ?></strong>.
          <a href="<?php echo e(route('admin.client-projects.edit', $project)); ?>">Edit project details to fix.</a>
        </div>
      </div>
    <?php endif; ?>

    <?php if($installments->isEmpty()): ?>
      <p class="admin-form-help" style="margin-top:10px;">
        <i class="fa fa-info-circle"></i>
        No payment plan has been saved yet. Add the total amount and installments on the
        <a href="#financials" data-cp-jump-tab="financials">Financials</a> tab first, then come back here to invoice each installment.
      </p>
    <?php else: ?>

      
      <p class="admin-form-help" style="margin:10px 0 6px;">
        <i class="fa fa-info-circle"></i>
        Subtotal comes from the pre-tax installment amount on the
        <a href="#financials" data-cp-jump-tab="financials">Financials payment plan</a>.
        HST is applied once at invoice time, sliced proportionally from the
        quotation's actual HST — so HST-exempt quotation lines (e.g. supplied
        material) never get re-taxed here.
        Existing draft / sent invoices keep their stored values; create a new
        invoice if you change the payment plan after one was sent.
      </p>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th style="width:50px;">#</th>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $installments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $n = $idx + 1;
                $invoice = $installmentInvoices->get($n);

                // Prefer the stored invoice values when an invoice exists —
                // those are what was (or will be) emailed. Otherwise project
                // the figures from the installment + quotation HST share.
                if ($invoice) {
                    $rowSubtotal = (float) $invoice->subtotal;
                    $rowHst = (float) $invoice->hst;
                    $rowTotal = (float) $invoice->total;
                } else {
                    $rowSubtotal = (float) $inst->amount;
                    $rowHst = $inst->effectiveHst();
                    $rowTotal = $inst->effectiveTotal();
                }

                // Highlight when the existing invoice's stored total no
                // longer matches the projected installment total (e.g.
                // an old invoice predates the HST fix). Admin should
                // delete + recreate to bring it in line.
                $expectedTotal = $inst->effectiveTotal();
                $hasMismatch = $invoice && abs($rowTotal - $expectedTotal) > 0.01;

                $status = $invoice?->status;
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($status);
              ?>
              <tr>
                <td><strong><?php echo e($n); ?></strong></td>
                <td>
                  <?php echo e($inst->label ?: 'Installment '.$n); ?>

                  <?php if($inst->is_paid): ?>
                    <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:4px;">Installment paid</span>
                  <?php endif; ?>
                  <?php if($hasMismatch): ?>
                    <div class="admin-form-help" style="margin-top:4px;color:#b45309;">
                      <i class="fa fa-exclamation-triangle"></i>
                      Stored invoice total (<?php echo e(\App\Models\ClientProject::formatMoney($rowTotal)); ?>)
                      doesn't match the current expected total
                      (<?php echo e(\App\Models\ClientProject::formatMoney($expectedTotal)); ?>).
                      Delete and recreate this invoice to refresh.
                    </div>
                  <?php endif; ?>
                  <?php if($invoice && $invoice->isHstExempt()): ?>
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($rowSubtotal)); ?></td>
                <td class="cp-col-amount">
                  <?php if($invoice && $invoice->isHstExempt()): ?>
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  <?php else: ?>
                    <?php echo e(\App\Models\ClientProject::formatMoney($rowHst)); ?>

                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($rowTotal)); ?></strong></td>
                <td>
                  <span class="cp-badge" style="background: <?php echo e($statusColor); ?>20; color: <?php echo e($statusColor); ?>;">
                    <?php echo e($statusLabel); ?>

                    <?php if($invoice && $invoice->invoice_number): ?>
                      <span style="font-weight:400;opacity:.8;">· <?php echo e($invoice->invoice_number); ?></span>
                    <?php endif; ?>
                  </span>
                </td>
                <td>
                  <?php echo $__env->make('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => $n,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    
    <?php if($manualInvoices->isNotEmpty()): ?>
      <h4 style="margin:24px 0 6px;">Manual invoices</h4>
      <p class="admin-form-help" style="margin:0 0 6px;">
        <i class="fa fa-info-circle"></i>
        HST (13%) is added to the manual invoice subtotal once — these invoices are not tied to the quotation, so the breakdown below is the only HST applied.
      </p>
      <div class="admin-table-scroll">
        <table class="admin-table cp-invoices-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style="width:120px;">Subtotal</th>
              <th style="width:120px;">HST (13%)</th>
              <th style="width:130px;">Total <span class="admin-form-help cp-incl-hst">(incl. HST)</span></th>
              <th style="width:130px;">Status</th>
              <th style="width:340px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $manualInvoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $statusLabel = \App\Models\ClientProjectInvoice::statusLabel($invoice->status);
                $statusColor = \App\Models\ClientProjectInvoice::statusColor($invoice->status);
              ?>
              <tr>
                <td>
                  <?php echo e($invoice->installment_description ?: 'Manual invoice'); ?>

                  <?php if($invoice->isHstExempt()): ?>
                    <div style="margin-top:4px;">
                      <span class="cp-badge" style="background:#fef3c7;color:#92400e;" title="Cash payment — no HST charged on this invoice.">
                        <i class="fa fa-money"></i> HST excluded
                      </span>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($invoice->subtotal)); ?></td>
                <td class="cp-col-amount">
                  <?php if($invoice->isHstExempt()): ?>
                    <span class="admin-form-help" style="font-style:italic;">— excluded</span>
                  <?php else: ?>
                    <?php echo e(\App\Models\ClientProject::formatMoney($invoice->hst)); ?>

                  <?php endif; ?>
                </td>
                <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($invoice->total)); ?></strong></td>
                <td>
                  <span class="cp-badge" style="background: <?php echo e($statusColor); ?>20; color: <?php echo e($statusColor); ?>;">
                    <?php echo e($statusLabel); ?>

                    <span style="font-weight:400;opacity:.8;">· <?php echo e($invoice->invoice_number); ?></span>
                  </span>
                </td>
                <td>
                  <?php echo $__env->make('admin.client_projects._invoice_row_actions', [
                    'project' => $project,
                    'invoice' => $invoice,
                    'installmentNumber' => null,
                    'hasContactInfo' => $hasContactInfo,
                    'missing' => $missing,
                  ], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>


<?php if($isHourly || $isAccepted): ?>
  <div class="cp-modal-backdrop" id="cp-mark-paid-modal" hidden>
    <div class="cp-modal" role="dialog" aria-labelledby="cp-mark-paid-title">
      <div class="cp-modal-header">
        <h3 id="cp-mark-paid-title">Mark invoice as paid</h3>
        <button type="button" class="cp-modal-close" data-cp-modal-close aria-label="Close">&times;</button>
      </div>
      <form method="post" id="cp-mark-paid-form">
        <?php echo csrf_field(); ?>
        <div class="cp-modal-body">
          <p class="admin-form-help" style="margin-top:0;">
            This will mark <strong id="cp-mark-paid-invoice-label">the invoice</strong>
            as paid, generate a <strong>Receipt PDF</strong>, and — if the client's email is on file —
            send the receipt to them automatically.
            <?php if (! ($isHourly)): ?> The linked installment on the Financials tab will be marked paid for you. <?php endif; ?>
          </p>
          <div class="admin-form-grid-2">
            <div class="admin-form-group">
              <label for="cp-mark-paid-date">Payment date</label>
              <input type="date" id="cp-mark-paid-date" name="paid_on" required value="<?php echo e(now()->toDateString()); ?>">
            </div>
            <div class="admin-form-group">
              <label for="cp-mark-paid-amount">Amount received (CAD)</label>
              <input type="text"
                     id="cp-mark-paid-amount"
                     name="paid_amount"
                     class="cp-money-input"
                     inputmode="decimal"
                     autocomplete="off"
                     placeholder="0.00"
                     required>
            </div>
          </div>
          <div class="admin-form-group cp-mark-paid-method">
            <label class="cp-mark-paid-method-label">Payment method</label>
            <div class="cp-mark-paid-method-options" role="radiogroup" aria-label="Payment method">
              <label class="cp-mark-paid-method-option">
                <input type="radio" name="payment_method" value="<?php echo e(\App\Models\ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER); ?>" checked>
                <span><i class="fa fa-bank"></i> e-Transfer</span>
              </label>
              <label class="cp-mark-paid-method-option">
                <input type="radio" name="payment_method" value="<?php echo e(\App\Models\ClientProjectInvoice::PAYMENT_METHOD_CASH); ?>">
                <span><i class="fa fa-money"></i> Cash</span>
              </label>
            </div>
          </div>

          <?php
            $markPaidStatusLabels = collect(\App\Models\ClientProject::statusLabels())
              ->reject(fn ($label, $key) => $key === \App\Models\ClientProject::STATUS_REJECTED);
          ?>
          <fieldset id="cp-mark-paid-status-fieldset" class="cp-mark-paid-project-status" disabled>
            <legend class="cp-mark-paid-project-status-legend">Project status</legend>
            <p class="admin-form-help" style="margin:0 0 8px;">
              This is the <strong>first paid invoice</strong> on this project. Optionally move the project to a new workflow step now.
            </p>
            <div class="admin-form-group" style="margin-bottom:0;">
              <label for="cp-mark-paid-project-status">Set project status to</label>
              <select id="cp-mark-paid-project-status" name="project_status" class="admin-input">
                <option value="">Keep current — <?php echo e($project->statusLabel()); ?></option>
                <?php $__currentLoopData = $markPaidStatusLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $lbl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="<?php echo e($key); ?>"><?php echo e($lbl); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              </select>
            </div>
          </fieldset>

          <label class="cp-checkbox-row" style="margin-top:10px;">
            <input type="hidden" name="also_email_receipt" value="0">
            <input type="checkbox" name="also_email_receipt" value="1" checked>
            <span>Email receipt to client now</span>
          </label>
        </div>
        <div class="cp-modal-footer">
          <button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-cp-modal-close>Cancel</button>
          <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">
            <i class="fa fa-check"></i> Confirm and generate receipt
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
    (function () {
      // ---- Mark-paid modal ---------------------------------------
      var modal = document.getElementById('cp-mark-paid-modal');
      var form = document.getElementById('cp-mark-paid-form');
      var amountInput = document.getElementById('cp-mark-paid-amount');
      var label = document.getElementById('cp-mark-paid-invoice-label');
      var statusFieldset = document.getElementById('cp-mark-paid-status-fieldset');
      var statusSelect = document.getElementById('cp-mark-paid-project-status');

      function openModal(trigger) {
        var action = trigger.getAttribute('data-cp-markpaid-url');
        var amount = trigger.getAttribute('data-cp-markpaid-amount') || '0.00';
        var invoiceLabel = trigger.getAttribute('data-cp-markpaid-label') || 'this invoice';
        var askStatus = trigger.getAttribute('data-cp-markpaid-first-paid') === '1';
        form.action = action;
        amountInput.value = amount;
        label.textContent = invoiceLabel;

        if (statusFieldset) {
          statusFieldset.disabled = !askStatus;
        }
        if (statusSelect && askStatus) {
          statusSelect.value = '';
        }

        // Re-wire the money mask on the freshly-shown input.
        if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
          window.cpMoneyMask.wire([amountInput]);
        }
        modal.hidden = false;
        document.body.classList.add('cp-modal-open');
      }

      function closeModal() {
        modal.hidden = true;
        document.body.classList.remove('cp-modal-open');
      }

      document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-cp-markpaid-url]');
        if (t) {
          e.preventDefault();
          openModal(t);
          return;
        }
        if (e.target.matches('[data-cp-modal-close]') || e.target === modal) {
          closeModal();
        }
      });

      document.addEventListener('keydown', function (e) {
        if (!modal.hidden && e.key === 'Escape') closeModal();
      });

      // ---- In-tab jump to another tab ---------------------------
      document.querySelectorAll('[data-cp-jump-tab]').forEach(function (a) {
        a.addEventListener('click', function (e) {
          var key = a.getAttribute('data-cp-jump-tab');
          var btn = document.querySelector('.cp-tab[data-tab="' + key + '"]');
          if (btn) {
            e.preventDefault();
            btn.click();
          }
        });
      });
    })();
  </script>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_invoice.blade.php ENDPATH**/ ?>