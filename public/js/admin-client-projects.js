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
 *   - Optional `data-cp-money-places="4"` sets fractional digits (default 2).
 *     Standard CAD amounts use 2; paving tier $/sq ft uses 4.
 *   - Calculator-style entry: only digits are accepted, and they accumulate
 *     from the right so (2 places) `1` -> `0.01`, `12` -> `0.12`, `123` -> `1.23`,
 *     `12345` -> `123.45`, `1234567` -> `12,345.67`.
 *   - Thousand separators (",") are inserted and refreshed on every keystroke
 *     (including backspace).
 *   - On form submit the visible value is replaced with a plain numeric string
 *     (e.g. `12345.67`) so server-side numeric validation keeps working.
 *   - Calling `cpMoneyMask.wire(input)` again on the same element re-formats
 *     the current value (e.g. mark-paid modal preset).
 */
(function () {
  // --- CAD money mask ---------------------------------------------------

  function moneyPlaces(input) {
    var p = parseInt(input && input.getAttribute('data-cp-money-places'), 10);
    if (!isFinite(p) || p < 2 || p > 8) {
      p = 2;
    }
    return p;
  }

  /** minorUnits = amount × 10^places (integer). */
  function formatMinorUnits(minorUnits, places) {
    var divisor = Math.pow(10, places);
    var whole = Math.floor(minorUnits / divisor);
    var frac = minorUnits % divisor;
    var wholeStr = whole.toLocaleString('en-US');
    if (places < 1) {
      return wholeStr;
    }
    var fracStr = String(frac).padStart(places, '0');
    return wholeStr + '.' + fracStr;
  }

  // Raw visible value -> integer minor units (digits only, right-aligned).
  function parseToMinorUnits(value) {
    if (value == null) return 0;
    var digits = String(value).replace(/\D/g, '');
    if (!digits) return 0;
    digits = digits.replace(/^0+(?=\d)/, '');
    var n = parseInt(digits, 10);
    return isNaN(n) ? 0 : n;
  }

  // Blade / presets often use plain "1234.56". Reformat to masked calculator display.
  function initExistingValue(input, places) {
    var raw = (input.value || '').trim();
    if (!raw) return;
    var num = parseFloat(raw.replace(/,/g, ''));
    if (!isFinite(num) || num < 0) {
      input.value = '';
      return;
    }
    var minor = Math.round(num * Math.pow(10, places));
    input.value = formatMinorUnits(minor, places);
  }

  function onMoneyInput(e, places) {
    var input = e.target;
    var raw = input.value;
    if (!raw.replace(/\D/g, '')) {
      input.value = '';
      return;
    }
    var minor = parseToMinorUnits(raw);
    if (minor === 0) {
      input.value = '';
      return;
    }
    input.value = formatMinorUnits(minor, places);
    var end = input.value.length;
    try { input.setSelectionRange(end, end); } catch (err) { /* ignore */ }
  }

  function onFormSubmitStripCommas(e) {
    var form = e.target;
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('.cp-money-input').forEach(function (input) {
      if (input.value) input.value = input.value.replace(/,/g, '');
    });
  }

  function wireMoneyInput(input) {
    if (!input) return;
    var places = moneyPlaces(input);
    if (input._cpMoneyWired) {
      initExistingValue(input, places);
      return;
    }
    input._cpMoneyWired = true;
    initExistingValue(input, places);
    input.addEventListener('input', function (e) {
      onMoneyInput(e, moneyPlaces(input));
    });
    input.addEventListener('keydown', function (ev) {
      if (ev.key && ev.key.length === 1 && !/[\d]/.test(ev.key) && !ev.ctrlKey && !ev.metaKey) {
        ev.preventDefault();
      }
    });
    if (input.form && !input.form._cpMoneyFormWired) {
      input.form._cpMoneyFormWired = true;
      input.form.addEventListener('submit', onFormSubmitStripCommas);
    }
  }

  function initMoneyMask() {
    document.querySelectorAll('.cp-money-input').forEach(wireMoneyInput);
  }

  window.cpMoneyMask = {
    wire: function (target) {
      if (!target) return;
      if (target.nodeType === 1) {
        wireMoneyInput(target);
      } else if (typeof target.forEach === 'function') {
        target.forEach(wireMoneyInput);
      }
    },
    /** Parse a masked value ("1,234.56") into a Number. NaN/empty → 0. */
    parse: function (value) {
      if (value == null || value === '') return 0;
      var n = parseFloat(String(value).replace(/,/g, ''));
      return isFinite(n) ? n : 0;
    }
  };

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
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-cp-edit-toggle');
        var row = document.querySelector('[data-cp-edit-row="' + id + '"]');
        if (!row) return;
        var details = row.closest('details');
        if (details) details.open = true;
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

    var fixedOnly = document.querySelectorAll('[data-cp-fixed-only]');
    var hourlyOnly = document.querySelectorAll('[data-cp-hourly-only]');

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
      fixedOnly.forEach(function (el) { el.style.display = isHourly ? 'none' : ''; });
      hourlyOnly.forEach(function (el) { el.style.display = isHourly ? '' : 'none'; });
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

  // Must stay in sync with ClientProjectFinanceController::installmentLabel().
  var PLAN_LABELS = {
    1: ['Full payment on completion'],
    2: [
      'On signing (holds the schedule)',
      'On completion',
    ],
    3: [
      'On signing (holds the schedule)',
      'Day before work begins',
      'On completion',
    ],
    4: [
      'On signing (holds the schedule)',
      'Day before work begins',
      'Mid-project',
      'On completion',
    ],
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
    var labels = PLAN_LABELS[plan] || PLAN_LABELS[1];
    var ordinals = ['1st', '2nd', '3rd', '4th'];

    if (plan === 1) {
      rowsEl.innerHTML = [
        '<div class="cp-installment-row" data-row>',
        '  <div class="cp-installment-label">' + labels[0] + '</div>',
        '  <div class="cp-installment-pct">',
        '    <label>Percentage</label>',
        '    <input type="hidden" name="percentages[]" value="100">',
        '    <input type="number" class="cp-pct-input" value="100" disabled>',
        '    <span>%</span>',
        '  </div>',
        '  <div class="cp-installment-amount">',
        '    <span class="admin-form-help">Amount (incl. 13% HST)</span>',
        '    <span class="cp-amount-preview" data-amount-preview>—</span>',
        '  </div>',
        '</div>',
      ].join('');
      remainingWrap.classList.add('is-hidden');
    } else {
      remainingWrap.classList.remove('is-hidden');

      var rowsHtml = '';
      for (var i = 0; i < plan; i++) {
        var label = labels[i] || ((ordinals[i] || (i + 1) + 'th') + ' installment');
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
          '    <span class="admin-form-help">Amount (incl. 13% HST)</span>',
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

    // The preview is client-facing: we display the amount the client
    // actually pays, which is the pre-tax pct% of total × (1 + HST).
    // The form's data-hst-rate is emitted from the Blade template so
    // the rate stays in sync with PHP's ClientProject::HST_RATE.
    var hstRate = parseFloat(form.getAttribute('data-hst-rate'));
    if (!isFinite(hstRate)) hstRate = 0.13;
    var withTax = 1 + hstRate;

    var sum = 0;
    rows.forEach(function (row) {
      var input = row.querySelector('.cp-pct-input');
      var preview = row.querySelector('[data-amount-preview]');
      var pct = input ? parseFloat(input.value) : 0;
      if (isNaN(pct)) pct = 0;
      sum += pct;

      if (preview) {
        var amount = total * (pct / 100) * withTax;
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

  // --- NANP phone mask (inputs with class `.cp-phone-input`) ---------------
  //
  // Behaviour:
  //   - Accepts only digits. Anything else (letters, punctuation) is
  //     silently discarded on every keystroke and on paste.
  //   - Caps at exactly 10 digits. The 11th keystroke is ignored so the
  //     field cannot exceed "(xxx) xxx-xxxx".
  //   - Formats progressively as the user types:
  //       1-3 digits    -> "123"
  //       4-6 digits    -> "(123) 456"
  //       7+ digits     -> "(123) 456-7890"
  //   - Legacy stored values (e.g. "6478347570" with no formatting, or
  //     "(647) 834-7570" already formatted) are reformatted on init.
  //   - On submit we don't mutate the field — the backend strips
  //     non-digits before validation, so the masked value is accepted
  //     as-is either way.

  function phoneDigitsFrom(value) {
    return (value == null ? '' : String(value)).replace(/\D/g, '').slice(0, 10);
  }

  function formatPhoneDigits(digits) {
    if (!digits) return '';
    if (digits.length <= 3) return digits;
    if (digits.length <= 6) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
    return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6, 10);
  }

  function onPhoneInput(e) {
    var input = e.target;
    var digits = phoneDigitsFrom(input.value);
    input.value = formatPhoneDigits(digits);
    // Calculator-style: keep the caret at the end so the natural "I'm
    // typing forwards" flow is preserved, even after auto-inserted
    // parens/space/dash.
    var end = input.value.length;
    try { input.setSelectionRange(end, end); } catch (err) { /* some input types disallow this */ }
  }

  function wirePhoneInput(input) {
    if (!input || input._cpPhoneWired) return;
    input._cpPhoneWired = true;
    input.setAttribute('inputmode', input.getAttribute('inputmode') || 'tel');
    input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'tel');
    input.setAttribute('maxlength', '14'); // "(123) 456-7890"
    input.value = formatPhoneDigits(phoneDigitsFrom(input.value));

    input.addEventListener('input', onPhoneInput);
    input.addEventListener('keydown', function (ev) {
      // Swallow any single-character key that isn't a digit. Control
      // keys (Backspace, Delete, Tab, arrows, etc.) have key.length > 1
      // and are left alone. Ctrl/Cmd shortcuts (copy/paste/select-all)
      // are also left alone.
      if (ev.key && ev.key.length === 1 && !/\d/.test(ev.key) && !ev.ctrlKey && !ev.metaKey) {
        ev.preventDefault();
      }
    });
    input.addEventListener('paste', function (ev) {
      var data = (ev.clipboardData || window.clipboardData).getData('text');
      if (data == null) return;
      ev.preventDefault();
      var merged = phoneDigitsFrom(input.value + data);
      input.value = formatPhoneDigits(merged);
    });
  }

  function initPhoneMask() {
    document.querySelectorAll('.cp-phone-input').forEach(wirePhoneInput);
  }

  window.cpPhoneMask = {
    wire: function (target) {
      if (!target) return;
      if (target.nodeType === 1) {
        wirePhoneInput(target);
      } else if (typeof target.forEach === 'function') {
        target.forEach(wirePhoneInput);
      }
    },
    /** Convert any string into the canonical "(xxx) xxx-xxxx" form. */
    format: function (value) {
      return formatPhoneDigits(phoneDigitsFrom(value));
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    initMoneyMask();
    initPhoneMask();
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
