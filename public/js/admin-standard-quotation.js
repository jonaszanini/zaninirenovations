(function () {
  'use strict';

  var form = document.getElementById('sq-standard-form');
  if (!form) return;

  var SCROLL_KEY = 'admin-sq-standard-scroll';
  var SCROLL_MAX_AGE_MS = 90 * 1000;

  function saveScrollPosition() {
    try {
      var y = window.scrollY || (document.scrollingElement && document.scrollingElement.scrollTop) || 0;
      sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
        y: Math.max(0, Math.round(y)),
        t: Date.now()
      }));
    } catch (err) {}
  }

  function restoreScrollPosition() {
    var raw;
    try {
      raw = sessionStorage.getItem(SCROLL_KEY);
      if (raw == null) return;
      sessionStorage.removeItem(SCROLL_KEY);
    } catch (err) {
      return;
    }
    var data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      return;
    }
    var y = data && typeof data.y === 'number' ? data.y : parseInt(raw, 10);
    var savedAt = data && typeof data.t === 'number' ? data.t : 0;
    if (!isFinite(y) || y <= 0) return;
    if (savedAt && (Date.now() - savedAt) > SCROLL_MAX_AGE_MS) return;
    var apply = function () {
      window.scrollTo(0, y);
    };
    apply();
    requestAnimationFrame(function () {
      apply();
      setTimeout(apply, 50);
      setTimeout(apply, 200);
    });
  }

  form.addEventListener('submit', saveScrollPosition);
  restoreScrollPosition();

  var HST = 0.13;
  var HPD = 8;
  var ELECTRICAL_DESC = form.getAttribute('data-electrical-desc') || 'Electrical work (Including material)';
  var worksRoot = document.getElementById('sq-works');
  var materialTemplate = document.getElementById('sq-material-template');
  var workTemplate = document.getElementById('sq-work-template');

  var sqCatalogOptions = [];
  try {
    var catalogJson = document.getElementById('admin-ss-catalog-options');
    sqCatalogOptions = catalogJson ? JSON.parse(catalogJson.textContent || '[]') : [];
  } catch (e) {
    sqCatalogOptions = [];
  }
  var sqCatalogOptionMap = {};
  sqCatalogOptions.forEach(function (item) {
    sqCatalogOptionMap[String(item.id)] = item;
  });

  function money(n) {
    var v = Number(n || 0);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function parseMoney(raw) {
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(raw);
    }
    if (raw == null) return 0;
    var s = String(raw).replace(/[^\d.-]/g, '');
    if (s === '' || s === '-') return 0;
    var v = parseFloat(s);
    return isFinite(v) ? v : 0;
  }

  function parseQty(raw) {
    var v = parseFloat(raw);
    return isFinite(v) && v >= 0 ? v : 0;
  }

  function workCards() {
    return worksRoot ? Array.prototype.slice.call(worksRoot.querySelectorAll('.sq-work-card')) : [];
  }

  var gMatSub = document.getElementById('sq-g-mat-sub');
  var gMatHst = document.getElementById('sq-g-mat-hst');
  var gMatTot = document.getElementById('sq-g-mat-total');
  var gLab = document.getElementById('sq-g-labour');
  var gLabHst = document.getElementById('sq-g-labour-hst');
  var gLabHstRow = document.getElementById('sq-g-labour-hst-row');
  var gLabTot = document.getElementById('sq-g-labour-total');
  var gLabTotLabel = document.getElementById('sq-g-labour-total-label');
  var includeLabourHstInput = document.getElementById('sq-include-labour-hst');

  function labourHstEnabled() {
    return !includeLabourHstInput || includeLabourHstInput.checked;
  }
  var gGrand = document.getElementById('sq-g-grand');
  var toolbarGrandAmount = document.getElementById('sq-toolbar-grand-amount');
  var gCommuteRow = document.getElementById('sq-g-commute-row');
  var gCommute = document.getElementById('sq-g-commute');
  var gDebrisRow = document.getElementById('sq-g-debris-row');
  var gDebris = document.getElementById('sq-g-debris');
  var gLabBeforeBuildingRow = document.getElementById('sq-g-labour-before-building-row');
  var gLabBeforeBuilding = document.getElementById('sq-g-labour-before-building');
  var gBuildingRow = document.getElementById('sq-g-building-row');
  var gBuilding = document.getElementById('sq-g-building');
  var gBuildingPct = document.getElementById('sq-g-building-pct');
  var buildingInclude = document.getElementById('q-building-include');
  var buildingPercent = document.getElementById('q-building-percent');
  var gJobsWrap = document.getElementById('sq-g-jobs-wrap');
  var gJobs = document.getElementById('sq-g-jobs');
  var lastCommuteAmount = 0;
  var lastDebrisAmount = 0;

  var contEnabled = document.getElementById('sq-contingency-enabled');
  var contPercent = document.getElementById('sq-contingency-percent');
  var gContRow = document.getElementById('sq-g-contingency-row');
  var gContPct = document.getElementById('sq-g-contingency-pct');
  var gContVal = document.getElementById('sq-g-contingency');
  var contAddedRow = document.getElementById('sq-contingency-added');
  var contAddedAmount = document.getElementById('sq-contingency-added-amount');
  var contAddedDetail = document.getElementById('sq-contingency-added-detail');

  function contingencyPctValue() {
    var raw = contPercent ? contPercent.value : '';
    var v = parseFloat(String(raw).replace(/[^0-9.]/g, ''));
    if (!isFinite(v) || v < 0) return 0;
    if (v > 100) return 100;
    return v;
  }

  function labourMode(card) {
    var m = card.querySelector('.sq-labour-mode:checked');
    return m ? m.value : 'hours';
  }

  function syncFixedLabourUI(card) {
    var useFixedCb = card.querySelector('.sq-labour-use-fixed-cb');
    var rateFields = card.querySelector('.sq-labour-rate-fields');
    var fixedFields = card.querySelector('.sq-labour-fixed-fields');
    var rateInput = card.querySelector('.sq-labour-rate');
    var qtyInput = card.querySelector('.sq-labour-qty');
    var modeRadios = card.querySelectorAll('.sq-labour-mode');
    var fixedAmtInput = card.querySelector('.sq-labour-fixed-amount');
    var fixedHstInc = card.querySelector('.sq-labour-fixed-hst-inc-cb');
    var on = useFixedCb && useFixedCb.checked;
    if (rateFields) rateFields.hidden = !!on;
    if (fixedFields) fixedFields.hidden = !on;
    if (rateInput) {
      rateInput.disabled = !!on;
      if (on) {
        rateInput.removeAttribute('required');
      } else {
        rateInput.setAttribute('required', 'required');
      }
    }
    if (qtyInput) qtyInput.disabled = !!on;
    modeRadios.forEach(function (r) { r.disabled = !!on; });
    if (fixedAmtInput) fixedAmtInput.disabled = !on;
    if (fixedHstInc) fixedHstInc.disabled = !on || !labourHstEnabled();
  }

  function syncHstToggles(row) {
    var inc = row.querySelector('.sq-mat-hst-inclusive-cb');
    var apply = row.querySelector('.sq-mat-hst-cb');
    if (!inc || !apply) return;
    if (inc.checked) {
      apply.checked = true;
      apply.disabled = true;
    } else {
      apply.disabled = false;
    }
  }

  function setText(el, value) {
    if (el) el.textContent = value;
  }

  function recalcJob(card) {
    var list = card.querySelector('.sq-materials-list');
    var rows = list ? list.querySelectorAll('.sq-material-row') : [];
    var subSum = 0;
    var hstSum = 0;
    var withSum = 0;
    var contSubSum = 0;
    var contHstSum = 0;

    rows.forEach(function (row) {
      syncHstToggles(row);
      var unitEl = row.querySelector('.sq-mat-unit');
      var qtyEl = row.querySelector('.sq-mat-qty');
      if (!unitEl || !qtyEl) return;
      var unit = parseMoney(unitEl.value);
      var qty = parseQty(qtyEl.value);
      var deliveryEl = row.querySelector('.sq-mat-delivery');
      var delivery = deliveryEl ? parseMoney(deliveryEl.value) : 0;
      var inclusive = row.querySelector('.sq-mat-hst-inclusive-cb') && row.querySelector('.sq-mat-hst-inclusive-cb').checked;
      var applyCb = row.querySelector('.sq-mat-hst-cb');
      var chargePreTax = applyCb && applyCb.checked;
      var source = row.querySelector('.sq-mat-source-radio:checked');
      var isElectrical = !!(source && source.value === 'electrical');
      var lineGross = Math.round((unit * qty + delivery) * 100) / 100;
      var lineSub;
      var lineHst;
      var lineTot;
      if (inclusive) {
        lineSub = Math.round(lineGross / (1 + HST) * 100) / 100;
        lineHst = Math.round((lineGross - lineSub) * 100) / 100;
        lineTot = lineGross;
      } else if (chargePreTax) {
        lineSub = lineGross;
        lineHst = Math.round(lineSub * HST * 100) / 100;
        lineTot = Math.round((lineSub + lineHst) * 100) / 100;
      } else {
        lineSub = lineGross;
        lineHst = 0;
        lineTot = lineSub;
      }

      setText(row.querySelector('.sq-mat-sub'), money(lineSub));
      setText(row.querySelector('.sq-mat-hst'), (inclusive || chargePreTax) ? money(lineHst) : '—');
      setText(row.querySelector('.sq-mat-total'), money(lineTot));

      var note = row.querySelector('.sq-mat-delivery-note');
      if (note) {
        if (delivery > 0) {
          note.hidden = false;
          note.textContent = 'Includes ' + money(delivery) + ' delivery (once, not × qty).';
        } else {
          note.hidden = true;
        }
      }

      subSum += lineSub;
      hstSum += lineHst;
      withSum += lineTot;
      if (!isElectrical) {
        contSubSum += lineSub;
        contHstSum += lineHst;
      }
    });

    subSum = Math.round(subSum * 100) / 100;
    hstSum = Math.round(hstSum * 100) / 100;
    withSum = Math.round(withSum * 100) / 100;

    var useFixedCb = card.querySelector('.sq-labour-use-fixed-cb');
    var useFixed = useFixedCb && useFixedCb.checked;
    var fixedAmtInput = card.querySelector('.sq-labour-fixed-amount');
    var fixedHstInc = card.querySelector('.sq-labour-fixed-hst-inc-cb');
    var rateInput = card.querySelector('.sq-labour-rate');
    var qtyInput = card.querySelector('.sq-labour-qty');
    var qtyLabel = card.querySelector('.sq-labour-qty-label');
    var equivEl = card.querySelector('.sq-labour-equiv');
    var lab;
    var labHst;
    var labWithHst;
    if (useFixed) {
      var fa = parseMoney(fixedAmtInput ? fixedAmtInput.value : '');
      if (fixedHstInc && fixedHstInc.checked && labourHstEnabled()) {
        lab = Math.round(fa / (1 + HST) * 100) / 100;
        labHst = Math.round((fa - lab) * 100) / 100;
        labWithHst = fa;
      } else {
        lab = Math.round(fa * 100) / 100;
        labHst = Math.round(lab * HST * 100) / 100;
        labWithHst = Math.round((lab + labHst) * 100) / 100;
      }
    } else {
      var rate = parseMoney(rateInput ? rateInput.value : '');
      var q = parseQty(qtyInput ? qtyInput.value : '');
      var hrs = labourMode(card) === 'days' ? q * HPD : q;
      lab = Math.round(rate * hrs * 100) / 100;
      labHst = Math.round(lab * HST * 100) / 100;
      labWithHst = Math.round((lab + labHst) * 100) / 100;
    }

    if (!labourHstEnabled()) {
      labHst = 0;
      labWithHst = lab;
    }

    setText(card.querySelector('.sq-job-mat-subtotal'), money(subSum));
    setText(card.querySelector('.sq-job-mat-with-hst'), money(withSum));
    setText(card.querySelector('.sq-job-labour-total'), money(lab));
    setText(card.querySelector('.sq-job-labour-hst'), money(labHst));
    setText(card.querySelector('.sq-job-labour-with-hst'), money(labWithHst));
    setText(card.querySelector('.sq-work-subtotal'), money(subSum + lab));

    if (useFixed) {
      if (equivEl) equivEl.textContent = '';
    } else if (qtyInput) {
      var lq = parseQty(qtyInput.value);
      if (labourMode(card) === 'days') {
        if (qtyLabel) qtyLabel.textContent = 'Estimated days';
        var hrsFromDays = lq * HPD;
        if (equivEl) equivEl.textContent = lq > 0 ? ('≈ ' + (Math.round(hrsFromDays * 100) / 100) + ' hours') : '';
      } else {
        if (qtyLabel) qtyLabel.textContent = 'Estimated hours';
        var daysFromHrs = lq / HPD;
        if (equivEl) equivEl.textContent = lq > 0 ? ('≈ ' + (Math.round(daysFromHrs * 1000) / 1000) + ' days (8 h/day)') : '';
      }
    }

    var titleEl = card.querySelector('.sq-work-title');
    return {
      title: titleEl ? String(titleEl.value || '').trim() : '',
      subSum: subSum,
      hstSum: hstSum,
      withSum: withSum,
      lab: lab,
      labHst: labHst,
      labWithHst: labWithHst,
      contSubSum: Math.round(contSubSum * 100) / 100,
      contHstSum: Math.round(contHstSum * 100) / 100
    };
  }

  function recalcAll() {
    var jobs = workCards().map(recalcJob);
    var subSum = 0;
    var hstSum = 0;
    var withSum = 0;
    var lab = 0;
    var labHst = 0;
    var labWithHst = 0;
    var contBase = 0;
    var contBaseHst = 0;
    jobs.forEach(function (j) {
      subSum += j.subSum;
      hstSum += j.hstSum;
      withSum += j.withSum;
      lab += j.lab;
      labHst += j.labHst;
      labWithHst += j.labWithHst;
      contBase += j.contSubSum || 0;
      contBaseHst += j.contHstSum || 0;
    });
    subSum = Math.round(subSum * 100) / 100;
    hstSum = Math.round(hstSum * 100) / 100;
    withSum = Math.round(withSum * 100) / 100;
    lab = Math.round(lab * 100) / 100;
    labHst = Math.round(labHst * 100) / 100;
    labWithHst = Math.round(labWithHst * 100) / 100;
    var labourBase = lab;

    var buildingOn = !!(buildingInclude && buildingInclude.checked);
    var buildingPct = buildingPercent ? parseFloat(String(buildingPercent.value || '').replace(/,/g, '')) : 30;
    if (!isFinite(buildingPct) || buildingPct < 0) buildingPct = 0;
    var buildingSurcharge = 0;
    if (buildingOn && lab > 0 && buildingPct > 0) {
      buildingSurcharge = Math.round(lab * (buildingPct / 100) * 100) / 100;
      lab = Math.round((lab + buildingSurcharge) * 100) / 100;
      if (labourHstEnabled()) {
        var buildingHst = Math.round(buildingSurcharge * HST * 100) / 100;
        labHst = Math.round((labHst + buildingHst) * 100) / 100;
        labWithHst = Math.round((lab + labHst) * 100) / 100;
      } else {
        labWithHst = lab;
      }
    }
    var showBuilding = buildingSurcharge > 0;
    if (gLabBeforeBuildingRow) gLabBeforeBuildingRow.hidden = !showBuilding;
    setText(gLabBeforeBuilding, money(labourBase));
    if (gBuildingRow) gBuildingRow.hidden = !showBuilding;
    if (gBuildingPct) gBuildingPct.textContent = String(Math.round(buildingPct * 100) / 100);
    setText(gBuilding, '+' + money(buildingSurcharge));
    if (window.QuotationBuildingLaborUi) {
      window.QuotationBuildingLaborUi.setLabourBase(labourBase);
    }

    var contOn = !!(contEnabled && contEnabled.checked);
    var contPct = contingencyPctValue();
    var contAmt = 0;
    var contHst = 0;
    if (contOn && contBase > 0 && contPct > 0) {
      contAmt = Math.round(contBase * (contPct / 100) * 100) / 100;
      var effRate = contBase > 0 ? (contBaseHst / contBase) : 0;
      contHst = Math.round(contAmt * effRate * 100) / 100;
    }

    var hstWithCont = Math.round((hstSum + contHst) * 100) / 100;
    var withSumWithCont = Math.round((withSum + contAmt + contHst) * 100) / 100;

    if (contPercent) contPercent.disabled = !contOn;
    var pctLabel = (Math.round(contPct * 100) / 100).toString();
    setText(gContPct, pctLabel);
    if (gContRow) gContRow.style.display = contOn ? '' : 'none';
    setText(gContVal, '+' + money(contAmt));
    if (contAddedRow) contAddedRow.hidden = !contOn;
    setText(contAddedAmount, money(contAmt + contHst));
    setText(
      contAddedDetail,
      '(' + pctLabel + '% of ' + money(contBase) + ' materials'
        + (contHst > 0 ? ', ' + money(contAmt) + ' + ' + money(contHst) + ' HST' : ' before HST')
        + ', not electrical work)'
    );
    setText(gMatSub, money(subSum));
    setText(gMatHst, money(hstWithCont));
    setText(gMatTot, money(withSumWithCont));
    setText(gLab, money(lab));
    setText(gLabHst, money(labHst));
    setText(gLabTot, money(labWithHst));
    var hstOn = labourHstEnabled();
    if (gLabHstRow) gLabHstRow.classList.toggle('sq-grand-row--labour-hst-excluded', !hstOn);
    if (gLabTotLabel) gLabTotLabel.textContent = hstOn ? 'Total labour with HST' : 'Total labour';
    document.querySelectorAll('.sq-job-labour-hst-row').forEach(function (row) {
      row.classList.toggle('is-hst-excluded', !hstOn);
    });
    document.querySelectorAll('.sq-job-labour-with-hst-label').forEach(function (el) {
      el.textContent = hstOn ? 'Total labour with HST' : 'Total labour';
    });
    if (gCommuteRow) gCommuteRow.classList.toggle('is-hidden', !(lastCommuteAmount > 0));
    setText(gCommute, money(lastCommuteAmount));
    if (gDebrisRow) gDebrisRow.classList.toggle('is-hidden', !(lastDebrisAmount > 0));
    setText(gDebris, money(lastDebrisAmount));
    setText(gGrand, money(withSumWithCont + labWithHst + lastCommuteAmount + lastDebrisAmount));
    setText(toolbarGrandAmount, money(withSumWithCont + labWithHst + lastCommuteAmount + lastDebrisAmount));
    window.__quoteGrandTotal = withSumWithCont + labWithHst + lastCommuteAmount + lastDebrisAmount;
    document.dispatchEvent(new CustomEvent('quote:grand-total', { detail: { total: window.__quoteGrandTotal } }));

    if (gJobsWrap && gJobs) {
      var multi = jobs.length >= 2;
      gJobsWrap.hidden = !multi;
      if (multi) {
        gJobs.innerHTML = '';
        jobs.forEach(function (j, idx) {
          var row = document.createElement('div');
          row.className = 'sq-grand-row sq-grand-row--sub';
          var label = document.createElement('span');
          label.textContent = j.title !== '' ? j.title : ('Job ' + (idx + 1));
          var amt = document.createElement('span');
          amt.textContent = money(j.subSum + j.lab);
          row.appendChild(label);
          row.appendChild(amt);
          gJobs.appendChild(row);
        });
      }
    }
  }

  function catalogRoot(row) {
    return row.querySelector('[data-admin-search-select]');
  }

  function destroyCatalogPicker(row) {
    var root = catalogRoot(row);
    if (root && window.AdminSearchSelect) {
      AdminSearchSelect.destroy(root);
    }
  }

  function rowHasMaterialContent(row) {
    var shortEl = row.querySelector('.sq-mat-short-name');
    var descEl = row.querySelector('.sq-mat-desc');
    var root = catalogRoot(row);
    var catalogVal = root && window.AdminSearchSelect
      ? AdminSearchSelect.getValue(root)
      : (root && root.querySelector('.admin-search-select__value') ? root.querySelector('.admin-search-select__value').value : '');
    return !!(catalogVal && String(catalogVal) !== '0')
      || (shortEl && String(shortEl.value || '').trim() !== '')
      || (descEl && String(descEl.value || '').trim() !== '');
  }

  function clearCatalogSelection(row) {
    var hidden = row.querySelector('.admin-search-select__value');
    if (hidden) hidden.value = '';
    var input = row.querySelector('.admin-search-select__input');
    if (input) input.value = '';
  }

  function applyElectricalDefaults(row, forceDescription) {
    var descEl = row.querySelector('.sq-mat-desc');
    if (descEl && (forceDescription || String(descEl.value || '').trim() === '')) {
      descEl.value = ELECTRICAL_DESC;
    }
    var shortEl = row.querySelector('.sq-mat-short-name');
    if (shortEl) shortEl.value = '';
    var urlEl = row.querySelector('.sq-mat-url');
    if (urlEl) {
      urlEl.value = '';
      syncUrlOpenLink(urlEl);
    }
    var imageEl = row.querySelector('.sq-mat-image-url');
    if (imageEl) imageEl.value = '';
    syncImagePreview(row);
    var qtyEl = row.querySelector('.sq-mat-qty');
    if (qtyEl && String(qtyEl.value || '').trim() === '') {
      qtyEl.value = '1';
    }
    var deliveryEl = row.querySelector('.sq-mat-delivery');
    if (deliveryEl) deliveryEl.value = '0.00';
    clearCatalogSelection(row);
  }

  function syncMatDetailsVisibility(row) {
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    var mode = checked ? checked.value : 'manual';
    var isCatalog = mode === 'catalog';
    var details = row.querySelector('.sq-mat-details-panel');
    var emptyHint = row.querySelector('.sq-mat-catalog-empty');
    var showDetails = !isCatalog || rowHasMaterialContent(row);
    if (details) details.hidden = !showDetails;
    if (emptyHint) emptyHint.hidden = !isCatalog || showDetails;
  }

  function syncMatSourceUI(row, options) {
    var opts = options || {};
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    var mode = checked ? checked.value : 'manual';
    var catalogWrap = row.querySelector('.sq-mat-catalog-wrap');
    var isCatalog = mode === 'catalog';
    var isElectrical = mode === 'electrical';
    row.classList.toggle('sq-material-row--electrical', isElectrical);
    var shortField = row.querySelector('.sq-mat-field--short-name');
    var urlRow = row.querySelector('.sq-mat-row--url');
    if (shortField) shortField.hidden = isElectrical;
    if (urlRow) urlRow.hidden = isElectrical;
    if (catalogWrap) catalogWrap.hidden = !isCatalog;
    if (!isCatalog) {
      destroyCatalogPicker(row);
      if (!isElectrical) {
        var deliveryEl = row.querySelector('.sq-mat-delivery');
        if (deliveryEl) deliveryEl.value = '0.00';
      }
    } else {
      initCatalogPicker(row);
    }
    if (isElectrical) {
      applyElectricalDefaults(row, !!opts.forceElectricalDescription);
    }
    syncMatDetailsVisibility(row);
    syncImagePreview(row);
  }

  function applyCatalogToRow(row, item) {
    var deliveryEl = row.querySelector('.sq-mat-delivery');
    if (!item) {
      if (deliveryEl) deliveryEl.value = '0.00';
      syncMatDetailsVisibility(row);
      recalcAll();
      return;
    }
    var shortEl = row.querySelector('.sq-mat-short-name');
    var descEl = row.querySelector('.sq-mat-desc');
    var unitEl = row.querySelector('.sq-mat-unit');
    var urlEl = row.querySelector('.sq-mat-url');
    if (shortEl) shortEl.value = item.name || '';
    if (descEl) {
      descEl.value = item.description || item.store_name || item.name || '';
    }
    if (unitEl) unitEl.value = item.price || '';
    if (deliveryEl) deliveryEl.value = item.delivery || '0.00';
    if (urlEl) {
      urlEl.value = item.url || '';
      syncUrlOpenLink(urlEl);
    }
    var imageEl = row.querySelector('.sq-mat-image-url');
    if (imageEl) imageEl.value = item.image || '';
    syncImagePreview(row);
    if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function' && unitEl) {
      window.cpMoneyMask.wire([unitEl]);
    }
    syncMatDetailsVisibility(row);
    recalcAll();
  }

  function initCatalogPicker(row) {
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    if (!checked || checked.value !== 'catalog') return;
    var root = catalogRoot(row);
    if (!root || !window.AdminSearchSelect) return;
    AdminSearchSelect.init(root);
  }

  function initMaterialRow(row) {
    syncMatSourceUI(row);
    syncImagePreview(row);
    row.querySelectorAll('.sq-mat-source-radio').forEach(function (radio) {
      if (radio.dataset.sqSourceBound === '1') return;
      radio.dataset.sqSourceBound = '1';
      radio.addEventListener('change', function () {
        syncMatSourceUI(row, { forceElectricalDescription: this.value === 'electrical' });
        recalcAll();
      });
    });
  }

  function rewriteAttr(el, attr, w, i) {
    var val = el.getAttribute(attr);
    if (!val) return;
    var next = val
      .replace(/works\[(?:\d+|__W__)]/g, 'works[' + w + ']')
      .replace(/materials\[(?:\d+|__I__)]/g, 'materials[' + i + ']')
      .replace(/sq-mat-catalog-w(?:\d+|__W__)-(?:\d+|__I__)/g, 'sq-mat-catalog-w' + w + '-' + i)
      .replace(/sq-w(?:\d+|__W__)/g, 'sq-w' + w);
    if (next !== val) el.setAttribute(attr, next);
  }

  function reindexMaterialNames(card, w) {
    var list = card.querySelector('.sq-materials-list');
    if (!list) return;
    var rows = list.querySelectorAll('.sq-material-row');
    rows.forEach(function (row, i) {
      destroyCatalogPicker(row);
      row.querySelectorAll('[name], [id], [for]').forEach(function (el) {
        ['name', 'id', 'for'].forEach(function (attr) {
          rewriteAttr(el, attr, w, i);
        });
      });
      initMaterialRow(row);
    });
  }

  function reindexAll() {
    workCards().forEach(function (card, w) {
      card.setAttribute('data-work-index', String(w));
      card.querySelectorAll('[name], [id], [for]').forEach(function (el) {
        ['name', 'id', 'for'].forEach(function (attr) {
          rewriteAttr(el, attr, w, 0);
        });
      });
      var num = card.querySelector('.sq-work-number-val');
      if (num) num.textContent = String(w + 1);
      reindexMaterialNames(card, w);
      syncFixedLabourUI(card);
    });
    updateWorkChrome();
  }

  function placeContingency() {
    var block = document.getElementById('sq-contingency-block');
    var cards = workCards();
    if (!block || !cards.length) return;
    var slot = cards[cards.length - 1].querySelector('.sq-materials-footer-start');
    if (!slot || block.parentElement === slot) return;
    slot.appendChild(block);
  }

  function updateWorkChrome() {
    var cards = workCards();
    var n = cards.length;
    if (worksRoot) worksRoot.setAttribute('data-count', String(n));
    cards.forEach(function (card) {
      var rm = card.querySelector('.sq-remove-work');
      if (rm) rm.hidden = n < 2;
      var title = card.querySelector('.sq-work-title');
      if (title) {
        title.required = n >= 2;
        title.placeholder = n >= 2 ? 'e.g. Replace vanity' : 'Job name (optional)';
      }
    });
    placeContingency();
  }

  function addMaterialRow(card) {
    if (!materialTemplate) return;
    var list = card.querySelector('.sq-materials-list');
    if (!list) return;
    var w = workCards().indexOf(card);
    if (w < 0) w = 0;
    var html = materialTemplate.innerHTML
      .replace(/__W__/g, String(w))
      .replace(/__I__/g, String(list.querySelectorAll('.sq-material-row').length));
    var wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    var newRow = wrap.firstElementChild;
    list.appendChild(newRow);
    reindexMaterialNames(card, w);
    if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
      window.cpMoneyMask.wire(newRow.querySelectorAll('.cp-money-input'));
    }
    recalcAll();
  }

  function addWorkCard() {
    if (!workTemplate || !worksRoot) return;
    var html = workTemplate.innerHTML.replace(/__W__/g, String(workCards().length));
    var wrap = document.createElement('div');
    wrap.innerHTML = html.trim();
    var card = wrap.querySelector('.sq-work-card') || wrap.firstElementChild;
    worksRoot.appendChild(card);
    workCards().forEach(function (c) {
      if (c !== card) {
        c.classList.remove('is-open');
        var tog = c.querySelector('.sq-work-toggle');
        if (tog) tog.setAttribute('aria-expanded', 'false');
      }
    });
    card.classList.add('is-open');
    var toggle = card.querySelector('.sq-work-toggle');
    if (toggle) toggle.setAttribute('aria-expanded', 'true');
    reindexAll();
    if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
      window.cpMoneyMask.wire(card.querySelectorAll('.cp-money-input'));
    }
    card.querySelectorAll('.sq-material-row').forEach(initMaterialRow);
    recalcAll();
    saveOpenJobs();
    var title = card.querySelector('.sq-work-title');
    if (title) title.focus();
  }

  function sanitizeDecimalInput(input) {
    var raw = input.value;
    var cleaned = raw.replace(/[^0-9.]/g, '');
    var firstDot = cleaned.indexOf('.');
    if (firstDot !== -1) {
      cleaned = cleaned.slice(0, firstDot + 1) + cleaned.slice(firstDot + 1).replace(/\./g, '');
    }
    if (cleaned !== raw) {
      var pos = input.selectionStart;
      input.value = cleaned;
      try { input.setSelectionRange(pos - (raw.length - cleaned.length), pos - (raw.length - cleaned.length)); } catch (_) {}
    }
  }

  function syncUrlOpenLink(input) {
    var control = input.closest('.sq-mat-url-control');
    if (!control) return;
    var open = control.querySelector('.sq-mat-url-open');
    if (!open) return;
    var val = String(input.value || '').trim();
    if (val === '') {
      open.hidden = true;
      open.setAttribute('href', '#');
    } else {
      open.hidden = false;
      open.setAttribute('href', val);
    }
  }

  function syncImagePreview(row) {
    if (!row) return;
    var input = row.querySelector('.sq-mat-image-url');
    var wrap = row.querySelector('.sq-mat-thumb');
    var img = row.querySelector('.sq-mat-thumb-img');
    if (!wrap || !img) return;
    var checked = row.querySelector('.sq-mat-source-radio:checked');
    var isElectrical = checked && checked.value === 'electrical';
    var val = input ? String(input.value || '').trim() : '';
    if (isElectrical || val === '' || !/^https?:\/\//i.test(val)) {
      wrap.hidden = true;
      img.removeAttribute('src');
      return;
    }
    img.onload = function () {
      wrap.hidden = false;
    };
    img.onerror = function () {
      wrap.hidden = true;
    };
    wrap.hidden = false;
    if (img.getAttribute('src') !== val) {
      img.src = val;
    } else if (img.complete && img.naturalWidth === 0) {
      wrap.hidden = true;
    }
  }

  form.addEventListener('click', function (e) {
    var addMat = e.target.closest('.sq-add-material');
    if (addMat) {
      var card = addMat.closest('.sq-work-card');
      if (card) addMaterialRow(card);
      return;
    }
    var rmMat = e.target.closest('.sq-remove-material');
    if (rmMat) {
      var row = rmMat.closest('.sq-material-row');
      var cardRm = rmMat.closest('.sq-work-card');
      var list = cardRm ? cardRm.querySelector('.sq-materials-list') : null;
      if (!row || !list) return;
      if (list.querySelectorAll('.sq-material-row').length <= 1) return;
      var matName = materialRowLabel(row);
      confirmRemove('Remove ' + matName + '?', 'Remove material', function () {
        destroyCatalogPicker(row);
        row.remove();
        reindexMaterialNames(cardRm, workCards().indexOf(cardRm));
        recalcAll();
      });
      return;
    }
    var rmWork = e.target.closest('.sq-remove-work');
    if (rmWork) {
      if (workCards().length <= 1) return;
      var cardDel = rmWork.closest('.sq-work-card');
      if (!cardDel) return;
      var titleEl = cardDel.querySelector('.sq-work-title');
      var jobLabel = titleEl && String(titleEl.value || '').trim()
        ? String(titleEl.value).trim()
        : ('Job ' + (workCards().indexOf(cardDel) + 1));
      var matCount = cardDel.querySelectorAll('.sq-material-row').length;
      var jobMsg = 'Remove ' + jobLabel + ' and its ' + matCount + ' material row'
        + (matCount === 1 ? '' : 's') + '?';
      confirmRemove(jobMsg, 'Remove job', function () {
        var contBlock = document.getElementById('sq-contingency-block');
        if (contBlock && cardDel.contains(contBlock)) {
          var host = workCards().filter(function (c) { return c !== cardDel; }).pop();
          var slot = host && host.querySelector('.sq-materials-footer-start');
          if (slot) slot.appendChild(contBlock);
        }
        cardDel.querySelectorAll('.sq-material-row').forEach(destroyCatalogPicker);
        cardDel.remove();
        reindexAll();
        recalcAll();
        saveOpenJobs();
      });
      return;
    }
    var tog = e.target.closest('.sq-work-toggle');
    if (tog) {
      var cardT = tog.closest('.sq-work-card');
      if (!cardT) return;
      toggleWorkCard(cardT);
      return;
    }
    var header = e.target.closest('.sq-work-card__header');
    if (header && !e.target.closest('input, textarea, select, a, .sq-remove-work, label')) {
      var cardH = header.closest('.sq-work-card');
      if (cardH) toggleWorkCard(cardH);
    }
  });

  function materialRowLabel(row) {
    var shortEl = row.querySelector('.sq-mat-short-name');
    var descEl = row.querySelector('.sq-mat-desc');
    var shortName = shortEl ? String(shortEl.value || '').trim() : '';
    var desc = descEl ? String(descEl.value || '').trim() : '';
    return shortName || desc || 'this material';
  }

  function confirmRemove(message, title, onConfirm) {
    if (window.AdminDialog && typeof window.AdminDialog.confirm === 'function') {
      window.AdminDialog.confirm(message, { title: title, danger: true, confirmLabel: 'Remove' }).then(function (ok) {
        if (ok) onConfirm();
      });
      return;
    }
    if (window.confirm(message)) onConfirm();
  }

  function openJobsKey() {
    return 'admin-sq-open-jobs:' + (form.getAttribute('data-quote-id') || 'new');
  }

  function setWorkOpen(card, open) {
    card.classList.toggle('is-open', !!open);
    var btn = card.querySelector('.sq-work-toggle');
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function saveOpenJobs() {
    try {
      var open = [];
      workCards().forEach(function (card, i) {
        if (card.classList.contains('is-open')) open.push(i);
      });
      sessionStorage.setItem(openJobsKey(), JSON.stringify({ open: open, t: Date.now() }));
    } catch (err) {}
  }

  function restoreOpenJobs() {
    var stored;
    try {
      var raw = sessionStorage.getItem(openJobsKey());
      if (raw == null) return;
      stored = JSON.parse(raw);
    } catch (err) {
      return;
    }
    if (!stored || !Array.isArray(stored.open)) return;
    if (stored.t && (Date.now() - stored.t) > SCROLL_MAX_AGE_MS) return;
    workCards().forEach(function (card, i) {
      var shouldOpen = stored.open.indexOf(i) !== -1;
      if (card.querySelector('.admin-form-error, .is-invalid')) {
        shouldOpen = true;
      }
      setWorkOpen(card, shouldOpen);
    });
  }

  function toggleWorkCard(card) {
    var open = !card.classList.contains('is-open');
    setWorkOpen(card, open);
    saveOpenJobs();
  }

  var addWorkBtn = document.getElementById('sq-add-work');
  if (addWorkBtn) addWorkBtn.addEventListener('click', addWorkCard);

  form.addEventListener('admin-search-select:change', function (e) {
    var row = e.target && e.target.closest ? e.target.closest('.sq-material-row') : null;
    if (!row) return;
    var option = e.detail && e.detail.option
      ? e.detail.option
      : (e.detail && e.detail.id ? sqCatalogOptionMap[String(e.detail.id)] : null);
    applyCatalogToRow(row, option);
  });

  form.addEventListener('input', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-qty')) {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-url')) {
      syncUrlOpenLink(e.target);
    }
    if (e.target && e.target.classList && e.target.classList.contains('sq-mat-image-url')) {
      var imageRow = e.target.closest('.sq-material-row');
      if (imageRow) syncImagePreview(imageRow);
    }
    if (e.target && e.target.id === 'sq-contingency-percent') {
      sanitizeDecimalInput(e.target);
    }
    if (e.target && e.target.closest('.sq-work-card, #sq-contingency-block, #q-building')) {
      recalcAll();
    }
  });

  form.addEventListener('paste', function (e) {
    if (!e.target || !e.target.classList || !e.target.classList.contains('sq-mat-image-url')) return;
    var pastedRow = e.target.closest('.sq-material-row');
    if (!pastedRow) return;
    setTimeout(function () { syncImagePreview(pastedRow); }, 0);
  });

  form.addEventListener('change', function (e) {
    if (!e.target) return;
    if (
      e.target.classList.contains('sq-mat-hst-cb') ||
      e.target.classList.contains('sq-mat-hst-inclusive-cb') ||
      e.target.classList.contains('sq-labour-mode') ||
      e.target.id === 'sq-contingency-enabled' ||
      e.target.classList.contains('sq-labour-use-fixed-cb') ||
      e.target.classList.contains('sq-labour-fixed-hst-inc-cb') ||
      e.target.id === 'sq-include-labour-hst' ||
      e.target.id === 'q-building-include'
    ) {
      if (e.target.classList.contains('sq-labour-use-fixed-cb') || e.target.id === 'sq-include-labour-hst') {
        workCards().forEach(syncFixedLabourUI);
      }
      recalcAll();
    }
  });

  workCards().forEach(function (card) {
    card.querySelectorAll('.sq-material-row').forEach(initMaterialRow);
    syncFixedLabourUI(card);
  });
  updateWorkChrome();
  restoreOpenJobs();
  form.addEventListener('submit', saveOpenJobs);
  recalcAll();

  document.addEventListener('commute-expense:change', function (e) {
    var d = e.detail || {};
    lastCommuteAmount = d.included ? Number(d.amount || 0) : 0;
    recalcAll();
  });
  document.addEventListener('debris-removal:change', function (e) {
    var d = e.detail || {};
    lastDebrisAmount = d.included ? Number(d.amount || 0) : 0;
    recalcAll();
  });
})();
