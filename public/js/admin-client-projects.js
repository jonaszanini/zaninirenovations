/*
 * Admin — Client Projects dynamic installment editor + CAD money mask.
 *
 * Keeps the payment-plan form in sync:
 *   - When the user selects "2/3/4 installments", the correct number of
 *     percentage rows is rendered.
 *   - As they type percentages, later installments fill with the leftover so
 *     the plan always totals 100%. Dollar amounts update live.
 *
 * Money mask (inputs with class `.cp-money-input`):
 *   - Optional `data-cp-money-places="4"` sets fractional digits (default 2).
 *     Standard CAD amounts use 2; paving tier $/sq ft uses 4.
 *   - Entry is always direct decimals: type 4.75 and the field stays 4.75.
 *   - A lone comma followed by 1–2 digits is the decimal point (4,75 → 4.75).
 *     US thousands (1,234.56) stay valid. Mixed/ambiguous forms (1.234,56)
 *     show an inline error instead of guessing. On 3+ decimal fields,
 *     `1,459` is flagged (not read as 1459).
 *   - Extra fractional digits use decimal-safe rounding on blur (1.005 → 1.01).
 *     Typed/pasted negatives show an inline error unless the field has
 *     `data-cp-money-allow-negative` (invoice line amounts). A stored
 *     negative is never blanked.
 *   - A lone "." is rejected and blocks submit.
 *   - Thousand separators are applied on blur and stripped on submit so
 *     server-side numeric validation works. This also runs for native
 *     `HTMLFormElement.submit()` (used by basement / millwork wizards).
 *   - Calling `cpMoneyMask.wire(input)` again on the same element re-formats
 *     the current value (e.g. mark-paid modal preset).
 *   - Server-side also accepts grouped amounts via `App\Support\CadMoney`
 *     and must not divide posted dollars by 100.
 */
(function () {
  document.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || t.type !== 'checkbox') return;
    var id = t.getAttribute('data-reveal');
    if (!id) return;
    var el = document.getElementById(id);
    if (el) el.hidden = !t.checked;
  });

  // --- CAD money mask ---------------------------------------------------

  function moneyPlaces(input) {
    var p = parseInt(input && input.getAttribute('data-cp-money-places'), 10);
    if (!isFinite(p) || p < 2 || p > 8) {
      p = 2;
    }
    return p;
  }

  function moneyAllowsNegative(input) {
    return !!(input && input.hasAttribute && input.hasAttribute('data-cp-money-allow-negative'));
  }

  function moneyParseOpts(input) {
    return { allowNegative: moneyAllowsNegative(input) };
  }

  /** minorUnits = amount × 10^places (integer). */
  function formatMinorUnits(minorUnits, places) {
    var divisor = Math.pow(10, places);
    var sign = minorUnits < 0 ? '-' : '';
    var abs = Math.abs(minorUnits);
    var whole = Math.floor(abs / divisor);
    var frac = abs % divisor;
    var wholeStr = whole.toLocaleString('en-US');
    if (places < 1) {
      return sign + wholeStr;
    }
    var fracStr = String(frac).padStart(places, '0');
    return sign + wholeStr + '.' + fracStr;
  }

  /**
   * Parse a typed CAD string.
   * Lone comma + 1–2 digits → decimal (4,75 → 4.75).
   * US thousands (1,234 / 1,234.56) → strip grouping on 2-place fields.
   * On 3+ place fields, a grouping comma without a decimal (1,459) is flagged.
   * Mixed EU (1.234,56) and other ambiguous forms → error.
   */
  function parseDirectCadMoney(value, places, opts) {
    places = places || 2;
    opts = opts || {};
    var allowNegative = !!opts.allowNegative;
    if (value == null) {
      return { ok: true, num: 0, normalized: '', empty: true };
    }
    var s = String(value).replace(/\u00a0/g, '').replace(/\$/g, '').replace(/ /g, '').trim();
    if (s === '') {
      return { ok: true, num: 0, normalized: '', empty: true };
    }
    var negative = false;
    if (s.charAt(0) === '-') {
      negative = true;
      s = s.slice(1);
    } else if (s.indexOf('-') !== -1) {
      return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount.' };
    }
    if (negative && !allowNegative) {
      return { ok: false, num: NaN, normalized: '-' + s, negative: true, error: 'Amount cannot be negative.' };
    }
    if (s === '' || s === '.' || s === ',') {
      return { ok: false, num: NaN, normalized: (negative ? '-' : '') + s, error: 'Enter a valid amount.' };
    }

    var hasComma = s.indexOf(',') !== -1;
    var hasDot = s.indexOf('.') !== -1;
    var normalized = s;

    if (hasComma && !hasDot) {
      if (/^\d+,\d{1,2}$/.test(s)) {
        normalized = s.replace(',', '.');
      } else if (/^\d{1,3}(,\d{3})+$/.test(s)) {
        if (places > 2) {
          return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount (e.g. 1.459 or 1,459.000).' };
        }
        normalized = s.replace(/,/g, '');
      } else if (/^\d+,$/.test(s)) {
        normalized = s.slice(0, -1);
      } else {
        return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount (e.g. 4.75 or 1,234.56).' };
      }
    } else if (hasDot && hasComma) {
      if (/^\d{1,3}(,\d{3})+\.\d*$/.test(s)) {
        normalized = s.replace(/,/g, '');
      } else {
        return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount (e.g. 4.75 or 1,234.56).' };
      }
    } else if (!hasComma && !hasDot) {
      if (!/^\d+$/.test(s)) {
        return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount.' };
      }
    } else if (!/^\d*\.\d*$/.test(s)) {
      return { ok: false, num: NaN, normalized: s, error: 'Enter a valid amount.' };
    }

    var num = parseFloat(normalized);
    if (!isFinite(num)) {
      return { ok: false, num: NaN, normalized: normalized, error: 'Enter a valid amount.' };
    }
    if (negative) {
      num = -num;
      normalized = '-' + normalized;
    }
    return { ok: true, num: num, normalized: normalized };
  }

  /** Half-up rounding that survives binary float (1.005 → 1.01). */
  function roundToPlaces(num, places) {
    places = places || 2;
    var n = Number(num);
    if (!isFinite(n)) return NaN;
    var sign = n < 0 ? -1 : 1;
    var shifted = Number(Math.abs(n) + 'e' + places);
    if (!isFinite(shifted)) {
      shifted = Math.abs(n) * Math.pow(10, places);
    }
    return sign * Number(Math.round(shifted) + 'e-' + places);
  }

  function toMinorUnits(num, places) {
    var rounded = roundToPlaces(num, places);
    return Math.round(Number(Math.abs(rounded) + 'e' + places)) * (rounded < 0 ? -1 : 1);
  }

  function moneyErrorHost(input) {
    return input.closest('.cp-discount-field-value')
      || input.closest('.admin-form-group')
      || input.parentElement;
  }

  function clearMoneyFieldError(input) {
    if (!input) return;
    input.classList.remove('is-invalid');
    input.setCustomValidity('');
    var host = moneyErrorHost(input);
    if (!host) return;
    var err = host.querySelector('.cp-money-error');
    if (err) err.remove();
  }

  function setMoneyFieldError(input, message) {
    if (!input) return;
    if (!message) {
      clearMoneyFieldError(input);
      return;
    }
    input.classList.add('is-invalid');
    input.setCustomValidity(message);
    var host = moneyErrorHost(input);
    if (!host) return;
    var err = host.querySelector('.cp-money-error');
    if (!err) {
      err = document.createElement('p');
      err.className = 'admin-form-error cp-money-error';
      host.appendChild(err);
    }
    err.textContent = message;
  }

  function roundedNormalized(num, places) {
    places = places || 2;
    return roundToPlaces(num, places).toFixed(places);
  }

  function formHasInvalidMoney(form) {
    var invalid = false;
    if (!form || !form.querySelectorAll) return false;
    form.querySelectorAll('.cp-money-input').forEach(function (input) {
      var raw = (input.value || '').trim();
      if (!raw) {
        clearMoneyFieldError(input);
        return;
      }
      var parsed = parseDirectCadMoney(raw, moneyPlaces(input), moneyParseOpts(input));
      if (!parsed.ok) {
        setMoneyFieldError(input, parsed.error);
        invalid = true;
      }
    });
    return invalid;
  }

  function stripMoneyCommasOnForm(form) {
    if (!form || !form.querySelectorAll) return;
    form.querySelectorAll('.cp-money-input').forEach(function (input) {
      var parsed = parseDirectCadMoney(input.value, moneyPlaces(input), moneyParseOpts(input));
      if (!parsed.ok || parsed.empty) {
        if (parsed && parsed.empty) input.value = '';
        return;
      }
      input.value = roundedNormalized(parsed.num, moneyPlaces(input));
    });
  }

  function onFormSubmitStripCommas(e) {
    var form = e && e.target ? e.target : e;
    if (formHasInvalidMoney(form)) {
      if (e && typeof e.preventDefault === 'function') {
        e.preventDefault();
      }
      return;
    }
    stripMoneyCommasOnForm(form);
  }

  // Native HTMLFormElement.submit() skips 'submit' listeners (basement / millwork
  // wizards use it after checkValidity). Still strip commas so Laravel numeric rules pass.
  if (!HTMLFormElement.prototype._cpMoneySubmitPatched) {
    HTMLFormElement.prototype._cpMoneySubmitPatched = true;
    var nativeFormSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
      if (formHasInvalidMoney(this)) {
        return;
      }
      stripMoneyCommasOnForm(this);
      return nativeFormSubmit.call(this);
    };
  }

  function normalizeDirectMoneyOnBlur(input, places) {
    places = places || 2;
    var raw = (input.value || '').trim();
    if (!raw) {
      clearMoneyFieldError(input);
      return;
    }
    var parsed = parseDirectCadMoney(raw, places, moneyParseOpts(input));
    if (!parsed.ok) {
      setMoneyFieldError(input, parsed.error);
      return;
    }
    clearMoneyFieldError(input);
    if (parsed.empty) {
      return;
    }
    input.value = formatMinorUnits(toMinorUnits(parsed.num, places), places);
  }

  /**
   * Money field: type dollars/decimals normally.
   * Live value stays parseable by cpMoneyMask.parse.
   */
  function wireDirectMoneyEntry(input) {
    if (!input) return;
    var places = moneyPlaces(input);
    if (input._cpMoneyWired) {
      if (typeof document === 'undefined' || document.activeElement !== input) {
        normalizeDirectMoneyOnBlur(input, places);
      }
      return;
    }
    input._cpMoneyWired = true;
    normalizeDirectMoneyOnBlur(input, places);

    input.addEventListener('keydown', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (
        ev.key === 'Backspace' ||
        ev.key === 'Delete' ||
        ev.key === 'Tab' ||
        ev.key === 'Enter' ||
        ev.key === 'ArrowLeft' ||
        ev.key === 'ArrowRight' ||
        ev.key === 'ArrowUp' ||
        ev.key === 'ArrowDown' ||
        ev.key === 'Home' ||
        ev.key === 'End'
      ) {
        return;
      }
      if (ev.key.length === 1) {
        if (/\d/.test(ev.key)) return;
        if (ev.key === '-') {
          return;
        }
        if (places > 0 && ev.key === '.') {
          var start = input.selectionStart == null ? input.value.length : input.selectionStart;
          var end = input.selectionEnd == null ? start : input.selectionEnd;
          var remaining = input.value.slice(0, start) + input.value.slice(end);
          if (remaining.indexOf('.') !== -1) {
            ev.preventDefault();
          }
          return;
        }
        if (places > 0 && ev.key === ',') {
          return;
        }
        ev.preventDefault();
      }
    });

    input.addEventListener('input', function () {
      var raw = input.value;
      var parsed = parseDirectCadMoney(raw, places, moneyParseOpts(input));
      if (!parsed.ok && raw.trim() !== '' && raw.trim() !== '.' && raw.trim() !== ',' && raw.trim() !== '-') {
        setMoneyFieldError(input, parsed.error);
        return;
      }
      clearMoneyFieldError(input);
    });

    input.addEventListener('blur', function () {
      normalizeDirectMoneyOnBlur(input, places);
    });

    if (input.form && !input.form._cpMoneyFormWired) {
      input.form._cpMoneyFormWired = true;
      input.form.addEventListener('submit', onFormSubmitStripCommas);
    }
  }

  function wireMoneyInput(input) {
    if (!input) return;
    wireDirectMoneyEntry(input);
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
    /** Parse a masked value ("1,234.56" or "4,75") into a Number. NaN/empty/invalid → 0. */
    parse: function (value, places, opts) {
      var parsed = parseDirectCadMoney(value, places, opts);
      return parsed.ok ? parsed.num : 0;
    },
    /** Half away from zero, matching PHP round() (1.005 → 1.01, −30.485 → −30.49). */
    round: function (num, places) {
      return roundToPlaces(num, places);
    },
    /** Strip thousands separators on `.cp-money-input` before a programmatic submit. */
    stripFormCommas: stripMoneyCommasOnForm,
    /** Re-read allow-negative / places and clear or reset the visible error. */
    refresh: function (input) {
      if (!input) return;
      clearMoneyFieldError(input);
      wireMoneyInput(input);
    }
  };

  // --- Time-entry form: start/end → hours -----------------------------

  function stripTimeDigits(value) {
    return (value || '').replace(/\D/g, '').slice(0, 4);
  }

  function formatTimeMaskFromDigits(digits) {
    var len = digits.length;
    if (len === 0) return '';
    if (len <= 2) return digits;
    if (len === 3) return digits.charAt(0) + ':' + digits.slice(1);
    return digits.slice(0, 2) + ':' + digits.slice(2);
  }

  function normalizeTimeInputValue(value) {
    var digits = stripTimeDigits(value);
    if (digits.length === 0) return '';
    var h;
    var m;
    if (digits.length <= 2) {
      h = parseInt(digits, 10);
      m = 0;
    } else if (digits.length === 3) {
      h = parseInt(digits.charAt(0), 10);
      m = parseInt(digits.slice(1), 10);
    } else {
      h = parseInt(digits.slice(0, 2), 10);
      m = parseInt(digits.slice(2), 10);
    }
    if (isNaN(h) || isNaN(m) || h > 23 || m > 59) return null;
    return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
  }

  function wireTimeInputMask(input, onChange) {
    if (!input || input._cpTimeMaskWired) return;
    input._cpTimeMaskWired = true;

    input.addEventListener('keydown', function (ev) {
      if (ev.ctrlKey || ev.metaKey || ev.altKey) return;
      if (ev.key && ev.key.length > 1) return;
      if (!/^\d$/.test(ev.key)) ev.preventDefault();
    });

    input.addEventListener('input', function () {
      var digits = stripTimeDigits(input.value);
      var formatted = formatTimeMaskFromDigits(digits);
      if (formatted !== input.value) input.value = formatted;
      input.setCustomValidity('');
      if (typeof onChange === 'function') onChange();
    });

    input.addEventListener('blur', function () {
      if (!stripTimeDigits(input.value).length) {
        input.value = '';
        input.setCustomValidity('');
        if (typeof onChange === 'function') onChange();
        return;
      }
      var normalized = normalizeTimeInputValue(input.value);
      if (normalized === null) {
        input.setCustomValidity('Enter a valid time like 09:30 (hours 0–23, minutes 0–59).');
      } else {
        input.setCustomValidity('');
        input.value = normalized;
      }
      if (typeof onChange === 'function') onChange();
    });
  }

  // Parses "HH:MM" (24h) into integer minutes-since-midnight, or null if invalid.
  function parseHhMm(value) {
    if (!value || typeof value !== 'string') return null;
    var m = value.match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return null;
    var h = parseInt(m[1], 10);
    var mm = parseInt(m[2], 10);
    if (isNaN(h) || isNaN(mm) || h < 0 || h > 23 || mm < 0 || mm > 59) return null;
    return h * 60 + mm;
  }

  function validateTimeEntryInputs(startInput, endInput, hoursInput) {
    var startMin = parseHhMm(startInput.value);
    var endMin = parseHhMm(endInput.value);
    var startDigits = stripTimeDigits(startInput.value);
    var endDigits = stripTimeDigits(endInput.value);

    startInput.setCustomValidity('');
    endInput.setCustomValidity('');
    if (hoursInput) hoursInput.setCustomValidity('');

    if (startDigits.length > 0 && startMin === null) {
      startInput.setCustomValidity('Enter a valid start time (hours 0–23, minutes 0–59).');
      return false;
    }
    if (endDigits.length > 0 && endMin === null) {
      endInput.setCustomValidity('Enter a valid end time (hours 0–23, minutes 0–59).');
      return false;
    }
    if (endMin !== null && startMin === null) {
      startInput.setCustomValidity('Pick a start time before setting an end time.');
      return false;
    }
    if (startMin !== null && endMin !== null) {
      if (endMin <= startMin) {
        endInput.setCustomValidity('End time must be after start time.');
        return false;
      }
    }

    return true;
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

    var startInput = form.querySelector('[data-cp-time-start]');
    var endInput = form.querySelector('[data-cp-time-end]');
    var hoursInput = form.querySelector('[data-cp-time-hours]');
    if (!startInput || !endInput || !hoursInput) return;

    function syncFromTimes() {
      if (!validateTimeEntryInputs(startInput, endInput, hoursInput)) {
        hoursInput.readOnly = false;
        hoursInput.classList.remove('is-locked');
        return;
      }

      var startMin = parseHhMm(startInput.value);
      var endMin = parseHhMm(endInput.value);

      if (startMin !== null && endMin !== null) {
        var hours = (endMin - startMin) / 60;
        hoursInput.value = (Math.round(hours * 100) / 100).toString();
        hoursInput.readOnly = true;
        hoursInput.classList.add('is-locked');
        hoursInput.setCustomValidity('');
        hoursInput.title = 'Calculated from start and end times. Clear End to edit manually or to save as in-progress.';
      } else if (startMin !== null && endMin === null) {
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

    wireTimeInputMask(startInput, syncFromTimes);
    wireTimeInputMask(endInput, syncFromTimes);

    form.addEventListener('submit', function (ev) {
      [startInput, endInput].forEach(function (input) {
        if (!stripTimeDigits(input.value).length) {
          input.value = '';
          input.setCustomValidity('');
          return;
        }
        var normalized = normalizeTimeInputValue(input.value);
        if (normalized !== null) {
          input.value = normalized;
        }
      });
      if (!validateTimeEntryInputs(startInput, endInput, hoursInput)) {
        ev.preventDefault();
        var invalid = !startInput.checkValidity() ? startInput : endInput;
        invalid.reportValidity();
        invalid.focus();
      }
    });
    // --- Hours field: digits and (at most) one decimal separator ---------
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

  function syncCreateVisitFooters(isHourly) {
    var visitCb = document.getElementById('cp-create-visit-required');
    var visitDate = document.getElementById('cp-create-visit-date');
    var visitRequired = visitCb && visitCb.checked;
    var hasDate = visitDate && visitDate.value && String(visitDate.value).trim() !== '';
    var quoteEl = document.querySelector('[data-cp-fixed-quote-pending]');
    var visitRequiredEl = document.querySelector('[data-cp-fixed-visit-required]');
    var visitScheduledEl = document.querySelector('[data-cp-fixed-visit-scheduled]');
    if (quoteEl) quoteEl.style.display = (isHourly || visitRequired) ? 'none' : '';
    if (visitRequiredEl) visitRequiredEl.style.display = (isHourly || !visitRequired || hasDate) ? 'none' : '';
    if (visitScheduledEl) visitScheduledEl.style.display = (isHourly || !visitRequired || !hasDate) ? 'none' : '';
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
      syncCreateVisitFooters(isHourly);
    }
    radios.forEach(function (r) { r.addEventListener('change', sync); });
    sync();
  }

  function initCreateDuplicateCheck() {
    var form = document.getElementById('cp-create-project-form');
    var box = document.getElementById('cp-duplicate-warning');
    if (!form || !box) return;
    var url = form.getAttribute('data-cp-duplicate-check');
    if (!url) return;

    var timer = null;
    var lastKey = '';
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function fieldValue(id) {
      var el = document.getElementById(id);
      return el ? String(el.value || '').trim() : '';
    }

    function payload() {
      return {
        name: fieldValue('cp-name'),
        client_id: fieldValue('cp-client-combobox-value'),
        client_name: fieldValue('cp-client-name'),
        client_email: fieldValue('cp-client-email'),
        client_phone: fieldValue('cp-client-phone'),
        client_address: fieldValue('cp-client-address'),
        client_address_place_id: fieldValue('cp-client-address-place-id')
      };
    }

    function hasClientSignal(data) {
      if (data.client_id) return true;
      if (data.client_name) return true;
      if (data.client_email) return true;
      if (data.client_phone.replace(/\D/g, '').length >= 10) return true;
      if (data.client_address.length >= 8) return true;
      return false;
    }

    function clearConfirmFlag() {
      var hidden = form.querySelector('input[name="confirm_duplicate_project"]');
      if (hidden) hidden.remove();
      var btn = document.querySelector('[data-cp-create-submit]');
      if (btn) {
        btn.innerHTML = '<i class="fa fa-check"></i> Create project';
      }
    }

    function render(candidates) {
      if (!candidates || !candidates.length) {
        box.hidden = true;
        box.innerHTML = '';
        return;
      }
      var title = candidates.length === 1 ? 'project' : 'projects';
      var items = candidates.map(function (row) {
        var meta = [row.client_name, row.status, row.client_address].filter(Boolean).join(' · ') || '—';
        return (
          '<li>' +
            '<a href="' + escapeHtml(row.url) + '" target="_blank" rel="noopener">' +
              escapeHtml(row.reference) + ' — ' + escapeHtml(row.name) +
            '</a>' +
            '<span class="cl-duplicate-warning__meta">' + escapeHtml(meta) + '</span>' +
          '</li>'
        );
      }).join('');
      box.innerHTML =
        '<p class="cl-duplicate-warning__title">' +
          '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' +
          'Possible duplicate ' + title +
        '</p>' +
        '<p class="admin-form-help">These existing projects look like the same job (same client or address, and a similar name). Open one below, or continue if this is a separate job.</p>' +
        '<ul class="cl-duplicate-warning__list">' + items + '</ul>';
      box.hidden = false;
    }

    function run() {
      var data = payload();
      var key = JSON.stringify(data);
      if (key === lastKey) return;
      lastKey = key;
      if (data.name.length < 2 || !hasClientSignal(data)) {
        render([]);
        return;
      }
      var params = new URLSearchParams();
      Object.keys(data).forEach(function (k) {
        if (data[k]) params.set(k, data[k]);
      });
      fetch(url + '?' + params.toString(), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf
        }
      }).then(function (res) {
        if (!res.ok) return { candidates: [] };
        return res.json();
      }).then(function (json) {
        render(json && json.candidates ? json.candidates : []);
      }).catch(function () {
        render([]);
      });
    }

    function schedule() {
      clearConfirmFlag();
      if (timer) clearTimeout(timer);
      timer = setTimeout(run, 400);
    }

    ['cp-name', 'cp-client-name', 'cp-client-email', 'cp-client-phone', 'cp-client-address', 'cp-client-address-place-id'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', schedule);
      el.addEventListener('change', schedule);
    });

    var picker = document.getElementById('cp-client-combobox');
    if (picker) {
      picker.addEventListener('admin-search-select:change', schedule);
    }

    if (box.hidden && payload().name.length >= 2 && hasClientSignal(payload())) {
      run();
    } else if (!box.hidden) {
      lastKey = JSON.stringify(payload());
    }
  }

  function initCreateVisitFields() {
    var visitCb = document.getElementById('cp-create-visit-required');
    if (!visitCb) return;
    var visitDate = document.getElementById('cp-create-visit-date');
    var visitExpand = document.getElementById('cp-create-visit-expandable');

    function syncCreateVisitExpandable() {
      if (!visitExpand) return;
      var open = visitCb.checked;
      visitExpand.classList.toggle('is-open', open);
      visitExpand.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function syncCreateVisitFields() {
      if (!visitCb.checked && visitDate) {
        visitDate.value = '';
      }
      if (visitDate) {
        visitDate.disabled = !visitCb.checked;
        visitDate.classList.toggle('cp-visit-date-input--locked', !visitCb.checked);
      }
      syncCreateVisitExpandable();
      var checked = document.querySelector('[data-cp-billing]:checked');
      var isHourly = checked && checked.value === 'hourly';
      syncCreateVisitFooters(isHourly);
    }

    visitCb.addEventListener('change', syncCreateVisitFields);
    if (visitDate) visitDate.addEventListener('change', function () {
      syncCreateVisitExpandable();
      var checked = document.querySelector('[data-cp-billing]:checked');
      var isHourly = checked && checked.value === 'hourly';
      syncCreateVisitFooters(isHourly);
    });
    syncCreateVisitFields();
  }

  function initScheduleToggle(opts) {
    var toggle = document.getElementById(opts.toggleId);
    if (!toggle) return;
    var expandable = document.getElementById(opts.expandableId);
    var start = document.getElementById(opts.startId);
    var end = document.getElementById(opts.endId);
    var warn = opts.warningId ? document.getElementById(opts.warningId) : null;
    var form = opts.formId ? document.getElementById(opts.formId) : null;

    function syncExpandable() {
      if (!expandable) return;
      var open = toggle.checked;
      expandable.classList.toggle('is-open', open);
      expandable.setAttribute('aria-hidden', open ? 'false' : 'true');
    }

    function checkDates() {
      if (!start || !end || !warn) return true;
      if (!toggle.checked || !start.value || !end.value) {
        warn.classList.add('admin-flash--js-hidden');
        warn.textContent = '';
        return true;
      }
      if (start.value > end.value) {
        warn.textContent = 'The start date is after the end date. Choose an end date on or after the start date (same day is fine), then save.';
        warn.classList.remove('admin-flash--js-hidden');
        return false;
      }
      warn.classList.add('admin-flash--js-hidden');
      warn.textContent = '';
      return true;
    }

    function syncFields() {
      if (!toggle.checked) {
        if (start) start.value = '';
        if (end) end.value = '';
        if (warn) {
          warn.classList.add('admin-flash--js-hidden');
          warn.textContent = '';
        }
      }
      if (start) start.disabled = !toggle.checked;
      if (end) end.disabled = !toggle.checked;
      syncExpandable();
    }

    toggle.addEventListener('change', syncFields);
    if (start) start.addEventListener('change', checkDates);
    if (end) end.addEventListener('change', checkDates);
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!toggle.checked) {
          if (start) {
            start.disabled = false;
            start.value = '';
          }
          if (end) {
            end.disabled = false;
            end.value = '';
          }
        }
        if (!checkDates()) {
          e.preventDefault();
          if (warn) warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      });
    }
    syncFields();
  }

  function initCreateScheduleFields() {
    initScheduleToggle({
      toggleId: 'cp-create-schedule-enabled',
      expandableId: 'cp-create-schedule-expandable',
      startId: 'cp-start-date',
      endId: 'cp-end-date',
      warningId: 'cp-create-date-warning',
      formId: 'cp-create-project-form',
    });
  }

  function initEditScheduleFields() {
    initScheduleToggle({
      toggleId: 'cp-edit-schedule-enabled',
      expandableId: 'cp-edit-schedule-expandable',
      startId: 'cp-start-date',
      endId: 'cp-end-date',
      warningId: 'cp-edit-date-warning',
      formId: 'cp-edit-project-form',
    });
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

  function roundPct(n) {
    return Math.round(n * 100) / 100;
  }

  function parsePct(input) {
    var pct = parseFloat(input && input.value);
    if (isNaN(pct) || pct < 0) return 0;
    if (pct > 100) return 100;
    return pct;
  }

  function installmentPctRows() {
    var form = document.getElementById('cp-installments-form');
    if (!form) return [];
    var rows = [];
    form.querySelectorAll('[data-row]').forEach(function (row) {
      var input = row.querySelector('input.cp-pct-input[name="percentages[]"]');
      if (!input) return;
      rows.push({
        input: input,
        paid: row.getAttribute('data-paid') === '1' || input.readOnly
      });
    });
    return rows;
  }

  /**
   * After the user edits installment N, split (100 − sum of 1..N) across the
   * following unpaid rows so 2/3/4-installment plans always add up to 100%.
   */
  function autoFillRemaining(changedInput) {
    var rows = installmentPctRows();
    if (rows.length < 2) return;

    var changedIdx = -1;
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].input === changedInput) {
        changedIdx = i;
        break;
      }
    }
    if (changedIdx < 0 || rows[changedIdx].paid) return;

    var used = 0;
    for (var j = 0; j <= changedIdx; j++) {
      used = roundPct(used + parsePct(rows[j].input));
    }

    var followers = [];
    for (var k = changedIdx + 1; k < rows.length; k++) {
      if (rows[k].paid) {
        used = roundPct(used + parsePct(rows[k].input));
      } else {
        followers.push(rows[k]);
      }
    }
    if (!followers.length) return;

    var remaining = roundPct(100 - used);
    if (remaining < 0) remaining = 0;

    var each = roundPct(remaining / followers.length);
    var allocated = 0;
    followers.forEach(function (row, idx) {
      var val = idx === followers.length - 1
        ? roundPct(remaining - allocated)
        : each;
      if (val < 0) val = 0;
      row.input.value = val.toFixed(2);
      allocated = roundPct(allocated + parsePct(row.input));
    });
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
      if (input._cpPctWired) return;
      input._cpPctWired = true;
      input.addEventListener('input', function () {
        autoFillRemaining(input);
        recalc();
      });
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

  function initExpenseEditToggles() {
    document.querySelectorAll('[data-cp-expense-edit-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-cp-expense-edit-toggle');
        var row = document.querySelector('[data-cp-expense-edit-row="' + id + '"]');
        if (!row) return;
        var opening = row.hidden;
        document.querySelectorAll('[data-cp-expense-edit-row]').forEach(function (r) {
          r.hidden = true;
        });
        if (opening) {
          row.hidden = false;
          var mi = row.querySelector('.cp-money-input');
          if (mi && window.cpMoneyMask) {
            window.cpMoneyMask.wire(mi);
          }
        }
      });
    });
    document.querySelectorAll('[data-cp-expense-edit-cancel]').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var id = btn.getAttribute('data-cp-expense-edit-cancel');
        var row = document.querySelector('[data-cp-expense-edit-row="' + id + '"]');
        if (row) row.hidden = true;
      });
    });
  }

  function initHelperPayForm() {
    var form = document.querySelector('[data-cp-helper-pay-form]');
    if (!form || form._cpHelperPayWired) return;
    form._cpHelperPayWired = true;

    var submitBtn = form.querySelector('[data-cp-helper-pay-submit]');
    var formId = form.id;
    var checks = formId
      ? document.querySelectorAll('[data-cp-helper-entry-check][form="' + formId + '"]')
      : form.querySelectorAll('[data-cp-helper-entry-check]');

    function syncPayState() {
      var selected = Array.prototype.filter.call(checks, function (c) { return c.checked; });
      if (!submitBtn) return;

      if (selected.length === 0) {
        submitBtn.disabled = true;
        return;
      }

      var helperId = selected[0].getAttribute('data-helper-id');
      var sameHelper = selected.every(function (c) {
        return c.getAttribute('data-helper-id') === helperId;
      });

      submitBtn.disabled = !sameHelper;
      submitBtn.title = sameHelper ? '' : 'Select days for one helper at a time.';
    }

    checks.forEach(function (check) {
      check.addEventListener('change', syncPayState);
    });
    syncPayState();
  }

  function initHelperAckForm() {
    var form = document.querySelector('[data-cp-helper-ack-form]');
    if (!form || form._cpHelperAckWired) return;
    form._cpHelperAckWired = true;

    var submitBtn = form.querySelector('[data-cp-helper-ack-submit]');
    var formId = form.id;
    var checks = formId
      ? document.querySelectorAll('[data-cp-helper-ack-check][form="' + formId + '"]')
      : form.querySelectorAll('[data-cp-helper-ack-check]');

    function syncAckState() {
      if (!submitBtn) return;
      var selected = Array.prototype.filter.call(checks, function (c) { return c.checked; });
      var uniquePayments = {};
      selected.forEach(function (c) {
        var pid = c.getAttribute('data-payment-id');
        if (pid) uniquePayments[pid] = true;
      });
      submitBtn.disabled = Object.keys(uniquePayments).length === 0;
    }

    checks.forEach(function (check) {
      check.addEventListener('change', function () {
        var paymentId = check.getAttribute('data-payment-id');
        if (paymentId) {
          document.querySelectorAll('[data-cp-helper-ack-check][data-payment-id="' + paymentId + '"]').forEach(function (c) {
            c.checked = check.checked;
          });
        }
        syncAckState();
      });
    });
    syncAckState();
  }

  function initQuotationCopyLink() {
    function copyQuotationLink(btn) {
      var url = btn.getAttribute('data-copy-url');
      if (!url) return;

      var originalHtml = btn.innerHTML;
      var originalTitle = btn.getAttribute('title') || '';

      function showCopied() {
        btn.innerHTML = '<i class="fa fa-check"></i> Copied';
        btn.setAttribute('title', 'Link copied to clipboard');
        window.setTimeout(function () {
          btn.innerHTML = originalHtml;
          btn.setAttribute('title', originalTitle);
        }, 2000);
      }

      function showErr() {
        window.alert('Could not copy to clipboard. Open the PDF and share the browser URL, or paste the link manually.');
      }

      function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try {
          if (document.execCommand('copy')) {
            showCopied();
          } else {
            showErr();
          }
        } catch (err) {
          showErr();
        }
        document.body.removeChild(ta);
      }

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(showCopied).catch(function () {
          fallbackCopy(url);
        });
      } else {
        fallbackCopy(url);
      }
    }

    document.querySelectorAll('[data-cp-copy-quotation-link]').forEach(function (btn) {
      if (btn._cpCopyLinkWired) return;
      btn._cpCopyLinkWired = true;
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        copyQuotationLink(btn);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initMoneyMask();
    initPhoneMask();
    initQuotationCopyLink();
    initTimeForms();
    initTimeEditToggles();
    initExpenseEditToggles();
    initHelperPayForm();
    initHelperAckForm();
    initBillingTypeToggle();
    initCreateVisitFields();
    initCreateScheduleFields();
    initCreateDuplicateCheck();
    initEditScheduleFields();
    initRecalculateInstallmentsModal();

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

  function formatCad(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
    });
  }

  function initRecalculateInstallmentsModal() {
    var modal = document.getElementById('cp-recalc-modal');
    if (!modal) return;

    var openBtns = document.querySelectorAll('[data-cp-recalc-open]');
    var closeBtns = modal.querySelectorAll('[data-cp-recalc-close]');
    var countSelect = document.getElementById('cp-recalc-remaining-count');
    var splitSelect = document.getElementById('cp-recalc-split-mode');
    var previewEl = document.getElementById('cp-recalc-preview');
    var errorEl = document.getElementById('cp-recalc-error');
    var confirmBtn = document.getElementById('cp-recalc-confirm');
    var previewTimer = null;
    var lastPreview = null;

    function openModal() {
      modal.hidden = false;
      document.body.classList.add('cp-modal-open');
      schedulePreview();
    }

    function closeModal() {
      modal.hidden = true;
      document.body.classList.remove('cp-modal-open');
    }

    openBtns.forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        openModal();
      });
    });
    closeBtns.forEach(function (btn) {
      btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        closeModal();
      });
    });
    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) closeModal();
    });

    function schedulePreview() {
      if (previewTimer) clearTimeout(previewTimer);
      previewTimer = setTimeout(runPreview, 180);
    }

    if (countSelect) countSelect.addEventListener('change', schedulePreview);
    if (splitSelect) splitSelect.addEventListener('change', schedulePreview);

    function runPreview() {
      if (!previewEl || !confirmBtn) return;
      confirmBtn.disabled = true;
      lastPreview = null;
      if (errorEl) {
        errorEl.hidden = true;
        errorEl.textContent = '';
      }
      previewEl.innerHTML = '<p class="admin-form-help">Calculating preview…</p>';

      var payload = {
        remaining_count: countSelect ? parseInt(countSelect.value, 10) : 1,
        split_mode: splitSelect ? splitSelect.value : 'defaults'
      };

      fetch(modal.getAttribute('data-preview-url'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': modal.getAttribute('data-csrf') || '',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          return res.json().then(function (data) {
            return { ok: res.ok, status: res.status, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            var msg = (result.data && result.data.error) ? result.data.error : 'Could not build preview.';
            previewEl.innerHTML = '';
            if (errorEl) {
              errorEl.hidden = false;
              errorEl.textContent = msg;
            }
            return;
          }
          lastPreview = result.data;
          renderPreview(result.data);
          confirmBtn.disabled = false;
        })
        .catch(function () {
          previewEl.innerHTML = '';
          if (errorEl) {
            errorEl.hidden = false;
            errorEl.textContent = 'Network error while loading preview.';
          }
        });
    }

    function renderPreview(data) {
      var rows = (data && data.rows) || [];
      var analysis = (data && data.analysis) || {};
      var html = '';
      html += '<p class="admin-form-help" style="margin-top:0;">';
      html += 'New plan: <strong>' + (data.new_plan || 0) + '</strong> installment(s). ';
      html += 'Existing invoices are kept. New drafts are created only for remaining unpaid installments that do not already have an invoice';
      html += ' (<strong>' + (data.drafts_to_create || 0) + '</strong> draft(s)).';
      html += '</p>';
      html += '<table><thead><tr>';
      html += '<th>#</th><th>Label</th><th class="num">%</th><th class="num">Amount (incl. HST)</th><th>Status</th>';
      html += '</tr></thead><tbody>';
      rows.forEach(function (row, idx) {
        html += '<tr class="' + (row.is_paid ? 'is-paid' : '') + '">';
        html += '<td>' + (idx + 1) + '</td>';
        html += '<td>' + escapeHtml(row.label || ('Installment ' + (idx + 1))) + '</td>';
        html += '<td class="num">' + Number(row.percentage || 0).toFixed(2) + '%</td>';
        html += '<td class="num">' + formatCad(row.total_incl_hst) + '</td>';
        html += '<td>' + (row.is_paid ? 'Paid (kept)' : 'New / unpaid') + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
      html += '<p class="admin-form-help" style="margin-bottom:0;">';
      html += 'Quotation total ' + formatCad(analysis.quotation_grand);
      html += ' − paid ' + formatCad(analysis.paid_grand);
      html += ' = remaining ' + formatCad(analysis.remaining_grand) + '.';
      html += '</p>';
      previewEl.innerHTML = html;
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', function () {
        if (!lastPreview) return;
        var remaining = countSelect ? parseInt(countSelect.value, 10) : 1;
        var msg = 'Recalculate remaining installments?\n\n'
          + 'Existing invoices (issued, sent, drafts, and payments) are not deleted or rewritten.\n'
          + 'Paid installments will not be changed.';

        var doSubmit = function () {
          var form = document.createElement('form');
          form.method = 'POST';
          form.action = modal.getAttribute('data-apply-url');
          form.style.display = 'none';

          var csrf = document.createElement('input');
          csrf.type = 'hidden';
          csrf.name = '_token';
          csrf.value = modal.getAttribute('data-csrf') || '';
          form.appendChild(csrf);

          var countInput = document.createElement('input');
          countInput.type = 'hidden';
          countInput.name = 'remaining_count';
          countInput.value = String(remaining);
          form.appendChild(countInput);

          var splitInput = document.createElement('input');
          splitInput.type = 'hidden';
          splitInput.name = 'split_mode';
          splitInput.value = splitSelect ? splitSelect.value : 'defaults';
          form.appendChild(splitInput);

          document.body.appendChild(form);
          form.submit();
        };

        if (window.AdminDialog && window.AdminDialog.confirm) {
          window.AdminDialog.confirm(msg, {
            title: 'Confirm recalculation',
            danger: true,
            confirmLabel: 'Recalculate'
          }).then(function (ok) {
            if (ok) doSubmit();
          });
        } else if (window.confirm(msg)) {
          doSubmit();
        }
      });
    }
  }
})();
