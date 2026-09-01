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
  var tokenEl = document.querySelector('meta[name="csrf-token"]');
  var token = tokenEl ? tokenEl.getAttribute('content') : '';
  var debounceTimer = null;
  var lastDebrisAmount = 0;
  var lastAdditionalMaterialsPreTax = 0;
  var lastAdditionalMaterialsHst = 0;
  var lastAdditionalTaxIn = 0;
  var lastPreviewData = null;
  var currentIndex = 0;

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
        if (idx < 0) idx = wizardKeys.indexOf('review');
        if (idx < 0) idx = 0;
        setWizardStep(idx, true);
      }
      setTimeout(function () {
        if (!invalid) return;
        try { invalid.focus(); } catch (err) { /* ignore */ }
        if (typeof invalid.reportValidity === 'function') {
          invalid.reportValidity();
        }
      }, 80);
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

  var lightingType = document.getElementById('br-lighting-type');
  var lightingLabel = document.getElementById('br-lights-label');
  var lightingFixtures = document.getElementById('br-lighting-fixture-qty');
  var spotHint = document.getElementById('br-spot-hint');
  function syncLightingType() {
    var type = lightingType ? lightingType.value : 'spot';
    if (lightingLabel) {
      lightingLabel.textContent = type === 'fixture' ? 'How many light fixtures' : 'How many spot lights';
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
      if (nest && (k === 'electrical' || k === 'plumbing')) return;
      next.push(k);
    });
    sectionKeys = next;
    wizardKeys = next.concat(['review']);
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
      var finish = row.querySelector('[data-br-tile-finish]');
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
        initTileRow(row);
        schedulePreview();
      });
    }
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
    var n = 0;
    sectionKeys.forEach(function (k) {
      n += 1;
      var el = sectionEl(k);
      if (!el) return;
      var num = el.querySelector('.br-acc__num');
      if (num) num.textContent = n + ' of ' + tradeCount;
    });
    var areaLabel = document.getElementById('br-area-label');
    if (areaLabel) {
      var labels = {
        full: 'Total basement area (sq ft)',
        bathroom: 'Bathroom / work area (sq ft)',
        wall: 'Basement area (sq ft, optional)',
        custom: 'Work area (sq ft)'
      };
      areaLabel.textContent = labels[currentPreset()] || labels.full;
    }
    if (currentIndex >= wizardKeys.length) {
      currentIndex = 0;
    }
    if (wizardKeys[currentIndex] !== 'review' && sectionKeys.indexOf(wizardKeys[currentIndex]) < 0) {
      currentIndex = 0;
    }
    relocateNestedTrades();
    syncBathroomElectricalHints();
  }

  function relocateNestedTrades() {
    var nest = currentPreset() === 'bathroom';
    [
      ['br-electrical-fields', 'br-electrical-slot-step', 'br-electrical-slot-bath'],
      ['br-plumbing-fields', 'br-plumbing-slot-step', 'br-plumbing-slot-bath']
    ].forEach(function (ids) {
      var fields = document.getElementById(ids[0]);
      var stepSlot = document.getElementById(ids[1]);
      var bathSlot = document.getElementById(ids[2]);
      if (!fields || !stepSlot || !bathSlot) return;
      (nest ? bathSlot : stepSlot).appendChild(fields);
      bathSlot.hidden = !nest;
    });
    ['electrical', 'plumbing'].forEach(function (k) {
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
    if (includeWrap) includeWrap.hidden = nest;
    if (nest) {
      setNamedCheck('include_bathroom', true);
      var bathFields = document.getElementById('br-bath-fields');
      if (bathFields) bathFields.hidden = false;
    }
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
    var keys = presetSections[preset] || presetSections.full || allSectionKeys;
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
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
    } else if (preset === 'full') {
      setNamedCheck('insulate_walls', true);
      setNamedValue('flooring_type', 'lvp');
      setNamedValue('detector_qty', 2);
      setNamedCheck('include_cleaning', true);
      setNamedCheck('include_touchups', true);
    }
    applyElectricalDefaults(preset);
    applyScope();
    syncRevealTargets();
    syncBathroomElectricalHints();
  }

  function setWizardStep(index, scroll) {
    if (index < 0) index = 0;
    if (index >= wizardKeys.length) index = wizardKeys.length - 1;
    currentIndex = index;
    var key = wizardKeys[currentIndex];
    wizardKeys.forEach(function (k) {
      var el = sectionEl(k);
      if (!el) return;
      var active = k === key;
      el.classList.toggle('is-active', active);
      if (!isEdit) {
        if (active) el.setAttribute('open', '');
        else el.removeAttribute('open');
      }
    });
    form.querySelectorAll('.br-progress__item').forEach(function (btn) {
      btn.classList.toggle('is-current', btn.getAttribute('data-br-goto') === key);
    });
    var status = document.getElementById('br-step-status');
    if (status) {
      status.textContent = key === 'review'
        ? 'Review'
        : ((currentIndex + 1) + ' of ' + sectionKeys.length);
    }
    var prev = document.getElementById('br-prev');
    var next = document.getElementById('br-next');
    if (prev) prev.disabled = currentIndex === 0;
    if (next) next.textContent = key === 'review' ? 'Review' : (currentIndex === sectionKeys.length - 1 ? 'Review' : 'Next');
    if (next && key === 'review') next.disabled = true;
    else if (next) next.disabled = false;
    if (scroll && !isEdit) {
      var el = sectionEl(key);
      if (el && el.scrollIntoView) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }
  }

  applyScope();
  bindLw();

  if (!isEdit) {
    setWizardStep(0, false);
    var prevBtn = document.getElementById('br-prev');
    var nextBtn = document.getElementById('br-next');
    if (prevBtn) prevBtn.addEventListener('click', function () { setWizardStep(currentIndex - 1, true); });
    if (nextBtn) nextBtn.addEventListener('click', function () { setWizardStep(currentIndex + 1, true); });
  } else {
    var nav = document.getElementById('br-step-nav');
    if (nav) nav.hidden = true;
  }

  form.querySelectorAll('[data-br-scope-preset]').forEach(function (el) {
    el.addEventListener('change', function () {
      applyPreset(el.value);
      if (!isEdit) setWizardStep(0, false);
      schedulePreview();
    });
  });
  form.querySelectorAll('[data-br-enabled-section]').forEach(function (cb) {
    cb.addEventListener('change', function () {
      applyScope();
      if (!isEdit) setWizardStep(currentIndex, false);
    });
  });

  form.querySelectorAll('[data-br-goto]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var key = btn.getAttribute('data-br-goto');
      var idx = wizardKeys.indexOf(key);
      if (idx < 0) return;
      if (isEdit) {
        var el = sectionEl(key);
        if (el) {
          el.setAttribute('open', '');
          el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        form.querySelectorAll('.br-progress__item').forEach(function (b) {
          b.classList.toggle('is-current', b.getAttribute('data-br-goto') === key);
        });
        return;
      }
      setWizardStep(idx, true);
    });
  });

  function schedulePreview() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(runPreview, 280);
  }

  function runPreview() {
    if (!previewUrl) return;
    var fd = new FormData(form);
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

  function renderPreview(data) {
    lastPreviewData = data;
    var empty = document.getElementById('br-live-empty');
    var body = document.getElementById('br-live-body');
    var summary = data.summary || {};
    rebuildLaborTable(data.labor || []);
    rebuildMatTable(data.materials || []);
    if (!summary.grand_total || summary.grand_total <= 0) {
      if (empty) empty.hidden = false;
      if (body) body.hidden = true;
      return;
    }
    if (empty) empty.hidden = true;
    if (body) body.hidden = false;
    var laborPre = Number(summary.labor_pre_hst || 0);
    var laborHst = Number(summary.labor_hst != null ? summary.labor_hst : laborPre * 0.13);
    var matPre = Number(summary.materials_pre_hst || 0) + lastAdditionalMaterialsPreTax;
    var matHst = Number(summary.materials_hst || 0) + lastAdditionalMaterialsHst;
    var html = '';
    html += '<div class="pq-row"><span>Labor (pre-HST)</span><span>' + fmt(laborPre) + '</span></div>';
    html += '<div class="pq-row"><span>Labor HST</span><span>' + fmt(laborHst) + '</span></div>';
    html += '<div class="pq-row pq-row--sub"><span>Total labor</span><span>' + fmt(laborPre + laborHst) + '</span></div>';
    html += '<div class="pq-row pq-row--group"><span>Materials (pre-HST)</span><span>' + fmt(matPre) + '</span></div>';
    html += '<div class="pq-row"><span>Materials HST</span><span>' + fmt(matHst) + '</span></div>';
    html += '<div class="pq-row pq-row--sub"><span>Total materials</span><span>' + fmt(matPre + matHst) + '</span></div>';
    if (lastDebrisAmount > 0) {
      html += '<div class="pq-row pq-row--group"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>';
    }
    var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn;
    html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + fmt(grand) + '</span></div>';
    body.innerHTML = html;
  }

  function activeIn(el) {
    return el && document.activeElement && el.contains(document.activeElement);
  }

  function rebuildLaborTable(rows) {
    var tbody = document.getElementById('br-labor-rows');
    if (!tbody || activeIn(tbody)) {
      if (tbody && lastPreviewData) {
        (lastPreviewData.labor || []).forEach(function (row) {
          var tr = tbody.querySelector('[data-br-labor-key="' + row.key + '"]');
          if (tr) {
            var amt = tr.querySelector('[data-br-labor-amt]');
            if (amt) amt.textContent = fmt(row.amount || 0);
          }
        });
      }
      return;
    }
    if (!rows.length) {
      tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter the basement area to generate labor lines.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (row) {
      return '<tr data-br-labor-key="' + escapeHtml(row.key) + '">'
        + '<td>' + escapeHtml(row.label) + '<br><span class="admin-form-help">' + escapeHtml(row.detail || '') + '</span></td>'
        + '<td><input type="number" step="0.01" min="0" class="admin-input br-preview" name="labor_override[' + escapeHtml(row.key) + '][hours]" value="' + Number(row.hours || 0) + '"></td>'
        + '<td class="cp-col-amount" data-br-labor-amt>' + fmt(row.amount || 0) + '</td>'
        + '</tr>';
    }).join('');
    tbody.querySelectorAll('.br-preview').forEach(bindPreview);
  }

  function rebuildMatTable(rows) {
    var tbody = document.getElementById('br-mat-rows');
    if (!tbody || activeIn(tbody)) {
      if (tbody && lastPreviewData) {
        (lastPreviewData.materials || []).forEach(function (row) {
          var tr = tbody.querySelector('[data-br-mat-key="' + row.key + '"]');
          if (tr) {
            var amt = tr.querySelector('[data-br-mat-amt]');
            if (amt) amt.textContent = fmt(row.amount || 0);
          }
        });
      }
      return;
    }
    if (!rows.length) {
      tbody.innerHTML = '<tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after quantities are entered.</td></tr>';
      return;
    }
    tbody.innerHTML = rows.map(function (row) {
      var provided = row.client_provided ? ' <span class="admin-form-help"> · client provided</span>' : '';
      var price = Number(row.unit_price || 0).toFixed(4);
      var derivedPlate = row.key === 'plate_2x4';
      var priceLock = derivedPlate
        ? ' readonly aria-readonly="true" title="Price is the 2x4x8 stud unit price ÷ 8."'
        : '';
      return '<tr data-br-mat-key="' + escapeHtml(row.key) + '">'
        + '<td>' + escapeHtml(row.label) + provided + '</td>'
        + '<td><input type="number" step="0.01" min="0" class="admin-input br-preview" name="materials_override[' + escapeHtml(row.key) + '][qty]" value="' + Number(row.qty || 0) + '"></td>'
        + '<td><input type="text" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct" data-cp-money-places="4" class="admin-input cp-money-input br-preview' + (derivedPlate ? ' is-locked' : '') + '" name="materials_override[' + escapeHtml(row.key) + '][unit_price]" value="' + price + '"' + priceLock + '></td>'
        + '<td class="cp-col-amount" data-br-mat-amt>' + fmt(row.amount || 0) + '</td>'
        + '</tr>';
    }).join('');
    tbody.querySelectorAll('.br-preview').forEach(bindPreview);
    if (window.cpMoneyMask) {
      window.cpMoneyMask.wire(tbody.querySelectorAll('.cp-money-input'));
    }
  }

  function bindPreview(el) {
    if (el.dataset.brBound === '1') return;
    el.dataset.brBound = '1';
    el.addEventListener('input', schedulePreview);
    el.addEventListener('change', schedulePreview);
  }

  function initExclusiveChecks() {
    var groups = {};
    form.querySelectorAll('[data-br-exclusive-group]').forEach(function (el) {
      var group = el.getAttribute('data-br-exclusive-group');
      if (!group) return;
      if (!groups[group]) groups[group] = [];
      groups[group].push(el);
      el.addEventListener('change', function () {
        if (!el.checked) return;
        groups[group].forEach(function (other) {
          if (other !== el) other.checked = false;
        });
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

  form.querySelectorAll('.br-preview').forEach(bindPreview);
  initTileAreas();
  initExclusiveChecks();

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
      initExtraRow(row);
      schedulePreview();
    });
  }
  if (extraList) {
    extraList.querySelectorAll('[data-br-extra-row]').forEach(initExtraRow);
  }

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
  document.addEventListener('additional-costs:change', function (e) {
    var d = e.detail || {};
    lastAdditionalMaterialsPreTax = Number(d.materialsPreTax || 0);
    lastAdditionalMaterialsHst = Number(d.materialsHst || 0);
    lastAdditionalTaxIn = Number(d.taxInclusiveTotal || 0);
    if (lastPreviewData) renderPreview(lastPreviewData);
  });

  if (cfg.initialPreview) {
    renderPreview(cfg.initialPreview);
  }
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
