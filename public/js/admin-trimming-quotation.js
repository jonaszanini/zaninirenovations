(function () {
  var form = document.getElementById('tm-main-form');
  var cfg = window.TM_QUOTATION || {};

  function fmt(n) {
    return 'CA$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }
  function escapeHtml(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  if (form) {
    var previewUrl = cfg.previewUrl || '';
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

    var intentEl = document.getElementById('tm-save-intent');
    var draftBtn = document.getElementById('tm-btn-draft');
    var publishBtn = document.getElementById('tm-btn-publish');
    if (draftBtn) {
      draftBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (intentEl) intentEl.value = 'draft';
        HTMLFormElement.prototype.submit.call(form);
      });
    }
    form.addEventListener('submit', function () {
      if (intentEl && intentEl.value !== 'draft') intentEl.value = 'publish';
    });
    if (publishBtn) {
      publishBtn.addEventListener('click', function () {
        if (intentEl) intentEl.value = 'publish';
      });
    }

    function syncFamilies() {
      form.querySelectorAll('[data-tm-family]').forEach(function (cb) {
        var key = cb.getAttribute('data-tm-family');
        var body = form.querySelector('[data-tm-family-body="' + key + '"]');
        if (body) body.hidden = !cb.checked;
      });
    }
    function flagChecked(name) {
      var cb = form.querySelector('input.admin-checkbox__input[name="' + name + '"]');
      return !!(cb && cb.checked);
    }
    function syncOverrides() {
      form.querySelectorAll('[data-tm-show-when]').forEach(function (el) {
        el.hidden = !flagChecked(el.getAttribute('data-tm-show-when'));
      });
    }
    form.querySelectorAll('[data-tm-family]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        syncFamilies();
        schedulePreview();
      });
    });
    form.addEventListener('change', function (e) {
      if (e.target && e.target.matches && e.target.matches('input.admin-checkbox__input')) {
        syncOverrides();
      }
    });
    syncFamilies();
    syncOverrides();

    function schedulePreview() {
      if (window.QuotationTotalsBar) {
        window.QuotationTotalsBar.setUpdating(true);
      }
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(runPreview, 280);
    }

    function runPreview() {
      if (!previewUrl) return;
      var body = new FormData(form);
      body.delete('_method');
      fetch(previewUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body,
        credentials: 'same-origin'
      }).then(function (res) { return res.json(); }).then(function (data) {
        renderPreview(data);
      }).catch(function () { /* ignore */ });
    }

    function renderPreview(data) {
      lastPreviewData = data;
      if (window.QuotationBuildingLaborUi) window.QuotationBuildingLaborUi.sync(data);
      var empty = document.getElementById('tm-live-empty');
      var body = document.getElementById('tm-live-body');
      var summary = (data && data.summary) || {};
      var grandBase = Number(summary.grand_total || 0);
      var liveGrand = grandBase + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
      window.__quoteGrandTotal = liveGrand;
      document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: liveGrand } }));
      if (grandBase <= 0 && lastCommuteAmount <= 0 && lastDebrisAmount <= 0 && lastAdditionalTaxIn <= 0) {
        if (empty) empty.hidden = false;
        if (body) body.hidden = true;
        if (window.QuotationTotalsBar) {
          window.QuotationTotalsBar.update({
            materials: 0,
            labour: 0,
            extras: 0,
            hst: 0,
            grand: 0
          });
        }
        return;
      }
      if (empty) empty.hidden = true;
      if (body) body.hidden = false;
      var laborPre = Number(summary.labor_pre_hst || 0) + lastAdditionalLaborPreTax;
      var laborHst = Number(summary.labor_hst != null ? summary.labor_hst : Number(summary.labor_pre_hst || 0) * 0.13) + lastAdditionalLaborHst;
      var matPre = Number(summary.materials_pre_hst || 0) + lastAdditionalMaterialsPreTax;
      var matHst = Number(summary.materials_hst || 0) + lastAdditionalMaterialsHst;
      var html = '';
      (data.breakdown || []).forEach(function (row) {
        html += '<div class="pq-row"><span>' + escapeHtml(row.label || '')
          + '<br><span class="pq-hint">' + escapeHtml(row.detail || '') + '</span></span><span>'
          + fmt(row.amount) + '</span></div>';
      });
      html += '<div class="pq-row"><span>Labor (pre-HST)</span><span>' + fmt(laborPre) + '</span></div>';
      html += '<div class="pq-row"><span>Materials (pre-HST)</span><span>' + fmt(matPre) + '</span></div>';
      html += '<div class="pq-row"><span>HST (13%)</span><span>' + fmt(laborHst + matHst) + '</span></div>';
      if (lastCommuteAmount > 0) {
        html += '<div class="pq-row"><span>Commute (incl. HST)</span><span>' + fmt(lastCommuteAmount) + '</span></div>';
      }
      if (lastDebrisAmount > 0) {
        html += '<div class="pq-row"><span>Debris removal (incl. HST)</span><span>' + fmt(lastDebrisAmount) + '</span></div>';
      }
      var grand = grandBase + lastDebrisAmount + lastAdditionalTaxIn + lastCommuteAmount;
      var extras = lastCommuteAmount + lastDebrisAmount;

      if (window.QuotationTotalsBar) {
        window.QuotationTotalsBar.update({
          materials: matPre,
          labour: laborPre,
          extras: extras,
          hst: laborHst + matHst,
          grand: grand
        });
      }

      html += '<div class="pq-row pq-row--hl"><span>Grand total</span><span>' + fmt(grand) + '</span></div>';
      var hours = Number(summary.total_hours || 0);
      if (hours > 0) {
        html += '<p class="pq-hint" style="margin-top:8px;">' + hours.toFixed(1) + ' labour hours</p>';
      }
      body.innerHTML = html;
    }

    form.querySelectorAll('.tm-preview').forEach(function (el) {
      el.addEventListener('input', schedulePreview);
      el.addEventListener('change', schedulePreview);
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

    if (cfg.initialPreview) renderPreview(cfg.initialPreview);
    schedulePreview();
  }
})();

(function () {
  var root = document.getElementById('tm-config-form');
  if (!root) return;
  var tabs = root.querySelectorAll('.cp-tab');
  var panels = root.querySelectorAll('.cp-tab-panel');
  var tabInput = document.getElementById('tm-config-tab');

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

  document.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('[data-br-config-row]') : null;
    if (!row || !root.contains(row)) return;
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
  });
})();
