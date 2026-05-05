<?php
  $isHourly = $project->isHourly();
  $installments = $project->installments;
  $totalAmount = (float) ($project->total_amount ?? 0);
  $paymentPlan = (int) ($project->payment_plan ?? 1);
  $paidInstallmentCount = $installments->where('is_paid', true)->count();
  $planIsLocked = $paidInstallmentCount > 0;
  $hourlyRateConfigured = (float) ($project->hourly_rate ?? 0);
  $quotSub = $project->quotationSubtotalForFinancials();
  $quotGrand = $project->quotationGrandTotalForFinancials();
  $hasQuot = $quotSub !== null && $quotSub >= 0.01;
  // Pre-tax basis for installment % (matches DB total_amount when in sync).
  $hiddenPreTax = $paidInstallmentCount > 0
      ? $totalAmount
      : ($quotSub ?? $totalAmount);
  $canSavePaymentPlan = $paidInstallmentCount > 0 || $hasQuot;

  // The stored `total_amount` and each installment's `amount` are
  // pre-tax (profit / HST set-aside calculations depend on that).
  //
  // For the client-facing "what they actually pay" figure we add the
  // installment's PROPORTIONAL slice of the quotation's HST — not a
  // flat 13% — because a quotation line may have been HST-exempt
  // (e.g. supplied material vs labour). The effective rate is the
  // quotation's HST ÷ subtotal, so it equals 13% only when every line
  // had HST applied. See ClientProjectInstallment::effectiveHst().
  $effectiveHstRate = $hasQuot && $quotSub > 0
      ? (float) $project->acceptedQuotationsFinancialAggregateHst() / (float) $quotSub
      : \App\Models\ClientProject::HST_RATE;
?>

<?php if($isHourly): ?>
  
  <div class="admin-card">
    <h3>Hourly billing</h3>
    <p class="admin-form-help">This project is billed by the hour. The amount earned is calculated from the rate × hours logged in the Time Tracking tab.</p>

    <form action="<?php echo e(route('admin.client-projects.update', $project)); ?>" method="post">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>
      
      <input type="hidden" name="name" value="<?php echo e($project->name); ?>">
      <input type="hidden" name="client_name" value="<?php echo e($project->client_name); ?>">
      <input type="hidden" name="client_email" value="<?php echo e($project->client_email); ?>">
      <input type="hidden" name="client_phone" value="<?php echo e($project->client_phone); ?>">
      <input type="hidden" name="client_address" value="<?php echo e($project->client_address); ?>">
      <input type="hidden" name="notes" value="<?php echo e($project->notes); ?>">
      <input type="hidden" name="start_date" value="<?php echo e(optional($project->start_date)->toDateString()); ?>">
      <input type="hidden" name="end_date" value="<?php echo e(optional($project->end_date)->toDateString()); ?>">
      <input type="hidden" name="billing_type" value="<?php echo e(\App\Models\ClientProject::BILLING_HOURLY); ?>">

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label for="cp-hourly-rate-edit">Rate (CAD / hour) <span class="cp-required">*</span></label>
          <input type="text" id="cp-hourly-rate-edit" name="hourly_rate" required
                 class="cp-money-input"
                 inputmode="decimal"
                 autocomplete="off"
                 placeholder="0.00"
                 value="<?php echo e($hourlyRateConfigured > 0 ? number_format($hourlyRateConfigured, 2, '.', '') : ''); ?>">
          <?php $__errorArgs = ['hourly_rate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </div>
        <div class="admin-form-group">
          <label>Earned so far</label>
          <p class="cp-hourly-earned">
            <?php echo e(\App\Models\ClientProject::formatMoney($project->hourlyAmountEarned())); ?>

            <span class="admin-form-help">
              <?php echo e(number_format($project->totalHours(), 2)); ?> h ×
              <?php echo e(\App\Models\ClientProject::formatMoney($hourlyRateConfigured)); ?> / h
            </span>
          </p>
        </div>
      </div>

      <button type="submit" class="admin-btn admin-btn--primary">Save hourly rate</button>
    </form>
  </div>
<?php else: ?>
  
  <div class="admin-card">
    <h3>Payment plan</h3>
    <form action="<?php echo e(route('admin.client-projects.installments.save', $project)); ?>"
          method="post"
          id="cp-installments-form"
          data-total="<?php echo e($hiddenPreTax); ?>"
          data-plan="<?php echo e($paymentPlan); ?>"
          data-paid-count="<?php echo e($paidInstallmentCount); ?>"
          data-hst-rate="<?php echo e($effectiveHstRate); ?>">
      <?php echo csrf_field(); ?>

      <input type="hidden" name="total_amount" id="cp-total" value="<?php echo e($hiddenPreTax > 0 ? number_format($hiddenPreTax, 2, '.', '') : ''); ?>">

      <div class="admin-form-grid-2">
        <div class="admin-form-group">
          <label>Total with HST (from quotation)</label>
          <?php if($hasQuot): ?>
            <p class="cp-financials-locked-total" id="cp-total-with-hst-display"><?php echo e(\App\Models\ClientProject::formatMoney($quotGrand)); ?></p>
            <p class="admin-form-help">Pre-tax subtotal (used for installment %): <?php echo e(\App\Models\ClientProject::formatMoney($quotSub)); ?>. Manage quotations on the <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>#quotation">Quotation tab</a>.</p>
          <?php else: ?>
            <p class="cp-financials-locked-total">—</p>
            <p class="admin-form-help">Add a quotation with line items first. <?php if($paidInstallmentCount > 0): ?> Payment totals stay fixed while installments are paid. <?php endif; ?></p>
          <?php endif; ?>
        </div>
        <div class="admin-form-group">
          <label for="cp-plan">Payment schedule
            <span class="cp-hint" title="Choose how the client will pay. With 2/3/4 installments, each row below takes a percentage — the dollar amount is calculated for you.">?</span>
          </label>
          <select id="cp-plan" name="payment_plan" <?php if($planIsLocked): echo 'disabled'; endif; ?>>
            <option value="1" <?php if($paymentPlan === 1): echo 'selected'; endif; ?>>Pay in full at the end</option>
            <option value="2" <?php if($paymentPlan === 2): echo 'selected'; endif; ?>>2 installments</option>
            <option value="3" <?php if($paymentPlan === 3): echo 'selected'; endif; ?>>3 installments</option>
            <option value="4" <?php if($paymentPlan === 4): echo 'selected'; endif; ?>>4 installments</option>
          </select>
          <?php if($planIsLocked): ?>
            
            <input type="hidden" name="payment_plan" value="<?php echo e($paymentPlan); ?>">
            <p class="admin-form-help">The payment plan is locked because one or more installments have already been marked paid.</p>
          <?php endif; ?>
        </div>
      </div>

      <div id="cp-installments-editor" class="cp-installments-editor">
        <div class="cp-installments-remaining" id="cp-installments-remaining">
          Remaining to allocate: <span data-remaining>0</span>%
        </div>
        <div class="cp-installments-rows" id="cp-installments-rows">
          <?php $__currentLoopData = $installments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="cp-installment-row <?php echo e($inst->is_paid ? 'is-paid-row' : ''); ?>" data-row data-paid="<?php echo e($inst->is_paid ? '1' : '0'); ?>">
              <div class="cp-installment-label">
                <?php echo e($inst->label ?: 'Installment '.($idx + 1)); ?>

                <?php if($inst->is_paid): ?>
                  <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:6px;">Paid</span>
                <?php endif; ?>
              </div>
              <div class="cp-installment-pct">
                <label>Percentage</label>
                <input type="number" class="cp-pct-input" name="percentages[]" step="0.01" min="0" max="100"
                       value="<?php echo e(number_format((float) $inst->percentage, 2, '.', '')); ?>"
                       <?php if($inst->is_paid): echo 'readonly'; endif; ?>
                       <?php if($inst->is_paid): ?> title="Locked — this installment is already paid." <?php endif; ?>>
                <span>%</span>
              </div>
              <div class="cp-installment-amount">
                <span class="admin-form-help">Amount (incl. 13% HST)</span>
                <span class="cp-amount-preview" data-amount-preview>—</span>
              </div>
            </div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>

      <?php $__errorArgs = ['percentages'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
        <p class="admin-form-error"><?php echo e($message); ?></p>
      <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

      <button type="submit" class="admin-btn admin-btn--primary" <?php if(!$canSavePaymentPlan): echo 'disabled'; endif; ?>>Save payment plan</button>
    </form>
  </div>

  
  <?php if($installments->isNotEmpty()): ?>
    <div class="admin-card">
      <h3>Installments</h3>
      <div class="admin-table-scroll">
      <table class="admin-table cp-installments-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Label</th>
            <th>%</th>
            <th class="cp-col-amount">Amount <span class="admin-form-help cp-incl-hst">(incl. 13% HST)</span></th>
            <th>Status</th>
            <th>Paid on</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php $__currentLoopData = $installments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr class="<?php echo e($inst->is_paid ? 'cp-row-paid' : ''); ?>">
              <td><?php echo e($idx + 1); ?></td>
              <td><?php echo e($inst->label); ?></td>
              <td><?php echo e(number_format((float) $inst->percentage, 2)); ?>%</td>
              <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($inst->effectiveTotal())); ?></td>
              <td>
                <?php if($inst->is_paid): ?>
                  <span class="cp-badge" style="background:#22c55e20;color:#15803d;">Paid</span>
                <?php else: ?>
                  <span class="cp-badge" style="background:#f59e0b20;color:#b45309;">Unpaid</span>
                <?php endif; ?>
              </td>
              <td><?php echo e(\App\Models\ClientProject::formatDate($inst->paid_on)); ?></td>
              <td>
                <form action="<?php echo e(route('admin.client-projects.installments.toggle-paid', [$project, $inst])); ?>" method="post" class="cp-inline-form">
                  <?php echo csrf_field(); ?>
                  <?php if($inst->is_paid): ?>
                    <input type="hidden" name="is_paid" value="0">
                    <button type="submit" class="admin-btn admin-btn--ghost admin-btn--sm">Mark unpaid</button>
                  <?php else: ?>
                    <input type="hidden" name="is_paid" value="1">
                    <input type="date" name="paid_on" value="<?php echo e(now()->toDateString()); ?>" class="cp-paid-date">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Mark paid</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          <?php
            $totalPaidWithHst = $installments->where('is_paid', true)->reduce(
                fn ($carry, $i) => $carry + $i->effectiveTotal(),
                0.0
            );
          ?>
          <tr class="cp-totals-row">
            <td colspan="3"><strong>Total paid</strong></td>
            <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($totalPaidWithHst)); ?></strong></td>
            <td colspan="3"><?php echo e(number_format($project->percentPaid(), 1)); ?>% of total amount</td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>
  <?php endif; ?>
<?php endif; ?>


<div class="admin-card">
  <h3>Expenses</h3>
  <form action="<?php echo e(route('admin.client-projects.expenses.store', $project)); ?>" method="post" class="cp-finance-form">
    <?php echo csrf_field(); ?>
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="<?php echo e(now()->toDateString()); ?>">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input type="text" name="description" required maxlength="500">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="cp-money-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="0.00">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add expense</button>
      </div>
    </div>
    <?php $__errorArgs = ['amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
  </form>

  <?php if($project->expenses->isNotEmpty()): ?>
    <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th class="cp-col-amount">Amount</th><th></th></tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $project->expenses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><?php echo e(\App\Models\ClientProject::formatDate($expense->occurred_on)); ?></td>
            <td><?php echo e($expense->description); ?></td>
            <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($expense->amount)); ?></td>
            <td>
              <form action="<?php echo e(route('admin.client-projects.expenses.destroy', [$project, $expense])); ?>" method="post" onsubmit="return confirm('Delete this expense?');" class="cp-inline-form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="cp-totals-row">
          <td colspan="2"><strong>Total expenses</strong></td>
          <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($project->totalExpensesAmount())); ?></strong></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>
  <?php else: ?>
    <p class="admin-form-help">No expenses recorded yet.</p>
  <?php endif; ?>
</div>


<div class="admin-card">
  <h3>Returns / Credits <span class="cp-hint" title="Money returned from suppliers or credits. These are subtracted from expenses to compute Total Spent.">?</span></h3>
  <form action="<?php echo e(route('admin.client-projects.returns.store', $project)); ?>" method="post" class="cp-finance-form">
    <?php echo csrf_field(); ?>
    <div class="cp-finance-form-grid">
      <div class="admin-form-group">
        <label>Date</label>
        <input type="date" name="occurred_on" required value="<?php echo e(now()->toDateString()); ?>">
      </div>
      <div class="admin-form-group">
        <label>Description</label>
        <input type="text" name="description" required maxlength="500">
      </div>
      <div class="admin-form-group">
        <label>Amount (CAD)</label>
        <input type="text" name="amount" required
               class="cp-money-input"
               inputmode="decimal"
               autocomplete="off"
               placeholder="0.00">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add return</button>
      </div>
    </div>
  </form>

  <?php if($project->returns->isNotEmpty()): ?>
    <div class="admin-table-scroll">
    <table class="admin-table">
      <thead>
        <tr><th>Date</th><th>Description</th><th class="cp-col-amount">Amount</th><th></th></tr>
      </thead>
      <tbody>
        <?php $__currentLoopData = $project->returns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $return): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td><?php echo e(\App\Models\ClientProject::formatDate($return->occurred_on)); ?></td>
            <td><?php echo e($return->description); ?></td>
            <td class="cp-col-amount">−<?php echo e(\App\Models\ClientProject::formatMoney($return->amount)); ?></td>
            <td>
              <form action="<?php echo e(route('admin.client-projects.returns.destroy', [$project, $return])); ?>" method="post" onsubmit="return confirm('Delete this return?');" class="cp-inline-form">
                <?php echo csrf_field(); ?>
                <?php echo method_field('DELETE'); ?>
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <tr class="cp-totals-row">
          <td colspan="2"><strong>Total returns</strong></td>
          <td class="cp-col-amount"><strong>−<?php echo e(\App\Models\ClientProject::formatMoney($project->totalReturnsAmount())); ?></strong></td>
          <td></td>
        </tr>
      </tbody>
    </table>
    </div>
  <?php else: ?>
    <p class="admin-form-help">No returns recorded yet.</p>
  <?php endif; ?>
</div>


<div class="admin-card cp-profit-card">
  <h3>Profit summary
    <span class="cp-hint" title="<?php echo e($isHourly
      ? 'Revenue = rate × logged hours (pre-tax labour). Expenses on Financials track materials reimbursed by the client—they are shown as cash tracked but do not reduce gross/net profit below. HST set aside = 13% of labour revenue.'
      : 'Revenue = paid installments (fixed price). HST set aside at 13% of revenue (Canadian HST).'); ?>">?</span></h3>
  <?php
    $revenue = $project->totalRevenueCollected();
    $spent = $project->totalSpent();
    $gross = $project->grossProfit();
    $hst = $project->hstToSave();
    $netAfterHst = $project->netProfitAfterHst();
  ?>
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label"><?php echo e($isHourly ? 'Revenue (rate × hours)' : 'Revenue (paid)'); ?></div>
      <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($revenue)); ?></div>
    </div>
    <div class="cp-profit-tile">
      <?php if($isHourly): ?>
        <div class="cp-profit-label">Total spent (tracked)</div>
        <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($spent)); ?></div>
        <p class="admin-form-help" style="margin:6px 0 0;font-size:12px;line-height:1.4;color:#6b7280;">
          Materials reimbursed on client invoices—not deducted from gross or net profit below.
        </p>
      <?php else: ?>
        <div class="cp-profit-label">Total spent (expenses − returns)</div>
        <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($spent)); ?></div>
      <?php endif; ?>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Gross profit</div>
      <div class="cp-profit-value <?php echo e($gross >= 0 ? 'is-positive' : 'is-negative'); ?>"><?php echo e(\App\Models\ClientProject::formatMoney($gross)); ?></div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">HST to save (13%)</div>
      <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($hst)); ?></div>
    </div>
    <div class="cp-profit-tile cp-profit-tile--highlight">
      <div class="cp-profit-label">Net profit after HST</div>
      <div class="cp-profit-value <?php echo e($netAfterHst >= 0 ? 'is-positive' : 'is-negative'); ?>"><?php echo e(\App\Models\ClientProject::formatMoney($netAfterHst)); ?></div>
    </div>
  </div>
</div>
<?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/_financials.blade.php ENDPATH**/ ?>