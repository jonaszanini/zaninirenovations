window.MillworkDimensionParser = (function () {
  var INVALID = 'Enter a valid size (for example 23 1/2, 2 ft, 2\' 6", or 762mm).';
  var NEGATIVE = 'Dimensions must be greater than zero.';
  var FRACS = { '½': '1/2', '¼': '1/4', '¾': '3/4', '⅓': '1/3', '⅔': '2/3', '⅛': '1/8', '⅜': '3/8', '⅝': '5/8', '⅞': '7/8' };

  function empty() { return { status: 'empty', inches: 0, message: null }; }
  function invalid(msg) { return { status: 'invalid', inches: 0, message: msg }; }
  function ok(inches) {
    if (!isFinite(inches) || inches <= 0) {
      return invalid(isFinite(inches) && inches <= 0 ? NEGATIVE : INVALID);
    }
    return { status: 'ok', inches: inches, message: null };
  }

  function toFloat(s) {
    s = String(s == null ? '' : s).trim();
    if (!s) return null;
    if (/[eE]/.test(s)) {
      if (!/^[+]?\d+(?:\.\d+)?[eE][+\-]?\d+$/.test(s)) return null;
      var sci = Number(s);
      return isFinite(sci) && sci >= 0 ? sci : null;
    }
    if (s.indexOf('.') !== -1) s = s.replace(/,/g, '');
    else if (s.indexOf(',') !== -1) {
      if ((s.match(/,/g) || []).length !== 1) return null;
      var last = s.lastIndexOf(',');
      var frac = s.slice(last + 1);
      if (frac.length >= 3) return null;
      s = s.slice(0, last).replace(/,/g, '') + '.' + frac;
    }
    if (!/^[+]?(?:\d+(?:\.\d+)?|\.\d+)$/.test(s)) return null;
    var n = Number(s);
    return isFinite(n) ? n : null;
  }

  function fraction(num, den) {
    var n = toFloat(num);
    var d = toFloat(den);
    if (n == null || d == null || d === 0) return null;
    return n / d;
  }

  function parseAmount(s) {
    s = String(s || '').trim();
    if (!s) return null;
    var m = s.match(/^(\d+(?:[.,]\d+)?)\s*[-\s]\s*(\d+)\s*\/\s*(\d+)$/);
    if (m) {
      var whole = toFloat(m[1]);
      var frac = fraction(m[2], m[3]);
      if (whole == null || frac == null) return null;
      return whole + frac;
    }
    m = s.match(/^(\d+)\s*\/\s*(\d+)$/);
    if (m) return fraction(m[1], m[2]);
    return toFloat(s);
  }

  function parseFeetAndInches(s) {
    s = s.replace(/feet|foot/ig, 'ft').replace(/inches|inch/ig, 'in');
    var m = s.match(/^(.+?)\s*(?:ft|')\s+(.+?)\s+(\d+)\s*\/\s*(\d+)\s*(?:in|")?\s*$/i);
    if (m) {
      var ft = parseAmount(m[1]);
      var whole = parseAmount(m[2]);
      var frac = fraction(m[3], m[4]);
      if (ft == null || whole == null || frac == null) return null;
      return (ft * 12) + whole + frac;
    }
    m = s.match(/^(.+?)\s*(?:ft|')\s*(\d+)\s*\/\s*(\d+)\s*(?:in|")?\s*$/i);
    if (m) {
      var ft2 = parseAmount(m[1]);
      var frac2 = fraction(m[2], m[3]);
      if (ft2 == null || frac2 == null) return null;
      return (ft2 * 12) + frac2;
    }
    m = s.match(/^(.+?)\s*(?:ft|')\s*(.+?)\s*(?:in|")\s*$/i);
    if (m) {
      var ft3 = parseAmount(m[1]);
      var inch = parseAmount(m[2]);
      if (ft3 == null || inch == null) return null;
      return (ft3 * 12) + inch;
    }
    m = s.match(/^(.+?)\s*(?:ft|')\s+(.+)$/i);
    if (m) {
      var ft4 = parseAmount(m[1]);
      var inch2 = parseAmount(m[2]);
      if (ft4 == null || inch2 == null) return null;
      return (ft4 * 12) + inch2;
    }
    return null;
  }

  function parseNormalizedToInches(s, fieldUnit) {
    var m = s.match(/^(.+?)\s*(?:mm|millimet(?:er|re)s?)$/i);
    if (m) { var n = parseAmount(m[1]); return n == null ? null : n / 25.4; }
    m = s.match(/^(.+?)\s*cm(?:s)?$/i);
    if (m) { var n2 = parseAmount(m[1]); return n2 == null ? null : n2 / 2.54; }
    m = s.match(/^(.+?)\s*m(?:eters?|etres?)?$/i);
    if (m && !/[cm]m$/i.test(s)) { var n3 = parseAmount(m[1]); return n3 == null ? null : n3 / 0.0254; }
    var fi = parseFeetAndInches(s);
    if (fi != null) return fi;
    m = s.match(/^(.+?)\s*(?:ft|feet|foot|')\s*$/i);
    if (m) { var n4 = parseAmount(m[1]); return n4 == null ? null : n4 * 12; }
    m = s.match(/^(.+?)\s*(?:in(?:ch(?:es)?)?|")\s*$/i);
    if (m) return parseAmount(m[1]);
    var n5 = parseAmount(s);
    if (n5 == null) return null;
    return String(fieldUnit || 'in').toLowerCase() === 'ft' ? n5 * 12 : n5;
  }

  function normalizeRaw(raw) {
    var s = String(raw == null ? '' : raw).trim().replace(/[\u00A0\t]/g, ' ');
    s = s.replace(/[\u2033\u201D\u201C\u201F]/g, '"').replace(/[\u2032\u2019\u2018\u201B\u00B4]/g, "'");
    Object.keys(FRACS).forEach(function (glyph) {
      s = s.replace(new RegExp('(\\d)' + glyph, 'g'), '$1 ' + FRACS[glyph]).replace(glyph, FRACS[glyph]);
    });
    return s.replace(/\s+/g, ' ').trim();
  }

  function inspect(raw, fieldUnit) {
    if (raw == null) return empty();
    if (typeof raw === 'number') {
      if (!isFinite(raw)) return invalid(INVALID);
      if (raw < 0) return invalid(NEGATIVE);
      if (raw === 0) return empty();
      return ok(String(fieldUnit || 'in').toLowerCase() === 'ft' ? raw * 12 : raw);
    }
    var s = normalizeRaw(raw);
    if (!s || /^[+]?(?:0+)(?:\.0+)?$/.test(s)) return empty();
    if (s.charAt(0) === '-') return invalid(NEGATIVE);
    s = s.replace(/^\+/, '');
    if (!s) return invalid(INVALID);
    var inches = parseNormalizedToInches(s, fieldUnit || 'in');
    if (inches == null) return invalid(INVALID);
    return ok(inches);
  }

  return {
    inspect: inspect,
    STATUS_OK: 'ok',
    STATUS_EMPTY: 'empty',
    STATUS_INVALID: 'invalid',
    INVALID_MESSAGE: INVALID,
    NEGATIVE_MESSAGE: NEGATIVE
  };
})();

(function () {
  var cfg = window.MW_QUOTATION || {};
  var form = document.getElementById('mw-main-form');
  if (form) {
    var previewUrl = cfg.previewUrl || '';
    var isEdit = !!cfg.isEdit;
    var sectionKeys = (cfg.sectionKeys || []).slice();
    var wizardKeys = sectionKeys.slice();
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
    var debounceTimer = null;
    var lastDebrisAmount = 0;
    var lastAdditionalMaterialsPreTax = 0;
    var lastAdditionalMaterialsHst = 0;
    var lastAdditionalLaborPreTax = 0;
    var lastAdditionalLaborHst = 0;
    var lastAdditionalTaxIn = 0;
    var lastCommuteAmount = 0;
    var lastPreviewData = null;
    var currentIndex = 0;
    var saving = false;
    var allowUnload = false;
    var previewSeq = 0;
    var previewAbort = null;
    var PREVIEW_TIMEOUT_MS = 8000;

    var fmt = function (n) {
      return 'CA$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    };
    var escapeHtml = function (s) {
      return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    };
    var kerfLabel = function (inches) {
      var n = Number(inches);
      if (!(n > 0)) return '';
      if (Math.abs(n - 0.125) < 0.0005) return '1/8"';
      if (Math.abs(n - 0.25) < 0.0005) return '1/4"';
      return String(Math.round(n * 1000) / 1000) + '"';
    };
    var cutPlanMetaText = function (plan) {
      var text = (plan.sheet_count || 0) + ' sheet(s)'
        + (plan.yield_pct ? ' · ' + plan.yield_pct + '% yield' : '');
      var kerf = kerfLabel(plan.kerf_in);
      if (kerf) text += ' · Kerf ' + kerf;
      return text;
    };

    function findStepKeyForElement(el) {
      var step = el && el.closest ? el.closest('[data-br-step]') : null;
      return step ? step.getAttribute('data-br-step') : null;
    }

    function sectionEl(key) {
      return form.querySelector('[data-br-step="' + key + '"]');
    }

    function setWizardStep(idx, scroll) {
      if (idx < 0) idx = 0;
      if (idx >= wizardKeys.length) idx = wizardKeys.length - 1;
      currentIndex = idx;
      form.querySelectorAll('.br-acc').forEach(function (el) {
        var on = el.getAttribute('data-br-step') === wizardKeys[idx];
        el.classList.toggle('is-active', on);
        if (on) el.setAttribute('open', '');
        else el.removeAttribute('open');
        el.hidden = !on;
      });
      form.querySelectorAll('.br-progress__item').forEach(function (b) {
        b.classList.toggle('is-current', b.getAttribute('data-mw-goto') === wizardKeys[idx]);
      });
      var status = document.getElementById('mw-step-status');
      if (status) status.textContent = (idx + 1) + ' of ' + wizardKeys.length;
      var prevBtn = document.getElementById('mw-prev');
      var nextBtn = document.getElementById('mw-next');
      if (prevBtn) {
        prevBtn.disabled = idx === 0;
        prevBtn.textContent = '← Back';
      }
      if (nextBtn) {
        nextBtn.disabled = false;
        nextBtn.textContent = idx === wizardKeys.length - 1 ? 'Review' : '→ Next';
      }
      if (scroll) {
        var el = sectionEl(wizardKeys[idx]);
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
      syncClientProvidesAll();
    }

    function setSaveBusy(on) {
      saving = !!on;
      [publishBtn, draftBtn].forEach(function (btn) {
        if (!btn) return;
        btn.disabled = !!on;
        btn.setAttribute('aria-busy', on ? 'true' : 'false');
      });
    }

    function allowFormUnload() {
      allowUnload = true;
      window.allowUnload = true;
      window.AdminQuoteAllowUnload = true;
    }

    function submitWizard(intent) {
      if (saving) return;
      if (previewAbort) {
        previewAbort.abort();
        previewAbort = null;
      }
      clearTimeout(debounceTimer);
      var intentEl = document.getElementById('mw-save-intent');
      if (intentEl) intentEl.value = intent;
      if (window.AdminQuoteDraftValidation) {
        if (intent === 'draft') window.AdminQuoteDraftValidation.relax(form);
        else window.AdminQuoteDraftValidation.restore(form);
      }
      if (!isEdit) {
        form.querySelectorAll('.br-acc').forEach(function (el) {
          el.classList.add('is-active');
          el.setAttribute('open', '');
          el.hidden = false;
        });
      }
      if (!form.checkValidity()) {
        var invalid = form.querySelector(':invalid');
        if (!isEdit) {
          var key = findStepKeyForElement(invalid);
          var idx = key ? wizardKeys.indexOf(key) : 0;
          if (idx < 0) idx = 0;
          setWizardStep(idx, true);
        }
        return;
      }
      allowFormUnload();
      setSaveBusy(true);
      HTMLFormElement.prototype.submit.call(form);
    }

    var draftBtn = document.getElementById('mw-btn-draft');
    var publishBtn = document.getElementById('mw-btn-publish');
    if (draftBtn) {
      draftBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitWizard('draft');
      });
    }
    if (publishBtn) {
      publishBtn.addEventListener('click', function (e) {
        e.preventDefault();
        submitWizard('publish');
      });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var intentEl = document.getElementById('mw-save-intent');
      submitWizard(intentEl && intentEl.value === 'draft' ? 'draft' : 'publish');
    });
    form.submit = function () {
      allowFormUnload();
      HTMLFormElement.prototype.submit.call(form);
    };
    var beforeUnloadBound = false;
    function bindBeforeUnload() {
      if (beforeUnloadBound) return;
      beforeUnloadBound = true;
      window.addEventListener('beforeunload', function (e) {
        if (allowUnload || saving || window.allowUnload || window.AdminQuoteAllowUnload) return;
        if (form.getAttribute('data-quotation-user-edited') !== '1') return;
        e.preventDefault();
        e.returnValue = '';
      });
    }
    if (form.getAttribute('data-quotation-user-edited') === '1') {
      bindBeforeUnload();
    } else if (window.MutationObserver) {
      var editedObserver = new MutationObserver(function () {
        if (form.getAttribute('data-quotation-user-edited') === '1') {
          bindBeforeUnload();
          editedObserver.disconnect();
        }
      });
      editedObserver.observe(form, { attributes: true, attributeFilter: ['data-quotation-user-edited'] });
    }
    window.addEventListener('pageshow', function (e) {
      if (e.persisted) {
        setSaveBusy(false);
        allowUnload = false;
      }
    });

    function anyNumberAtLeast(selector, min) {
      var nodes = form.querySelectorAll(selector);
      for (var i = 0; i < nodes.length; i++) {
        if ((parseFloat(String(nodes[i].value || '').replace(/,/g, '')) || 0) >= min) {
          return true;
        }
      }
      return false;
    }

    function showStepErrors(messages) {
      var host = document.getElementById('mw-step-nav');
      var box = document.getElementById('mw-step-errors');
      if (!box) {
        box = document.createElement('div');
        box.id = 'mw-step-errors';
        box.className = 'qh-step-errors';
        box.setAttribute('role', 'alert');
        if (host && host.parentNode) {
          host.parentNode.insertBefore(box, host.nextSibling);
        } else {
          form.insertBefore(box, form.firstChild);
        }
      }
      if (!messages.length) {
        box.hidden = true;
        box.innerHTML = '';
        return;
      }
      box.hidden = false;
      box.innerHTML = '<p>This step is missing required information:</p><ul>'
        + messages.map(function (m) { return '<li>' + escapeHtml(m) + '</li>'; }).join('')
        + '</ul>';
    }

    function collectRequiredLabels(scope) {
      var missing = [];
      if (!scope) return missing;
      var fields = scope.querySelectorAll('input, select, textarea');
      for (var i = 0; i < fields.length; i++) {
        var el = fields[i];
        if (el.disabled || el.readOnly) continue;
        if (el.closest && el.closest('[hidden]')) continue;
        if (typeof el.checkValidity === 'function' && !el.checkValidity()) {
          var label = '';
          if (el.id) {
            var lab = form.querySelector('label[for="' + el.id + '"]');
            if (lab) label = (lab.textContent || '').replace(/\s+/g, ' ').trim();
          }
          missing.push(label || el.getAttribute('aria-label') || el.name || 'A required field');
        }
      }
      return missing;
    }

    function validateCurrentStep() {
      var missing = [];
      var key = wizardKeys[currentIndex];
      var step = sectionEl(key);
      var clientCard = form.querySelector('.mw-client-card');
      if (currentIndex === 0 && clientCard) {
        missing = missing.concat(collectRequiredLabels(clientCard));
      }
      missing = missing.concat(collectRequiredLabels(step));
      if (key === 'pieces') {
        var hasPieceDims = anyNumberAtLeast('[name^="pieces["][name$="[width]"]', 0.01)
          || anyNumberAtLeast('[name^="pieces["][name$="[length]"]', 0.01)
          || anyNumberAtLeast('[name^="pieces["][name$="[height]"]', 0.01)
          || anyNumberAtLeast('[name^="pieces["][name$="[depth]"]', 0.01)
          || anyNumberAtLeast('[name^="pieces["][name*="[sets]"][name$="[length]"]', 0.01)
          || anyNumberAtLeast('[name^="pieces["][name*="[sets]"][name$="[width]"]', 0.01);
        if (!hasPieceDims) {
          missing.push('At least one piece with a length, width, height, or depth');
        }
      }
      showStepErrors(missing);
      if (missing.length) {
        var firstInvalid = (step || clientCard || form).querySelector(':invalid');
        if (firstInvalid) {
          try { firstInvalid.focus(); } catch (err) { /* ignore */ }
        }
        return false;
      }
      return true;
    }

    if (!isEdit) {
      setWizardStep(0, false);
      var prevBtn = document.getElementById('mw-prev');
      var nextBtn = document.getElementById('mw-next');
      if (prevBtn) prevBtn.addEventListener('click', function () { setWizardStep(currentIndex - 1, true); });
      if (nextBtn) nextBtn.addEventListener('click', function () {
        if (!validateCurrentStep()) return;
        setWizardStep(currentIndex + 1, true);
      });
    } else {
      var nav = document.getElementById('mw-step-nav');
      if (nav) nav.hidden = true;
    }

    form.querySelectorAll('[data-mw-goto]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-mw-goto');
        var idx = wizardKeys.indexOf(key);
        if (idx < 0) return;
        if (isEdit) {
          var el = sectionEl(key);
          if (el) {
            el.setAttribute('open', '');
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
          form.querySelectorAll('.br-progress__item').forEach(function (b) {
            b.classList.toggle('is-current', b.getAttribute('data-mw-goto') === key);
          });
          return;
        }
        setWizardStep(idx, true);
      });
    });

    function setCutPlanLoading(on) {
      var root = document.getElementById('mw-cut-plan');
      var status = document.getElementById('mw-cut-plan-status');
      if (root) {
        root.classList.toggle('is-loading', !!on);
        root.setAttribute('aria-busy', on ? 'true' : 'false');
      }
      if (status && on) {
        status.hidden = false;
        if (!status.textContent) status.textContent = 'Updating cut plan…';
      }
    }

    function clientProvidesAll() {
      var cb = document.getElementById('mw-client-all');
      return !!(cb && cb.checked);
    }

    function syncClientProvidesAll() {
      var hide = clientProvidesAll();
      form.classList.toggle('is-client-all', hide);
      var wrap = document.getElementById('mw-delivery-wrap');
      if (wrap) wrap.hidden = hide;
      var bar = document.getElementById('mw-bar-delivery-wrap');
      if (hide && bar) bar.hidden = true;
    }

    function syncHardwarePrices() {
      form.querySelectorAll('[data-mw-hw-select]').forEach(function (sel) {
        var hint = sel.parentElement ? sel.parentElement.querySelector('[data-mw-hw-price]') : null;
        if (!hint) return;
        var opt = sel.options[sel.selectedIndex];
        var price = opt ? Number(opt.getAttribute('data-price') || 0) : 0;
        if (price > 0) {
          hint.hidden = false;
          hint.textContent = fmt(price) + ' each';
        } else {
          hint.hidden = true;
          hint.textContent = '';
        }
      });
    }

    function schedulePreview() {
      syncHardwareCatalog();
      syncMaterialsCatalog();
      syncClientProvidesAll();
      syncHardwarePrices();
      setCutPlanLoading(true);
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(runPreview, 400);
    }

    function showPreviewMessage(text) {
      var status = document.getElementById('mw-cut-plan-status');
      if (!status) return;
      status.hidden = !text;
      status.textContent = text || 'Updating cut plan…';
    }

    function previewFormData() {
      var body = new FormData(form);
      body.delete('_method');
      return body;
    }

    function fetchPreview() {
      if (!previewUrl) return Promise.resolve(null);
      if (previewAbort) previewAbort.abort();
      previewAbort = new AbortController();
      var timer = setTimeout(function () { previewAbort.abort(); }, PREVIEW_TIMEOUT_MS);
      var body = previewFormData();
      return fetch(previewUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin',
        signal: previewAbort.signal
      }).then(function (res) { return res.json(); }).finally(function () {
        clearTimeout(timer);
      });
    }

    function inputErrorKey(input) {
      return String(input.getAttribute('name') || '').replace(/\]\[/g, '.').replace(/\[/g, '.').replace(/\]/g, '');
    }

    function dimFieldUnit(input) {
      var wrap = input.closest('.mw-dim');
      var sel = wrap ? wrap.querySelector('.mw-dim__unit') : null;
      return sel && String(sel.value).toLowerCase() === 'ft' ? 'ft' : 'in';
    }

    function dimFieldIsActive(input) {
      var group = input.closest('.mw-dim-field');
      if (!group) return true;
      if (group.hidden || group.hasAttribute('hidden')) return false;
      var show = group.getAttribute('data-mw-show');
      if (show && window.getComputedStyle(group).display === 'none') return false;
      return true;
    }

    function setDimInvalid(input, message) {
      var wrap = input.closest('.mw-dim-field') || input.closest('.admin-form-group');
      input.classList.toggle('is-invalid', !!message);
      if (message) input.setAttribute('aria-invalid', 'true');
      else input.removeAttribute('aria-invalid');
      if (!wrap) return;
      var err = wrap.querySelector('.admin-form-error');
      if (!message) {
        if (err && err.getAttribute('data-mw-dim-error') === '1') err.remove();
        return;
      }
      if (!err) {
        err = document.createElement('p');
        err.className = 'admin-form-error';
        err.setAttribute('data-mw-dim-error', '1');
        wrap.appendChild(err);
      }
      err.textContent = message;
    }

    function quoteUsesNewParsing() {
      return Number(cfg.calcVersion || cfg.cutPlanVersion || 1) >= 3
        || (document.getElementById('mw-cut-plan-version') || {}).value === '3';
    }

    function pieceUsesNewParsing(input) {
      if (quoteUsesNewParsing()) return true;
      var card = input.closest('[data-mw-piece]');
      if (!card) return false;
      var flag = card.querySelector('[data-mw-calc-version]');
      return flag && Number(flag.value) >= 3;
    }

    function normalizeSizeToken(value) {
      return String(value == null ? '' : value).replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function pieceSizeFingerprint(card) {
      var parts = [];
      card.querySelectorAll('.mw-input--dim, .mw-dim__unit').forEach(function (el) {
        var name = String(el.getAttribute('name') || '');
        var key = name.replace(/^.*\[/, '').replace(/\]$/, '');
        parts.push(key + '=' + normalizeSizeToken(el.value));
      });
      return parts.join('|');
    }

    function snapshotPieceSizes(card, opts) {
      if (!card) return;
      opts = opts || {};
      card.querySelectorAll('.mw-input--dim').forEach(function (el) {
        if (opts.asNew || !el.hasAttribute('data-mw-initial-dim')) {
          el.setAttribute('data-mw-initial-dim', el.value);
        }
      });
      var flag = card.querySelector('[data-mw-calc-version]');
      if (opts.asNew) {
        if (flag) flag.value = latestCalcVersion();
        card.setAttribute('data-mw-saved-fingerprint', '');
        card.setAttribute('data-mw-saved-calc-version', latestCalcVersion());
        return;
      }
      if (card.getAttribute('data-mw-saved-fingerprint') == null) {
        card.setAttribute('data-mw-saved-fingerprint', pieceSizeFingerprint(card));
        card.setAttribute('data-mw-saved-calc-version', flag ? String(flag.value || '0') : '0');
      }
    }

    function markPieceEdited(input) {
      var card = input.closest('[data-mw-piece]');
      if (!card) return;
      var flag = card.querySelector('[data-mw-calc-version]');
      if (!flag) return;
      snapshotPieceSizes(card);
      var saved = card.getAttribute('data-mw-saved-fingerprint');
      if (saved !== '' && pieceSizeFingerprint(card) === saved) {
        flag.value = card.getAttribute('data-mw-saved-calc-version') || '0';
        return;
      }
      flag.value = latestCalcVersion();
    }

    function latestCalcVersion() {
      return String(cfg.latestCalcVersion || cfg.calcVersion || 4);
    }

    function collectClientCountErrors() {
      var errors = {};
      var countLabels = {
        qty: 'Quantity',
        door_count: 'Door count',
        shelf_count: 'Shelf count',
        drawer_count: 'Drawer count',
        division_count: 'Division count',
        columns: 'Columns',
        rows: 'Rows',
        rod_count: 'Rod count',
        openings: 'Openings',
        pull_qty: 'Pull quantity',
        hinge_qty: 'Hinge quantity'
      };
      var spare = document.getElementById('mw-spare');
      if (spare && (quoteUsesNewParsing() || document.querySelector('[data-mw-calc-version][value="3"]')) && Number(spare.value) > 50) {
        errors.spare_sheets = 'Spare sheets cannot be more than 50.';
      }
      form.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(function (card) {
        var probe = card.querySelector('.mw-input--qty, .mw-input--dim, [data-mw-calc-version]');
        if (!probe || !pieceUsesNewParsing(probe)) return;
        card.querySelectorAll('.mw-input--qty').forEach(function (input) {
          var key = inputErrorKey(input);
          if (!key) return;
          var n = Number(input.value);
          if (!isFinite(n)) return;
          var field = key.split('.').pop();
          var label = countLabels[field] || (/\.qty$/.test(key) ? 'Quantity' : 'Count');
          if (/\.qty$/.test(key) && n > 200) {
            errors[key] = label + ' cannot be more than 200.';
          } else if (!/\.qty$/.test(key) && n > 100) {
            errors[key] = label + ' cannot be more than 100.';
          }
        });
      });
      return errors;
    }

    function collectClientDimErrors() {
      var errors = collectClientCountErrors();
      if (!window.MillworkDimensionParser) return errors;
      form.querySelectorAll('.mw-input--dim').forEach(function (input) {
        if (!dimFieldIsActive(input)) return;
        var card = input.closest('[data-mw-piece]');
        var existing = card && card.getAttribute('data-mw-saved-fingerprint') !== '';
        if (!quoteUsesNewParsing() && existing && input.getAttribute('data-mw-initial-dim') === String(input.value)) {
          return;
        }
        var parsed = window.MillworkDimensionParser.inspect(input.value, dimFieldUnit(input));
        if (parsed.status === 'invalid') {
          errors[inputErrorKey(input)] = parsed.message || window.MillworkDimensionParser.INVALID_MESSAGE;
        }
      });
      return errors;
    }

    function applyFieldErrors(errors) {
      errors = errors || {};
      form.querySelectorAll('.mw-input--dim, .mw-input--qty, #mw-spare').forEach(function (input) {
        setDimInvalid(input, errors[inputErrorKey(input)] || '');
      });
    }

    function runPreview() {
      var seq = ++previewSeq;
      var clientErrors = collectClientDimErrors();
      applyFieldErrors(clientErrors);
      setCutPlanLoading(true);
      showPreviewMessage('Updating cut plan…');
      return fetchPreview().then(function (data) {
        if (seq !== previewSeq) return data;
        var serverErrors = (data && data.field_errors) || {};
        var merged = Object.assign({}, serverErrors, clientErrors);
        applyFieldErrors(merged);
        var skipped = !!(data && data.cut_plan && data.cut_plan.preview_skipped);
        var hasErrors = Object.keys(merged).length > 0;
        var keepLast = hasErrors && skipped && lastPreviewData && !(lastPreviewData.cut_plan && lastPreviewData.cut_plan.preview_skipped);
        if (!keepLast && data) renderPreview(data);
        else if (keepLast && data && data.cut_plan && data.cut_plan.preview_message) {
          showPreviewMessage(data.cut_plan.preview_message);
        }
        return data;
      }).catch(function () {
        if (seq === previewSeq) {
          showPreviewMessage('Preview timed out. Save to calculate, or simplify the quote.');
        }
        return { _previewTimedOut: true };
      }).then(function (data) {
        if (seq !== previewSeq) return data;
        setCutPlanLoading(false);
        if (data && data._previewTimedOut) {
          return null;
        }
        if (data && data.cut_plan && data.cut_plan.preview_message) {
          showPreviewMessage(data.cut_plan.preview_message);
        } else if (!(data && data.field_errors && Object.keys(data.field_errors).length)) {
          showPreviewMessage('');
        }
        return data;
      });
    }

    function renderPreview(data) {
      lastPreviewData = data;
      if (window.QuotationBuildingLaborUi) window.QuotationBuildingLaborUi.sync(data);
      var empty = document.getElementById('mw-live-empty');
      var body = document.getElementById('mw-live-body');
      var summary = data.summary || {};
      rebuildLaborTable(data.labor || []);
      rebuildMatTable((data.materials || []).concat(data.extra_materials || []).filter(function (r) {
        return !String(r.key || '').startsWith('extra_');
      }));
      renderCutPlan(data.cut_plan || {});
      renderUnplacedBanner(data.cut_plan || {}, data);
      var liveGrand = Number(summary.grand_total || 0) > 0
        ? Number(summary.grand_total) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount
        : 0;
      window.__quoteGrandTotal = liveGrand;
      document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: liveGrand } }));
      if (empty) empty.hidden = Number(summary.grand_total || 0) > 0;
      if (body) body.hidden = false;
      var delivery = Number(summary.delivery_amount || 0);
      var markup = Number(summary.markup_amount || 0);
      var laborPre = Number(summary.labor_pre_hst || 0) + lastAdditionalLaborPreTax;
      var goodsPre = summary.materials_goods_pre_hst != null
        ? Number(summary.materials_goods_pre_hst)
        : Math.max(0, Number(summary.materials_pre_hst || 0) - delivery);
      goodsPre += lastAdditionalMaterialsPreTax;
      var subtotal = Number(summary.subtotal_pre_hst || 0) + lastAdditionalLaborPreTax + lastAdditionalMaterialsPreTax;
      var hst = Number(summary.hst || 0) + lastAdditionalLaborHst + lastAdditionalMaterialsHst;
      var barMat = document.getElementById('mw-bar-mat');
      var barLabor = document.getElementById('mw-bar-labor');
      var barSub = document.getElementById('mw-bar-sub');
      var barHst = document.getElementById('mw-bar-hst');
      var barTotal = document.getElementById('mw-bar-total');
      var barDelivery = document.getElementById('mw-bar-delivery');
      var barMarkup = document.getElementById('mw-bar-markup');
      var wrapDelivery = document.getElementById('mw-bar-delivery-wrap');
      var wrapMarkup = document.getElementById('mw-bar-markup-wrap');
      if (barMat) barMat.textContent = fmt(goodsPre);
      if (barLabor) barLabor.textContent = fmt(laborPre);
      if (barDelivery) barDelivery.textContent = fmt(delivery);
      if (barMarkup) barMarkup.textContent = fmt(markup);
      if (wrapDelivery) wrapDelivery.hidden = clientProvidesAll() || delivery <= 0;
      if (wrapMarkup) wrapMarkup.hidden = markup <= 0;
      if (barSub) barSub.textContent = fmt(subtotal);
      if (barHst) barHst.textContent = fmt(hst);
      if (barTotal) barTotal.textContent = fmt(Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount);
      syncTotalsBarSpace();
      var html = '';
      html += '<div class="pq-row"><span>Material</span><span>' + fmt(goodsPre) + '</span></div>';
      html += '<div class="pq-row"><span>Labour</span><span>' + fmt(laborPre) + '</span></div>';
      if (delivery > 0 && !clientProvidesAll()) {
        html += '<div class="pq-row"><span>Delivery</span><span>' + fmt(delivery) + '</span></div>';
      }
      if (markup > 0) {
        html += '<div class="pq-row"><span>Markup</span><span>' + fmt(markup) + '</span></div>';
      }
      html += '<div class="pq-row pq-row--sub"><span>Subtotal</span><span>' + fmt(subtotal) + '</span></div>';
      html += '<div class="pq-row"><span>HST 13%</span><span>' + fmt(hst) + '</span></div>';
      if (lastCommuteAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Commute (incl. HST)</span><span>' + fmt(lastCommuteAmount) + '</span></div>';
      }
      if (lastDebrisAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>';
      }
      var edgeLf = Number(summary.edge_band_lf || 0);
      var ebMeta = document.getElementById('mw-eb-meta');
      if (ebMeta) {
        ebMeta.textContent = edgeLf > 0 ? ('Total edge banding: ' + edgeLf.toFixed(1) + ' LF required') : '';
      }
      var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
      html += '<div class="pq-row pq-row--hl"><span>Total</span><span>' + fmt(grand) + '</span></div>';
      body.innerHTML = html;
    }

    function seamTipHtml(original) {
      var text = 'Finished sizes still add up to ' + original + '.';
      if (window.AdminInfoTip && typeof window.AdminInfoTip.html === 'function') {
        return ' ' + window.AdminInfoTip.html(text, 'Finished size');
      }
      return ' <button type="button" class="admin-info-tip" aria-expanded="false" aria-label="Finished size">'
        + '<i class="fa fa-info-circle" aria-hidden="true"></i>'
        + '<span class="admin-info-tip__bubble" role="tooltip">' + escapeHtml(text) + '</span></button>';
    }

    function renderUnplacedBanner(plan, data) {
      var banner = document.getElementById('mw-unplaced-banner');
      var list = document.getElementById('mw-unplaced-list');
      var seamsRoot = document.getElementById('mw-cut-seams');
      var recalc = document.getElementById('mw-recalc-banner');
      var recalcText = document.getElementById('mw-recalc-text');
      var unplaced = plan.unplaced || [];
      var seams = plan.seams || [];
      var notice = (data && data.recalc_notice) || (plan && plan.recalc_notice) || null;
      if (banner) {
        banner.hidden = unplaced.length === 0;
      }
      if (list) {
        list.innerHTML = unplaced.map(function (row) {
          return '<li>' + escapeHtml(row.label || 'Part') + '</li>';
        }).join('');
      }
      if (recalc) {
        recalc.hidden = !notice;
      }
      if (recalcText && notice) {
        var wasLabel = notice.was_label || fmt(notice.was);
        var nowLabel = notice.now_label || fmt(notice.now);
        recalcText.textContent = 'was ' + wasLabel
          + ', now ' + nowLabel
          + '. Review the cut plan before you save.';
      }
      if (seamsRoot) {
        seamsRoot.hidden = seams.length === 0;
        seamsRoot.innerHTML = seams.map(function (seam) {
          var panels = (seam.panels || []).join(' + ');
          var note = seam.note || '';
          var original = seam.original || '';
          var tip = original ? seamTipHtml(original) : '';
          return '<p>' + escapeHtml(seam.label || 'Back') + ': ' + escapeHtml(note)
            + (panels ? ' (' + escapeHtml(panels) + ')' : '')
            + tip + '</p>';
        }).join('');
      }
    }

    function renderCutPlan(plan) {
      var root = document.getElementById('mw-cut-plan');
      var meta = document.getElementById('mw-cut-meta');
      var printBtn = document.getElementById('mw-print-cut-list');
      var layouts = plan.layouts || [];
      if (meta) {
        meta.textContent = cutPlanMetaText(plan);
      }
      if (printBtn) printBtn.disabled = !layouts.length;
      if (!root) return;
      if (!layouts.length) {
        root.innerHTML = '<p class="admin-form-help">Enter piece dimensions to generate a guillotine cut plan.</p>';
        return;
      }
      root.innerHTML = layouts.map(function (layout) {
        var stats = layout.stats || {};
        return '<div class="mw-cut-sheet">'
          + '<div class="mw-cut-sheet__stats"><strong>' + escapeHtml(stats.label || 'Sheet') + '</strong>'
          + '<span>Used ' + escapeHtml(stats.used_pct || 0) + '% · waste ' + escapeHtml(stats.waste_pct || 0) + '%</span></div>'
          + '<div class="mw-cut-svg-wrap">' + (layout.svg || '') + '</div>'
          + '</div>';
      }).join('') + combinedPartsTable(layouts);
    }

    function combinedPartsTable(layouts) {
      var groups = {};
      var order = [];
      (layouts || []).forEach(function (layout) {
        (layout.legend || []).forEach(function (row) {
          var part = String(row.label || 'Part');
          var size = String(row.size || '');
          var material = String(row.material || (layout.stats && layout.stats.label) || '');
          var edge = String(row.edge || 'None');
          var key = [part, size, material, edge].join('|');
          if (!groups[key]) {
            groups[key] = { part: part, size: size, qty: 0, material: material, edge: edge };
            order.push(key);
          }
          groups[key].qty += Math.max(1, Number(row.qty || 1));
        });
      });
      if (!order.length) return '';
      var body = order.map(function (key) {
        var row = groups[key];
        return '<tr><td>' + escapeHtml(row.part) + '</td><td>' + escapeHtml(row.size) + '</td><td>'
          + row.qty + '</td><td>' + escapeHtml(row.material) + '</td><td>' + escapeHtml(row.edge) + '</td></tr>';
      }).join('');
      return '<div class="mw-parts-list admin-table-scroll"><table class="admin-table">'
        + '<thead><tr><th>Part</th><th>Size</th><th>Qty</th><th>Material</th><th>Edge banding</th></tr></thead>'
        + '<tbody>' + body + '</tbody></table></div>';
    }

    function printCutList() {
      var plan = (lastPreviewData && lastPreviewData.cut_plan) || {};
      var layouts = plan.layouts || [];
      if (!layouts.length) return;
      var clientEl = document.getElementById('mw-client-name');
      var client = clientEl ? String(clientEl.value || '').trim() : '';
      var quote = String(cfg.quoteNumber || '').trim();
      var titleBits = [];
      if (quote) titleBits.push(quote);
      if (client) titleBits.push(client);
      var drawingsHtml = layouts.map(function (layout, i) {
        return '<section class="mw-cut-print-drawing" aria-label="Sheet ' + (i + 1) + ' drawing">'
          + '<div class="mw-cut-svg-wrap">' + (layout.svg || '') + '</div>'
          + '</section>';
      }).join('');
      var detailsHtml = layouts.map(function (layout, i) {
        var stats = layout.stats || {};
        var legend = (layout.legend || []).map(function (row) {
          return '<li><span class="mw-cut-legend__n">' + escapeHtml(row.n || '') + '</span>'
            + '<span>' + escapeHtml(row.label || 'Part') + ' · ' + escapeHtml(row.size || '')
            + (row.rotated ? ' · rotated' : '') + '</span></li>';
        }).join('');
        return '<article class="mw-cut-print-sheet-details">'
          + '<h2>Sheet ' + (i + 1) + ': ' + escapeHtml(stats.label || 'Sheet')
          + ' Used ' + escapeHtml(stats.used_pct || 0) + '% · waste ' + escapeHtml(stats.waste_pct || 0) + '%</h2>'
          + (legend ? '<ul class="mw-cut-legend">' + legend + '</ul>' : '')
          + '</article>';
      }).join('');
      var html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        + '<meta name="viewport" content="width=device-width, initial-scale=1">'
        + '<title>Cut list' + (quote ? ' — ' + escapeHtml(quote) : '') + '</title>'
        + (cfg.printCssUrl ? '<link rel="stylesheet" href="' + escapeHtml(cfg.printCssUrl) + '">' : '')
        + '</head><body>'
        + '<div class="toolbar no-print"><button type="button" onclick="window.print()">Print cut list</button></div>'
        + '<div class="mw-cut-print-drawings">' + drawingsHtml + '</div>'
        + '<section class="mw-cut-print-details"><h1>Details</h1>'
        + '<p class="meta">' + (titleBits.length ? '<strong>' + escapeHtml(titleBits.join(' · ')) + '</strong><br>' : '')
        + escapeHtml(cutPlanMetaText(plan)) + '</p>'
        + detailsHtml + '</section>'
        + '</body></html>';
      printHtmlDocument(html);
    }

    function printHtmlDocument(html) {
      var existing = document.getElementById('mw-cut-print-frame');
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      var iframe = document.createElement('iframe');
      iframe.id = 'mw-cut-print-frame';
      iframe.setAttribute('title', 'Cut list print');
      iframe.setAttribute('aria-hidden', 'true');
      iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
      document.body.appendChild(iframe);
      var win = iframe.contentWindow;
      var doc = win && win.document;
      if (!doc) return;
      doc.open();
      doc.write(html);
      doc.close();

      var printed = false;
      var finish = function () {
        if (printed) return;
        printed = true;
        try {
          win.focus();
          win.print();
        } catch (err) { /* ignore */ }
        window.setTimeout(function () {
          if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
        }, 1500);
      };

      var links = doc.querySelectorAll('link[rel="stylesheet"]');
      if (!links.length) {
        window.setTimeout(finish, 50);
        return;
      }
      var pending = links.length;
      var onSheet = function () {
        pending -= 1;
        if (pending <= 0) finish();
      };
      for (var i = 0; i < links.length; i++) {
        links[i].addEventListener('load', onSheet);
        links[i].addEventListener('error', onSheet);
      }
      window.setTimeout(finish, 1200);
    }

    function rebuildLaborTable(rows) {
      var tbody = document.getElementById('mw-labor-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Labour appears after pieces are sized.</td></tr>';
        return;
      }
      var laborRows = rows.filter(function (row) {
        return row && row.key !== 'markup';
      });
      if (!laborRows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Labour appears after pieces are sized.</td></tr>';
        return;
      }
      tbody.innerHTML = laborRows.map(function (row) {
        return '<tr><td>' + escapeHtml(row.label) + '</td><td>' + Number(row.hours || 0).toFixed(2) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td></tr>';
      }).join('');
    }

    function rebuildMatTable(rows) {
      var tbody = document.getElementById('mw-mat-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after size is entered.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(function (row) {
        var provided = row.client_provided ? ' <span class="admin-form-help"> · client provided</span>' : '';
        return '<tr><td>' + escapeHtml(row.label) + provided + '</td>'
          + '<td>' + Number(row.qty || 0).toFixed(2) + ' ' + escapeHtml(row.unit || '') + '</td>'
          + '<td>' + fmt(row.unit_price || 0) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td></tr>';
      }).join('');
    }

    function bindPreview(el) {
      if (!el || el.dataset.mwBound === '1') return;
      el.dataset.mwBound = '1';
      el.addEventListener('input', function () {
        if (el.classList.contains('mw-input--dim') || (el.classList && el.classList.contains('mw-dim__unit'))) {
          markPieceEdited(el);
        }
        if (el.classList.contains('mw-input--dim') || el.classList.contains('mw-input--qty') || el.id === 'mw-spare' || (el.classList && el.classList.contains('mw-dim__unit'))) {
          applyFieldErrors(collectClientDimErrors());
        }
        schedulePreview();
      });
      el.addEventListener('change', function () {
        if (el.classList.contains('mw-input--dim') || (el.classList && el.classList.contains('mw-dim__unit'))) {
          markPieceEdited(el);
        }
        if (el.classList.contains('mw-input--dim') || el.classList.contains('mw-input--qty') || el.id === 'mw-spare' || (el.classList && el.classList.contains('mw-dim__unit'))) {
          applyFieldErrors(collectClientDimErrors());
        }
        schedulePreview();
      });
    }
    form.querySelectorAll('.mw-preview').forEach(bindPreview);

    function pieceTypeOf(piece) {
      var typeEl = piece.querySelector('[data-mw-type-input]');
      if (!typeEl) return piece.getAttribute('data-mw-type') || 'shelf';
      if (typeEl.type === 'radio') {
        var checked = piece.querySelector('[data-mw-type-input]:checked');
        return checked ? checked.value : 'shelf';
      }
      return typeEl.value || 'shelf';
    }

    function syncFinishFields(piece) {
      var selects = piece.querySelectorAll('select[name$="[finish]"]');
      if (!selects.length) return;
      var current = '';
      selects.forEach(function (sel) {
        var hide = false;
        var node = sel.parentElement;
        while (node && node !== piece) {
          if (node.hasAttribute && node.hasAttribute('data-mw-show') && window.getComputedStyle(node).display === 'none') {
            hide = true;
            break;
          }
          node = node.parentElement;
        }
        if (!hide) current = sel.value;
      });
      if (!current) current = selects[0].value;
      selects.forEach(function (sel) {
        sel.disabled = false;
        sel.value = current;
      });
    }

    function syncPieceType(piece) {
      var type = pieceTypeOf(piece);
      piece.setAttribute('data-mw-type', type);
      var below = piece.querySelector('select[name$="[below]"]');
      piece.setAttribute('data-mw-below', (below && below.value === 'drawers') ? 'drawers' : 'divisions');
      syncUnitLabels(piece);
      syncFinishFields(piece);
      syncPieceFieldNames(piece);
      syncHardwareCatalog();
      syncMaterialsCatalog();
    }

    function setPieceFieldName(el, on) {
      if (!el) return;
      var stored = el.getAttribute('data-mw-name');
      if (!stored) return;
      if (on) el.setAttribute('name', stored);
      else el.removeAttribute('name');
    }

    function pieceHeightInput(piece) {
      var inputs = piece.querySelectorAll('input[name$="[height]"], input[data-mw-name$="[height]"]');
      for (var i = 0; i < inputs.length; i++) {
        var name = inputs[i].getAttribute('name') || inputs[i].getAttribute('data-mw-name') || '';
        if (/\[height\]$/.test(name) && name.indexOf('[sets]') === -1) return inputs[i];
      }
      return null;
    }

    function syncPieceFieldNames(piece) {
      var type = pieceTypeOf(piece);
      var height = pieceHeightInput(piece);
      var open = type === 'shelf' && !!(height && String(height.value || '').trim() !== '');
      piece.querySelectorAll('[data-mw-field]').forEach(function (el) {
        var field = el.getAttribute('data-mw-field');
        var owner = el.getAttribute('data-mw-owner');
        var on = false;
        if (field === 'columns') {
          on = open ? owner === 'shelf' : owner === 'pack';
        } else if (field === 'shelf_count') {
          if (type === 'closet') on = owner === 'closet';
          else if (open) on = owner === 'shelf';
          else on = owner === 'cabinet';
        }
        setPieceFieldName(el, on);
      });
    }

    function syncUnitLabels(piece) {
      var unitEl = piece.querySelector('[data-mw-unit]');
      var label = unitEl && unitEl.value === 'ft' ? 'ft' : 'in';
      piece.querySelectorAll('[data-mw-unit-label]').forEach(function (el) {
        el.textContent = label;
      });
    }

    function pieceNeedsCabinetHardware(piece) {
      var type = piece.getAttribute('data-mw-type') || 'shelf';
      if (type === 'cabinet' || type === 'cabinet_door' || type === 'vanity' || type === 'closet') return true;
      return type === 'bench' && piece.getAttribute('data-mw-below') === 'drawers';
    }

    function pieceNeedsClosetHardware(piece) {
      return (piece.getAttribute('data-mw-type') || '') === 'closet';
    }

    function syncHardwareCatalog() {
      var show = false;
      var showCloset = false;
      form.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(function (piece) {
        if (pieceNeedsCabinetHardware(piece)) show = true;
        if (pieceNeedsClosetHardware(piece)) showCloset = true;
      });
      form.querySelectorAll('[data-mw-hw="cabinet-family"]').forEach(function (el) {
        el.hidden = !show;
      });
      form.querySelectorAll('[data-mw-hw="closet-family"]').forEach(function (el) {
        el.hidden = !showCloset;
      });
    }

    function syncMaterialsCatalog() {
      var hasBack = false;
      var hasEdgeBanding = false;
      var hasPaint = false;
      var hasStain = false;

      form.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(function (piece) {
        var type = pieceTypeOf(piece);

        var backSupports = ['floating_shelf', 'kallax', 'cabinet', 'vanity', 'closet', 'bench', 'mantel'].indexOf(type) !== -1;
        var backCb = piece.querySelector('input[type="checkbox"][name$="[include_back]"]');
        if (backSupports && backCb && backCb.checked) {
          hasBack = true;
        }

        var ebCb = piece.querySelector('input[type="checkbox"][name$="[edge_banding]"]');
        if (ebCb && ebCb.checked) {
          hasEdgeBanding = true;
        }

        var finishSel = piece.querySelector('select[name$="[finish]"]');
        var finishVal = finishSel ? finishSel.value : 'none';
        if (finishVal === 'paint') {
          hasPaint = true;
        } else if (finishVal === 'stain' || finishVal === 'clear') {
          hasStain = true;
        }
      });

      form.querySelectorAll('[data-mw-mat="back"]').forEach(function (el) {
        el.hidden = !hasBack;
      });
      form.querySelectorAll('[data-mw-mat="edge-banding"]').forEach(function (el) {
        el.hidden = !hasEdgeBanding;
      });
      form.querySelectorAll('[data-mw-mat="paint"]').forEach(function (el) {
        el.hidden = !hasPaint;
      });
      form.querySelectorAll('[data-mw-mat="stain"]').forEach(function (el) {
        el.hidden = !hasStain;
      });
    }

    function reindexPieces() {
      form.querySelectorAll('[data-mw-piece]').forEach(function (piece, i) {
        var n = piece.querySelector('[data-mw-piece-n]');
        if (n) n.textContent = String(i + 1);
        if (window.AdminInfoTip && typeof window.AdminInfoTip.refreshRepeatLabels === 'function') {
          window.AdminInfoTip.refreshRepeatLabels(piece);
        }
        piece.querySelectorAll('[name]').forEach(function (input) {
          input.name = input.name.replace(/pieces\[\d+\]/, 'pieces[' + i + ']');
          if (input.id) input.id = input.id.replace(/pieces-\d+/, 'pieces-' + i);
        });
        piece.querySelectorAll('label[for]').forEach(function (lab) {
          var current = lab.getAttribute('for') || lab.htmlFor || '';
          if (!current) return;
          var next = current.replace(/pieces-\d+/, 'pieces-' + i);
          lab.setAttribute('for', next);
          lab.htmlFor = next;
        });
      });
    }

    var CABINET_PRESETS = {
      base: { height: '34.5', depth: '24', toe: true },
      upper: { height: '30', depth: '12', toe: false },
      tall: { height: '84', depth: '24', toe: true }
    };

    function fillIfEmpty(el, value) {
      if (!el) return;
      if (String(el.value || '').trim() === '') el.value = value;
    }

    function applyTypeDefaults(piece, type) {
      if (type === 'cabinet') {
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '34.5');
        fillIfEmpty(piece.querySelector('input[name$="[depth]"]'), '24');
      } else if (type === 'vanity') {
        fillIfEmpty(piece.querySelector('input[name$="[width]"]'), '36');
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '34.5');
        fillIfEmpty(piece.querySelector('input[name$="[depth]"]'), '21');
      } else if (type === 'closet') {
        fillIfEmpty(piece.querySelector('input[name$="[width]"]'), '72');
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '84');
        fillIfEmpty(piece.querySelector('input[name$="[depth]"]'), '14');
      } else if (type === 'mantel') {
        fillIfEmpty(piece.querySelector('input[name$="[length]"]'), '60');
        fillIfEmpty(piece.querySelector('input[name$="[depth]"]'), '10');
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '8');
      } else if (type === 'wainscoting') {
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '36');
      } else if (type === 'bench') {
        fillIfEmpty(piece.querySelector('input[name$="[height]"]'), '18');
        fillIfEmpty(piece.querySelector('input[name$="[depth]"]'), '16');
      }
    }

    function applyCabinetStyle(piece, style) {
      var preset = CABINET_PRESETS[style];
      if (!preset) return;
      var h = piece.querySelector('input[name$="[height]"]');
      var d = piece.querySelector('input[name$="[depth]"]');
      var toe = piece.querySelector('input[type="checkbox"][name$="[include_toe_kick]"]');
      if (h) h.value = preset.height;
      if (d) d.value = preset.depth;
      if (toe) toe.checked = !!preset.toe;
    }

    function initPiece(piece) {
      if (!piece) return;
      piece.querySelectorAll('.mw-preview').forEach(bindPreview);
      piece.querySelectorAll('[data-mw-type-input]').forEach(function (inp) {
        inp.addEventListener('change', function () {
          applyTypeDefaults(piece, pieceTypeOf(piece));
          syncPieceType(piece);
          schedulePreview();
        });
      });
      var shelfHeight = piece.querySelector('input[name$="[height]"]');
      if (shelfHeight) {
        shelfHeight.addEventListener('input', function () { syncPieceFieldNames(piece); });
      }
      var below = piece.querySelector('select[name$="[below]"]');
      if (below) {
        below.addEventListener('change', function () {
          syncPieceType(piece);
          schedulePreview();
        });
      }
      var unitEl = piece.querySelector('[data-mw-unit]');
      if (unitEl) {
        unitEl.addEventListener('change', function () { syncUnitLabels(piece); });
      }
      var styleEl = piece.querySelector('[data-mw-cabinet-style]');
      if (styleEl) {
        styleEl.addEventListener('change', function () {
          applyCabinetStyle(piece, styleEl.value);
          schedulePreview();
        });
      }
      syncPieceType(piece);
      var remove = piece.querySelector('[data-mw-remove-piece]');
      if (remove) {
        remove.addEventListener('click', function () {
          var list = document.getElementById('mw-piece-list');
          if (list && list.querySelectorAll('[data-mw-piece]').length <= 1) return;
          piece.remove();
          reindexPieces();
          syncHardwareCatalog();
          schedulePreview();
        });
      }
      var dup = piece.querySelector('[data-mw-duplicate-piece]');
      if (dup) {
        dup.addEventListener('click', function () {
          var list = document.getElementById('mw-piece-list');
          if (!list) return;
          var clone = piece.cloneNode(true);
          clone.querySelectorAll('[data-mw-bound]').forEach(function (el) {
            delete el.dataset.mwBound;
          });
          clone.querySelectorAll('.mw-preview').forEach(function (el) {
            delete el.dataset.mwBound;
          });
          clone.removeAttribute('data-mw-saved-fingerprint');
          clone.removeAttribute('data-mw-saved-calc-version');
          list.insertBefore(clone, piece.nextSibling);
          reindexPieces();
          var refreshClone = window.AdminInfoTip && (window.AdminInfoTip.refreshControlIds || window.AdminInfoTip.refreshIds);
          if (refreshClone) refreshClone(clone);
          initPiece(clone);
          snapshotPieceSizes(clone, { asNew: true });
          schedulePreview();
        });
      }
      var addSet = piece.querySelector('[data-mw-add-set]');
      if (addSet) {
        addSet.addEventListener('click', function () {
          var list = piece.querySelector('[data-mw-set-list]');
          var tpl = document.getElementById('mw-set-template');
          if (!list || !tpl) return;
          var pIndex = Array.prototype.indexOf.call(document.querySelectorAll('#mw-piece-list [data-mw-piece]'), piece);
          var sIndex = list.querySelectorAll('[data-mw-set-row]').length;
          var wrap = document.createElement('div');
          wrap.innerHTML = tpl.innerHTML.replace(/__P__/g, String(pIndex)).replace(/__S__/g, String(sIndex)).trim();
          var row = wrap.firstElementChild;
          list.appendChild(row);
          var refreshSet = window.AdminInfoTip && (window.AdminInfoTip.refreshControlIds || window.AdminInfoTip.refreshIds);
          if (refreshSet) refreshSet(row);
          row.querySelectorAll('.mw-preview').forEach(bindPreview);
          bindSetRemove(row);
          schedulePreview();
        });
      }
      piece.querySelectorAll('[data-mw-set-row]').forEach(bindSetRemove);
      snapshotPieceSizes(piece);
    }

    function bindSetRemove(row) {
      var btn = row.querySelector('[data-mw-remove-set]');
      if (!btn) return;
      btn.addEventListener('click', function () {
        var list = row.closest('[data-mw-set-list]');
        if (list && list.querySelectorAll('[data-mw-set-row]').length <= 1) return;
        row.remove();
        schedulePreview();
      });
    }

    document.querySelectorAll('#mw-piece-list [data-mw-piece]').forEach(initPiece);
    syncHardwareCatalog();
    syncMaterialsCatalog();
    var addPiece = document.getElementById('mw-add-piece');
    var pieceTpl = document.getElementById('mw-piece-template');
    var pieceList = document.getElementById('mw-piece-list');
    if (addPiece && pieceTpl && pieceList) {
      addPiece.addEventListener('click', function () {
        var i = pieceList.querySelectorAll('[data-mw-piece]').length;
        var wrap = document.createElement('div');
        wrap.innerHTML = pieceTpl.innerHTML.replace(/__P__/g, String(i)).trim();
        var piece = wrap.firstElementChild;
        pieceList.appendChild(piece);
        reindexPieces();
        var refreshPiece = window.AdminInfoTip && (window.AdminInfoTip.refreshControlIds || window.AdminInfoTip.refreshIds);
        if (refreshPiece) refreshPiece(piece);
        initPiece(piece);
        snapshotPieceSizes(piece, { asNew: true });
        if (window.cpMoneyMask) window.cpMoneyMask.wire(piece.querySelectorAll('.cp-money-input'));
        schedulePreview();
      });
    }

    var extraList = document.getElementById('mw-extra-list');
    var extraTpl = document.getElementById('mw-extra-template');
    var extraAdd = document.getElementById('mw-extra-add');

    function setExtraSource(row, linked) {
      if (!row) return;
      row.classList.toggle('is-linked', !!linked);
      row.classList.toggle('is-manual', !linked);
      var badge = row.querySelector('[data-mw-extra-source]');
      if (!badge) return;
      badge.className = 'br-config-source ' + (linked ? 'br-config-source--linked' : 'br-config-source--manual');
      badge.innerHTML = linked
        ? '<i class="fa fa-link" aria-hidden="true"></i> Catalog'
        : '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
    }

    function initExtraRow(row) {
      if (!row) return;
      row.querySelectorAll('.mw-preview').forEach(bindPreview);
      if (window.cpMoneyMask) window.cpMoneyMask.wire(row.querySelectorAll('.cp-money-input'));
      var root = row.querySelector('[data-admin-search-select]');
      if (root && window.AdminSearchSelect) AdminSearchSelect.init(root);
      var remove = row.querySelector('.br-extra-remove');
      if (remove) {
        remove.addEventListener('click', function () {
          if (root && window.AdminSearchSelect) AdminSearchSelect.destroy(root);
          row.remove();
          schedulePreview();
        });
      }
    }

    if (extraAdd && extraTpl && extraList) {
      extraAdd.addEventListener('click', function () {
        var i = extraList.querySelectorAll('[data-mw-extra-row]').length;
        var wrap = document.createElement('div');
        wrap.innerHTML = extraTpl.innerHTML.replace(/__I__/g, String(i)).trim();
        var row = wrap.firstElementChild;
        extraList.appendChild(row);
        var refreshExtra = window.AdminInfoTip && (window.AdminInfoTip.refreshControlIds || window.AdminInfoTip.refreshIds);
        if (refreshExtra) refreshExtra(row);
        initExtraRow(row);
        schedulePreview();
      });
    }
    if (extraList) extraList.querySelectorAll('[data-mw-extra-row]').forEach(initExtraRow);

    document.addEventListener('admin-search-select:change', function (e) {
      var row = e.target && e.target.closest ? e.target.closest('[data-mw-extra-row]') : null;
      if (!row) return;
      var opt = (e.detail && e.detail.option) || null;
      var deliveryEl = row.querySelector('[data-mw-extra-delivery]');
      var nameEl = row.querySelector('[data-mw-extra-name]');
      var priceEl = row.querySelector('[data-mw-extra-price]');
      if (!opt) {
        if (deliveryEl) deliveryEl.value = '0.00';
        setExtraSource(row, false);
        schedulePreview();
        return;
      }
      if (nameEl) nameEl.value = opt.name || '';
      if (priceEl) priceEl.value = opt.price || '';
      if (deliveryEl) deliveryEl.value = opt.delivery || '0.00';
      if (window.cpMoneyMask && priceEl) window.cpMoneyMask.wire(priceEl);
      setExtraSource(row, true);
      schedulePreview();
    });

    document.addEventListener('debris-removal:change', function (e) {
      var d = e.detail || {};
      lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
      if (lastPreviewData) renderPreview(lastPreviewData);
    });
    document.addEventListener('additional-costs:change', function (e) {
      var d = e.detail || {};
      lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
      lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
      lastAdditionalLaborPreTax = Number(d.laborPreTax || 0);
      lastAdditionalLaborHst = Number(d.laborHst || 0);
      lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
      if (lastPreviewData) renderPreview(lastPreviewData);
    });
    document.addEventListener('commute-expense:change', function (e) {
      var d = e.detail || {};
      lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
      if (lastPreviewData) renderPreview(lastPreviewData);
    });

    form.querySelectorAll('[data-mw-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var href = btn.getAttribute('data-mw-nav');
        if (href) window.location.href = href;
      });
    });

    var printBtn = document.getElementById('mw-print-cut-list');
    if (printBtn) {
      printBtn.addEventListener('click', function () { printCutList(); });
    }

    var totalsBar = document.getElementById('mw-totals-bar');
    var totalsExpand = document.getElementById('mw-bar-expand');
    var totalsBarMobile = function () {
      return window.matchMedia('(max-width: 700px)').matches;
    };
    var syncTotalsBarSpace = function () {
      if (!form || !totalsBar) return;
      var h = Math.ceil(totalsBar.getBoundingClientRect().height) || 44;
      form.style.setProperty('--mw-bar-space', (h + 12) + 'px');
    };
    var setTotalsBarOpen = function (open) {
      if (!totalsBar) return;
      totalsBar.classList.toggle('is-open', !!open);
      if (totalsExpand) {
        totalsExpand.setAttribute('aria-expanded', open ? 'true' : 'false');
        totalsExpand.setAttribute('aria-label', open ? 'Hide totals details' : 'Show totals details');
      }
      window.requestAnimationFrame(syncTotalsBarSpace);
    };
    if (totalsExpand) {
      totalsExpand.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        if (!totalsBarMobile()) return;
        setTotalsBarOpen(!totalsBar.classList.contains('is-open'));
      });
    }
    if (totalsBar) {
      totalsBar.addEventListener('click', function (e) {
        if (!totalsBarMobile()) return;
        if (e.target && e.target.closest && e.target.closest('#mw-bar-expand, [data-qh-footer-save]')) return;
        setTotalsBarOpen(!totalsBar.classList.contains('is-open'));
      });
    }
    window.addEventListener('resize', function () {
      if (!totalsBarMobile()) setTotalsBarOpen(false);
      syncTotalsBarSpace();
    });
    syncTotalsBarSpace();

    var upgradeBtn = document.getElementById('mw-upgrade-cut-plan-btn');
    var undoBtn = document.getElementById('mw-undo-cut-plan-btn');
    var undoBannerBtn = document.getElementById('mw-undo-cut-plan-banner-btn');
    var legacyVersion = String(cfg.calcVersion || cfg.cutPlanVersion || 1);

    function setUpgradeFlags(on) {
      var flag = document.getElementById('mw-upgrade-cut-plan');
      var ver = document.getElementById('mw-cut-plan-version');
      if (flag) flag.value = on ? '1' : '0';
      if (ver) ver.value = on ? latestCalcVersion() : legacyVersion;
    }

    function setUpgradeUi(on) {
      if (upgradeBtn) upgradeBtn.hidden = !!on;
      if (undoBtn) undoBtn.hidden = !on;
      if (undoBannerBtn) undoBannerBtn.hidden = !on;
    }

    function confirmRecalc(message) {
      if (window.AdminDialog && typeof window.AdminDialog.confirm === 'function') {
        return window.AdminDialog.confirm(message, {
          title: 'Recalculate with new cut plan',
          confirmLabel: 'Recalculate',
          cancelLabel: 'Cancel'
        });
      }
      return Promise.resolve(window.confirm(message));
    }

    function revertToLegacy() {
      setUpgradeFlags(false);
      setUpgradeUi(false);
      runPreview();
    }

    if (upgradeBtn) {
      upgradeBtn.addEventListener('click', function () {
        setUpgradeFlags(true);
        fetchPreview().then(function (data) {
          var notice = data && data.recalc_notice;
          var wasLabel = notice && notice.was_label ? String(notice.was_label) : '';
          var nowLabel = notice && notice.now_label ? String(notice.now_label) : '';
          var msg = (wasLabel && nowLabel)
            ? ('Was ' + wasLabel + ', now ' + nowLabel + '. Recalculate this quote with the new cut plan? You can undo before saving.')
            : 'Recalculate this quote with the new cut plan? You can undo before saving.';
          return confirmRecalc(msg).then(function (ok) {
            if (ok) {
              if (data) renderPreview(data);
              setUpgradeUi(true);
            } else {
              setUpgradeFlags(false);
            }
          });
        }).catch(function () {
          setUpgradeFlags(false);
        });
      });
    }
    if (undoBtn) {
      undoBtn.addEventListener('click', revertToLegacy);
    }
    if (undoBannerBtn) {
      undoBannerBtn.addEventListener('click', revertToLegacy);
    }

    var clientAll = document.getElementById('mw-client-all');
    if (clientAll) {
      clientAll.addEventListener('change', function () {
        syncClientProvidesAll();
        schedulePreview();
      });
    }
    syncClientProvidesAll();
    if (cfg.initialPreview) renderPreview(cfg.initialPreview);
    schedulePreview();
  }
})();

(function () {
  var root = document.getElementById('mw-config-form');
  if (!root) return;
  var tabs = root.querySelectorAll('.cp-tab');
  var panels = root.querySelectorAll('.cp-tab-panel');
  var tabInput = document.getElementById('mw-config-tab');

  function show(key) {
    if (!key || !root.querySelector('.cp-tab[data-tab="' + key + '"]')) return;
    tabs.forEach(function (t) {
      var on = t.getAttribute('data-tab') === key;
      t.classList.toggle('is-active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      p.classList.toggle('is-active', p.getAttribute('data-panel') === key);
    });
    if (tabInput) tabInput.value = key;
    try {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', key);
      if (window.history && window.history.replaceState) window.history.replaceState(null, '', url.toString());
    } catch (e) {}
  }

  tabs.forEach(function (t) {
    t.addEventListener('click', function () { show(t.getAttribute('data-tab')); });
  });

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-mw-catalog-row]') : null;
    if (!row) return;
    var opt = (e.detail && e.detail.option) || null;
    var nameEl = row.querySelector('[data-mw-cat-name]');
    var priceEl = row.querySelector('[data-mw-cat-price]');
    var covEl = row.querySelector('[data-mw-cat-coverage]');
    if (!opt) return;
    if (nameEl && !nameEl.value) nameEl.value = opt.name || '';
    if (priceEl) priceEl.value = opt.price || '';
    if (window.cpMoneyMask && priceEl) window.cpMoneyMask.wire(priceEl);
    if (covEl) {
      var text = (opt.name || '') + ' ' + (opt.store_name || '') + ' ' + (opt.description || '');
      var match = text.match(/(\d+(?:\.\d+)?)\s*(?:feet|foot|ft|lf)\b/i);
      if (match && match[1]) {
        covEl.value = match[1];
      } else if (!covEl.value || Number(covEl.value) <= 0) {
        covEl.value = '50';
      }
    }
    row.classList.add('is-linked');
    row.classList.remove('is-manual');
  });

  root.querySelectorAll('[data-mw-add-catalog]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var slot = btn.getAttribute('data-mw-add-catalog');
      var body = root.querySelector('[data-mw-catalog-body="' + slot + '"]');
      var tpl = root.querySelector('[data-mw-catalog-template="' + slot + '"]');
      if (!body || !tpl) return;
      var i = body.querySelectorAll('[data-mw-catalog-row]').length;
      var html = tpl.innerHTML.replace(/__R__/g, String(i));
      var wrap = document.createElement('tbody');
      wrap.innerHTML = html;
      var row = wrap.querySelector('[data-mw-catalog-row]');
      if (!row) return;
      body.appendChild(row);
      var ss = row.querySelector('[data-admin-search-select]');
      if (ss && window.AdminSearchSelect) AdminSearchSelect.init(ss);
      if (window.cpMoneyMask) window.cpMoneyMask.wire(row.querySelectorAll('.cp-money-input'));
    });
  });

  root.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-mw-remove-catalog]') : null;
    if (!btn) return;
    var row = btn.closest('[data-mw-catalog-row]');
    if (row) row.remove();
  });
})();
