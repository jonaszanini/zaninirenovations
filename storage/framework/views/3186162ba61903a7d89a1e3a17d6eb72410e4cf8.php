<?php
  $installments = $project->installments;
  $totalAmount = (float) ($project->total_amount ?? 0);
  $paymentPlan = (int) ($project->payment_plan ?? 1);
?>


<div class="admin-card">
  <h3>Payment plan</h3>
  <form action="<?php echo e(route('admin.client-projects.installments.save', $project)); ?>" method="post" id="cp-installments-form" data-total="<?php echo e($totalAmount); ?>" data-plan="<?php echo e($paymentPlan); ?>">
    <?php echo csrf_field(); ?>

    <div class="admin-form-grid-2">
      <div class="admin-form-group">
        <label for="cp-total">Total project amount (CAD) <span class="cp-required">*</span></label>
        <input type="number" id="cp-total" name="total_amount" step="0.01" min="0.01" required
               value="<?php echo e(old('total_amount', $totalAmount > 0 ? number_format($totalAmount, 2, '.', '') : '')); ?>">
        <?php $__errorArgs = ['total_amount'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="admin-form-error"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div class="admin-form-group">
        <label for="cp-plan">Payment schedule
          <span class="cp-hint" title="Choose how the client will pay. With 2/3/4 installments, each row below takes a percentage — the dollar amount is calculated for you.">?</span>
        </label>
        <select id="cp-plan" name="payment_plan">
          <option value="1" <?php if($paymentPlan === 1): echo 'selected'; endif; ?>>Pay in full at the end</option>
          <option value="2" <?php if($paymentPlan === 2): echo 'selected'; endif; ?>>2 installments</option>
          <option value="3" <?php if($paymentPlan === 3): echo 'selected'; endif; ?>>3 installments</option>
          <option value="4" <?php if($paymentPlan === 4): echo 'selected'; endif; ?>>4 installments</option>
        </select>
      </div>
    </div>

    <div id="cp-installments-editor" class="cp-installments-editor">
      <div class="cp-installments-remaining" id="cp-installments-remaining">
        Remaining to allocate: <span data-remaining>0</span>%
      </div>
      <div class="cp-installments-rows" id="cp-installments-rows">
        <?php $__currentLoopData = $installments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $idx => $inst): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="cp-installment-row" data-row>
            <div class="cp-installment-label"><?php echo e($inst->label ?: 'Installment '.($idx + 1)); ?></div>
            <div class="cp-installment-pct">
              <label>Percentage</label>
              <input type="number" class="cp-pct-input" name="percentages[]" step="0.01" min="0" max="100"
                     value="<?php echo e(number_format((float) $inst->percentage, 2, '.', '')); ?>">
              <span>%</span>
            </div>
            <div class="cp-installment-amount">
              <span class="admin-form-help">Amount</span>
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

    <button type="submit" class="admin-btn admin-btn--primary">Save payment plan</button>
  </form>
</div>


<?php if($installments->isNotEmpty()): ?>
  <div class="admin-card">
    <h3>Installments</h3>
    <table class="admin-table cp-installments-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Label</th>
          <th>%</th>
          <th class="cp-col-amount">Amount</th>
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
            <td class="cp-col-amount"><?php echo e(\App\Models\ClientProject::formatMoney($inst->amount)); ?></td>
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
        <tr class="cp-totals-row">
          <td colspan="3"><strong>Total paid</strong></td>
          <td class="cp-col-amount"><strong><?php echo e(\App\Models\ClientProject::formatMoney($project->totalRevenueCollected())); ?></strong></td>
          <td colspan="3"><?php echo e(number_format($project->percentPaid(), 1)); ?>% of total amount</td>
        </tr>
      </tbody>
    </table>
  </div>
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
        <input type="number" name="amount" required step="0.01" min="0.01">
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
        <input type="number" name="amount" required step="0.01" min="0.01">
      </div>
      <div class="admin-form-group cp-finance-form-actions">
        <button type="submit" class="admin-btn admin-btn--primary admin-btn--sm">Add return</button>
      </div>
    </div>
  </form>

  <?php if($project->returns->isNotEmpty()): ?>
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
  <?php else: ?>
    <p class="admin-form-help">No returns recorded yet.</p>
  <?php endif; ?>
</div>


<div class="admin-card cp-profit-card">
  <h3>Profit summary <span class="cp-hint" title="Revenue = sum of paid installments. HST set aside at 13% of revenue (Canadian HST).">?</span></h3>
  <?php
    $revenue = $project->totalRevenueCollected();
    $spent = $project->totalSpent();
    $gross = $project->grossProfit();
    $hst = $project->hstToSave();
    $netAfterHst = $project->netProfitAfterHst();
  ?>
  <div class="cp-profit-grid">
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Revenue (paid)</div>
      <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($revenue)); ?></div>
    </div>
    <div class="cp-profit-tile">
      <div class="cp-profit-label">Total spent (expenses − returns)</div>
      <div class="cp-profit-value"><?php echo e(\App\Models\ClientProject::formatMoney($spent)); ?></div>
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