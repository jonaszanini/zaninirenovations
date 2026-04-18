/*
 * Admin — Client Projects dynamic installment editor + CAD money mask.
 *
 * Keeps the payment-plan form in sync:
 *   - When the user selects "2/3/4 installments", the correct number of
 *     percentage rows is rendered.
 *   - As they type percentages, the dollar-amount next to each row updates
 *     live and a "Remaining to allocate" counter shows how far from 100% they are.
 *
 * Money mask (inputs with class `.cp-money-input`):
 *   - Calculator-style entry: only digits are accepted, and they accumulate
 *     from the right so `1` -> `0.01`, `12` -> `0.12`, `123` -> `1.23`,
 *     `12345` -> `123.45`, `1234567` -> `12,345.67`.
 *   - Thousand separators (",") are inserted and refreshed on every keystroke
 *     (including backspace).
 *   - On form submit the visible value is replaced with a plain numeric string
 *     (e.g. `12345.67`) so server-side numeric validation keeps working.
 */
(function () {
  // --- CAD money mask ---------------------------------------------------

  function formatCentsAsCad(cents) {
    var whole = Math.floor(cents / 100);
    var frac = cents % 100;
    var wholeStr = whole.toLocaleString('en-US'); // 1234567 -> "1,234,567"
    var fracStr = frac < 10 ? '0' + frac : String(frac);
    return wholeStr + '.' + fracStr;
  }

  // Read a raw visible value ("1,234.56" or "1234.56" or "") and turn it
  // into integer cents. Empty / NaN -> 0.
  function parseToCents(value) {
    if (value == null) return 0;
    var digits = String(value).replace(/\D/g, '');
    if (!digits) return 0;
    // Strip leading zeros so "00000123" -> "123".
    digits = digits.replace(/^0+(?=\d)/, '');
    var n = parseInt(digits, 10);
    return isNaN(n) ? 0 : n;
  }

  // Blade renders pre-existing amounts as "1234.56" (no commas). Convert that
  // to the calculator representation: the whole string is treated as a real
  // dollar amount, so "1234.56" -> 123456 cents -> "1,234.56".
  function initExistingValue(input) {
    var raw = (input.value || '').trim();
    if (!raw) return;
    var num = parseFloat(raw.replace(/,/g, ''));
    if (!isFinite(num) || num <= 0) {
      input.value = '';
      return;
    }
    var cents = Math.round(num * 100);
    input.value = formatCentsAsCad(cents);
  }

  function onMoneyInput(e) {
    var input = e.target;
    var raw = input.value;
    // If the field was fully cleared, keep it empty — calculator style means
    // the user can get back to a blank slate by deleting everything.
    if (!raw.replace(/\D/g, '')) {
      input.value = '';
      return;
    }
    var cents = parseToCents(raw);
    if (cents === 0) {
      input.value = '';
      return;
    }
    input.value = formatCentsAsCad(cents);
    // Always keep caret at the end; it's the calculator-entry convention and
    // avoids the "cursor jumps around after reformat" problem.
    var end = input.value.length;
    try { input.setSelectionRange(end, end); } catch (err) { /* some input types disallow this */ }
  }

  // Strip thousand separators just before the form is submitted, so the
  // backend sees "1234.56" instead of "1,234.56".
  function onFormSubmitStripCommas(e) {
    var form = e.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('.cp-money-input').forEach(function (input) {
      if (input.value) input.value = input.value.replace(/,/g, '');
    });
  }

  function initMoneyMask() {
    var inputs = document.querySelectorAll('.cp-money-input');
    if (!inputs.length) return;
    var forms = new Set();
    inputs.forEach(function (input) {
      initExistingValue(input);
      input.addEventListener('input', onMoneyInput);
      // Block weird key combos that would insert letters (e.g. "e" in some
      // locales) — the input filter in onMoneyInput handles it too, but this
      // keeps the field from flickering.
      input.addEventListener('keydown', function (ev) {
        if (ev.key && ev.key.length === 1 && !/[\d]/.test(ev.key) && !ev.ctrlKey && !ev.metaKey) {
          ev.preventDefault();
        }
      });
      if (input.form) forms.add(input.form);
    });
    forms.forEach(function (form) {
      form.addEventListener('submit', onFormSubmitStripCommas);
    });
  }

  // --- Time-entry form: start/end → hours -----------------------------

  // Parses "HH:MM" (24h) into integer minutes-since-midnight.
  function parseHhMm(value) {
    if (!value || typeof value !== 'string') return null;
    var m = value.match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return null;
    var h = parseInt(m[1], 10);
    var mm = parseInt(m[2], 10);
    if (isNaN(h) || isNaN(mm)) return null;
    return h * 60 + mm;
  }

  // Wire one time-entry form (used by both the "Add time entry" form at the
  // top of the panel AND each inline "edit existing entry" form in the table).
  // Behaviour:
  //   - Start + End both picked  → Hours auto-computed and field locked.
  //   - Start only (no End)      → Hours stays empty, field unlocked.
  //                                Submitting saves an "in progress" entry.
  //   - End only / Hours only    → Field unlocked, user can type freely.
  function initOneTimeForm(form) {
    if (!form || form._cpTimeWired) return;
    form._cpTimeWired = true;

    var startSel = form.querySelector('[data-cp-time-start]');
    var endSel = form.querySelector('[data-cp-time-end]');
    var hoursInput = form.querySelector('[data-cp-time-hours]');
    if (!startSel || !endSel || !hoursInput) return;

    // --- 1) Only digits and (at most) one decimal separator in Hours -----
    hoursInput.addEventListener('keydown', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (ev.key && ev.key.length > 1) return; // arrows, backspace, tab...
      if (ev.key === '.' || ev.key === ',') {
        if (hoursInput.value.indexOf('.') !== -1) ev.preventDefault();
        return;
      }
      if (!/^\d$/.test(ev.key)) ev.preventDefault();
    });
    hoursInput.addEventListener('input', function () {
      var v = hoursInput.value.replace(/,/g, '.').replace(/[^\d.]/g, '');
      var firstDot = v.indexOf('.');
      if (firstDot !== -1) {
        v = v.slice(0, firstDot + 1) + v.slice(firstDot + 1).replace(/\./g, '');
      }
      if (v !== hoursInput.value) hoursInput.value = v;
    });

    function syncFromTimes() {
      var startMin = parseHhMm(startSel.value);
      var endMin = parseHhMm(endSel.value);

      if (startMin !== null && endMin !== null) {
        if (endMin <= startMin) {
          hoursInput.readOnly = false;
          hoursInput.classList.remove('is-locked');
          hoursInput.value = '';
          hoursInput.setCustomValidity('End time must be after start time.');
          hoursInput.title = 'End time must be after start time.';
          return;
        }
        var hours = (endMin - startMin) / 60;
        hoursInput.value = (Math.round(hours * 100) / 100).toString();
        hoursInput.readOnly = true;
        hoursInput.classList.add('is-locked');
        hoursInput.setCustomValidity('');
        hoursInput.title = 'Calculated from start and end times. Clear End to edit manually or to save as in-progress.';
      } else if (startMin !== null && endMin === null) {
        // "Start only" mode — server will save this as an in-progress entry,
        // so leave hours empty + unlock for optional manual override.
        hoursInput.readOnly = false;
        hoursInput.classList.remove('is-locked');
        hoursInput.setCustomValidity('');
        hoursInput.title = 'No End time — this will be saved as an in-progress entry.';
      } else {
        hoursInput.readOnly = false;
        hoursInput.classList.remove('is-locked');
        hoursInput.setCustomValidity('');
        hoursInput.title = '';
      }
    }
    startSel.addEventListener('change', syncFromTimes);
    endSel.addEventListener('change', syncFromTimes);
    syncFromTimes();
  }

  function initTimeForms() {
    document.querySelectorAll('[data-cp-time-form]').forEach(initOneTimeForm);
  }

  // --- Inline edit toggles for existing time entries --------------------
  function initTimeEditToggles() {
    document.querySelectorAll('[data-cp-edit-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-cp-edit-toggle');
        var row = document.querySelector('[data-cp-edit-row="' + id + '"]');
        if (!row) return;
        var isHidden = row.hasAttribute('hidden');
        if (isHidden) {
          row.removeAttribute('hidden');
          // Wire the form on first open (it might not have been wired yet
          // because it was hidden during initial pass — though we already
          // call initTimeForms once on load, this is a safety net).
          var form = row.querySelector('[data-cp-time-form]');
          if (form) initOneTimeForm(form);
        } else {
          row.setAttribute('hidden', '');
        }
      });
    });
    document.querySelectorAll('[data-cp-edit-cancel]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-cp-edit-cancel');
        var row = document.querySelector('[data-cp-edit-row="' + id + '"]');
        if (row) row.setAttribute('hidden', '');
      });
    });
  }

  // --- Billing-type radios (create/edit project): toggle hourly_rate field ---
  function initBillingTypeToggle() {
    var radios = document.querySelectorAll('[data-cp-billing]');
    if (!radios.length) return;
    var rateGroup = document.querySelector('[data-cp-hourly-rate]');
    if (!rateGroup) return;

    function sync() {
      var checked = document.querySelector('[data-cp-billing]:checked');
      var isHourly = checked && checked.value === 'hourly';
      rateGroup.style.display = isHourly ? '' : 'none';
      var input = rateGroup.querySelector('input[name="hourly_rate"]');
      if (input) {
        if (isHourly) {
          input.required = true;
        } else {
          input.required = false;
          input.value = '';
        }
      }
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }

  // --- Dynamic installment editor --------------------------------------

  // Pre-populated percentages whenever a plan is (re)selected.
  //   1 → 100%
  //   2 → 50 / 50
  //   3 → 30 / 30 / 40
  //   4 → 20 / 20 / 30 / 30
  var PLAN_DEFAULTS = {
    1: [100],
    2: [50, 50],
    3: [30, 30, 40],
    4: [20, 20, 30, 30],
  };

  function render() {
    var form = document.getElementById('cp-installments-form');
    if (!form) return;

    var planSelect = document.getElementById('cp-plan');
    var totalInput = document.getElementById('cp-total');
    var rowsEl = document.getElementById('cp-installments-rows');
    var remainingWrap = document.getElementById('cp-installments-remaining');
    if (!planSelect || !totalInput || !rowsEl || !remainingWrap) return;

    // If any installment is already paid, the payment plan is locked. Never
    // rebuild rows in that case — we'd throw away the paid/percentage record.
    var paidCount = parseInt(form.getAttribute('data-paid-count'), 10) || 0;
    if (paidCount > 0) {
      wirePercentInputs();
      recalc();
      return;
    }

    var plan = parseInt(planSelect.value, 10) || 1;
    var defaults = PLAN_DEFAULTS[plan] || PLAN_DEFAULTS[1];
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

      var rowsHtml = '';
      for (var i = 0; i < plan; i++) {
        var label = (ordinals[i] || (i + 1) + 'th') + ' installment';
        var defaultPct = (defaults[i] !== undefined ? defaults[i] : (100 / plan)).toFixed(2);

        rowsHtml += [
          '<div class="cp-installment-row" data-row data-paid="0">',
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

    // totalInput uses the CAD mask: "1,234.56". Strip commas before parsing.
    var total = parseFloat(String(totalInput.value || '').replace(/,/g, ''));
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
    initMoneyMask();
    initTimeForms();
    initTimeEditToggles();
    initBillingTypeToggle();

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
