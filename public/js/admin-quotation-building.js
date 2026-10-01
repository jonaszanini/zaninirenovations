(function () {
  var HST = 0.13;
  var lastBase = 0;
  var amountEl = document.getElementById('q-building-added-amount');
  var detailEl = document.getElementById('q-building-added-detail');
  var includeEl = document.getElementById('q-building-include');
  var percentEl = document.getElementById('q-building-percent');
  if (!amountEl) return;

  function round2(n) {
    return Math.round(Number(n || 0) * 100) / 100;
  }

  function money(n) {
    var v = round2(n);
    return 'CA$' + v.toLocaleString('en-CA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function percent() {
    if (!percentEl) return 30;
    var raw = String(percentEl.value || '').trim();
    if (raw === '') return 30;
    var v = parseFloat(raw.replace(/,/g, ''));
    if (!isFinite(v) || v < 0) return 0;
    if (v > 200) return 200;
    return v;
  }

  function formatPct(n) {
    return String(Math.round(n * 100) / 100);
  }

  function labourHstEnabled() {
    var el = document.getElementById('sq-include-labour-hst');
    if (el) return !!el.checked;
    return true;
  }

  function buildingRowAmount(data) {
    var lists = [data && data.breakdown, data && data.labor];
    for (var i = 0; i < lists.length; i++) {
      var rows = lists[i] || [];
      for (var j = 0; j < rows.length; j++) {
        if (rows[j] && rows[j].key === 'building') {
          return Number(rows[j].amount || 0);
        }
      }
    }
    return 0;
  }

  function labourBaseFrom(data) {
    if (!data || typeof data !== 'object') return null;
    var calc = data.calculation;
    if (calc && calc.labor_total != null) {
      var tileLabor = Number(calc.labor_total || 0);
      var tileSur = Number(calc.building_labor_surcharge || 0);
      if (!isFinite(tileLabor)) return null;
      if (!isFinite(tileSur)) tileSur = 0;
      return round2(Math.max(0, tileLabor - tileSur));
    }
    var summary = data.summary;
    if (!summary || summary.labor_pre_hst == null) return null;
    var laborPre = Number(summary.labor_pre_hst);
    if (!isFinite(laborPre)) return null;
    var sur = 0;
    if (summary.building_labor_surcharge != null && summary.building_labor_surcharge !== '') {
      sur = Number(summary.building_labor_surcharge) || 0;
    } else {
      sur = buildingRowAmount(data);
    }
    if (!isFinite(sur)) sur = 0;
    return round2(Math.max(0, laborPre - sur));
  }

  function render() {
    var pct = percent();
    var surcharge = round2(lastBase * (pct / 100));
    var hst = labourHstEnabled() ? round2(surcharge * HST) : 0;
    var quoteAdd = round2(surcharge + hst);
    amountEl.textContent = money(quoteAdd);
    if (!detailEl) return;
    var pctLabel = formatPct(pct) + '% of labour';
    if (hst > 0 && surcharge > 0) {
      detailEl.textContent = pctLabel + ' (' + money(surcharge) + ' before HST)';
    } else {
      detailEl.textContent = pctLabel;
    }
  }

  window.QuotationBuildingLaborUi = {
    setLabourBase: function (base) {
      var n = Number(base);
      lastBase = isFinite(n) && n > 0 ? round2(n) : 0;
      render();
    },
    sync: function (data) {
      var base = labourBaseFrom(data);
      if (base == null) return;
      lastBase = base;
      render();
    }
  };

  if (percentEl) {
    percentEl.addEventListener('input', render);
    percentEl.addEventListener('change', render);
  }
  var expandEl = document.getElementById('q-building-expandable');
  var settleTimer = null;

  function syncExpand(animate) {
    if (!expandEl || !includeEl) return;
    var open = !!includeEl.checked;
    clearTimeout(settleTimer);
    expandEl.classList.remove('is-settled');
    if (open) {
      settleTimer = setTimeout(function () {
        expandEl.classList.add('is-settled');
      }, animate ? 380 : 0);
    }
    expandEl.classList.toggle('is-open', open);
    expandEl.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
      expandEl.removeAttribute('inert');
    } else {
      expandEl.setAttribute('inert', '');
    }
  }

  if (includeEl) {
    includeEl.addEventListener('change', function () {
      syncExpand(true);
      render();
    });
    syncExpand(false);
  }
  document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'sq-include-labour-hst') render();
  });

  render();
})();
