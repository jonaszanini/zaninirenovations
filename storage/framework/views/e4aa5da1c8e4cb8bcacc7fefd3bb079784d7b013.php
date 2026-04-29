

<?php $__env->startSection('heading', 'Edit Invoice — '.$project->name); ?>

<?php $__env->startSection('content'); ?>
  <?php
    /*
     * Build the items array rendered on initial load. Need at least one
     * row visible so the user has something to type into. Saved items
     * are shown first; if none, three empty rows.
     */
    $existing = old('items') ?? array_map(
        fn ($i) => [
            'description' => $i['description'],
            'amount' => $i['amount'],
            'line_kind' => $i['line_kind'] ?? 'labour',
            'expense_id' => $i['expense_id'] ?? null,
        ],
        $invoice->cleanItems()
    );
    if (empty($existing)) {
        $existing = [['description' => '', 'amount' => '', 'line_kind' => 'labour', 'expense_id' => null]];
    }

    $isManual = $invoice->isManual();
    $isHourlyManual = $project->isHourly() && $isManual;
    $isFromTimeEntries = $isManual && $invoice->isFromTimeEntries();
    $manualDescription = old('manual_description', $invoice->installment_description);
    $manualTotal = old('manual_total', $invoice->subtotal > 0 ? number_format((float) $invoice->subtotal, 2, '.', '') : '');
    $dueOn = old('due_on', $invoice->due_on ? $invoice->due_on->toDateString() : null);

    // $lockedSubtotal + $lockedHst + $lockedTotalWithHst are provided by
    // the controller. For installment-linked invoices, $lockedHst is the
    // proportional share of the quotation's HST (NOT a flat 13% of the
    // subtotal), so HST-exempt quotation lines aren't re-taxed.
  ?>

  <div class="admin-card cp-contract-editor-card">
    <div class="cp-contract-editor-toolbar">
      <div class="cp-contract-editor-toolbar-left">
        <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>#invoice"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          <i class="fa fa-arrow-left"></i> Back to project
        </a>
      </div>
      <div class="cp-contract-editor-toolbar-right">
        <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>#invoice"
           class="admin-btn admin-btn--ghost admin-btn--sm">
          Cancel
        </a>
        <button type="submit"
                form="cp-invoice-form"
                class="admin-btn admin-btn--ghost admin-btn--sm"
                data-cp-invoice-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                form="cp-invoice-form"
                class="admin-btn admin-btn--primary admin-btn--sm"
                data-cp-invoice-intent="close">
          Save &amp; Close
        </button>
      </div>
    </div>

    <div class="cp-contract-editor-heading">
      <h3 style="margin:0;">
        Invoice editor — <span class="cp-quotation-number"><?php echo e($invoice->invoice_number); ?></span>
        <?php if($invoice->isSent()): ?>
          <span class="cp-badge" style="background:#3b82f620;color:#1e40af;margin-left:8px;">Sent</span>
        <?php elseif($invoice->isPaid()): ?>
          <span class="cp-badge" style="background:#22c55e20;color:#15803d;margin-left:8px;">Paid</span>
        <?php else: ?>
          <span class="cp-badge" style="background:#f59e0b20;color:#b45309;margin-left:8px;">Draft</span>
        <?php endif; ?>
      </h3>
      <p class="admin-form-help" style="margin:4px 0 0;">
        <?php if($isFromTimeEntries): ?>
          Hourly invoice — prefilled from <?php echo e($invoice->timeEntries->count()); ?> unbilled time entr<?php echo e($invoice->timeEntries->count() === 1 ? 'y' : 'ies'); ?>.
          You can edit the descriptions and amounts freely before sending. Deleting this draft will release the entries back to "unbilled".
          Material lines are tax-included (no extra HST on materials); HST applies to labour only.
        <?php elseif($isManual): ?>
          Manual invoice — no installment link. Enter the pre-HST total and optionally itemise the work below.
        <?php else: ?>
          <strong><?php echo e($invoice->installmentTitle()); ?></strong>.
          The invoice total is locked to the installment amount from the Financials payment plan.
        <?php endif; ?>
      </p>
    </div>

    <form action="<?php echo e(route('admin.client-projects.invoices.update', [$project, $invoice])); ?>"
          method="post"
          id="cp-invoice-form">
      <?php echo csrf_field(); ?>
      <?php echo method_field('PUT'); ?>

      <input type="hidden" name="intent" value="draft" id="cp-invoice-intent">

      
      <div class="cp-quotation-form-grid">
        <div class="admin-form-group">
          <label>
            <?php if($isFromTimeEntries): ?> Source <?php else: ?> Linked installment <?php endif; ?>
          </label>
          <?php if($isFromTimeEntries): ?>
            <div class="cp-invoice-locked-field">
              <span class="cp-badge" style="background:#ecfdf5;color:#15803d;">
                <i class="fa fa-clock-o"></i>
                Hourly — <?php echo e($invoice->timeEntries->count()); ?> time entr<?php echo e($invoice->timeEntries->count() === 1 ? 'y' : 'ies'); ?>

              </span>
            </div>
          <?php elseif($isManual): ?>
            <div class="cp-invoice-locked-field">
              <span class="cp-badge" style="background:#e5e7eb;color:#4b5563;">Manual — no installment</span>
            </div>
          <?php else: ?>
            <div class="cp-invoice-locked-field">
              <strong><?php echo e($invoice->installmentTitle()); ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <div class="admin-form-group">
          <label for="cp-invoice-due">Due date <span style="color:#ef4444;">*</span></label>
          <input type="date"
                 id="cp-invoice-due"
                 name="due_on"
                 class="admin-input"
                 value="<?php echo e($dueOn); ?>">
          <p class="admin-form-help" style="margin:4px 0 0;">Required before the invoice can be sent to the client.</p>
        </div>
      </div>

      <?php if($isManual): ?>
        <div class="admin-form-grid-2">
          <div class="admin-form-group">
            <label for="cp-invoice-manual-desc">Invoice description</label>
            <input type="text"
                   id="cp-invoice-manual-desc"
                   name="manual_description"
                   class="admin-input"
                   maxlength="255"
                   value="<?php echo e($manualDescription); ?>"
                   placeholder="e.g. Extra work — additional drywall">
          </div>
          <?php if (! ($isHourlyManual)): ?>
            <div class="admin-form-group">
              <label for="cp-invoice-manual-total">Pre-HST total (CAD)</label>
              <input type="text"
                     id="cp-invoice-manual-total"
                     name="manual_total"
                     class="admin-input cp-money-input"
                     inputmode="decimal"
                     autocomplete="off"
                     placeholder="0.00"
                     value="<?php echo e($manualTotal); ?>">
              <p class="admin-form-help" style="margin:4px 0 0;">
                If you itemise the work below, the sum of the line items will be used instead.
              </p>
            </div>
          <?php else: ?>
            <div class="admin-form-group">
              <span class="admin-form-help" style="display:block;margin-top:22px;line-height:1.5;color:#374151;">
                Mark each line as Labour (pre-tax) or Materials (<strong>amount includes HST</strong>). Invoice HST applies to labour lines only — materials pass through tax-included.
              </span>
            </div>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="cp-invoice-locked-total">
          <div>
            <span class="admin-form-help">Locked total (from Financials — must match):</span>
          </div>
          <div class="cp-invoice-locked-total-value">
            <div class="cp-invoice-locked-total-subtotal">
              Subtotal: <strong><?php echo e(\App\Models\ClientProject::formatMoney($lockedSubtotal)); ?></strong>
            </div>
            <div class="cp-invoice-locked-total-subtotal">
              HST (from quotation share): <strong><?php echo e(\App\Models\ClientProject::formatMoney($lockedHst)); ?></strong>
            </div>
            <div class="cp-invoice-locked-total-grand">
              Total (incl. HST): <strong><?php echo e(\App\Models\ClientProject::formatMoney($lockedTotalWithHst)); ?></strong>
            </div>
          </div>
        </div>
      <?php endif; ?>

      
      <div class="cp-quotation-items-card" style="margin-top:18px;">
        <div class="cp-quotation-items-header">
          <h4>Line items</h4>
          <button type="button"
                  id="cp-invoice-add-row"
                  class="admin-btn admin-btn--ghost admin-btn--sm">
            <i class="fa fa-plus"></i> Add row
          </button>
        </div>

        <table class="cp-quotation-items-table" id="cp-invoice-items">
          <thead>
            <tr>
              <th class="col-description">Description</th>
              <?php if($isHourlyManual): ?>
                <th class="col-line-kind">Type</th>
              <?php endif; ?>
              <th class="col-amount">Amount ($)</th>
              <th class="col-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php $__currentLoopData = $existing; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $lk = strtolower((string) ($row['line_kind'] ?? 'labour'));
                $lk = $lk === 'materials' ? 'materials' : 'labour';
                $expenseId = $row['expense_id'] ?? null;
              ?>
              <tr class="cp-quotation-row">
                <td class="col-description">
                  <textarea name="items[<?php echo e($i); ?>][description]"
                            class="admin-input cp-quotation-row-desc"
                            rows="2"
                            placeholder="Describe this line item"><?php echo e($row['description']); ?></textarea>
                </td>
                <?php if($isHourlyManual): ?>
                  <td class="col-line-kind">
                    <?php if(!empty($expenseId)): ?>
                      <span class="cp-badge" style="background:#ecfdf5;color:#047857;white-space:nowrap;">Materials (expense)</span>
                      <input type="hidden" name="items[<?php echo e($i); ?>][line_kind]" value="materials">
                      <input type="hidden" name="items[<?php echo e($i); ?>][expense_id]" value="<?php echo e((int) $expenseId); ?>">
                    <?php else: ?>
                      <select name="items[<?php echo e($i); ?>][line_kind]" class="admin-input cp-invoice-line-kind" aria-label="Line type">
                        <option value="labour" <?php if($lk === 'labour'): echo 'selected'; endif; ?>>Labour</option>
                        <option value="materials" <?php if($lk === 'materials'): echo 'selected'; endif; ?>>Materials</option>
                      </select>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
                <td class="col-amount">
                  <input type="text"
                         inputmode="decimal"
                         autocomplete="off"
                         name="items[<?php echo e($i); ?>][amount]"
                         value="<?php echo e($row['amount'] !== '' ? number_format((float) $row['amount'], 2, '.', '') : ''); ?>"
                         class="admin-input cp-money-input cp-quotation-row-amount"
                         placeholder="0.00">
                </td>
                <td class="col-actions">
                  <button type="button"
                          class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                          aria-label="Remove row">
                    <i class="fa fa-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </tbody>
        </table>

        <?php if($isHourlyManual): ?>
          <div class="cp-quotation-totals" id="cp-invoice-hourly-totals">
            <div class="cp-quotation-totals-row">
              <span>Labour subtotal (pre-HST)</span>
              <span id="cp-invoice-labour-sub">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row">
              <span>Materials (tax-included)</span>
              <span id="cp-invoice-materials-sub">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row">
              <span id="cp-invoice-hst-label">HST (13% on labour)</span>
              <span id="cp-invoice-hst">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row cp-quotation-totals-grand">
              <span>Total due</span>
              <span id="cp-invoice-grand">CA$0.00</span>
            </div>
          </div>
        <?php else: ?>
          <div class="cp-quotation-totals" id="cp-invoice-standard-totals">
            <div class="cp-quotation-totals-row">
              <span>Subtotal</span>
              <span id="cp-invoice-subtotal">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row">
              <span id="cp-invoice-hst-label"><?php echo e($isManual ? 'HST (13%)' : 'HST (from quotation share)'); ?></span>
              <span id="cp-invoice-hst">CA$0.00</span>
            </div>
            <div class="cp-quotation-totals-row cp-quotation-totals-grand">
              <span>Total with HST</span>
              <span id="cp-invoice-grand">CA$0.00</span>
            </div>
            <?php if (! ($isManual)): ?>
              <div class="cp-quotation-totals-row cp-invoice-mismatch" id="cp-invoice-mismatch" style="display:none;">
                <span>
                  <i class="fa fa-exclamation-triangle"></i>
                  Line-item subtotal must equal
                  <strong><?php echo e(\App\Models\ClientProject::formatMoney($lockedSubtotal)); ?></strong>
                  to match the locked installment. Current difference: <span id="cp-invoice-diff">—</span>
                </span>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="cp-contract-editor-actions">
        <a href="<?php echo e(route('admin.client-projects.show', $project)); ?>#invoice"
           class="admin-btn admin-btn--ghost">
          Cancel
        </a>
        <button type="submit"
                class="admin-btn admin-btn--ghost"
                data-cp-invoice-intent="draft">
          Save Draft
        </button>
        <button type="submit"
                class="admin-btn admin-btn--primary"
                data-cp-invoice-intent="close">
          Save &amp; Close
        </button>
      </div>
    </form>
  </div>

  
  <?php if($isHourlyManual): ?>
    <template id="cp-invoice-row-template-hourly">
      <tr class="cp-quotation-row">
        <td class="col-description">
          <textarea name="items[__INDEX__][description]"
                    class="admin-input cp-quotation-row-desc"
                    rows="2"
                    placeholder="Describe this line item"></textarea>
        </td>
        <td class="col-line-kind">
          <select name="items[__INDEX__][line_kind]" class="admin-input cp-invoice-line-kind" aria-label="Line type">
            <option value="labour" selected>Labour</option>
            <option value="materials">Materials</option>
          </select>
        </td>
        <td class="col-amount">
          <input type="text"
                 inputmode="decimal"
                 autocomplete="off"
                 name="items[__INDEX__][amount]"
                 class="admin-input cp-money-input cp-quotation-row-amount"
                 placeholder="0.00">
        </td>
        <td class="col-actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                  aria-label="Remove row">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
    </template>
  <?php else: ?>
    <template id="cp-invoice-row-template">
      <tr class="cp-quotation-row">
        <td class="col-description">
          <textarea name="items[__INDEX__][description]"
                    class="admin-input cp-quotation-row-desc"
                    rows="2"
                    placeholder="Describe this line item"></textarea>
        </td>
        <td class="col-amount">
          <input type="text"
                 inputmode="decimal"
                 autocomplete="off"
                 name="items[__INDEX__][amount]"
                 class="admin-input cp-money-input cp-quotation-row-amount"
                 placeholder="0.00">
        </td>
        <td class="col-actions">
          <button type="button"
                  class="admin-btn admin-btn--ghost admin-btn--sm cp-quotation-remove-row"
                  aria-label="Remove row">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
    </template>
  <?php endif; ?>

  <script>
    (function () {
      // Manual invoices use a flat 13% on the full line sum, except hourly manual:
      // labour lines are pre-tax; materials are tax-included; HST = 13% × labour only.
      // Installment-linked invoices freeze HST to the quotation share.
      var HST_RATE = 0.13;
      var HOURLY_MANUAL = <?php echo e($isHourlyManual ? 'true' : 'false'); ?>;
      var LOCKED_SUBTOTAL = <?php echo e($isManual ? 'null' : json_encode(round((float) $lockedSubtotal, 2))); ?>;
      var LOCKED_HST = <?php echo e($isManual ? 'null' : json_encode(round((float) $lockedHst, 2))); ?>;

      var tbody = document.querySelector('#cp-invoice-items tbody');
      var addBtn = document.getElementById('cp-invoice-add-row');
      var template = document.getElementById(HOURLY_MANUAL ? 'cp-invoice-row-template-hourly' : 'cp-invoice-row-template');
      var subtotalEl = document.getElementById('cp-invoice-subtotal');
      var labourEl = document.getElementById('cp-invoice-labour-sub');
      var materialsEl = document.getElementById('cp-invoice-materials-sub');
      var hstEl = document.getElementById('cp-invoice-hst');
      var grandEl = document.getElementById('cp-invoice-grand');
      var mismatchEl = document.getElementById('cp-invoice-mismatch');
      var diffEl = document.getElementById('cp-invoice-diff');

      function formatMoney(n) {
        var v = (typeof n === 'number' && !isNaN(n)) ? n : 0;
        return 'CA$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
      }

      function parseAmount(input) {
        if (!input) return 0;
        if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
          return window.cpMoneyMask.parse(input.value);
        }
        var n = parseFloat(String(input.value).replace(/,/g, ''));
        return isFinite(n) ? n : 0;
      }

      function rowLineKind(tr) {
        var hidden = tr.querySelector('input[type="hidden"][name*="[line_kind]"]');
        if (hidden) return hidden.value || 'labour';
        var sel = tr.querySelector('select.cp-invoice-line-kind');
        return sel ? sel.value : 'labour';
      }

      function recalc() {
        if (HOURLY_MANUAL) {
          var labour = 0;
          var materials = 0;
          tbody.querySelectorAll('tr.cp-quotation-row').forEach(function (tr) {
            var amt = parseAmount(tr.querySelector('.cp-quotation-row-amount'));
            var kind = rowLineKind(tr);
            if (kind === 'materials') materials += amt;
            else labour += amt;
          });
          labour = Math.round(labour * 100) / 100;
          materials = Math.round(materials * 100) / 100;
          var hst = Math.round(labour * HST_RATE * 100) / 100;
          if (labourEl) labourEl.textContent = formatMoney(labour);
          if (materialsEl) materialsEl.textContent = formatMoney(materials);
          if (hstEl) hstEl.textContent = formatMoney(hst);
          if (grandEl) grandEl.textContent = formatMoney(Math.round((labour + hst + materials) * 100) / 100);
          return;
        }

        var inputs = tbody.querySelectorAll('.cp-quotation-row-amount');
        var subtotal = 0;
        inputs.forEach(function (input) {
          subtotal += parseAmount(input);
        });
        subtotal = Math.round(subtotal * 100) / 100;

        var hst;
        if (LOCKED_HST !== null) {
          hst = LOCKED_HST;
        } else {
          hst = Math.round(subtotal * HST_RATE * 100) / 100;
        }

        if (subtotalEl) subtotalEl.textContent = formatMoney(subtotal);
        if (hstEl) hstEl.textContent = formatMoney(hst);
        if (grandEl) grandEl.textContent = formatMoney(Math.round((subtotal + hst) * 100) / 100);

        if (LOCKED_SUBTOTAL !== null && mismatchEl && diffEl) {
          var diff = Math.round((subtotal - LOCKED_SUBTOTAL) * 100) / 100;
          if (Math.abs(diff) > 0.01) {
            mismatchEl.style.display = '';
            diffEl.textContent = (diff > 0 ? '+' : '') + formatMoney(diff);
          } else {
            mismatchEl.style.display = 'none';
          }
        }
      }

      function reindex() {
        tbody.querySelectorAll('tr.cp-quotation-row').forEach(function (tr, idx) {
          tr.querySelectorAll('[name]').forEach(function (el) {
            el.name = el.name.replace(/items\[(\d+|__INDEX__)\]/, 'items[' + idx + ']');
          });
        });
      }

      addBtn.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, tbody.querySelectorAll('tr').length);
        var wrapper = document.createElement('tbody');
        wrapper.innerHTML = html.trim();
        var newRow = wrapper.firstChild;
        tbody.appendChild(newRow);
        reindex();
        if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
          window.cpMoneyMask.wire(newRow.querySelectorAll('.cp-money-input'));
        }
        var desc = newRow.querySelector('.cp-quotation-row-desc');
        if (desc) desc.focus();
      });

      tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('.cp-quotation-remove-row');
        if (!btn) return;
        var rows = tbody.querySelectorAll('tr.cp-quotation-row');
        if (rows.length <= 1) {
          var tr = btn.closest('tr');
          var d = tr.querySelector('.cp-quotation-row-desc');
          var a = tr.querySelector('.cp-quotation-row-amount');
          if (d) d.value = '';
          if (a) a.value = '';
          var kindSel = tr.querySelector('select.cp-invoice-line-kind');
          if (kindSel) kindSel.value = 'labour';
        } else {
          btn.closest('tr').remove();
        }
        reindex();
        recalc();
      });

      tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('cp-quotation-row-amount')) recalc();
      });

      tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('cp-invoice-line-kind')) recalc();
      });

      var intentField = document.getElementById('cp-invoice-intent');
      document.querySelectorAll('[data-cp-invoice-intent]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          intentField.value = btn.getAttribute('data-cp-invoice-intent') || 'draft';
        });
      });

      recalc();
    })();
  </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('admin.layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\xampp\htdocs\ZaniniRenovations\resources\views/admin/client_projects/invoice/edit.blade.php ENDPATH**/ ?>