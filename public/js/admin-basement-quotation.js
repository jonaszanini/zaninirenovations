(function () {
  var cfg = window.BR_QUOTATION || {};
  var form = document.getElementById('br-main-form');
  if (!form) return;

  var previewUrl = cfg.previewUrl || '';
  var isEdit = !!cfg.isEdit;
  var allSectionKeys = (cfg.sectionKeys || []).slice();
  var sectionKeys = allSectionKeys.slice();
  var wizardKeys = sectionKeys.concat(['review']);
  var presetSections = cfg.presetSections || {};
  var FLOOD_FALLBACK_SECTIONS = ['overview', 'insulation', 'drywall', 'flooring', 'doors_trim', 'painting', 'finishing'];
  var applyingFloodPrefill = false;
  var applyingLayout = false;
  var tokenEl = document.querySelector('meta[name="csrf-token"]');
  var token = tokenEl ? tokenEl.getAttribute('content') : '';
  var debounceTimer = null;
  var lastDebrisAmount = 0;
  var lastCommuteAmount = 0;
  var lastAdditionalMaterialsPreTax = 0;
  var lastAdditionalMaterialsHst = 0;
  var lastAdditionalLaborPreTax = 0;
  var lastAdditionalLaborHst = 0;
  var lastAdditionalTaxIn = 0;
  var lastPreviewData = null;
  var initialExpenseTotals = cfg.initialExpenseTotals || {};
  lastCommuteAmount = Number(initialExpenseTotals.commute || 0);
  lastDebrisAmount = Number(initialExpenseTotals.debris || 0);
  lastAdditionalTaxIn = Number(initialExpenseTotals.additionalTaxIn || 0);
  var currentIndex = 0;
  var isAllSections = form.classList.contains('br-layout--all-sections');

  var fmt = function (n) {
    return 'CA$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  };
  var fmtCompact = function (n) {
    return fmt(n);
  };
  var formatLayoutQty = function (n) {
    var rounded = Math.round(Number(n) * 100) / 100;
    if (!isFinite(rounded)) return '0';
    return String(rounded);
  };
  var escapeHtml = function (s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  };

  var draftBtn = document.getElementById('br-btn-draft');
  var publishBtn = document.getElementById('br-btn-publish');

  function findStepKeyForElement(el) {
    var step = el && el.closest ? el.closest('[data-br-step]') : null;
    return step ? step.getAttribute('data-br-step') : null;
  }

  /**
   * Create-mode wizard hides inactive steps with display:none. Required fields in
   * Review (payment schedule, debris) can still block submit with no visible
   * browser message. Reveal → validate → jump to the failing step when needed.
   */
  function submitWizard(intent) {
    var intentEl = document.getElementById('br-save-intent');
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
    syncDemoChoiceRequired(intent === 'publish');
    if (intent === 'publish') {
      validateDemoTearoutForPublish();
    } else {
      clearDemoTearoutValidity();
    }

    if (!form.checkValidity()) {
      var invalid = form.querySelector(':invalid');
      if (!isEdit) {
        var key = findStepKeyForElement(invalid);
        var idx = key ? wizardKeys.indexOf(key) : 0;
        if (idx < 0) idx = wizardKeys.indexOf('review');
        if (idx < 0) idx = 0;
        setWizardStep(idx, true);
      }
      if (window.AdminQuoteDraftValidation && typeof window.AdminQuoteDraftValidation.reportInvalid === 'function') {
        window.AdminQuoteDraftValidation.reportInvalid(form);
      } else if (invalid && typeof invalid.reportValidity === 'function') {
        invalid.reportValidity();
      }
      return;
    }

    HTMLFormElement.prototype.submit.call(form);
  }

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
    var intentEl = document.getElementById('br-save-intent');
    submitWizard(intentEl && intentEl.value ? intentEl.value : 'publish');
  });

  document.querySelectorAll('[data-br-reveal]').forEach(function (cb) {
    var target = document.getElementById(cb.getAttribute('data-br-reveal'));
    if (!target) return;
    var sync = function () {
      target.hidden = !cb.checked;
      schedulePreview();
    };
    cb.addEventListener('change', sync);
    target.hidden = !cb.checked;
  });

  function demolitionSectionEnabled() {
    return enabledTradeKeys().indexOf('demolition') !== -1;
  }

  function syncDemoChoiceRequired(forPublish) {
    var publishing = forPublish === true;
    var need = publishing && demolitionSectionEnabled();
    form.querySelectorAll('[data-br-demo-choice]').forEach(function (el) {
      el.required = need;
    });
  }

  function demoQtyValue(name, fallback) {
    var el = form.querySelector('[name="' + name + '"]');
    var raw = el ? String(el.value || '').trim() : '';
    if (raw === '') return fallback;
    var n = parseFloat(raw.replace(/,/g, ''));
    return isFinite(n) ? Math.max(0, n) : 0;
  }

  function demoLineChecked(name) {
    var el = form.querySelector('[name="' + name + '"]');
    return !!(el && el.checked);
  }

  function clearDemoTearoutValidity() {
    ['demo_flooring_sf', 'demo_studs_lf', 'demo_drywall_sf', 'demo_baseboard_lf'].forEach(function (name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (el && typeof el.setCustomValidity === 'function') el.setCustomValidity('');
    });
  }

  function validateDemoTearoutForPublish() {
    clearDemoTearoutValidity();
    var yes = form.querySelector('[data-br-demo-choice="1"]');
    if (!demolitionSectionEnabled() || !yes || !yes.checked) return;
    var mode = form.getAttribute('data-br-mode');
    var isDraftQuote = form.getAttribute('data-quotation-is-draft') === '1';
    if (mode === 'edit' && !isDraftQuote) return;
    var areaEl = form.querySelector('[name="area_sqft"]');
    var area = parseFloat(areaEl && areaEl.value ? areaEl.value : '0') || 0;
    var partitionEl = form.querySelector('[name="partition_lf"]');
    var partition = parseFloat(partitionEl && partitionEl.value ? partitionEl.value : '0') || 0;
    var periEl = form.querySelector('[name="perimeter_lf"]');
    var peri = parseFloat(periEl && periEl.value ? periEl.value : '0') || 0;
    var squarePeri = area > 0 ? 4 * Math.sqrt(area) : 0;
    var perimeter = peri > 0 ? peri : squarePeri;
    var wallPreset = !!(form.querySelector('[name="scope_preset"]:checked') && form.querySelector('[name="scope_preset"]:checked').value === 'wall');
    var lines = [
      ['demo_include_flooring', 'demo_flooring_sf', area, 'flooring'],
      ['demo_include_studs', 'demo_studs_lf', partition, 'studs'],
      ['demo_include_drywall', 'demo_drywall_sf', 0, 'drywall'],
      ['demo_include_baseboard', 'demo_baseboard_lf', wallPreset ? partition * 2 : perimeter, 'baseboard']
    ];
    lines.forEach(function (row) {
      if (!demoLineChecked(row[0])) return;
      if (demoQtyValue(row[1], row[2]) > 0) return;
      var el = form.querySelector('[name="' + row[1] + '"]');
      if (el && typeof el.setCustomValidity === 'function') {
        el.setCustomValidity('Enter a positive ' + row[3] + ' tear-out quantity, or uncheck ' + row[3] + '.');
      }
    });
  }

  function syncDemoChoiceFields() {
    var yes = form.querySelector('[data-br-demo-choice="1"]');
    var fields = document.getElementById('br-demo-fields');
    if (fields) {
      fields.hidden = !(yes && yes.checked);
    }
    syncDemoChoiceRequired(false);
  }

  form.querySelectorAll('[data-br-demo-choice]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      form.querySelectorAll('.q-commute-choice__option').forEach(function (opt) {
        if (opt.querySelector('[data-br-demo-choice]')) {
          opt.classList.toggle('is-selected', !!(opt.querySelector('[data-br-demo-choice]:checked')));
        }
      });
      syncDemoChoiceFields();
      schedulePreview();
    });
  });
  syncDemoChoiceFields();

  var lightingType = document.getElementById('br-lighting-type');
  var lightingLabel = document.getElementById('br-lights-label');
  var lightingFixtures = document.getElementById('br-lighting-fixture-qty');
  var spotHint = document.getElementById('br-spot-hint');
  function syncLightingType() {
    var type = lightingType ? lightingType.value : 'spot';
    if (lightingLabel) {
      lightingLabel.textContent = type === 'fixture' ? 'Fixtures' : 'Pot lights';
    }
    if (lightingFixtures) {
      lightingFixtures.hidden = type !== 'both';
    }
    if (spotHint) {
      spotHint.hidden = type === 'fixture';
    }
  }
  if (lightingType) {
    lightingType.addEventListener('change', function () {
      syncLightingType();
      schedulePreview();
    });
    syncLightingType();
  }

  form.querySelectorAll('[data-br-bath-light]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      if (cb.checked) {
        var wrap = document.getElementById(cb.getAttribute('data-br-reveal'));
        var qty = wrap ? wrap.querySelector('input[type="number"]') : null;
        if (qty && Number(qty.value) <= 0) {
          qty.value = '1';
        }
      }
    });
  });

  var clientAll = document.getElementById('br-client-all');
  var provideWrap = document.getElementById('br-provide-wrap');
  function syncClientAll() {
    if (!provideWrap) return;
    var on = !!(clientAll && clientAll.checked);
    provideWrap.hidden = on;
    provideWrap.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
      el.disabled = on;
    });
    syncInlineClientProvides();
  }
  if (clientAll) {
    clientAll.addEventListener('change', function () {
      syncClientAll();
      schedulePreview();
    });
    syncClientAll();
  }

  function sectionEl(key) {
    return form.querySelector('[data-br-step="' + key + '"]');
  }

  function currentPreset() {
    var el = form.querySelector('[data-br-scope-preset]:checked');
    return el ? el.value : 'full';
  }

  function enabledTradeKeys() {
    var keys = ['overview'];
    form.querySelectorAll('[data-br-enabled-section]').forEach(function (cb) {
      if (cb.checked && keys.indexOf(cb.value) === -1) {
        keys.push(cb.value);
      }
    });
    return keys;
  }

  function rebuildWizardKeys() {
    var enabled = enabledTradeKeys();
    var nest = currentPreset() === 'bathroom';
    var next = [];
    allSectionKeys.forEach(function (k) {
      if (enabled.indexOf(k) === -1) return;
      if (nest && (k === 'electrical' || k === 'plumbing' || k === 'framing')) return;
      next.push(k);
    });
    sectionKeys = next;
    wizardKeys = next.concat(['client_scope', 'review']);
  }

  function setNamedCheck(name, on) {
    form.querySelectorAll('input[name="' + name + '"][type="checkbox"]').forEach(function (cb) {
      cb.checked = !!on;
    });
  }

  function setNamedValue(name, value) {
    var el = form.querySelector('[name="' + name + '"]');
    if (el && el.type !== 'checkbox' && el.type !== 'hidden') {
      el.value = value;
    } else if (el && el.tagName === 'SELECT') {
      el.value = value;
    }
  }

  function syncRevealTargets() {
    form.querySelectorAll('[data-br-reveal]').forEach(function (cb) {
      var target = document.getElementById(cb.getAttribute('data-br-reveal'));
      if (target) target.hidden = !cb.checked;
    });
  }

  function formatAreaSqft(n) {
    var rounded = Math.round(Number(n) * 100) / 100;
    if (!isFinite(rounded) || rounded <= 0) return '';
    return String(rounded);
  }

  function lwMode(root) {
    var el = root.querySelector('[data-br-lw-mode]:checked');
    return el ? el.value : 'sqft';
  }

  function syncLw(root) {
    var dims = lwMode(root) === 'dimensions';
    var sqft = root.querySelector('[data-br-lw-sqft]');
    var dimWrap = root.querySelector('[data-br-lw-dims]');
    var out = root.querySelector('[data-br-lw-computed]');
    if (sqft) sqft.hidden = dims;
    if (dimWrap) dimWrap.hidden = !dims;
    if (out) out.hidden = !dims;
    if (!dims) return;
    var qty = root.querySelector('[data-br-lw-qty]');
    var lenEl = root.querySelector('[data-br-lw-length]');
    var widEl = root.querySelector('[data-br-lw-width]');
    var len = lenEl ? parseFloat(lenEl.value) : 0;
    var wid = widEl ? parseFloat(widEl.value) : 0;
    var zero = root.getAttribute('data-br-lw-zero') === '1';
    if (len > 0 && wid > 0) {
      var area = formatAreaSqft(len * wid);
      if (qty) qty.value = area;
      if (out) out.textContent = area + ' sq ft';
    } else {
      if (zero && qty) qty.value = '0';
      if (out) out.textContent = 'Enter length and width';
    }
    if (qty && qty.id === 'br-ceil-sf') {
      syncUnifiedCeilingArea();
    }
    if (qty && qty.id === 'br-dw-wall' && currentPreset() === 'flood' && !applyingFloodPrefill && !applyingLayout) {
      qty.dataset.brFloodManual = (String(qty.value || '').trim() !== '') ? '1' : '';
      qty.dataset.brFloodAuto = '';
    }
    if (qty && qty.id === 'br-area' && currentPreset() === 'flood' && !applyingFloodPrefill && !applyingLayout) {
      syncFloodDrywallPrefill(true);
    }
  }

  function bindLw() {
    form.querySelectorAll('[data-br-lw]').forEach(function (root) {
      if (root.getAttribute('data-br-lw-bound') === '1') return;
      root.setAttribute('data-br-lw-bound', '1');
      root.querySelectorAll('[data-br-lw-mode]').forEach(function (el) {
        el.addEventListener('change', function () { syncLw(root); });
      });
      var lenEl = root.querySelector('[data-br-lw-length]');
      var widEl = root.querySelector('[data-br-lw-width]');
      if (lenEl) lenEl.addEventListener('input', function () { syncLw(root); });
      if (widEl) widEl.addEventListener('input', function () { syncLw(root); });
      syncLw(root);
    });
  }

  function ceilingHeightFt() {
    var el = document.getElementById('br-height');
    var n = el ? parseFloat(el.value) : NaN;
    return (isFinite(n) && n > 0) ? n : 8;
  }

  function setLwToSqft(qtyId, value) {
    var qty = document.getElementById(qtyId);
    if (!qty) return;
    var root = qty.closest('[data-br-lw]');
    if (root) {
      var radio = root.querySelector('[data-br-lw-mode][value="sqft"]');
      if (radio && !radio.checked) {
        radio.checked = true;
        syncLw(root);
      }
    }
    qty.value = (value === '' || value == null) ? '' : formatLayoutQty(value);
  }

  function clearLayoutBadges() {
    form.querySelectorAll('[data-br-layout-badge]').forEach(function (badge) {
      badge.remove();
    });
    form.querySelectorAll('[data-br-layout-synced]').forEach(function (el) {
      el.removeAttribute('data-br-layout-synced');
    });
  }

  function layoutBadgeContainer(el) {
    if (!el) return null;
    var lw = el.closest ? el.closest('.br-lw') : null;
    if (lw) {
      var head = lw.querySelector('.br-lw__head');
      if (head) return head;
    }
    return el.closest ? el.closest('.admin-form-group') : el.parentElement;
  }

  function removeLayoutBadgeForElement(el) {
    if (!el) return;
    el.removeAttribute('data-br-layout-synced');
    var container = layoutBadgeContainer(el);
    if (container) {
      container.querySelectorAll('[data-br-layout-badge]').forEach(function (badge) {
        badge.remove();
      });
    }
    var group = el.closest ? el.closest('.admin-form-group') : null;
    if (group && group !== container) {
      group.querySelectorAll('[data-br-layout-badge]').forEach(function (badge) {
        badge.remove();
      });
    }
  }

  function markLayoutSynced(el) {
    if (!el) return;
    el.setAttribute('data-br-layout-synced', '1');
    var container = layoutBadgeContainer(el);
    if (container) {
      container.querySelectorAll('[data-br-layout-badge]').forEach(function (badge) {
        badge.remove();
      });
      var badge = document.createElement('span');
      badge.className = 'br-field-source-badge';
      badge.setAttribute('data-br-layout-badge', '');
      badge.textContent = 'Room Area';
      var tipWrap = container.querySelector('.admin-label-with-tip');
      if (tipWrap) {
        tipWrap.appendChild(badge);
      } else {
        var lbl = container.querySelector('label');
        if (lbl && lbl.parentNode === container) {
          lbl.insertAdjacentElement('afterend', badge);
        } else {
          container.appendChild(badge);
        }
      }
    }
    if (el.dataset.brLayoutBadgeBound !== '1') {
      el.dataset.brLayoutBadgeBound = '1';
      el.addEventListener('input', function () {
        if (applyingLayout) return;
        removeLayoutBadgeForElement(el);
      });
    }
  }

  function renderLayoutTakeoffSummary(opt, proposed, layoutImport) {
    var wrap = document.getElementById('br-layout-takeoff-summary');
    var cards = document.getElementById('br-layout-takeoff-cards');
    if (!wrap || !cards) return;
    if (!opt || !layoutImport || typeof layoutImport.takeoffSummary !== 'function') {
      cards.innerHTML = '';
      wrap.hidden = true;
      return;
    }
    var items = layoutImport.takeoffSummary(opt, proposed, { heightFt: ceilingHeightFt() }) || [];
    if (!items.length) {
      cards.innerHTML = '';
      wrap.hidden = true;
      return;
    }
    cards.innerHTML = items.map(function (item) {
      return '<div class="br-takeoff-chip" data-br-takeoff-key="' + escapeHtml(item.key || '') + '">'
        + '<span class="br-takeoff-chip__label">' + escapeHtml(item.label || '') + '</span>'
        + '<strong class="br-takeoff-chip__value">' + escapeHtml(item.value || '—') + '</strong>'
        + '</div>';
    }).join('');
    wrap.hidden = false;
  }

  function perimeterOrSquareLf() {
    var periEl = document.getElementById('br-perimeter-lf');
    var peri = periEl ? parseFloat(periEl.value) : 0;
    if (isFinite(peri) && peri > 0) return peri;
    var areaEl = document.getElementById('br-area');
    var area = areaEl ? parseFloat(areaEl.value) : 0;
    return (isFinite(area) && area > 0) ? (4 * Math.sqrt(area)) : 0;
  }

  function floodGeometry() {
    var peri = perimeterOrSquareLf();
    var partEl = document.getElementById('br-partition');
    var part = partEl ? Math.max(0, parseFloat(partEl.value) || 0) : 0;
    var bothEl = document.getElementById('br-flood-both-faces');
    var both = bothEl ? !!bothEl.checked : true;
    var cutEl = document.getElementById('br-flood-cut-height');
    var cutRaw = cutEl ? parseFloat(cutEl.value) : NaN;
    var cutIn = (isFinite(cutRaw) && cutRaw > 0) ? cutRaw : 24;
    var wallRun = peri + (both ? part * 2 : part);
    return {
      peri: peri,
      wallRun: wallRun,
      cutFt: cutIn / 12,
      height: ceilingHeightFt()
    };
  }

  function computeFloodDrywallWallSf() {
    var geo = floodGeometry();
    return Math.round(geo.wallRun * geo.cutFt * 100) / 100;
  }

  function refreshFloodAutoQty(id, value) {
    var el = document.getElementById(id);
    if (!el || el.dataset.brFloodQtyAuto !== '1' || el.dataset.brFloodQtyManual === '1') return;
    el.value = value > 0 ? formatLayoutQty(value) : '';
  }

  function markFloodImportedQty(id, key) {
    if (currentPreset() !== 'flood') return;
    if (key !== 'insulation_wall_sf' && key !== 'paint_wall_sf' && key !== 'baseboard_lf') return;
    var el = document.getElementById(id);
    if (!el) return;
    el.dataset.brFloodQtyAuto = '1';
    el.dataset.brFloodQtyManual = '';
  }

  function syncFloodCutChips() {
    var cutEl = document.getElementById('br-flood-cut-height');
    var cutVal = cutEl ? parseFloat(cutEl.value) : NaN;
    form.querySelectorAll('[data-br-flood-cut]').forEach(function (btn) {
      var chipVal = parseFloat(btn.getAttribute('data-br-flood-cut') || '');
      var active = isFinite(cutVal) && isFinite(chipVal) && Math.abs(cutVal - chipVal) < 0.01;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
  }

  function syncFloodDrywallPrefill(forceWhenEmpty) {
    var dwWall = document.getElementById('br-dw-wall');
    if (!dwWall) return;
    if (currentPreset() !== 'flood') {
      if (dwWall.dataset.brFloodAuto === '1') {
        applyingFloodPrefill = true;
        setLwToSqft('br-dw-wall', '');
        dwWall.dataset.brFloodAuto = '';
        applyingFloodPrefill = false;
      }
      return;
    }
    var currentEmpty = String(dwWall.value || '').trim() === '';
    if (!(dwWall.dataset.brFloodManual === '1' && !currentEmpty)) {
      var sf = computeFloodDrywallWallSf();
      if (sf > 0) {
        applyingFloodPrefill = true;
        setLwToSqft('br-dw-wall', sf);
        dwWall.dataset.brFloodAuto = '1';
        applyingFloodPrefill = false;
      } else if (forceWhenEmpty && dwWall.dataset.brFloodManual !== '1') {
        applyingFloodPrefill = true;
        setLwToSqft('br-dw-wall', '');
        dwWall.dataset.brFloodAuto = '';
        applyingFloodPrefill = false;
      }
    }
    var geo = floodGeometry();
    refreshFloodAutoQty('br-ins-wall-sf', Math.round(geo.peri * geo.cutFt * 100) / 100);
    refreshFloodAutoQty('br-p-wall', Math.round(geo.wallRun * geo.height * 100) / 100);
    refreshFloodAutoQty('br-base', Math.round(geo.wallRun * 100) / 100);
  }

  var lastAppliedPreset = null;
  var presetQtyMemory = {};
  var PRESET_QTY_IDS = [
    'br-dw-wall',
    'br-ins-wall-sf',
    'br-ins-ceil-sf',
    'br-ins-slab-sf',
    'br-ceil-sf',
    'br-dw-ceil',
    'br-p-wall',
    'br-p-ceil',
    'br-p-trim',
    'br-base',
    'br-fl-sf',
    'br-shoe',
    'br-flood-cut-height'
  ];
  var PRESET_CHECK_NAMES = [
    'include_building_permit',
    'flood_partition_both_faces'
  ];
  var PRESET_VALUE_NAMES = ['ceiling_type', 'flooring_type'];
  var FLOOD_DERIVED_QTY_IDS = [
    'br-dw-wall',
    'br-ins-wall-sf',
    'br-ins-ceil-sf',
    'br-ins-slab-sf',
    'br-ceil-sf',
    'br-dw-ceil',
    'br-p-wall',
    'br-p-ceil',
    'br-p-trim',
    'br-base',
    'br-fl-sf',
    'br-shoe'
  ];
  var FLOOD_QTY_FLAG_IDS = ['br-ins-wall-sf', 'br-p-wall', 'br-base'];

  function capturePresetQty() {
    var snap = { fields: {}, checks: {}, values: {}, qtyFlags: {}, dw: null, casing: '' };
    PRESET_QTY_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      snap.fields[id] = el.value;
    });
    var dw = document.getElementById('br-dw-wall');
    if (dw) {
      snap.dw = {
        manual: dw.dataset.brFloodManual || '',
        auto: dw.dataset.brFloodAuto || '',
        fromLayout: dw.getAttribute('data-br-from-layout') || ''
      };
    }
    PRESET_CHECK_NAMES.forEach(function (name) {
      var el = form.querySelector('input[type="checkbox"][name="' + name + '"]');
      if (!el) return;
      snap.checks[name] = !!el.checked;
    });
    PRESET_VALUE_NAMES.forEach(function (name) {
      var el = form.querySelector('[name="' + name + '"]');
      if (!el || el.type === 'checkbox') return;
      snap.values[name] = el.value;
    });
    var casing = form.querySelector('input[name="flood_casing_mode"]:checked');
    snap.casing = casing ? casing.value : '';
    FLOOD_QTY_FLAG_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      snap.qtyFlags[id] = {
        auto: el.dataset.brFloodQtyAuto || '',
        manual: el.dataset.brFloodQtyManual || ''
      };
    });
    return snap;
  }

  function restorePresetQty(snap) {
    if (!snap) return;
    Object.keys(snap.fields || {}).forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      if (id === 'br-dw-wall') {
        setLwToSqft(id, snap.fields[id]);
      } else {
        el.value = snap.fields[id];
      }
    });
    var dw = document.getElementById('br-dw-wall');
    if (dw && snap.dw) {
      dw.dataset.brFloodManual = snap.dw.manual || '';
      dw.dataset.brFloodAuto = snap.dw.auto || '';
      if (snap.dw.fromLayout) dw.setAttribute('data-br-from-layout', snap.dw.fromLayout);
      else dw.removeAttribute('data-br-from-layout');
    }
    Object.keys(snap.checks || {}).forEach(function (name) {
      setNamedCheck(name, !!snap.checks[name]);
    });
    Object.keys(snap.values || {}).forEach(function (name) {
      setNamedValue(name, snap.values[name]);
    });
    if (snap.casing) {
      var radio = form.querySelector('input[name="flood_casing_mode"][value="' + snap.casing + '"]');
      if (radio) radio.checked = true;
    }
    Object.keys(snap.qtyFlags || {}).forEach(function (id) {
      var flagged = document.getElementById(id);
      if (!flagged || !snap.qtyFlags[id]) return;
      flagged.dataset.brFloodQtyAuto = snap.qtyFlags[id].auto || '';
      flagged.dataset.brFloodQtyManual = snap.qtyFlags[id].manual || '';
    });
    syncFloodCutChips();
  }

  function clearFloodDerivedQty() {
    FLOOD_DERIVED_QTY_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      if (id === 'br-dw-wall') {
        setLwToSqft(id, '');
        el.dataset.brFloodManual = '';
        el.dataset.brFloodAuto = '';
        el.removeAttribute('data-br-from-layout');
        return;
      }
      el.value = '';
      el.dataset.brFloodQtyAuto = '';
      el.dataset.brFloodQtyManual = '';
    });
  }

  function computeDemoDrywallSuggestedSf() {
    var btn = document.getElementById('br-fill-demo-drywall');
    var imported = btn ? parseFloat(btn.getAttribute('data-br-demo-suggested') || '0') : 0;
    if (isFinite(imported) && imported > 0) return imported;
    var h = ceilingHeightFt();
    if (currentPreset() === 'wall') {
      var partEl = document.getElementById('br-partition');
      var partLf = partEl ? parseFloat(partEl.value) : 0;
      if (isFinite(partLf) && partLf > 0) {
        return Math.round(partLf * h * 2 * 100) / 100;
      }
      return 0;
    }
    var areaEl = document.getElementById('br-area');
    var area = areaEl ? parseFloat(areaEl.value) : 0;
    var dwWallEl = document.getElementById('br-dw-wall');
    var dwWall = dwWallEl ? parseFloat(dwWallEl.value) : 0;
    var ceilEl = document.getElementById('br-ceil-sf');
    var ceilSf = ceilEl ? parseFloat(ceilEl.value) : 0;
    var peri = perimeterOrSquareLf();
    var wallBasis = (isFinite(dwWall) && dwWall > 0) ? dwWall : (peri * h);
    var ceilBasis = (isFinite(ceilSf) && ceilSf > 0) ? ceilSf : (isFinite(area) && area > 0 ? area : 0);
    var total = wallBasis + ceilBasis;
    return total > 0 ? Math.round(total * 100) / 100 : 0;
  }

  function syncDemoDrywallHelper() {
    var btn = document.getElementById('br-fill-demo-drywall');
    if (!btn) return;
    var suggested = computeDemoDrywallSuggestedSf();
    btn.hidden = !(suggested > 0);
  }

  function syncPartitionHelpers() {
    var partEl = document.getElementById('br-partition');
    var dwPartBtn = document.getElementById('br-dw-add-partition-btn');
    var basePartBtn = document.getElementById('br-base-add-partition-btn');
    if (!dwPartBtn && !basePartBtn) return;
    if (currentPreset() === 'flood') {
      if (dwPartBtn) dwPartBtn.hidden = true;
      if (basePartBtn) basePartBtn.hidden = true;
      return;
    }
    var partitionLf = partEl ? parseFloat(partEl.value) : 0;
    var dwWallEl = document.getElementById('br-dw-wall');
    var isMeasuredNetImport = !!(dwWallEl && dwWallEl.getAttribute('data-br-from-layout') === 'net');
    if (!isFinite(partitionLf) || partitionLf <= 0 || isMeasuredNetImport) {
      if (dwPartBtn) dwPartBtn.hidden = true;
      if (basePartBtn) basePartBtn.hidden = true;
      return;
    }
    var basePeri = currentPreset() === 'wall' ? 0 : perimeterOrSquareLf();
    var h = ceilingHeightFt();
    var targetDwWall = Math.round((basePeri * h + partitionLf * h * 2) * 100) / 100;
    var targetBaseLf = Math.round((basePeri + partitionLf * 2) * 100) / 100;
    if (dwPartBtn) {
      dwPartBtn.hidden = false;
      dwPartBtn.textContent = '+ Set walls w/ 2-sided partitions (' + formatLayoutQty(targetDwWall) + ' sq ft)';
    }
    if (basePartBtn) {
      basePartBtn.hidden = false;
      basePartBtn.textContent = '+ Set baseboard w/ partitions (' + formatLayoutQty(targetBaseLf) + ' lf)';
    }
  }

  function applyRoomLayout(opt) {
    var labelEl = document.getElementById('br-layout-import-label');
    var status = document.getElementById('br-layout-import-status');
    var wall = document.getElementById('br-dw-wall');
    if (!opt) {
      if (labelEl) labelEl.value = '';
      if (status) {
        status.hidden = true;
        status.textContent = '';
        status.classList.remove('is-set', 'is-warning');
      }
      renderLayoutTakeoffSummary(null, null, null);
      var fillDemo = document.getElementById('br-fill-demo-drywall');
      if (fillDemo) fillDemo.removeAttribute('data-br-demo-suggested');
      syncDemoDrywallHelper();
      return;
    }
    if (opt.geometry_unmeasurable) {
      applyingLayout = true;
      if (labelEl) labelEl.value = opt.short_name || opt.name || '';
      if (status) {
        var warnMsg = opt.geometry_message
          || 'Fix the room outline in Room Area before trusting measurements or quotes.';
        var roomLabel = opt.short_name || opt.name || 'layout';
        status.hidden = false;
        status.classList.add('is-set', 'is-warning');
        status.textContent = 'ⓘ ' + warnMsg + ' "' + roomLabel + '" was not imported into quantities.';
      }
      applyingLayout = false;
      schedulePreview();
      return;
    }
    var layoutImport = window.BasementLayoutImport;
    var ceilingTypeEl = document.getElementById('br-ceil-type');
    var ceilingType = ceilingTypeEl ? ceilingTypeEl.value : '';
    var cutHeightEl = document.getElementById('br-flood-cut-height');
    var bothFacesEl = document.getElementById('br-flood-both-faces');
    var casingModeEl = form.querySelector('input[name="flood_casing_mode"]:checked');
    var layoutOptions = {
      scope_preset: currentPreset(),
      flood_cut_height_in: cutHeightEl ? cutHeightEl.value : 24,
      flood_partition_both_faces: bothFacesEl ? !!bothFacesEl.checked : true,
      flood_casing_mode: casingModeEl ? casingModeEl.value : 'legs'
    };
    var proposed = layoutImport
      ? layoutImport.proposedQuantities(opt, ceilingHeightFt(), ceilingType, layoutOptions)
      : {};
    if (layoutImport) {
      var current = layoutImport.currentQuantities(document);
      var needsConfirm = layoutImport.needsImportConfirm
        ? layoutImport.needsImportConfirm(current, proposed, opt)
        : layoutImport.wouldOverwrite(current, proposed);
      if (needsConfirm) {
        var msg = layoutImport.confirmImportMessage
          ? layoutImport.confirmImportMessage(current, proposed, opt)
          : layoutImport.CONFIRM_MESSAGE;
        var runApply = function () {
          applyRoomLayoutConfirmed(opt, proposed, layoutImport);
        };
        if (window.AdminDialog && typeof window.AdminDialog.confirm === 'function') {
          window.AdminDialog.confirm(msg, {
            title: 'Replace layout quantities?',
            confirmLabel: 'Replace',
            cancelLabel: 'Keep current'
          }).then(function (ok) {
            if (ok) runApply();
          });
          return;
        }
        if (!window.confirm(msg)) return;
      }
    }
    applyRoomLayoutConfirmed(opt, proposed, layoutImport);
  }

  function applyRoomLayoutConfirmed(opt, proposed, layoutImport) {
    applyingLayout = true;
    clearLayoutBadges();
    var labelEl = document.getElementById('br-layout-import-label');
    var status = document.getElementById('br-layout-import-status');
    var wall = document.getElementById('br-dw-wall');
    var fieldIds = (layoutImport && layoutImport.FIELD_IDS) || {};
    var lwKeys = (layoutImport && layoutImport.LW_KEYS) || {};
    if (labelEl) labelEl.value = opt.name || opt.label || '';

    var heightKey = 'ceiling_height_ft';
    if (proposed[heightKey] != null && fieldIds[heightKey]) {
      var heightEl = document.getElementById(fieldIds[heightKey]);
      if (heightEl) {
        heightEl.value = formatLayoutQty(proposed[heightKey]);
        markLayoutSynced(heightEl);
      }
    }

    var skipForm = (layoutImport && layoutImport.SKIP_FORM_KEYS) || { drywall_ceiling_sf: true };
    Object.keys(proposed).forEach(function (key) {
      if (key === heightKey) return;
      if (skipForm[key]) return;
      if (proposed[key] == null) return;
      var id = fieldIds[key];
      if (!id) return;
      if (lwKeys[key]) {
        setLwToSqft(id, proposed[key]);
        markLayoutSynced(document.getElementById(id));
        markFloodImportedQty(id, key);
        return;
      }
      var el = document.getElementById(id);
      if (!el) return;
      el.value = formatLayoutQty(proposed[key]);
      markLayoutSynced(el);
      markFloodImportedQty(id, key);
    });

    var ext = (layoutImport && typeof layoutImport.extendedQuantities === 'function')
      ? layoutImport.extendedQuantities(opt)
      : null;
    if (ext) {
      if (ext.include_stairs) {
        var cbStairs = document.getElementById('br-include-stairs');
        var stairsFields = document.getElementById('br-stairs-fields');
        if (cbStairs) cbStairs.checked = true;
        if (stairsFields) stairsFields.hidden = false;
        var treadsEl = document.getElementById('br-stairs-treads');
        if (treadsEl && ext.stairs_treads != null) {
          treadsEl.value = formatLayoutQty(ext.stairs_treads);
          markLayoutSynced(treadsEl);
        }
        var railEl = document.getElementById('br-stairs-handrail-lf');
        if (railEl && ext.stairs_handrail_lf != null) {
          railEl.value = formatLayoutQty(ext.stairs_handrail_lf);
          markLayoutSynced(railEl);
        }
      }
      if (ext.include_millwork) {
        var cbMill = document.getElementById('br-include-millwork');
        var millFields = document.getElementById('br-millwork-fields');
        if (cbMill) cbMill.checked = true;
        if (millFields) millFields.hidden = false;
        var baseCabEl = document.getElementById('br-cabinet-base-lf');
        if (baseCabEl && ext.cabinet_base_lf != null) {
          baseCabEl.value = formatLayoutQty(ext.cabinet_base_lf);
          markLayoutSynced(baseCabEl);
        }
        var upperCabEl = document.getElementById('br-cabinet-upper-lf');
        if (upperCabEl && ext.cabinet_upper_lf != null) {
          upperCabEl.value = formatLayoutQty(ext.cabinet_upper_lf);
          markLayoutSynced(upperCabEl);
        }
      }
      syncRevealTargets();
      var fillDemoBtn = document.getElementById('br-fill-demo-drywall');
      if (fillDemoBtn) {
        if (ext.demo_drywall_suggested_sf != null && Number(ext.demo_drywall_suggested_sf) > 0) {
          fillDemoBtn.setAttribute('data-br-demo-suggested', formatLayoutQty(ext.demo_drywall_suggested_sf));
        } else {
          fillDemoBtn.removeAttribute('data-br-demo-suggested');
        }
      }
    }

    var isFloodPreset = currentPreset() === 'flood';
    var measuredNet = isFloodPreset
      ? false
      : ((layoutImport && layoutImport.present)
        ? (layoutImport.present(opt, 'finish_drywall_sqft')
          || layoutImport.present(opt, 'net_wall_area_sqft')
          || layoutImport.present(opt, 'wall_area_sqft'))
        : Number(opt.wall_area_sqft || 0) > 0);
    if (wall) {
      wall.setAttribute('data-br-from-layout', measuredNet ? 'net' : '1');
      if (isFloodPreset && proposed.drywall_wall_sf != null) {
        wall.dataset.brFloodManual = '';
        wall.dataset.brFloodAuto = '1';
      }
    }

    if (status) {
      status.classList.remove('is-warning');
      status.hidden = false;
      status.classList.add('is-set');
      status.textContent = (layoutImport && layoutImport.importStatusText)
        ? layoutImport.importStatusText(proposed, opt, {
            heightFt: ceilingHeightFt(),
            measuredNet: measuredNet
          })
        : ('Imported ' + (opt.short_name || opt.name || 'layout') + '.');
    }
    renderLayoutTakeoffSummary(opt, proposed, layoutImport);
    applyingLayout = false;
    syncUnifiedCeilingArea();
    updateDwWallPlaceholder();
    syncDemoDrywallHelper();
    syncPartitionHelpers();
    schedulePreview();
  }

  function isSplitCeilingQuote() {
    return form.getAttribute('data-br-split-ceiling') === '1';
  }

  function syncUnifiedCeilingArea() {
    // Hang is derived server-side from Ceiling area on new/adopted quotes.
    // Do not copy into drywall_ceiling_sf: posting that field would mark a
    // new quote as a legacy hang source on the next save.
  }

  function adoptUnifiedCeilingHang() {
    var btn = form.querySelector('[data-br-adopt-ceiling-hang]');
    var message = (btn && btn.getAttribute('data-br-adopt-message'))
      || 'Use Ceiling area for hang, tape, mud, and blank paint from now on? Totals will change.';
    if (!window.confirm(message)) return;
    form.setAttribute('data-br-split-ceiling', '0');
    var flag = document.getElementById('br-ceiling-hang-unified');
    if (flag) flag.value = '1';
    var dw = document.getElementById('br-dw-ceil');
    if (dw) {
      dw.value = '';
      dw.disabled = true;
    }
    var notice = form.querySelector('[data-br-legacy-ceiling-notice]');
    if (notice) notice.hidden = true;
    schedulePreview();
  }

  function refreshLayoutWallFromHeight() {
    if (currentPreset() === 'flood') return;
    var wall = document.getElementById('br-dw-wall');
    var periEl = document.getElementById('br-perimeter-lf');
    var from = wall ? wall.getAttribute('data-br-from-layout') : '';
    if (!wall || (from !== '1' && from !== 'gross') || !periEl) return;
    var peri = parseFloat(periEl.value);
    if (!isFinite(peri) || peri <= 0) return;
    applyingLayout = true;
    setLwToSqft('br-dw-wall', peri * ceilingHeightFt());
    applyingLayout = false;
    var status = document.getElementById('br-layout-import-status');
    var labelEl = document.getElementById('br-layout-import-label');
    if (status && !status.hidden && periEl) {
      var areaEl = document.getElementById('br-area');
      var area = areaEl ? parseFloat(areaEl.value) : 0;
      var name = (labelEl && labelEl.value) ? labelEl.value : 'layout';
      var openEl = document.getElementById('br-dw-open');
      var doorEl = document.getElementById('br-dw-door-open');
      var windowEl = document.getElementById('br-dw-window-open');
      var openings = (parseInt(openEl && openEl.value, 10) || 0)
        + (parseInt(doorEl && doorEl.value, 10) || 0)
        + (parseInt(windowEl && windowEl.value, 10) || 0);
      var openLabel = openings === 1 ? '1 opening' : openings + ' openings';
      var wallNow = wall ? parseFloat(wall.value) : (peri * ceilingHeightFt());
      status.textContent = 'Imported ' + name + ': ' + formatLayoutQty(area) + ' sq ft floor, '
        + formatLayoutQty(peri) + ' lf walls × ' + formatLayoutQty(ceilingHeightFt()) + ' ft'
        + (isFinite(wallNow) && wallNow > 0 ? ' = ' + formatLayoutQty(wallNow) + ' sq ft wall drywall' : '')
        + ', ' + openLabel + '.';
    }
  }

  function formatTileTotal(n) {
    var rounded = Math.round(Number(n) * 100) / 100;
    if (!isFinite(rounded) || rounded <= 0) return '0 sq ft';
    return String(rounded) + ' sq ft';
  }

  function syncTileTotal() {
    var list = document.getElementById('br-tile-list');
    var out = form.querySelector('[data-br-tile-total]');
    if (!list || !out) return;
    var tile = 0;
    var vinyl = 0;
    list.querySelectorAll('[data-br-tile-row]').forEach(function (row) {
      var qty = row.querySelector('[data-br-lw-qty]');
      var finish = row.querySelector('[data-br-tile-finish]:checked') || row.querySelector('[data-br-tile-finish]');
      var n = qty ? parseFloat(qty.value) : NaN;
      if (!isFinite(n) || n <= 0) return;
      if (finish && String(finish.value).toLowerCase() === 'vinyl') vinyl += n;
      else tile += n;
    });
    var total = tile + vinyl;
    var text = 'Combined: ' + formatTileTotal(total);
    if (total > 0 && tile > 0 && vinyl > 0) {
      text += ' (tile ' + formatTileTotal(tile) + ' · vinyl ' + formatTileTotal(vinyl) + ')';
    } else if (total > 0 && vinyl > 0) {
      text += ' (vinyl)';
    }
    out.textContent = text;
  }

  function initTileRow(row) {
    if (!row || row.getAttribute('data-br-tile-bound') === '1') return;
    row.setAttribute('data-br-tile-bound', '1');
    bindLw();
    row.querySelectorAll('.br-preview').forEach(bindPreview);
    var remove = row.querySelector('[data-br-tile-remove]');
    if (remove) {
      remove.addEventListener('click', function () {
        row.remove();
        syncTileTotal();
        schedulePreview();
      });
    }
    row.querySelectorAll('.br-preview').forEach(function (el) {
      el.addEventListener('input', syncTileTotal);
      el.addEventListener('change', syncTileTotal);
    });
  }

  function initTileAreas() {
    var list = document.getElementById('br-tile-list');
    var tpl = document.getElementById('br-tile-template');
    var add = document.getElementById('br-tile-add');
    if (!list) return;
    list.querySelectorAll('[data-br-tile-row]').forEach(initTileRow);
    syncTileTotal();
    if (add && tpl) {
      add.addEventListener('click', function () {
        var i = 't' + Date.now();
        var wrap = document.createElement('div');
        wrap.innerHTML = tpl.innerHTML.replace(/__I__/g, String(i)).trim();
        var row = wrap.firstElementChild;
        list.appendChild(row);
        var refreshTile = window.AdminInfoTip && (window.AdminInfoTip.refreshControlIds || window.AdminInfoTip.refreshIds);
        if (refreshTile) refreshTile(row);
        initTileRow(row);
        schedulePreview();
      });
    }
  }

  function updateDwWallPlaceholder() {
    var dwWall = document.getElementById('br-dw-wall');
    if (!dwWall) return;
    var wallOnly = currentPreset() === 'wall';
    var periEl = document.getElementById('br-perimeter-lf');
    var peri = periEl ? parseFloat(periEl.value) : 0;
    dwWall.placeholder = 'Auto';
  }

  function applyScope() {
    rebuildWizardKeys();
    var enabled = enabledTradeKeys();
    allSectionKeys.forEach(function (k) {
      var show = enabled.indexOf(k) !== -1;
      var el = sectionEl(k);
      if (el) {
        el.hidden = !show;
        if (!show) {
          el.classList.remove('is-active');
          if (!isEdit) el.removeAttribute('open');
        } else if (isEdit) {
          el.removeAttribute('hidden');
        }
      }
      form.querySelectorAll('.br-progress__item[data-br-goto="' + k + '"]').forEach(function (btn) {
        btn.hidden = !show;
      });
    });
    var tradeCount = Math.max(1, sectionKeys.length);
    var withScope = tradeCount + 1;
    var n = 0;
    sectionKeys.forEach(function (k) {
      n += 1;
      var el = sectionEl(k);
      if (!el) return;
      var num = el.querySelector('.br-acc__num');
      if (num) num.textContent = n + ' of ' + withScope;
    });
    var scopeEl = sectionEl('client_scope');
    if (scopeEl) {
      var scopeNum = scopeEl.querySelector('.br-acc__num');
      if (scopeNum) scopeNum.textContent = withScope + ' of ' + withScope;
    }
    var areaLabel = document.getElementById('br-area-label');
    if (areaLabel) {
      var labels = {
        full: 'Total basement area (sq ft)',
        bathroom: 'Bathroom / work area (sq ft)',
        wall: 'Basement area (sq ft, optional)',
        flood: 'Affected floor area (sq ft)',
        custom: 'Work area (sq ft)'
      };
      var nextLabel = labels[currentPreset()] || labels.full;
      if (window.AdminInfoTip && typeof window.AdminInfoTip.setLabelText === 'function') {
        window.AdminInfoTip.setLabelText(areaLabel, nextLabel);
      }
    }
    var baseEl = document.getElementById('br-base');
    if (baseEl) {
      baseEl.placeholder = 'Auto';
    }
    var preset = currentPreset();
    var wallOnly = preset === 'wall';
    var floodOnly = preset === 'flood';
    form.querySelectorAll('[data-br-skip-on-wall]').forEach(function (el) {
      el.hidden = wallOnly;
    });
    form.querySelectorAll('[data-br-hide-on-wall]').forEach(function (el) {
      el.hidden = wallOnly;
    });
    form.querySelectorAll('[data-br-flood-only]').forEach(function (el) {
      el.hidden = !floodOnly;
    });
    var floodBathOptIn = document.getElementById('br-flood-bath-use-restoration');
    var keepLegacyBath = floodOnly && floodBathOptIn && !floodBathOptIn.checked;
    form.querySelectorAll('[data-br-hide-on-flood]').forEach(function (el) {
      el.hidden = floodOnly && !keepLegacyBath;
    });
    form.querySelectorAll('[data-br-flood-bath-options]').forEach(function (el) {
      var hideFloodBath = !floodOnly || keepLegacyBath;
      el.hidden = hideFloodBath;
      el.querySelectorAll('input, select, textarea').forEach(function (input) {
        input.disabled = hideFloodBath;
      });
    });
    var areaOverview = form.querySelector('[data-br-area-overview]');
    var wallOverview = form.querySelector('[data-br-wall-overview]');
    if (areaOverview) areaOverview.hidden = wallOnly;
    if (wallOverview) wallOverview.hidden = !wallOnly;
    var overviewGeom = form.querySelector('[data-br-overview-geom]');
    if (overviewGeom) {
      overviewGeom.setAttribute('data-br-overview-mode', wallOnly ? 'wall' : 'area');
    }
    relocateWallPrimary();
    syncFloodCutChips();
    if (!floodOnly) {
      syncFloodDrywallPrefill(false);
    }
    updateDwWallPlaceholder();
    var pWall = document.getElementById('br-p-wall');
    if (pWall) {
      pWall.placeholder = 'Auto';
    }
    if (currentIndex >= wizardKeys.length) {
      currentIndex = 0;
    }
    if (wizardKeys[currentIndex] !== 'review' && sectionKeys.indexOf(wizardKeys[currentIndex]) < 0) {
      currentIndex = 0;
    }
    relocateNestedTrades();
    syncBathroomElectricalHints();
    syncDemoChoiceRequired(false);
    if (isAllSections) {
      syncAllSectionsOpenState();
    }
    syncPartitionHelpers();
    syncDemoDrywallHelper();
    syncInlineClientProvides();
  }

  function relocateNestedTrades() {
    var nest = currentPreset() === 'bathroom';
    [
      ['br-electrical-fields', 'br-electrical-slot-step', 'br-electrical-slot-bath'],
      ['br-plumbing-fields', 'br-plumbing-slot-step', 'br-plumbing-slot-bath'],
      ['br-framing-fields', 'br-framing-slot-step', 'br-framing-slot-bath']
    ].forEach(function (ids) {
      var fields = document.getElementById(ids[0]);
      var stepSlot = document.getElementById(ids[1]);
      var bathSlot = document.getElementById(ids[2]);
      if (!fields || !stepSlot || !bathSlot) return;
      (nest ? bathSlot : stepSlot).appendChild(fields);
      bathSlot.hidden = !nest;
    });
    ['electrical', 'plumbing', 'framing'].forEach(function (k) {
      var cb = form.querySelector('[data-br-enabled-section="' + k + '"]');
      var row = cb ? cb.closest('.admin-checkbox') : null;
      if (row) {
        row.classList.toggle('br-trade-nested', nest);
        if (nest) cb.checked = true;
      }
      var step = sectionEl(k);
      if (nest && step) {
        step.hidden = true;
        step.classList.remove('is-active');
        if (!isEdit) step.removeAttribute('open');
      }
      form.querySelectorAll('.br-progress__item[data-br-goto="' + k + '"]').forEach(function (btn) {
        if (nest) btn.hidden = true;
      });
    });
    var includeWrap = form.querySelector('[data-br-bath-include-wrap]');
    if (includeWrap) includeWrap.hidden = nest || currentPreset() === 'flood';
    if (nest) {
      setNamedCheck('include_bathroom', true);
      var bathFields = document.getElementById('br-bath-fields');
      if (bathFields) bathFields.hidden = false;
    }
    relocateWallPrimary();
  }

  function relocateWallPrimary() {
    var preset = currentPreset();
    var wall = preset === 'wall';
    var flood = preset === 'flood';
    var fields = document.getElementById('br-wall-primary-fields');
    var overviewSlot = document.getElementById('br-wall-primary-slot');
    var framingSlot = document.getElementById('br-framing-primary-slot');
    var partField = document.getElementById('br-partition-field');
    var floodSlot = document.getElementById('br-flood-partition-slot');
    if (fields && overviewSlot && framingSlot) {
      (wall ? overviewSlot : framingSlot).appendChild(fields);
    }
    if (partField) {
      if (flood && floodSlot) {
        floodSlot.appendChild(partField);
      } else if (!flood && fields) {
        fields.insertBefore(partField, fields.firstChild);
      }
    }
    if (fields) {
      var toggleUi = fields.querySelector('[data-br-door-toggle-ui]');
      var countUi = fields.querySelector('[data-br-door-count-ui]');
      if (toggleUi) toggleUi.hidden = !wall;
      if (countUi) countUi.hidden = wall;
    }
    syncWallDoorToggleFromOpenings();
  }

  function syncWallDoorFromToggle() {
    var cb = document.getElementById('br-wall-include-door');
    var openings = document.getElementById('br-frame-doors');
    var doors = document.getElementById('br-doors');
    if (!cb || !openings) return;
    if (cb.checked) {
      if (Number(openings.value) <= 0) openings.value = '1';
      if (doors && Number(doors.value) <= 0) doors.value = '1';
    } else {
      openings.value = '0';
      if (doors) doors.value = '0';
    }
    try {
      openings.dispatchEvent(new Event('input', { bubbles: true }));
      if (doors) doors.dispatchEvent(new Event('input', { bubbles: true }));
    } catch (err) { /* ignore */ }
  }

  function syncWallDoorToggleFromOpenings() {
    var cb = document.getElementById('br-wall-include-door');
    var openings = document.getElementById('br-frame-doors');
    var doors = document.getElementById('br-doors');
    if (!cb) return;
    var openN = openings ? Number(openings.value) : 0;
    var doorN = doors ? Number(doors.value) : 0;
    cb.checked = (isFinite(openN) && openN > 0) || (isFinite(doorN) && doorN > 0);
  }

  function applyElectricalDefaults(preset) {
    var map = (cfg.electricalByPreset || {})[preset];
    if (!map) return;
    var keys = Object.keys(map);
    if (!keys.length) return;
    keys.forEach(function (name) {
      var val = map[name];
      if (typeof val === 'boolean') {
        setNamedCheck(name, val);
      } else {
        setNamedValue(name, val);
      }
    });
    syncLightingType();
    syncBathLightQtyDefaults();
  }

  function syncBathroomElectricalHints() {
    var bath = currentPreset() === 'bathroom';
    form.querySelectorAll('[data-br-basement-lighting]').forEach(function (el) {
      if (bath) {
        el.hidden = true;
        return;
      }
      if (el.id === 'br-lighting-fixture-qty') {
        return;
      }
      el.hidden = false;
    });
    if (!bath) {
      syncLightingType();
    }
  }

  function syncBathLightQtyDefaults() {
    form.querySelectorAll('[data-br-bath-light]').forEach(function (cb) {
      if (!cb.checked) return;
      var wrap = document.getElementById(cb.getAttribute('data-br-reveal'));
      var qty = wrap ? wrap.querySelector('input[type="number"]') : null;
      if (qty && Number(qty.value) <= 0) {
        qty.value = '1';
      }
    });
  }

  function applyPreset(preset) {
    if (lastAppliedPreset && lastAppliedPreset !== preset) {
      presetQtyMemory[lastAppliedPreset] = capturePresetQty();
    }
    var keys = presetSections[preset]
      || (preset === 'flood' ? FLOOD_FALLBACK_SECTIONS : null)
      || presetSections.full
      || allSectionKeys;
    form.querySelectorAll('[data-br-enabled-section]').forEach(function (cb) {
      cb.checked = keys.indexOf(cb.value) !== -1;
    });
    if (preset === 'bathroom') {
      setNamedCheck('include_plumbing', true);
      setNamedCheck('include_bathroom', true);
      setNamedCheck('bath_rough_in', true);
      setNamedCheck('insulate_walls', false);
      setNamedValue('flooring_type', 'none');
      setNamedValue('detector_qty', 0);
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
    } else if (preset === 'wall') {
      setNamedCheck('insulate_walls', false);
      setNamedValue('flooring_type', 'none');
      setNamedValue('detector_qty', 0);
      setNamedValue('ceiling_type', 'none');
      setNamedValue('drywall_ceiling_sf', '');
      setNamedValue('paint_ceiling_sf', '');
      setNamedValue('area_sqft', 0);
      setNamedCheck('include_cleaning', false);
      setNamedCheck('include_touchups', false);
    } else if (preset === 'flood') {
      setNamedCheck('insulate_walls', true);
      setNamedCheck('insulate_ceiling', false);
      setNamedCheck('underslab', false);
      setNamedValue('flooring_type', 'lvp');
      setNamedValue('ceiling_type', 'none');
      setNamedCheck('include_bathroom', false);
      setNamedCheck('include_demolition', false);
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
      setNamedCheck('include_building_permit', false);
      setNamedCheck('flood_partition_both_faces', true);
      var cutEl = document.getElementById('br-flood-cut-height');
      var cutVal = cutEl ? parseFloat(cutEl.value) : NaN;
      if (!isFinite(cutVal) || cutVal <= 0) {
        setNamedValue('flood_cut_height_in', '24');
      }
      if (!form.querySelector('input[name="flood_casing_mode"]:checked')) {
        var legsRadio = form.querySelector('input[name="flood_casing_mode"][value="legs"]');
        if (legsRadio) legsRadio.checked = true;
      }
    } else if (preset === 'full') {
      setNamedCheck('insulate_walls', true);
      setNamedValue('flooring_type', 'lvp');
      setNamedValue('detector_qty', 2);
      setNamedValue('ceiling_type', 'drywall');
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
    }
    applyElectricalDefaults(preset);
    applyScope();
    if (lastAppliedPreset && lastAppliedPreset !== preset && presetQtyMemory[preset]) {
      restorePresetQty(presetQtyMemory[preset]);
    } else if (lastAppliedPreset === 'flood' && preset !== 'flood') {
      clearFloodDerivedQty();
    } else if (preset === 'flood') {
      var dwEnter = document.getElementById('br-dw-wall');
      if (dwEnter) {
        dwEnter.dataset.brFloodManual = '';
        dwEnter.dataset.brFloodAuto = '';
      }
    }
    if (preset === 'flood') {
      form.querySelectorAll('[data-br-enabled-section="finishing"]').forEach(function (cb) {
        cb.checked = true;
      });
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
      applyScope();
      syncFloodDrywallPrefill(true);
    }
    lastAppliedPreset = preset;
    syncRevealTargets();
    syncBathroomElectricalHints();
    syncBathSinkQty();
  }

  function clearStepErrors() {
    form.querySelectorAll('.br-field-error').forEach(function (el) { el.remove(); });
    form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });
  }

  function showFieldError(input, message) {
    if (!input) return;
    input.classList.add('is-invalid');
    var group = input.closest('.admin-form-group') || input.closest('.br-lw') || input.parentElement;
    if (!group) return;
    var existing = group.querySelector('.br-field-error');
    if (existing) {
      existing.textContent = message;
      return;
    }
    var p = document.createElement('p');
    p.className = 'br-field-error';
    p.textContent = message;
    group.appendChild(p);
  }

  function validateCurrentStep() {
    clearStepErrors();
    var key = wizardKeys[currentIndex];
    if (key !== 'overview') return true;
    var preset = currentPreset();
    if (preset === 'wall') {
      var wall = document.getElementById('br-partition');
      var lf = wall ? parseFloat(wall.value) : 0;
      if (!isFinite(lf) || lf <= 0) {
        showFieldError(wall, 'Enter the new wall length in linear feet.');
        if (wall && wall.focus) wall.focus();
        return false;
      }
      return true;
    }
    if (preset === 'flood') {
      var modeFlood = form.querySelector('[name="area_entry_mode"]:checked');
      var useDimsFlood = modeFlood && modeFlood.value === 'dimensions';
      var areaFloodEl = document.getElementById('br-area');
      var areaFlood = areaFloodEl ? parseFloat(areaFloodEl.value) : 0;
      var lenFloodEl = form.querySelector('[name="area_length_ft"]');
      var widFloodEl = form.querySelector('[name="area_width_ft"]');
      var lFlood = lenFloodEl ? parseFloat(lenFloodEl.value) : 0;
      var wFlood = widFloodEl ? parseFloat(widFloodEl.value) : 0;
      var periFloodEl = document.getElementById('br-perimeter-lf');
      var periFlood = periFloodEl ? parseFloat(periFloodEl.value) : 0;
      var partFloodEl = document.getElementById('br-partition');
      var partFlood = partFloodEl ? parseFloat(partFloodEl.value) : 0;
      var hasArea = useDimsFlood ? (lFlood > 0 && wFlood > 0) : (isFinite(areaFlood) && areaFlood > 0);
      var hasLinear = (isFinite(periFlood) && periFlood > 0) || (isFinite(partFlood) && partFlood > 0);
      if (!hasArea && !hasLinear) {
        var errTarget = periFloodEl || areaFloodEl;
        showFieldError(errTarget, 'Enter the affected floor area or exterior wall perimeter in linear feet.');
        if (errTarget && errTarget.focus) errTarget.focus();
        return false;
      }
      return true;
    }
    var mode = form.querySelector('[name="area_entry_mode"]:checked');
    var useDims = mode && mode.value === 'dimensions';
    if (useDims) {
      var len = form.querySelector('[name="area_length_ft"]');
      var wid = form.querySelector('[name="area_width_ft"]');
      var l = len ? parseFloat(len.value) : 0;
      var w = wid ? parseFloat(wid.value) : 0;
      if (!(l > 0 && w > 0)) {
        showFieldError(len || wid, 'Enter length and width in feet.');
        if (len && len.focus) len.focus();
        return false;
      }
      return true;
    }
    var area = document.getElementById('br-area');
    var sf = area ? parseFloat(area.value) : 0;
    if (!isFinite(sf) || sf <= 0) {
      showFieldError(area, preset === 'bathroom'
        ? 'Enter the bathroom / work area in square feet.'
        : 'Enter the total basement area in square feet.');
      if (area && area.focus) area.focus();
      return false;
    }
    return true;
  }

  function syncAllSectionsOpenState() {
    wizardKeys.forEach(function (k) {
      var el = sectionEl(k);
      if (!el) return;
      el.setAttribute('open', '');
    });
  }

  function syncViewModeUi() {
    form.classList.toggle('br-layout--all-sections', isAllSections);
    var toggleBtn = document.getElementById('br-view-mode-toggle');
    if (toggleBtn) {
      toggleBtn.setAttribute('aria-pressed', isAllSections ? 'true' : 'false');
      var span = toggleBtn.querySelector('[data-br-view-mode-label], span');
      (span || toggleBtn).textContent = isAllSections ? 'Guided steps' : 'All sections';
    }
    var summaryEl = document.getElementById('br-workbench-summary');
    if (summaryEl) {
      summaryEl.textContent = isAllSections ? 'All enabled sections open' : 'Guided step-by-step builder';
    }
  }

  function setAllSectionsMode(on) {
    isAllSections = !!on;
    syncViewModeUi();
    if (isAllSections) {
      syncAllSectionsOpenState();
    } else {
      setWizardStep(currentIndex, false);
    }
  }

  function stickyHeaderOffset() {
    var offset = 12;
    try {
      if (!window.getComputedStyle || !document.querySelector) return offset;
      var header = document.querySelector('.admin-topbar');
      if (!header || typeof header.getBoundingClientRect !== 'function') return offset;
      var pos = window.getComputedStyle(header).position;
      if (pos === 'sticky' || pos === 'fixed') {
        offset = Math.ceil(header.getBoundingClientRect().height) + 12;
      }
    } catch (err) {}
    return offset;
  }

  function scrollSectionIntoView(el) {
    if (!el) return;
    var offset = stickyHeaderOffset();
    if (el.style && typeof el.style.setProperty === 'function') {
      el.style.setProperty('scroll-margin-top', offset + 'px');
    }
    if (typeof el.scrollIntoView === 'function') {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function syncAccExpanded(el) {
    if (!el || typeof el.querySelector !== 'function') return;
    var summary = el.querySelector('.br-acc__summary');
    if (!summary || typeof summary.setAttribute !== 'function') return;
    var open = typeof el.hasAttribute === 'function' && el.hasAttribute('open');
    summary.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function jumpToSection(key) {
    if (!key) return;
    var idx = wizardKeys.indexOf(key);
    if (idx >= 0) {
      setWizardStep(idx, true, true);
      return;
    }
    if (currentPreset() === 'bathroom' && (key === 'electrical' || key === 'plumbing' || key === 'framing')) {
      var bathIdx = wizardKeys.indexOf('bathroom');
      if (bathIdx >= 0) {
        setWizardStep(bathIdx, false, true);
        var nestedEl = document.getElementById('br-' + key + '-fields');
        scrollSectionIntoView(nestedEl || sectionEl('bathroom'));
      }
    }
  }

  function setWizardStep(index, scroll, exclusive) {
    if (index < 0) index = 0;
    if (index >= wizardKeys.length) index = wizardKeys.length - 1;
    if (exclusive && isAllSections) {
      isAllSections = false;
      syncViewModeUi();
    }
    currentIndex = index;
    var key = wizardKeys[currentIndex];
    wizardKeys.forEach(function (k) {
      var el = sectionEl(k);
      if (!el) return;
      var active = k === key;
      el.classList.toggle('is-active', active);
      if (exclusive || isAllSections || !isEdit) {
        if (isAllSections && !exclusive) {
          el.setAttribute('open', '');
        } else if (active) {
          el.setAttribute('open', '');
        } else {
          el.removeAttribute('open');
        }
      }
      syncAccExpanded(el);
    });
    form.querySelectorAll('.br-progress__item').forEach(function (btn) {
      btn.classList.toggle('is-current', btn.getAttribute('data-br-goto') === key);
    });
    var status = document.getElementById('br-step-status');
    if (status) {
      // Accordion nums use trades + client scope (not Review). Keep the
      // footer counter on that same denominator so "1 of 14" does not
      // flip to "1 of 13" after hydrate.
      var numberedTotal = Math.max(1, sectionKeys.length + 1);
      status.textContent = key === 'review'
        ? 'Review'
        : ((currentIndex + 1) + ' of ' + numberedTotal);
    }
    var prev = document.getElementById('br-prev');
    var next = document.getElementById('br-next');
    if (prev) {
      prev.disabled = currentIndex === 0;
      prev.textContent = '← Back';
    }
    if (next) {
      next.textContent = key === 'review' ? 'Publish' : '→ Next';
      next.disabled = false;
    }
    if (scroll) {
      scrollSectionIntoView(sectionEl(key));
    }
    schedulePreview();
  }

  var dwWallBoot = document.getElementById('br-dw-wall');
  if (dwWallBoot && currentPreset() === 'flood' && String(dwWallBoot.value || '').trim() !== '') {
    dwWallBoot.dataset.brFloodManual = '1';
  }
  applyScope();
  lastAppliedPreset = currentPreset();
  bindLw();

  function syncStickyBarSpace() {
    var bar = document.getElementById('br-sticky-total');
    if (!bar) return;
    var h = Math.ceil(bar.getBoundingClientRect().height);
    if (!isFinite(h) || h < 1) h = 64;
    document.documentElement.style.setProperty('--br-sticky-bar-h', h + 'px');
  }
  syncStickyBarSpace();
  window.addEventListener('resize', syncStickyBarSpace);
  if (typeof ResizeObserver !== 'undefined') {
    var stickyBar = document.getElementById('br-sticky-total');
    if (stickyBar) new ResizeObserver(syncStickyBarSpace).observe(stickyBar);
  }

  var reviewStepEl = sectionEl('review');
  var hasSendOptionsError = !!(reviewStepEl && (
    reviewStepEl.querySelector('#br-send-options .admin-form-error')
    || reviewStepEl.querySelector('.cp-reply-to-accept--error')
  ));
  if (hasSendOptionsError) {
    var sendOpts = reviewStepEl.querySelector('#br-send-options');
    if (sendOpts && sendOpts.tagName === 'DETAILS') {
      sendOpts.setAttribute('open', '');
    }
  }
  var bootStepIndex = (isEdit || hasSendOptionsError)
    ? Math.max(0, wizardKeys.indexOf('review'))
    : 0;
  syncViewModeUi();
  setWizardStep(bootStepIndex, false);
  var viewModeToggle = document.getElementById('br-view-mode-toggle');
  if (viewModeToggle) {
    viewModeToggle.addEventListener('click', function () {
      setAllSectionsMode(!isAllSections);
    });
  }
  var prevBtn = document.getElementById('br-prev');
  var nextBtn = document.getElementById('br-next');
  if (prevBtn) prevBtn.addEventListener('click', function () { setWizardStep(currentIndex - 1, true); });
  if (nextBtn) nextBtn.addEventListener('click', function () {
    var key = wizardKeys[currentIndex];
    if (key === 'review') {
      if (publishBtn) publishBtn.click();
      else if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.submit();
      return;
    }
    if (!validateCurrentStep()) return;
    setWizardStep(currentIndex + 1, true);
  });

  form.querySelectorAll('[data-br-scope-preset]').forEach(function (el) {
    el.addEventListener('change', function () {
      applyPreset(el.value);
      if (!isEdit) setWizardStep(0, false);
      schedulePreview();
    });
  });
  form.addEventListener('change', function (e) {
    if (!e.target) return;
    if (e.target.getAttribute('data-br-wall-door') === '1') {
      syncWallDoorFromToggle();
      schedulePreview();
      return;
    }
    if (e.target.hasAttribute('data-br-inline-client-provides')) {
      var sec = e.target.getAttribute('data-br-inline-client-provides') || '';
      var auth = form.querySelector('input[name="client_provides_sections[]"][value="' + sec + '"]');
      if (auth) {
        auth.checked = !!e.target.checked;
        try {
          auth.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err) {}
      }
      schedulePreview();
      return;
    }
    if (e.target.name === 'client_provides_sections[]') {
      syncInlineClientProvides();
    }
  });
  form.addEventListener('input', function (e) {
    if (!e.target) return;
    if (e.target.id === 'br-frame-doors' || e.target.id === 'br-doors') {
      syncWallDoorToggleFromOpenings();
    }
    if (
      e.target.id === 'br-partition'
      || e.target.id === 'br-area'
      || e.target.id === 'br-perimeter-lf'
      || e.target.id === 'br-height'
      || e.target.id === 'br-dw-wall'
      || e.target.id === 'br-ceil-sf'
    ) {
      syncPartitionHelpers();
      syncDemoDrywallHelper();
    }
  });
  form.addEventListener('click', function (e) {
    var switchAllBtn = e.target && e.target.closest ? e.target.closest('[data-br-switch-all-sections]') : null;
    if (switchAllBtn) {
      e.preventDefault();
      setAllSectionsMode(true);
      return;
    }
    var jumpReviewBtn = e.target && e.target.closest ? e.target.closest('[data-br-jump-review]') : null;
    if (jumpReviewBtn) {
      e.preventDefault();
      jumpToSection('review');
      return;
    }
    var fillDemoBtn = e.target && e.target.closest ? e.target.closest('#br-fill-demo-drywall') : null;
    if (fillDemoBtn) {
      e.preventDefault();
      var suggested = computeDemoDrywallSuggestedSf();
      if (suggested > 0) {
        setLwToSqft('br-demo-dw', suggested);
        var dwEl = document.getElementById('br-demo-dw');
        if (dwEl) {
          try {
            dwEl.dispatchEvent(new Event('input', { bubbles: true }));
            dwEl.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) {}
        }
        schedulePreview();
      }
      return;
    }
    var dwPartBtn = e.target && e.target.closest ? e.target.closest('#br-dw-add-partition-btn') : null;
    if (dwPartBtn) {
      e.preventDefault();
      var partEl = document.getElementById('br-partition');
      var partLf = partEl ? parseFloat(partEl.value) : 0;
      if (isFinite(partLf) && partLf > 0) {
        var basePeri = currentPreset() === 'wall' ? 0 : perimeterOrSquareLf();
        var h = ceilingHeightFt();
        var targetWall = Math.round((basePeri * h + partLf * h * 2) * 100) / 100;
        setLwToSqft('br-dw-wall', targetWall);
        var dwWallInput = document.getElementById('br-dw-wall');
        if (dwWallInput) {
          dwWallInput.removeAttribute('data-br-from-layout');
          try {
            dwWallInput.dispatchEvent(new Event('input', { bubbles: true }));
            dwWallInput.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) {}
        }
        syncDemoDrywallHelper();
        schedulePreview();
      }
      return;
    }
    var basePartBtn = e.target && e.target.closest ? e.target.closest('#br-base-add-partition-btn') : null;
    if (basePartBtn) {
      e.preventDefault();
      var partEl2 = document.getElementById('br-partition');
      var partLf2 = partEl2 ? parseFloat(partEl2.value) : 0;
      if (isFinite(partLf2) && partLf2 > 0) {
        var basePeri2 = currentPreset() === 'wall' ? 0 : perimeterOrSquareLf();
        var targetBase = Math.round((basePeri2 + partLf2 * 2) * 100) / 100;
        var baseInput = document.getElementById('br-base');
        if (baseInput) {
          baseInput.value = formatLayoutQty(targetBase);
          try {
            baseInput.dispatchEvent(new Event('input', { bubbles: true }));
            baseInput.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) {}
        }
        schedulePreview();
      }
      return;
    }
    var elecGenBtn = e.target && e.target.closest ? e.target.closest('#br-elec-generate-lines') : null;
    if (elecGenBtn) {
      e.preventDefault();
      generateElectricalLines(elecGenBtn);
      return;
    }
    var plumbGenBtn = e.target && e.target.closest ? e.target.closest('#br-plumb-generate-lines') : null;
    if (plumbGenBtn) {
      e.preventDefault();
      generatePlumbingLines(plumbGenBtn);
      return;
    }
    var rowGoto = e.target && e.target.closest ? e.target.closest('[data-br-goto-row]') : null;
    if (rowGoto) {
      e.preventDefault();
      jumpToSection(rowGoto.getAttribute('data-br-goto-row'));
    }
  });
  form.querySelectorAll('[data-br-enabled-section]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      if (cb.getAttribute('data-br-enabled-section') === 'bathroom' && currentPreset() === 'flood') {
        setNamedCheck('include_bathroom', cb.checked);
        syncRevealTargets();
      }
      applyScope();
      if (!isEdit) setWizardStep(currentIndex, false);
    });
  });
  var floodBathOptIn = document.getElementById('br-flood-bath-use-restoration');
  if (floodBathOptIn) {
    floodBathOptIn.addEventListener('change', function () {
      applyScope();
    });
  }

  form.querySelectorAll('[data-br-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-br-goto');
      jumpToSection(key);
    });
  });

  function schedulePreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runPreview, 280);
  }

  function syncClientProvidesFormData(fd) {
    ['client_provides_sections[]', 'client_provides_bath_items[]'].forEach(function (fieldName) {
      fd.delete(fieldName);
      form.querySelectorAll('input.admin-checkbox__input[name="' + fieldName + '"]').forEach(function (cb) {
        if (cb.checked && !cb.disabled) {
          fd.append(fieldName, cb.value || '');
        }
      });
    });
  }

  function runPreview() {
    if (!previewUrl) return;
    var fd = new FormData(form);
    form.querySelectorAll('input.admin-checkbox__input[name]').forEach(function (cb) {
      var name = cb.name || '';
      if (!name || name.indexOf('[') !== -1) return;
      fd.set(name, cb.checked ? (cb.value || '1') : '0');
    });
    syncClientProvidesFormData(fd);
    form.querySelectorAll('.cp-money-input[name]').forEach(function (input) {
      fd.set(input.name, (input.value || '').replace(/,/g, ''));
    });
    fd.delete('_method');
    fetch(previewUrl, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
      body: fd,
      credentials: 'same-origin'
    }).then(function (r) {
      return r.ok ? r.json() : null;
    }).then(function (data) {
      if (data) renderPreview(data);
    }).catch(function () {});
  }

  function updateToolbarGrand(grand) {
    var amountEl = document.getElementById('br-toolbar-grand-amount');
    if (amountEl) {
      amountEl.textContent = fmt(grand);
    }
  }

  var SECTION_FALLBACK_LABELS = {
    demolition: 'Demolition & Preparation',
    framing: 'Framing',
    electrical: 'Electrical',
    plumbing: 'Plumbing',
    insulation: 'Insulation & Vapour Barrier',
    ceiling: 'Ceiling Options',
    drywall: 'Drywall / Mud / Tape / Sand',
    flooring: 'Flooring',
    doors_trim: 'Doors, Trim & Baseboards',
    painting: 'Painting',
    bathroom: 'Bathroom',
    finishing: 'Final Details & Cleanup'
  };

  function sectionDisplayLabel(key) {
    var btn = form.querySelector('.br-progress__item[data-br-goto="' + key + '"] .br-progress__label');
    if (btn && String(btn.textContent || '').trim() !== '') {
      return String(btn.textContent || '').trim();
    }
    return SECTION_FALLBACK_LABELS[key] || key;
  }

  function renderPreview(data) {
    lastPreviewData = data;
    var empty = document.getElementById('br-live-empty');
    var body = document.getElementById('br-live-body');
    var summary = data.summary || {};
    rebuildLaborTable(data.labor || []);
    rebuildMatTable(data.materials || []);
    var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
    updateToolbarGrand(grand);
    window.__quoteGrandTotal = grand;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: grand } }));
    if (!summary.grand_total || summary.grand_total <= 0) {
      if (empty) empty.hidden = false;
      if (body) body.hidden = true;
      updateDrywallNetHint(data.scope || {});
      updateClientScopeFromPreview(data);
      updateClientScopeAllowanceStale(data);
      updateSectionSubtotals(data);
      updateClientProvidesSectionState(data);
      return;
    }
    if (empty) empty.hidden = true;
    if (body) body.hidden = false;
    function liveRow(opts) {
      var cls = 'br-live-row';
      if (opts.trade) cls += ' br-live-row--trade br-live-trade-row';
      if (opts.emph) cls += ' br-live-row--emph';
      if (opts.grand) cls += ' br-live-row--grand';
      if (opts.zero) cls += ' br-live-row--zero';
      var attrs = '';
      if (opts.reconKey) attrs += ' data-br-recon-key="' + escapeHtml(opts.reconKey) + '"';
      if (opts.goto) attrs += ' data-br-goto-row="' + escapeHtml(opts.goto) + '"';
      var tag = opts.trade ? 'button' : 'div';
      var typeAttr = opts.trade ? ' type="button"' : '';
      return '<' + tag + typeAttr + ' class="' + cls + '"' + attrs + '>'
        + '<span class="br-live-row__label">' + escapeHtml(opts.label) + '</span>'
        + '<span class="br-live-row__value">' + opts.valueHtml + '</span>'
        + '</' + tag + '>';
    }
    function liveGroup(title, rowsHtml, extraAttrs) {
      if (!rowsHtml) return '';
      return '<div class="br-live-group"' + (extraAttrs || '') + '>'
        + '<p class="br-live-group__title">' + escapeHtml(title) + '</p>'
        + rowsHtml
        + '</div>';
    }
    var html = '';
    var wasteHtml = '';
    var wastePct = Number(summary.material_waste_percent);
    if (isFinite(wastePct) && wastePct > 0) {
      wasteHtml += liveRow({ label: 'Material waste', valueHtml: escapeHtml(String(wastePct) + '%') });
    }
    var floorWastePct = Number(summary.flooring_waste_percent);
    if (isFinite(floorWastePct) && floorWastePct >= 0 && document.getElementById('br-fl-waste')) {
      wasteHtml += liveRow({ label: 'Flooring waste', valueHtml: escapeHtml(String(floorWastePct) + '%') });
    }
    html += liveGroup('Waste settings', wasteHtml, ' data-br-live-group="waste"');
    var sectionSubtotals = summary.section_subtotals_pre_hst || {};
    var enabledSections = enabledTradeKeys();
    var tradeRowsHtml = '';
    allSectionKeys.forEach(function (k) {
      if (k === 'overview') return;
      if (enabledSections.indexOf(k) === -1) return;
      var subAmt = Number(sectionSubtotals[k] || 0);
      tradeRowsHtml += liveRow({
        trade: true,
        zero: !(subAmt > 0),
        goto: k,
        label: sectionDisplayLabel(k),
        valueHtml: fmt(subAmt)
      });
    });
    html += liveGroup('Trade breakdown', tradeRowsHtml, ' data-br-live-group="trades" data-br-live-trades="1"');
    var recon = summary.reconciliation || {};
    var reconLines = recon.lines || [];
    if (reconLines.length) {
      var feeHtml = '';
      var taxHtml = '';
      var grandHtml = '';
      var feeKeys = { sections_subtotal_sum: 1, extra_materials: 1, contractor_fee: 1 };
      var taxKeys = { electrical_tax_in: 1, plumbing_tax_in: 1, hst: 1 };
      reconLines.forEach(function (line) {
        var lineAmount = Number(line.amount || 0);
        if (line.key === 'grand_total') lineAmount = grand;
        var row = liveRow({
          reconKey: line.key || '',
          emph: line.key === 'subtotal_pre_hst',
          grand: line.key === 'grand_total',
          zero: line.key !== 'grand_total' && line.key !== 'subtotal_pre_hst' && !(lineAmount > 0),
          label: line.label || '',
          valueHtml: fmt(lineAmount)
        });
        if (line.key === 'grand_total') grandHtml = row;
        else if (taxKeys[line.key]) taxHtml += row;
        else if (feeKeys[line.key] || line.key === 'subtotal_pre_hst') feeHtml += row;
        else feeHtml += row;
        if (line.key === 'contractor_fee') {
          if (lastCommuteAmount > 0) {
            feeHtml += liveRow({ label: 'Commute (incl. HST)', valueHtml: fmt(lastCommuteAmount) });
          }
          if (lastDebrisAmount > 0) {
            feeHtml += liveRow({ label: 'Debris removal (incl. HST)', valueHtml: fmt(lastDebrisAmount) });
          }
        }
      });
      if (lastAdditionalTaxIn > 0) {
        taxHtml += liveRow({
          reconKey: 'additional_costs_tax_in',
          label: 'Additional costs (incl. HST)',
          valueHtml: fmt(lastAdditionalTaxIn)
        });
      }
      html += '<div class="br-reconciliation" data-br-reconciliation="1">'
        + liveGroup('Subtotals and fees', feeHtml, ' data-br-live-group="fees"')
        + liveGroup('Taxes', taxHtml, ' data-br-live-group="taxes"')
        + (grandHtml ? '<div class="br-live-footer" data-br-live-group="grand">' + grandHtml + '</div>' : '')
        + '</div>';
    }
    body.innerHTML = html;
    updateDrywallNetHint(data.scope || {});
    updateClientScopeFromPreview(data);
    updateClientScopeAllowanceStale(data);
    updateSectionSubtotals(data);
    updateClientProvidesSectionState(data);
  }

  function updateClientScopeAllowanceStale(data) {
    var field = document.getElementById('br-scope-allowances');
    if (!field || !clientScopeFieldLocked(field)) return;
    var scope = data && data.client_scope;
    if (!scope) return;
    var stale = !!scope.allowances_stale;
    var banner = document.getElementById('br-scope-allowances-stale');
    if (stale && !banner) {
      var help = document.createElement('p');
      help.className = 'admin-form-help br-scope-stale';
      help.id = 'br-scope-allowances-stale';
      help.setAttribute('role', 'status');
      help.innerHTML = 'Allowance amounts no longer match live electrical, plumbing, or permit lines. '
        + '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" id="br-scope-allowances-reset">Reset to current amounts</button>';
      field.parentNode.insertBefore(help, field);
      bindAllowancesReset(help.querySelector('#br-scope-allowances-reset'));
    } else if (!stale && banner) {
      banner.remove();
      field.removeAttribute('data-br-scope-allowances-stale');
    } else if (stale) {
      field.setAttribute('data-br-scope-allowances-stale', '1');
    }
  }

  function bindAllowancesReset(btn) {
    if (!btn || btn.dataset.brBound === '1') return;
    btn.dataset.brBound = '1';
    btn.addEventListener('click', function () {
      var field = document.getElementById('br-scope-allowances');
      var scope = lastPreviewData && lastPreviewData.client_scope;
      var resetText = scope && scope.allowances_reset;
      if (resetText == null || resetText === '') {
        resetText = scope && scope.generated && scope.generated.allowances;
      }
      if (!field || resetText == null || resetText === '') return;
      field.value = String(resetText).replace(/\r\n/g, '\n').replace(/\r/g, '\n');
      field.setAttribute('data-br-scope-dirty', '1');
      var banner = document.getElementById('br-scope-allowances-stale');
      if (banner) banner.remove();
      field.removeAttribute('data-br-scope-allowances-stale');
      schedulePreview();
    });
  }

  function sectionHasPricedMaterials(data, sectionKey) {
    var mats = (data && data.materials) || [];
    return mats.some(function (row) {
      return String(row.section || '') === sectionKey && Number(row.amount || 0) > 0;
    });
  }

  function bathroomSectionEnabled() {
    var el = form.querySelector('input[type="checkbox"][name="include_bathroom"]');
    return el ? !!el.checked : false;
  }

  function bathItemHasPricedMaterials(data, itemKey) {
    var mats = (data && data.materials) || [];
    var keys = {
      vanity: ['vanity'],
      pedestal_sink: ['pedestal_sink'],
      toilet: ['toilet'],
      shower_frame: ['shower_kit'],
      tub: ['tub_kit'],
      exhaust_fan: ['exhaust_fan']
    };
    var want = keys[itemKey] || [itemKey];
    return mats.some(function (row) {
      var k = String(row.key || '');
      return want.indexOf(k) !== -1 && Number(row.amount || 0) > 0;
    });
  }

  function applyClientProvidesInert(cb, inert, tooltip) {
    var checked = !!cb.checked;
    var disable = !checked && inert;
    cb.disabled = disable;
    var row = cb.closest('label') || cb.parentElement;
    if (row) {
      row.classList.toggle('br-client-provides--inert', disable);
      row.title = disable ? (tooltip || '') : '';
    }
  }

  function syncInlineClientProvides() {
    var allOn = !!(clientAll && clientAll.checked);
    form.querySelectorAll('[data-br-inline-client-provides]').forEach(function (inlineCb) {
      var sec = inlineCb.getAttribute('data-br-inline-client-provides') || '';
      var auth = form.querySelector('input[name="client_provides_sections[]"][value="' + sec + '"]');
      if (!auth) return;
      var hideInline = allOn || !!auth.hidden;
      inlineCb.checked = !!auth.checked;
      inlineCb.disabled = allOn || !!auth.disabled;
      inlineCb.hidden = hideInline;
      var inlineRow = inlineCb.closest('.br-inline-client-provides') || inlineCb.closest('label') || inlineCb.parentElement;
      if (inlineRow && inlineRow !== form) {
        inlineRow.hidden = hideInline;
        inlineRow.classList.toggle('br-client-provides--inert', inlineCb.disabled && !inlineCb.checked);
        var authRow = auth.closest('label') || auth.parentElement;
        if (authRow) inlineRow.title = authRow.title || '';
      }
      var footerWrap = inlineCb.closest('.br-section-footer__client-provides');
      if (footerWrap) {
        footerWrap.hidden = hideInline;
      }
    });
  }

  function updateClientProvidesSectionState(data) {
    var enabled = enabledTradeKeys();
    form.querySelectorAll('input[name="client_provides_sections[]"]').forEach(function (cb) {
      var key = cb.value || '';
      var sectionOn = enabled.indexOf(key) !== -1;
      var hasMats = sectionHasPricedMaterials(data, key);
      var inert = !sectionOn || !hasMats;
      applyClientProvidesInert(
        cb,
        inert,
        !sectionOn
          ? 'This trade is off for this quotation.'
          : 'This trade has no priced materials to omit.'
      );
    });
    var bathOn = bathroomSectionEnabled();
    form.querySelectorAll('input[name="client_provides_bath_items[]"]').forEach(function (cb) {
      var key = cb.value || '';
      var hasMats = bathItemHasPricedMaterials(data, key);
      var inert = !bathOn || !hasMats;
      applyClientProvidesInert(
        cb,
        inert,
        !bathOn
          ? 'Bathroom is off for this quotation.'
          : 'This item has no priced materials to omit.'
      );
    });
    syncInlineClientProvides();
  }

  function ceilingHangPricedInDrywallSection() {
    var el = document.getElementById('br-ceil-type');
    if (!el) return false;
    var v = el.value;
    return v === 'drywall' || v === 'paint';
  }

  function updateCeilingDrywallPricedNote() {
    var note = document.getElementById('br-ceiling-drywall-priced-note');
    if (!note) return;
    var wrap = form.querySelector('[data-br-section-subtotal="ceiling"]');
    var show = !!wrap && !wrap.hidden && ceilingHangPricedInDrywallSection();
    note.hidden = !show;
  }

  function electricalScopeCount() {
    return formIntVal('outlet_qty')
      + formIntVal('switch_qty')
      + formIntVal('light_qty')
      + formIntVal('fixture_qty')
      + formIntVal('detector_qty')
      + (formChecked('include_bath_fixture') ? formIntVal('bath_fixture_qty') : 0)
      + (formChecked('include_bath_spot') ? formIntVal('bath_spot_qty') : 0)
      + (formChecked('panel_upgrade') ? 1 : 0);
  }

  function plumbingScopeCount() {
    if (!formChecked('include_plumbing')) return 0;
    return ['bath_rough_in', 'sink_laundry', 'wet_bar', 'floor_drain', 'wh_reloc'].reduce(function (sum, k) {
      return sum + (formChecked(k) ? 1 : 0);
    }, 0);
  }

  function updateStepRailMeta(data) {
    var summary = (data && data.summary) || {};
    var scope = (data && data.scope) || {};
    var map = summary.section_subtotals_pre_hst || {};
    var enabled = enabledTradeKeys();
    var areaEl = document.getElementById('br-area');
    var areaDom = areaEl ? (parseFloat(areaEl.value) || 0) : 0;
    var areaSqft = Number(scope.area_sqft != null ? scope.area_sqft : areaDom) || areaDom;
    var partEl = document.getElementById('br-partition');
    var partLf = partEl ? (parseFloat(partEl.value) || 0) : 0;
    var periEl = document.getElementById('br-perimeter-lf');
    var periLf = periEl ? (parseFloat(periEl.value) || 0) : 0;
    var presetNow = currentPreset();
    var overviewReady = presetNow === 'wall'
      ? partLf > 0
      : (presetNow === 'flood' ? (areaSqft > 0 || periLf > 0 || partLf > 0) : areaSqft > 0);
    var hasWork = overviewReady;
    var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;

    form.querySelectorAll('.br-progress__item[data-br-goto]').forEach(function (btn) {
      var key = btn.getAttribute('data-br-goto') || '';
      var meta = btn.querySelector('[data-br-step-meta]');
      btn.classList.remove('is-ready', 'is-attention');

      if (key === 'overview') {
        if (overviewReady) {
          btn.classList.add('is-ready');
          if (meta) meta.textContent = '✓ Ready';
        } else {
          btn.classList.add('is-attention');
          if (meta) meta.textContent = 'Required';
        }
        return;
      }

      if (key === 'client_scope') {
        if (hasWork) {
          btn.classList.add('is-ready');
          if (meta) meta.textContent = 'Ready';
        } else if (meta) {
          meta.textContent = '';
        }
        return;
      }

      if (key === 'review') {
        if (grand > 0) {
          btn.classList.add('is-ready');
          if (meta) meta.textContent = fmtCompact(grand);
        } else {
          if (meta) meta.textContent = 'Ready';
        }
        return;
      }

      var on = enabled.indexOf(key) !== -1;
      if (!on) {
        if (meta) meta.textContent = '';
        return;
      }
      var amt = Number(map[key] || 0);
      if (amt > 0) {
        btn.classList.add('is-ready');
        if (meta) meta.textContent = fmtCompact(amt);
        return;
      }

      if (key === 'demolition') {
        var demoChoice = form.querySelector('[data-br-demo-choice]:checked');
        if (!demoChoice || demoChoice.value === '1') {
          btn.classList.add('is-attention');
          if (meta) meta.textContent = 'Check';
        } else if (meta) {
          meta.textContent = fmt(0);
        }
        return;
      }

      if (key === 'electrical' || key === 'plumbing') {
        var hasScope = (key === 'electrical' && electricalScopeCount() > 0)
          || (key === 'plumbing' && plumbingScopeCount() > 0);
        if (hasWork || hasScope) {
          btn.classList.add('is-attention');
          if (meta) meta.textContent = hasScope ? 'Check' : fmt(0);
        } else if (meta) {
          meta.textContent = '';
        }
        return;
      }

      if (hasWork) {
        btn.classList.add('is-attention');
        if (meta) meta.textContent = fmt(0);
      } else if (meta) {
        meta.textContent = '';
      }
    });
  }

  function updateSectionSubtotals(data) {
    if (data && data.summary) {
      var map = data.summary.section_subtotals_pre_hst || {};
      var enabled = enabledTradeKeys();
      form.querySelectorAll('[data-br-section-subtotal]').forEach(function (wrap) {
        var key = wrap.getAttribute('data-br-section-subtotal') || '';
        var on = enabled.indexOf(key) !== -1;
        var amt = Number(map[key] || 0);
        var val = wrap.querySelector('[data-br-section-subtotal-amount]');
        if (val) {
          val.textContent = fmt(amt);
        }
        wrap.hidden = !on;
      });
      updateCeilingDrywallPricedNote();
    }
    updateStepRailMeta(data);
  }

  function formIntVal(name) {
    var el = form.querySelector('[name="' + name + '"]');
    var n = el ? parseInt(el.value || '0', 10) : 0;
    return isFinite(n) && n > 0 ? n : 0;
  }

  function formChecked(name) {
    var el = form.querySelector('input[type="checkbox"][name="' + name + '"]:checked');
    return !!el;
  }

  function normalizeTradePrefix(desc) {
    return String(desc || '').replace(/^\d+x\s+/i, '').trim().toLowerCase();
  }

  function setGeneratorStatus(statusEl, message) {
    if (!statusEl) return;
    statusEl.textContent = message || '';
    statusEl.hidden = !message;
  }

  function fillTradeRow(row, item) {
    if (!row) return;
    var descEl = row.querySelector('[data-ac-description]');
    var amtEl = row.querySelector('[data-ac-amount]');
    var hstEl = row.querySelector('[data-ac-hst-toggle]');
    if (descEl) {
      descEl.value = item.description;
      try {
        descEl.dispatchEvent(new Event('input', { bubbles: true }));
        descEl.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (err) {}
    }
    if (amtEl) {
      amtEl.value = Number(item.amount || 0).toFixed(2);
      if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
        window.cpMoneyMask.wire(amtEl);
      }
      try {
        amtEl.dispatchEvent(new Event('input', { bubbles: true }));
        amtEl.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (err) {}
    }
    if (hstEl) {
      hstEl.checked = false;
      try {
        hstEl.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (err) {}
    }
  }

  function appendGeneratedTradeLines(cardSelector, lines, statusEl) {
    var card = document.querySelector(cardSelector);
    if (!card) return 0;
    if (!lines || !lines.length) {
      setGeneratorStatus(statusEl, 'No scope items selected to generate.');
      return 0;
    }
    var existingRows = Array.prototype.slice.call(card.querySelectorAll('.q-ac-row, [data-ac-row]'));
    var existingPrefixes = {};
    existingRows.forEach(function (row) {
      var d = row.querySelector('[data-ac-description]');
      var norm = normalizeTradePrefix(d ? d.value : '');
      if (norm) existingPrefixes[norm] = true;
    });
    var added = 0;
    var addBtn = card.querySelector('[data-ac-add]');
    lines.forEach(function (item) {
      var norm = normalizeTradePrefix(item.description);
      if (!norm || existingPrefixes[norm]) return;
      var targetRow = null;
      var currentRows = card.querySelectorAll('.q-ac-row, [data-ac-row]');
      for (var i = 0; i < currentRows.length; i++) {
        var dEl = currentRows[i].querySelector('[data-ac-description]');
        var aEl = currentRows[i].querySelector('[data-ac-amount]');
        if (dEl && aEl && String(dEl.value || '').trim() === '' && String(aEl.value || '').trim() === '') {
          targetRow = currentRows[i];
          break;
        }
      }
      if (!targetRow && addBtn) {
        addBtn.click();
        var afterRows = card.querySelectorAll('.q-ac-row, [data-ac-row]');
        targetRow = afterRows.length ? afterRows[afterRows.length - 1] : null;
      }
      if (targetRow) {
        fillTradeRow(targetRow, item);
        existingPrefixes[norm] = true;
        added += 1;
      }
    });
    if (added > 0) {
      setGeneratorStatus(statusEl, 'Added ' + added + ' allowance line' + (added === 1 ? '' : 's') + '.');
      schedulePreview();
    } else {
      setGeneratorStatus(statusEl, 'All selected items are already in the quote.');
    }
    return added;
  }

  function generateElectricalLines(btn) {
    var lines = [];
    var outletQty = formIntVal('outlet_qty');
    if (outletQty > 0) {
      lines.push({ description: outletQty + 'x Basement electrical receptacle outlet(s)', amount: outletQty * 95 });
    }
    var switchQty = formIntVal('switch_qty');
    if (switchQty > 0) {
      lines.push({ description: switchQty + 'x Wall switch(es) & 3-way controls', amount: switchQty * 85 });
    }
    var lightTypeEl = form.querySelector('[name="lighting_type"]');
    var lightType = lightTypeEl ? String(lightTypeEl.value || 'spot') : 'spot';
    var lightQty = formIntVal('light_qty');
    if (lightQty > 0) {
      if (lightType === 'fixture') {
        lines.push({ description: lightQty + 'x Ceiling surface light fixture(s)', amount: lightQty * 145 });
      } else {
        lines.push({ description: lightQty + 'x Recessed LED pot light(s)', amount: lightQty * 135 });
      }
    }
    var fixtureQty = formIntVal('fixture_qty');
    if (lightType === 'both' && fixtureQty > 0) {
      lines.push({ description: fixtureQty + 'x Ceiling light fixture(s)', amount: fixtureQty * 145 });
    }
    var bathOn = bathroomSectionEnabled() || currentPreset() === 'bathroom';
    var bathFixQty = formIntVal('bath_fixture_qty');
    if (bathOn && formChecked('include_bath_fixture') && bathFixQty > 0) {
      lines.push({ description: bathFixQty + 'x Bathroom vanity/ceiling fixture(s)', amount: bathFixQty * 145 });
    }
    var bathSpotQty = formIntVal('bath_spot_qty');
    if (bathOn && formChecked('include_bath_spot') && bathSpotQty > 0) {
      lines.push({ description: bathSpotQty + 'x Bathroom shower/ceiling pot light(s)', amount: bathSpotQty * 135 });
    }
    var detectorQty = formIntVal('detector_qty');
    if (detectorQty > 0) {
      lines.push({ description: detectorQty + 'x Interconnected smoke / CO detector(s) (OBC)', amount: detectorQty * 165 });
    }
    if (formChecked('panel_upgrade')) {
      lines.push({ description: 'Electrical panel / sub-panel capacity upgrade', amount: 2400 });
    }
    var statusEl = document.getElementById('br-elec-generator-hint')
      || document.getElementById('br-elec-gen-status')
      || (btn && btn.parentElement && btn.parentElement.querySelector('[data-br-gen-status], [role="status"]'));
    appendGeneratedTradeLines('#br-electrical-fields [data-ac-card]', lines, statusEl);
  }

  function generatePlumbingLines(btn) {
    var incPlumb = form.querySelector('input[type="checkbox"][name="include_plumbing"]');
    if (incPlumb && !incPlumb.checked) {
      incPlumb.checked = true;
      var plumbFields = document.getElementById('br-plumb-fields');
      if (plumbFields) plumbFields.hidden = false;
      try {
        incPlumb.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (err) {}
    }
    var lines = [];
    if (formChecked('bath_rough_in')) {
      lines.push({ description: '3-piece basement bathroom drain, vent & supply rough-in', amount: 2400 });
    }
    if (formChecked('sink_laundry')) {
      lines.push({ description: 'Laundry / utility sink rough-in', amount: 650 });
    }
    if (formChecked('wet_bar')) {
      lines.push({ description: 'Wet bar sink drain & water supply rough-in', amount: 850 });
    }
    if (formChecked('floor_drain')) {
      lines.push({ description: 'Mechanical / basement floor drain & trap', amount: 450 });
    }
    if (formChecked('wh_reloc')) {
      lines.push({ description: 'Water heater relocation', amount: 750 });
    }
    var statusEl = document.getElementById('br-plumb-generator-hint')
      || document.getElementById('br-plumb-gen-status')
      || (btn && btn.parentElement && btn.parentElement.querySelector('[data-br-gen-status], [role="status"]'));
    appendGeneratedTradeLines('#br-plumbing-fields [data-ac-card]', lines, statusEl);
  }

  function updateDrywallNetHint(scope) {
    var hint = document.getElementById('br-dw-net-hint');
    if (!hint) return;
    var net = Number(scope.drywall_wall_net_sf || 0);
    var opening = Number(scope.drywall_opening_sf || 0);
    var sheet = scope.drywall_sheet_size || '4x8';
    var sheetSf = Number(scope.drywall_sheet_sf || 32);
    if (net <= 0 && opening <= 0) {
      hint.hidden = true;
      hint.textContent = '';
      return;
    }
    var parts = [];
    if (opening > 0) {
      parts.push(formatLayoutQty(opening) + ' sq ft openings deducted');
    }
    if (net > 0) {
      parts.push(formatLayoutQty(net) + ' sq ft net walls');
    }
    parts.push(sheet + ' sheets (' + formatLayoutQty(sheetSf) + ' sq ft each)');
    hint.hidden = false;
    hint.textContent = parts.join(' · ') + '.';
  }

  function clientScopeFieldLocked(el) {
    return el.getAttribute('data-br-scope-dirty') === '1'
      || el.getAttribute('data-br-scope-persisted') === '1';
  }

  function hydratePersistedClientScope() {
    form.querySelectorAll('[data-br-scope-field]').forEach(function (el) {
      if (el.getAttribute('data-br-scope-persisted') === '1') {
        el.setAttribute('data-br-scope-dirty', '1');
      }
    });
  }

  function updateClientScopeFromPreview(data) {
    var generated = data && data.client_scope && data.client_scope.generated;
    if (!generated) return;
    form.querySelectorAll('[data-br-scope-field]').forEach(function (el) {
      if (clientScopeFieldLocked(el)) return;
      var key = el.getAttribute('data-br-scope-field');
      if (generated[key] != null) el.value = generated[key];
    });
  }

  function tradeBreakdownHtml(label, trade) {
    var taxIn = Number((trade && trade.tax_in) || 0);
    if (taxIn <= 0) {
      return '';
    }
    return '<div class="pq-row pq-row--group"><span>' + label + ' (incl. HST)</span><span>' + fmt(taxIn) + '</span></div>';
  }

  function rebuildLaborTable(rows) {
    var tbody = document.getElementById('br-labor-rows');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter the basement area to generate labour lines.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (row) {
      var trade = escapeHtml(row.label);
      if (row.detail) trade += ' <span class="admin-form-help">- ' + escapeHtml(row.detail) + '</span>';
      return '<tr data-br-labor-key="' + escapeHtml(row.key) + '">'
        + '<td>' + trade + '</td>'
        + '<td data-br-labor-hrs>' + Number(row.hours || 0).toFixed(2) + '</td>'
        + '<td class="cp-col-amount" data-br-labor-amt>' + fmt(row.amount || 0) + '</td>'
        + '</tr>';
    }).join('');
  }

  function rebuildMatTable(rows) {
    var tbody = document.getElementById('br-mat-rows');
    if (!tbody) return;
    if (!rows.length) {
      tbody.innerHTML = '<tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after quantities are entered.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (row) {
      var provided = row.client_provided ? ' <span class="admin-form-help"> · client provided</span>' : '';
      return '<tr data-br-mat-key="' + escapeHtml(row.key) + '">'
        + '<td>' + escapeHtml(row.label) + provided + '</td>'
        + '<td>' + Number(row.qty || 0).toFixed(2) + '</td>'
        + '<td>' + fmt(row.unit_price || 0) + '</td>'
        + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td>'
        + '</tr>';
    }).join('');
  }

  function bindPreview(el) {
    if (el.dataset.brBound === '1') return;
    el.dataset.brBound = '1';
    el.addEventListener('input', schedulePreview);
    el.addEventListener('keyup', schedulePreview);
    el.addEventListener('change', schedulePreview);
  }

  function syncRateOverride(input) {
    if (!input) return;
    var wrap = input.closest ? input.closest('[data-br-rate]') : null;
    if (!wrap) return;
    var posted = String(input.value || '').replace(/[$,\s]/g, '');
    var sharedRaw = input.getAttribute('data-br-rate-shared');
    var sharedNum = sharedRaw === null || sharedRaw === '' ? null : Number(sharedRaw);
    var postedNum = posted === '' ? null : Number(posted);
    var custom = postedNum !== null && sharedNum !== null && isFinite(postedNum) && isFinite(sharedNum)
      && Math.abs(postedNum - sharedNum) >= 0.001;
    if (wrap.classList) wrap.classList.toggle('is-custom', custom);
    var hint = wrap.querySelector('[data-br-rate-custom]');
    var reset = wrap.querySelector('[data-br-rate-reset]');
    if (hint) hint.hidden = !custom;
    if (reset) reset.hidden = !custom;
  }

  function bindRateOverrides() {
    form.querySelectorAll('[data-br-rate-input]').forEach(function (input) {
      if (input.dataset && input.dataset.brRateBound === '1') return;
      if (input.dataset) input.dataset.brRateBound = '1';
      var sync = function () { syncRateOverride(input); };
      input.addEventListener('input', sync);
      input.addEventListener('change', sync);
      input.addEventListener('blur', sync);
      var wrap = input.closest ? input.closest('[data-br-rate]') : null;
      var reset = wrap ? wrap.querySelector('[data-br-rate-reset]') : null;
      if (reset && reset.dataset && reset.dataset.brRateResetBound !== '1') {
        reset.dataset.brRateResetBound = '1';
        reset.addEventListener('click', function () {
          input.value = input.getAttribute('data-br-rate-shared') || '';
          try {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) {}
          syncRateOverride(input);
        });
      }
      sync();
    });
  }

  function initExclusiveChecks() {
    var groups = {};
    form.querySelectorAll('[data-br-exclusive-group]').forEach(function (el) {
      var group = el.getAttribute('data-br-exclusive-group');
      if (!group) return;
      if (!groups[group]) groups[group] = [];
      groups[group].push(el);
      el.addEventListener('change', function () {
        if (!el.checked) {
          syncBathSinkQty();
          return;
        }
        groups[group].forEach(function (other) {
          if (other !== el) other.checked = false;
        });
        syncBathSinkQty();
      });
    });
    Object.keys(groups).forEach(function (group) {
      var checked = groups[group].filter(function (el) { return el.checked; });
      if (checked.length <= 1) return;
      var keep = checked[checked.length - 1];
      checked.forEach(function (el) {
        if (el !== keep) el.checked = false;
      });
    });
  }

  function syncBathSinkQty() {
    var vanity = form.querySelector('#br-include-vanity');
    var pedestal = form.querySelector('#br-include-pedestal');
    var wrap = document.getElementById('br-bath-sink-qty');
    if (!wrap) return;
    wrap.hidden = !((vanity && vanity.checked) || (pedestal && pedestal.checked));
  }

  function resizeProjectDescriptionEditor() {
    if (typeof tinymce === 'undefined' || !tinymce.get) return;
    var ed = tinymce.get('br-work-description');
    if (!ed) return;
    requestAnimationFrame(function () {
      try {
        if (typeof ed.dispatch === 'function') ed.dispatch('ResizeEditor');
        else if (typeof ed.fire === 'function') ed.fire('ResizeEditor');
      } catch (err) {}
    });
  }

  function initProjectDescriptionDisclosure() {
    var acc = document.getElementById('br-desc-acc');
    if (!acc) return;
    var summary = acc.querySelector('.br-desc-acc__summary');
    function sync() {
      if (summary) summary.setAttribute('aria-expanded', acc.open ? 'true' : 'false');
      if (acc.open) resizeProjectDescriptionEditor();
    }
    acc.addEventListener('toggle', sync);
    sync();
  }

  form.querySelectorAll('.br-preview').forEach(bindPreview);
  bindRateOverrides();
  setTimeout(bindRateOverrides, 0);
  form.querySelectorAll('details[data-br-step]').forEach(function (el) {
    syncAccExpanded(el);
    el.addEventListener('toggle', function () {
      syncAccExpanded(el);
    });
  });
  form.querySelectorAll('[data-br-scope-field]').forEach(function (el) {
    var markDirty = function () { el.setAttribute('data-br-scope-dirty', '1'); };
    el.addEventListener('input', markDirty);
    el.addEventListener('change', markDirty);
  });
  var floorTypeEl = document.getElementById('br-fl-type');
  var floorWasteEl = document.getElementById('br-fl-waste');
  function syncHardwoodCaution() {
    var caution = document.getElementById('br-hardwood-caution');
    if (!caution || !floorTypeEl) return;
    caution.hidden = floorTypeEl.value !== 'hardwood';
  }
  if (floorTypeEl && floorWasteEl) {
    floorWasteEl.addEventListener('input', function () {
      floorWasteEl.setAttribute('data-br-floor-waste-dirty', '1');
    });
    floorTypeEl.addEventListener('change', function () {
      if (floorWasteEl.getAttribute('data-br-floor-waste-dirty') === '1') return;
      var opt = floorTypeEl.options[floorTypeEl.selectedIndex];
      var waste = opt ? opt.getAttribute('data-br-floor-waste') : '';
      if (waste !== '' && waste != null) {
        floorWasteEl.value = waste;
        schedulePreview();
      }
    });
  }
  if (floorTypeEl) {
    floorTypeEl.addEventListener('change', syncHardwoodCaution);
    syncHardwoodCaution();
  }
  var ceilTypeEl = document.getElementById('br-ceil-type');
  if (ceilTypeEl) {
    ceilTypeEl.addEventListener('change', updateCeilingDrywallPricedNote);
  }
  initTileAreas();
  initExclusiveChecks();
  syncBathSinkQty();
  initProjectDescriptionDisclosure();

  var dwWallEl = document.getElementById('br-dw-wall');
  var layoutImportVal = document.getElementById('br-layout-import-value');
  if (dwWallEl && layoutImportVal && layoutImportVal.value) {
    dwWallEl.setAttribute('data-br-from-layout', '1');
  }
  if (dwWallEl) {
    dwWallEl.addEventListener('input', function () {
      if (currentPreset() === 'flood' && !applyingFloodPrefill && !applyingLayout) {
        dwWallEl.dataset.brFloodManual = (String(dwWallEl.value || '').trim() !== '') ? '1' : '';
        dwWallEl.dataset.brFloodAuto = '';
      }
      if (!applyingLayout) {
        dwWallEl.removeAttribute('data-br-from-layout');
        var demoBtn = document.getElementById('br-fill-demo-drywall');
        if (demoBtn) demoBtn.removeAttribute('data-br-demo-suggested');
        syncDemoDrywallHelper();
      }
    });
  }
  ['br-ins-wall-sf', 'br-p-wall', 'br-base'].forEach(function (id) {
    var qtyEl = document.getElementById(id);
    if (!qtyEl) return;
    qtyEl.addEventListener('input', function () {
      if (applyingFloodPrefill || applyingLayout || currentPreset() !== 'flood') return;
      qtyEl.dataset.brFloodQtyAuto = '';
      qtyEl.dataset.brFloodQtyManual = '1';
    });
  });
  function handleFloodDimChange() {
    syncFloodCutChips();
    if (currentPreset() === 'flood') {
      syncFloodDrywallPrefill(true);
    }
  }
  [
    document.getElementById('br-perimeter-lf'),
    document.getElementById('br-area'),
    form.querySelector('[name="area_length_ft"]'),
    form.querySelector('[name="area_width_ft"]'),
    document.getElementById('br-partition'),
    document.getElementById('br-flood-cut-height'),
    document.getElementById('br-flood-both-faces')
  ].forEach(function (el) {
    if (!el) return;
    el.addEventListener('input', handleFloodDimChange);
    el.addEventListener('change', handleFloodDimChange);
  });
  form.querySelectorAll('[data-br-flood-cut]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var cutEl = document.getElementById('br-flood-cut-height');
      var val = btn.getAttribute('data-br-flood-cut') || '24';
      if (cutEl) cutEl.value = val;
      syncFloodCutChips();
      if (currentPreset() === 'flood') {
        syncFloodDrywallPrefill(true);
      }
      schedulePreview();
    });
  });
  var heightEl = document.getElementById('br-height');
  if (heightEl) {
    heightEl.addEventListener('input', refreshLayoutWallFromHeight);
  }

  var extraList = document.getElementById('br-extra-list');
  var extraTpl = document.getElementById('br-extra-template');
  var extraAdd = document.getElementById('br-extra-add');

  function setExtraSource(row, linked) {
    if (!row) return;
    row.classList.toggle('is-linked', !!linked);
    row.classList.toggle('is-manual', !linked);
    var badge = row.querySelector('[data-br-extra-source]');
    if (!badge) return;
    badge.className = 'br-config-source ' + (linked ? 'br-config-source--linked' : 'br-config-source--manual');
    badge.innerHTML = linked
      ? '<i class="fa fa-link" aria-hidden="true"></i> Catalog'
      : '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
  }

  function extraIndex() {
    return extraList ? extraList.querySelectorAll('[data-br-extra-row]').length : 0;
  }

  function initExtraRow(row) {
    if (!row) return;
    row.querySelectorAll('.br-preview').forEach(bindPreview);
    if (window.cpMoneyMask) {
      window.cpMoneyMask.wire(row.querySelectorAll('.cp-money-input'));
    }
    var root = row.querySelector('[data-admin-search-select]');
    if (root && window.AdminSearchSelect) {
      AdminSearchSelect.init(root);
    }
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
      var i = extraIndex();
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
  if (extraList) {
    extraList.querySelectorAll('[data-br-extra-row]').forEach(initExtraRow);
  }

  document.addEventListener('admin-search-select:change', function (e) {
    var picker = e.target && e.target.closest ? e.target.closest('[data-br-layout-picker]') : null;
    if (!picker) return;
    applyRoomLayout((e.detail && e.detail.option) || null);
  });

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-br-extra-row]') : null;
    if (!row) return;
    var opt = (e.detail && e.detail.option) || null;
    var deliveryEl = row.querySelector('[data-br-extra-delivery]');
    if (!opt) {
      if (deliveryEl) deliveryEl.value = '0.00';
      setExtraSource(row, false);
      schedulePreview();
      return;
    }
    var nameEl = row.querySelector('[data-br-extra-name]');
    var priceEl = row.querySelector('[data-br-extra-price]');
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
  document.addEventListener('commute-expense:change', function (e) {
    var d = e.detail || {};
    lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
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
  document.addEventListener('trade-lines:change', function () {
    schedulePreview();
  });
  document.addEventListener('amount-lines:row-added', function (e) {
    var row = e.detail && e.detail.row;
    if (row) {
      row.querySelectorAll('.br-preview').forEach(bindPreview);
    }
    schedulePreview();
  });

  var ceilSfEl = document.getElementById('br-ceil-sf');
  if (ceilSfEl) {
    ceilSfEl.addEventListener('input', function () {
      if (!applyingLayout) {
        var demoBtn = document.getElementById('br-fill-demo-drywall');
        if (demoBtn) demoBtn.removeAttribute('data-br-demo-suggested');
        syncDemoDrywallHelper();
      }
      syncUnifiedCeilingArea();
    });
    ceilSfEl.addEventListener('change', syncUnifiedCeilingArea);
  }
  var adoptCeil = form.querySelector('[data-br-adopt-ceiling-hang]');
  if (adoptCeil) {
    adoptCeil.addEventListener('click', adoptUnifiedCeilingHang);
  }
  syncUnifiedCeilingArea();

  var FLOORING_LABELS = {
    hardwood: 'Hardwood',
    engineered: 'Engineered wood',
    lvp: 'LVP',
    laminate: 'Laminate',
    tile: 'Tile',
    none: 'Skip flooring'
  };

  function resolveScopeCopyField(fieldId, fallbackVal) {
    var el = document.getElementById(fieldId);
    if (el && String(el.value || '').trim() !== '') {
      return el.value;
    }
    return fallbackVal || '';
  }

  function formatScopeLinesHtml(text) {
    var trimmed = String(text || '').trim();
    if (!trimmed) return '<p class="admin-form-help">—</p>';
    return '<div class="br-scope-preview__copy">' + escapeHtml(trimmed).replace(/\r\n|\r|\n/g, '<br>') + '</div>';
  }

  function openScopePreviewModal() {
    var modal = document.getElementById('br-scope-preview-modal');
    var body = document.getElementById('br-scope-preview-body');
    if (!modal || !body) return;
    var data = lastPreviewData;
    if (!data) {
      body.innerHTML = '<p class="admin-form-help">Enter the basement area or project scope to generate a client proposal preview.</p>';
      modal.hidden = false;
      return;
    }
    var scope = data.scope || {};
    var clientScope = data.client_scope || {};
    var summary = data.summary || {};
    var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
    var flRaw = String(scope.flooring_type || '').toLowerCase();
    var flooringLabel = FLOORING_LABELS[flRaw] || scope.flooring_type || '—';
    var areaText = Number(scope.area_sqft || 0) > 0 ? (formatLayoutQty(scope.area_sqft) + ' sq ft') : '—';
    var heightText = Number(scope.ceiling_height_ft || 0) > 0 ? (formatLayoutQty(scope.ceiling_height_ft) + ' ft') : '—';
    var finishText = scope.finish_label || '—';

    var includedText = resolveScopeCopyField('br-scope-included', clientScope.included);
    var notIncludedText = resolveScopeCopyField('br-scope-not-included', clientScope.not_included);
    var assumptionsText = resolveScopeCopyField('br-scope-assumptions', clientScope.assumptions);
    var allowancesText = resolveScopeCopyField('br-scope-allowances', clientScope.allowances);

    var trades = summary.trades || {};
    var elecTax = Number((trades.electrical && trades.electrical.tax_in) || 0);
    var plumbTax = Number((trades.plumbing && trades.plumbing.tax_in) || 0);

    var html = ''
      + '<section class="br-scope-preview__section">'
      +   '<h4>Project specifications</h4>'
      +   '<div class="br-scope-preview__specs">'
      +     '<div class="br-scope-preview__spec"><span>Finished area</span><strong>' + escapeHtml(areaText) + '</strong></div>'
      +     '<div class="br-scope-preview__spec"><span>Ceiling height</span><strong>' + escapeHtml(heightText) + '</strong></div>'
      +     '<div class="br-scope-preview__spec"><span>Drywall finish level</span><strong>' + escapeHtml(finishText) + '</strong></div>'
      +     '<div class="br-scope-preview__spec"><span>Flooring type</span><strong>' + escapeHtml(flooringLabel) + '</strong></div>'
      +   '</div>'
      + '</section>'
      + '<section class="br-scope-preview__section">'
      +   '<h4>Included</h4>' + formatScopeLinesHtml(includedText)
      + '</section>'
      + '<section class="br-scope-preview__section">'
      +   '<h4>Not included</h4>' + formatScopeLinesHtml(notIncludedText)
      + '</section>'
      + '<section class="br-scope-preview__section">'
      +   '<h4>Assumptions</h4>' + formatScopeLinesHtml(assumptionsText)
      + '</section>'
      + '<section class="br-scope-preview__section">'
      +   '<h4>Allowances</h4>' + formatScopeLinesHtml(allowancesText)
      + '</section>'
      + '<section class="br-scope-preview__section">'
      +   '<h4>Investment Summary &amp; HST</h4>'
      +   '<div class="br-scope-preview__summary">'
      +     '<div class="pq-row"><span>Subtotal (pre-HST)</span><span>' + fmt(summary.subtotal_pre_hst || 0) + '</span></div>'
      +     '<div class="pq-row"><span>HST (13%)</span><span>' + fmt(summary.hst || 0) + '</span></div>'
      +     (elecTax > 0 ? '<div class="pq-row"><span>Electrical (incl. HST)</span><span>' + fmt(elecTax) + '</span></div>' : '')
      +     (plumbTax > 0 ? '<div class="pq-row"><span>Plumbing (incl. HST)</span><span>' + fmt(plumbTax) + '</span></div>' : '')
      +     (lastCommuteAmount > 0 ? '<div class="pq-row"><span>Commute (incl. HST)</span><span>' + fmt(lastCommuteAmount) + '</span></div>' : '')
      +     (lastDebrisAmount > 0 ? '<div class="pq-row"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>' : '')
      +     (lastAdditionalTaxIn > 0 ? '<div class="pq-row"><span>Additional costs (incl. HST)</span><span>' + fmt(lastAdditionalTaxIn) + '</span></div>' : '')
      +     '<div class="pq-row pq-row--hl"><span>Total investment (incl. HST)</span><span>' + fmt(grand) + '</span></div>'
      +   '</div>'
      + '</section>';

    body.innerHTML = html;
    modal.hidden = false;
    var closeBtn = modal.querySelector('[data-br-close-preview]');
    if (closeBtn && typeof closeBtn.focus === 'function') {
      closeBtn.focus();
    }
  }

  function closeScopePreviewModal() {
    var modal = document.getElementById('br-scope-preview-modal');
    if (modal) modal.hidden = true;
  }

  document.addEventListener('click', function (e) {
    var openBtn = e.target && e.target.closest ? e.target.closest('#br-preview-scope-btn') : null;
    if (openBtn) {
      e.preventDefault();
      openScopePreviewModal();
      return;
    }
    var closeBtn = e.target && e.target.closest ? e.target.closest('[data-br-close-preview]') : null;
    if (closeBtn) {
      var modal = document.getElementById('br-scope-preview-modal');
      if (modal && !modal.hidden) {
        e.preventDefault();
        closeScopePreviewModal();
      }
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modal = document.getElementById('br-scope-preview-modal');
    if (modal && !modal.hidden) {
      closeScopePreviewModal();
    }
  });

  hydratePersistedClientScope();
  bindAllowancesReset(document.getElementById('br-scope-allowances-reset'));
  if (cfg.initialPreview) {
    renderPreview(cfg.initialPreview);
  } else {
    updateStepRailMeta(null);
  }
  syncPartitionHelpers();
  syncDemoDrywallHelper();
  syncInlineClientProvides();
  schedulePreview();
})();

/* Price configuration: catalog pick fills name + starting price; price stays editable. */
(function () {
  var table = document.querySelector('.br-config-table');
  if (!table) return;

  var STUD_KEY = 'stud_2x4';
  var PLATE_FEET = 8;

  function setLocked(priceEl, locked, title) {
    if (!priceEl) return;
    var wrap = priceEl.closest('.br-config-price-wrap');
    var icon = wrap ? wrap.querySelector('.br-config-price-lock') : null;
    if (locked) {
      priceEl.readOnly = true;
      priceEl.setAttribute('aria-readonly', 'true');
      priceEl.classList.add('is-locked');
      priceEl.title = title || 'Price is the 2x4x8 stud unit price ÷ 8 and cannot be typed.';
      if (icon) icon.hidden = false;
    } else {
      priceEl.readOnly = false;
      priceEl.removeAttribute('aria-readonly');
      priceEl.classList.remove('is-locked');
      priceEl.title = 'Base CAD$ in this line’s unit. Link a catalog item to fill a starting price, or type it.';
      if (icon) icon.hidden = true;
    }
  }

  function sourceHtml(kind) {
    if (kind === 'linked') {
      return '<i class="fa fa-link" aria-hidden="true"></i> Catalog';
    }
    if (kind === 'derived') {
      return '<i class="fa fa-lock" aria-hidden="true"></i> From stud';
    }
    return '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
  }

  function setRowSource(row, kind) {
    if (!row) return;
    row.classList.toggle('is-linked', kind === 'linked');
    row.classList.toggle('is-manual', kind === 'manual');
    row.classList.toggle('is-derived', kind === 'derived');
    var badge = row.querySelector('[data-br-cfg-source]');
    if (!badge) return;
    badge.className = 'br-config-source br-config-source--' + kind;
    badge.innerHTML = sourceHtml(kind);
  }

  function applyPrice(priceEl, value) {
    if (!priceEl) return;
    priceEl.value = value == null || value === '' ? '' : String(value);
    if (window.cpMoneyMask) window.cpMoneyMask.wire(priceEl);
  }

  function parsePrice(el) {
    if (!el) return 0;
    var raw = String(el.value || '').replace(/[^0-9.]/g, '');
    var n = parseFloat(raw);
    return isFinite(n) ? n : 0;
  }

  function syncPlateFromStud() {
    var studRow = table.querySelector('[data-br-material-key="' + STUD_KEY + '"]');
    var plateRow = table.querySelector('[data-br-derived-from="' + STUD_KEY + '"]');
    if (!studRow || !plateRow) return;
    var studPriceEl = studRow.querySelector('[data-br-cfg-price]');
    var platePriceEl = plateRow.querySelector('[data-br-cfg-price]');
    var stud = parsePrice(studPriceEl);
    var plate = stud > 0 ? (stud / PLATE_FEET).toFixed(4) : '0.0000';
    applyPrice(platePriceEl, plate);
    setLocked(platePriceEl, true, 'Price is the 2x4x8 stud unit price ÷ 8.');
  }

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-br-config-row]') : null;
    if (!row || row.getAttribute('data-br-derived-from')) return;
    var opt = (e.detail && e.detail.option) || null;
    var hasId = !!(e.detail && e.detail.id) || !!(opt && opt.id);
    var nameEl = row.querySelector('[data-br-cfg-name]');
    var priceEl = row.querySelector('[data-br-cfg-price]');

    if (opt && hasId) {
      if (nameEl && (opt.name || opt.label)) {
        nameEl.value = opt.name || opt.label;
      }
      applyPrice(priceEl, opt.price);
      setLocked(priceEl, false);
      setRowSource(row, 'linked');
      if (row.getAttribute('data-br-material-key') === STUD_KEY) {
        syncPlateFromStud();
      }
      return;
    }

    if (nameEl) {
      nameEl.value = nameEl.getAttribute('data-fallback-name') || '';
    }
    applyPrice(priceEl, priceEl ? priceEl.getAttribute('data-fallback-price') : '');
    setLocked(priceEl, false);
    setRowSource(row, 'manual');
    if (row.getAttribute('data-br-material-key') === STUD_KEY) {
      syncPlateFromStud();
    }
  });

  table.addEventListener('input', function (e) {
    var t = e.target;
    if (!t || !t.matches || !t.matches('[data-br-cfg-price]')) return;
    var row = t.closest('[data-br-config-row]');
    if (row && row.getAttribute('data-br-material-key') === STUD_KEY) {
      syncPlateFromStud();
    }
  });

  table.addEventListener('keydown', function (e) {
    var t = e.target;
    if (!t || !t.matches || !t.matches('[data-br-cfg-price][readonly]')) return;
    if (e.ctrlKey || e.metaKey || e.key === 'Tab') return;
    e.preventDefault();
  });
})();

(function () {
  var btn = document.getElementById('br-intake-copy');
  var input = document.getElementById('br-intake-url');
  if (!btn || !input) return;

  btn.addEventListener('click', function () {
    var url = input.value;
    var original = btn.innerHTML;
    function done() {
      btn.innerHTML = '<i class="fa fa-check" aria-hidden="true"></i> Copied';
      window.setTimeout(function () {
        btn.innerHTML = original;
      }, 1600);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done).catch(function () {
        input.select();
        document.execCommand('copy');
        done();
      });
      return;
    }
    input.select();
    document.execCommand('copy');
    done();
  });
})();
