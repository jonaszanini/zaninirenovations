(function () {
  var cfg = window.SH_QUOTATION || {};
  var form = document.getElementById('sh-main-form');
  if (form) {
    var previewUrl = cfg.previewUrl || '';
    var isEdit = !!cfg.isEdit;
    var sectionKeys = (cfg.sectionKeys || []).slice();
    var wizardKeys = sectionKeys.concat(['review']);
    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var token = tokenEl ? tokenEl.getAttribute('content') : '';
    var debounceTimer = null;
    var lastDebrisAmount = 0;
    var lastAdditionalMaterialsPreTax = 0;
    var lastAdditionalMaterialsHst = 0;
    var lastAdditionalTaxIn = 0;
    var lastCommuteAmount = 0;
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

    function findStepKeyForElement(el) {
      var step = el && el.closest ? el.closest('[data-br-step]') : null;
      return step ? step.getAttribute('data-br-step') : null;
    }

    function submitWizard(intent) {
      var intentEl = document.getElementById('sh-save-intent');
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
          if (typeof invalid.reportValidity === 'function') invalid.reportValidity();
        }, 80);
        return;
      }
      HTMLFormElement.prototype.submit.call(form);
    }

    var draftBtn = document.getElementById('sh-btn-draft');
    var publishBtn = document.getElementById('sh-btn-publish');
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
      var intentEl = document.getElementById('sh-save-intent');
      submitWizard(intentEl && intentEl.value ? intentEl.value : 'publish');
    });

    function syncReveal() {
      form.querySelectorAll('[data-sh-reveal]').forEach(function (cb) {
        var target = document.getElementById(cb.getAttribute('data-sh-reveal'));
        if (target) target.hidden = !cb.checked;
      });
    }
    form.querySelectorAll('[data-sh-reveal]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        syncReveal();
        schedulePreview();
      });
    });
    syncReveal();

    function syncFoundation() {
      var val = (form.querySelector('[data-sh-foundation]:checked') || {}).value || 'skids';
      var pad = document.getElementById('sh-found-pad');
      var piles = document.getElementById('sh-found-piles');
      var skids = document.getElementById('sh-found-skids');
      var blocks = document.getElementById('sh-found-blocks');
      if (pad) pad.hidden = val !== 'pad';
      if (piles) piles.hidden = val !== 'piles';
      if (skids) skids.hidden = val !== 'skids';
      if (blocks) blocks.hidden = val !== 'blocks';
    }
    form.querySelectorAll('[data-sh-foundation]').forEach(function (el) {
      el.addEventListener('change', function () {
        syncFoundation();
        schedulePreview();
      });
    });
    syncFoundation();

    function syncPaintWrap() {
      var siding = (form.querySelector('[name="siding"]:checked') || {}).value || 'vinyl';
      var wrap = document.getElementById('sh-paint-wrap');
      if (wrap) wrap.hidden = (siding === 'none' || siding === 'weather_coat');
    }
    form.querySelectorAll('[name="siding"]').forEach(function (el) {
      el.addEventListener('change', function () {
        syncPaintWrap();
        schedulePreview();
      });
    });
    syncPaintWrap();

    function syncAreaReadout() {
      var len = parseFloat((document.getElementById('sh-length') || {}).value || 0);
      var wid = parseFloat((document.getElementById('sh-width') || {}).value || 0);
      var out = document.getElementById('sh-area-readout');
      if (!out) return;
      if (!(len > 0 && wid > 0)) {
        out.textContent = '—';
        return;
      }
      var sf = Math.round(len * wid * 100) / 100;
      out.textContent = sf + ' sq ft';
    }
    ['sh-length', 'sh-width'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', syncAreaReadout);
    });
    syncAreaReadout();

    function renderPermit(warnings) {
      var box = document.getElementById('sh-permit');
      var list = document.getElementById('sh-permit-list');
      if (!box || !list) return;
      var rows = Array.isArray(warnings) ? warnings.filter(Boolean) : [];
      if (!rows.length) {
        box.hidden = true;
        list.innerHTML = '';
        return;
      }
      box.hidden = false;
      list.innerHTML = rows.map(function (w) {
        return '<p>' + escapeHtml(w) + '</p>';
      }).join('');
    }

    function sectionEl(key) {
      return form.querySelector('[data-br-step="' + key + '"]');
    }

    function setWizardStep(idx, scroll) {
      if (idx < 0) idx = 0;
      if (idx >= wizardKeys.length) idx = wizardKeys.length - 1;
      currentIndex = idx;
      var key = wizardKeys[idx];
      form.querySelectorAll('.br-acc').forEach(function (el) {
        var active = el.getAttribute('data-br-step') === key;
        el.classList.toggle('is-active', active);
        el.hidden = !isEdit && !active;
        if (!isEdit) {
          if (active) el.setAttribute('open', '');
          else el.removeAttribute('open');
        }
      });
      form.querySelectorAll('.br-progress__item').forEach(function (btn) {
        btn.classList.toggle('is-current', btn.getAttribute('data-sh-goto') === key);
      });
      var status = document.getElementById('sh-step-status');
      if (status) {
        status.textContent = key === 'review'
          ? 'Review'
          : ((currentIndex + 1) + ' of ' + sectionKeys.length);
      }
      var prev = document.getElementById('sh-prev');
      var next = document.getElementById('sh-next');
      if (prev) prev.disabled = currentIndex === 0;
      if (next) next.textContent = key === 'review' ? 'Review' : (currentIndex === sectionKeys.length - 1 ? 'Review' : 'Next');
      if (next && key === 'review') next.disabled = true;
      else if (next) next.disabled = false;
      if (scroll && !isEdit) {
        var el = sectionEl(key);
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    if (!isEdit) {
      setWizardStep(0, false);
      var prevBtn = document.getElementById('sh-prev');
      var nextBtn = document.getElementById('sh-next');
      if (prevBtn) prevBtn.addEventListener('click', function () { setWizardStep(currentIndex - 1, true); });
      if (nextBtn) nextBtn.addEventListener('click', function () { setWizardStep(currentIndex + 1, true); });
    } else {
      var nav = document.getElementById('sh-step-nav');
      if (nav) nav.hidden = true;
    }

    form.querySelectorAll('[data-sh-goto]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-sh-goto');
        var idx = wizardKeys.indexOf(key);
        if (idx < 0) return;
        if (isEdit) {
          var el = sectionEl(key);
          if (el) {
            el.setAttribute('open', '');
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
          form.querySelectorAll('.br-progress__item').forEach(function (b) {
            b.classList.toggle('is-current', b.getAttribute('data-sh-goto') === key);
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
      var body = new FormData(form);
      body.delete('_method'); // Edit forms include @method('PUT'); must not spoof preview POST as PUT.
      fetch(previewUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin'
      }).then(function (res) { return res.json(); }).then(function (data) {
        renderPreview(data);
        var scope = data.scope || {};
        renderPermit(scope.permit_warnings || []);
      }).catch(function () { /* ignore */ });
    }

    function renderPreview(data) {
      lastPreviewData = data;
      var empty = document.getElementById('sh-live-empty');
      var body = document.getElementById('sh-live-body');
      var summary = data.summary || {};
      rebuildLaborTable(data.labor || []);
      rebuildMatTable((data.materials || []).concat(data.extra_materials || []).filter(function (r) {
        return !String(r.key || '').startsWith('extra_');
      }));
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
      if (lastCommuteAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Commute (incl. HST)</span><span>' + fmt(lastCommuteAmount) + '</span></div>';
      }
      if (lastDebrisAmount > 0) {
        html += '<div class="pq-row pq-row--group"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>';
      }
      var grand = Number(summary.grand_total || 0) + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
      html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + fmt(grand) + '</span></div>';
      var warnings = (data.scope && data.scope.permit_warnings) || [];
      if (warnings.length) {
        html += '<p class="sh-live-permit">' + escapeHtml(warnings[0]) + '</p>';
      }
      body.innerHTML = html;
    }

    function rebuildLaborTable(rows) {
      var tbody = document.getElementById('sh-labor-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="3" class="admin-form-help">Enter length and width to generate labor lines.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(function (row) {
        return '<tr data-sh-labor-key="' + escapeHtml(row.key) + '">'
          + '<td>' + escapeHtml(row.label) + '<br><span class="admin-form-help">' + escapeHtml(row.detail || '') + '</span></td>'
          + '<td>' + Number(row.hours || 0).toFixed(2) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td>'
          + '</tr>';
      }).join('');
    }

    function rebuildMatTable(rows) {
      var tbody = document.getElementById('sh-mat-rows');
      if (!tbody) return;
      if (!rows.length) {
        tbody.innerHTML = '<tr class="br-empty-row"><td colspan="4" class="admin-form-help">Materials appear after size is entered.</td></tr>';
        return;
      }
      tbody.innerHTML = rows.map(function (row) {
        var provided = row.client_provided ? ' <span class="admin-form-help"> · client provided</span>' : '';
        return '<tr data-sh-mat-key="' + escapeHtml(row.key) + '">'
          + '<td>' + escapeHtml(row.label) + provided + '</td>'
          + '<td>' + Number(row.qty || 0).toFixed(2) + '</td>'
          + '<td>' + fmt(row.unit_price || 0) + '</td>'
          + '<td class="cp-col-amount">' + fmt(row.amount || 0) + '</td>'
          + '</tr>';
      }).join('');
    }

    function bindPreview(el) {
      if (el.dataset.shBound === '1') return;
      el.dataset.shBound = '1';
      el.addEventListener('input', schedulePreview);
      el.addEventListener('change', schedulePreview);
    }
    form.querySelectorAll('.sh-preview').forEach(bindPreview);

    var extraList = document.getElementById('sh-extra-list');
    var extraTpl = document.getElementById('sh-extra-template');
    var extraAdd = document.getElementById('sh-extra-add');

    function setExtraSource(row, linked) {
      if (!row) return;
      row.classList.toggle('is-linked', !!linked);
      row.classList.toggle('is-manual', !linked);
      var badge = row.querySelector('[data-sh-extra-source]');
      if (!badge) return;
      badge.className = 'br-config-source ' + (linked ? 'br-config-source--linked' : 'br-config-source--manual');
      badge.innerHTML = linked
        ? '<i class="fa fa-link" aria-hidden="true"></i> Catalog'
        : '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
    }

    function extraIndex() {
      return extraList ? extraList.querySelectorAll('[data-sh-extra-row]').length : 0;
    }

    function initExtraRow(row) {
      if (!row) return;
      row.querySelectorAll('.sh-preview').forEach(bindPreview);
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
        var i = extraIndex();
        var wrap = document.createElement('div');
        wrap.innerHTML = extraTpl.innerHTML.replace(/__I__/g, String(i)).trim();
        var row = wrap.firstElementChild;
        extraList.appendChild(row);
        initExtraRow(row);
        schedulePreview();
      });
    }
    if (extraList) extraList.querySelectorAll('[data-sh-extra-row]').forEach(initExtraRow);

    document.addEventListener('admin-search-select:change', function (e) {
      var row = e.target && e.target.closest ? e.target.closest('[data-sh-extra-row]') : null;
      if (!row) return;
      var opt = (e.detail && e.detail.option) || null;
      var deliveryEl = row.querySelector('[data-sh-extra-delivery]');
      if (!opt) {
        if (deliveryEl) deliveryEl.value = '0.00';
        setExtraSource(row, false);
        schedulePreview();
        return;
      }
      var nameEl = row.querySelector('[data-sh-extra-name]');
      var priceEl = row.querySelector('[data-sh-extra-price]');
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
    document.addEventListener('commute-expense:change', function (e) {
      var d = e.detail || {};
      lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
      if (lastPreviewData) renderPreview(lastPreviewData);
    });

    var allCb = document.getElementById('sh-client-all');
    var provideWrap = document.getElementById('sh-provide-wrap');
    function syncProvide() {
      if (provideWrap) provideWrap.hidden = !!(allCb && allCb.checked);
    }
    if (allCb) allCb.addEventListener('change', syncProvide);
    syncProvide();

    if (cfg.initialPreview) renderPreview(cfg.initialPreview);
    schedulePreview();
  }
})();

(function () {
  var root = document.getElementById('sh-config-form');
  if (!root) return;
  var tabs = root.querySelectorAll('.cp-tab');
  var panels = root.querySelectorAll('.cp-tab-panel');
  var tabInput = document.getElementById('sh-config-tab');

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

  root.addEventListener('submit', function (e) {
    var invalid = root.querySelector(':invalid');
    if (!invalid) return;
    var panel = invalid.closest('[data-panel]');
    if (panel && !panel.classList.contains('is-active')) {
      e.preventDefault();
      show(panel.getAttribute('data-panel'));
      if (typeof invalid.reportValidity === 'function') invalid.reportValidity();
    }
  });

  var table = root.querySelector('.br-config-table');
  if (!table) return;
  var PLATE_FEET = 8;

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-br-config-row]') : null;
    if (!row || row.getAttribute('data-br-derived-from')) return;
    var opt = (e.detail && e.detail.option) || null;
    var nameEl = row.querySelector('[data-br-cfg-name]');
    var priceEl = row.querySelector('[data-br-cfg-price]');
    var badge = row.querySelector('[data-br-cfg-source]');
    if (!opt) {
      row.classList.remove('is-linked');
      row.classList.add('is-manual');
      if (badge) {
        badge.className = 'br-config-source br-config-source--manual';
        badge.innerHTML = '<i class="fa fa-pencil" aria-hidden="true"></i> Manual';
      }
      return;
    }
    if (nameEl) nameEl.value = opt.name || nameEl.getAttribute('data-fallback-name') || '';
    if (priceEl) priceEl.value = opt.price || priceEl.getAttribute('data-fallback-price') || '';
    if (window.cpMoneyMask && priceEl) window.cpMoneyMask.wire(priceEl);
    row.classList.add('is-linked');
    row.classList.remove('is-manual');
    if (badge) {
      badge.className = 'br-config-source br-config-source--linked';
      badge.innerHTML = '<i class="fa fa-link" aria-hidden="true"></i> Catalog';
    }
    syncDerivedPlates();
  });

  function syncDerivedPlates() {
    table.querySelectorAll('[data-br-derived-from]').forEach(function (row) {
      var from = row.getAttribute('data-br-derived-from');
      var src = table.querySelector('[data-br-material-key="' + from + '"] [data-br-cfg-price]');
      var dest = row.querySelector('[data-br-cfg-price]');
      if (!src || !dest) return;
      var stud = parseFloat(String(src.value || '').replace(/,/g, ''));
      dest.value = stud > 0 ? (stud / PLATE_FEET).toFixed(4) : '0.0000';
    });
  }

  table.querySelectorAll('[data-br-cfg-price]').forEach(function (el) {
    el.addEventListener('input', syncDerivedPlates);
    el.addEventListener('change', syncDerivedPlates);
  });
})();
