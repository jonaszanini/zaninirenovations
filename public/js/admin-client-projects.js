/*
 * Admin — Client Projects dynamic installment editor.
 *
 * Keeps the payment-plan form in sync:
 *   - When the user selects "2/3/4 installments", the correct number of
 *     percentage rows is rendered.
 *   - As they type percentages, the dollar-amount next to each row updates
 *     live and a "Remaining to allocate" counter shows how far from 100% they are.
 */
(function () {
  function render() {
    var form = document.getElementById('cp-installments-form');
    if (!form) return;

    var planSelect = document.getElementById('cp-plan');
    var totalInput = document.getElementById('cp-total');
    var rowsEl = document.getElementById('cp-installments-rows');
    var remainingWrap = document.getElementById('cp-installments-remaining');
    if (!planSelect || !totalInput || !rowsEl || !remainingWrap) return;

    var plan = parseInt(planSelect.value, 10) || 1;

    // Collect existing percentage values before re-rendering so we preserve user input.
    var existingPercents = Array.from(rowsEl.querySelectorAll('.cp-pct-input')).map(function (inp) {
      return inp.value;
    });

    var ordinals = ['1st', '2nd', '3rd', '4th'];

    if (plan === 1) {
      rowsEl.innerHTML = [
        '<div class="cp-installment-row" data-row>',
        '  <div class="cp-installment-label">Full payment</div>',
        '  <div class="cp-installment-pct">',
        '    <label>Percentage</label>',
        '    <input type="hidden" name="percentages[]" value="100">',
        '    <input type="number" class="cp-pct-input" value="100" disabled>',
        '    <span>%</span>',
        '  </div>',
        '  <div class="cp-installment-amount">',
        '    <span class="admin-form-help">Amount</span>',
        '    <span class="cp-amount-preview" data-amount-preview>—</span>',
        '  </div>',
        '</div>',
      ].join('');
      remainingWrap.classList.add('is-hidden');
    } else {
      remainingWrap.classList.remove('is-hidden');

      // Default an even split for new rows if no existing values are present.
      var evenSplit = (100 / plan);
      var rowsHtml = '';
      for (var i = 0; i < plan; i++) {
        var label = (ordinals[i] || (i + 1) + 'th') + ' installment';
        var defaultPct = (existingPercents[i] !== undefined && existingPercents[i] !== '')
          ? existingPercents[i]
          : evenSplit.toFixed(2);

        rowsHtml += [
          '<div class="cp-installment-row" data-row>',
          '  <div class="cp-installment-label">', label, '</div>',
          '  <div class="cp-installment-pct">',
          '    <label>Percentage</label>',
          '    <input type="number" class="cp-pct-input" name="percentages[]" step="0.01" min="0" max="100" value="', defaultPct, '">',
          '    <span>%</span>',
          '  </div>',
          '  <div class="cp-installment-amount">',
          '    <span class="admin-form-help">Amount</span>',
          '    <span class="cp-amount-preview" data-amount-preview>—</span>',
          '  </div>',
          '</div>',
        ].join('');
      }
      rowsEl.innerHTML = rowsHtml;
    }

    wirePercentInputs();
    recalc();
  }

  function recalc() {
    var form = document.getElementById('cp-installments-form');
    if (!form) return;

    var totalInput = document.getElementById('cp-total');
    var remainingSpan = form.querySelector('[data-remaining]');
    var rows = form.querySelectorAll('[data-row]');

    var total = parseFloat(totalInput.value);
    if (isNaN(total)) total = 0;

    var sum = 0;
    rows.forEach(function (row) {
      var input = row.querySelector('.cp-pct-input');
      var preview = row.querySelector('[data-amount-preview]');
      var pct = input ? parseFloat(input.value) : 0;
      if (isNaN(pct)) pct = 0;
      sum += pct;

      if (preview) {
        var amount = total * (pct / 100);
        preview.textContent = formatMoney(amount);
      }
    });

    if (remainingSpan) {
      var remaining = 100 - sum;
      remainingSpan.textContent = (Math.round(remaining * 100) / 100).toString();
      var wrap = document.getElementById('cp-installments-remaining');
      if (wrap) {
        wrap.classList.toggle('is-ok', Math.abs(remaining) < 0.005);
        wrap.classList.toggle('is-error', remaining < -0.005);
      }
    }
  }

  function wirePercentInputs() {
    var form = document.getElementById('cp-installments-form');
    if (!form) return;
    form.querySelectorAll('.cp-pct-input').forEach(function (input) {
      input.addEventListener('input', recalc);
    });
    var total = document.getElementById('cp-total');
    if (total && !total._wired) {
      total.addEventListener('input', recalc);
      total._wired = true;
    }
  }

  function formatMoney(n) {
    if (!isFinite(n)) return '—';
    return 'CA$' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var planSelect = document.getElementById('cp-plan');
    if (!planSelect) return;

    planSelect.addEventListener('change', render);

    // Initial render: if the current plan doesn't match the number of rows we have,
    // rebuild rows. Otherwise just wire events and recalc.
    var form = document.getElementById('cp-installments-form');
    var currentRows = form ? form.querySelectorAll('[data-row]').length : 0;
    var currentPlan = parseInt(planSelect.value, 10) || 1;
    if (currentRows !== currentPlan) {
      render();
    } else {
      wirePercentInputs();
      recalc();
    }
  });
})();
