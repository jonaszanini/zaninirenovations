/**
 * Room Area → quote: live line preview from saved metrics + waste/price.
 * Posted quantities are ignored server-side; this preview is display-only.
 */
(function () {
  var root = document.querySelector('[data-room-area-quote]');
  if (!root) return;

  var form = root.querySelector('#rq-form');
  var preview = root.querySelector('[data-rq-preview]');
  var previewBody = root.querySelector('[data-rq-preview-body]');
  var previewTotal = root.querySelector('[data-rq-preview-total]');
  var submitBtn = root.querySelector('[data-rq-submit]');
  var quoteSelect = root.querySelector('#rq-quotation');
  var newFields = root.querySelectorAll('[data-rq-new-fields]');
  var tradesMeta = {};
  try {
    tradesMeta = JSON.parse(root.getAttribute('data-trades') || '{}') || {};
  } catch (e) {
    tradesMeta = {};
  }

  function parseMoney(value) {
    if (window.cpMoneyMask && typeof window.cpMoneyMask.parse === 'function') {
      return window.cpMoneyMask.parse(value);
    }
    var n = parseFloat(String(value || '').replace(/,/g, ''));
    return isFinite(n) ? n : 0;
  }

  function formatMoney(n) {
    var v = Math.round((n + Number.EPSILON) * 100) / 100;
    return 'CA$' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  function formatQty(n) {
    var s = (Math.round(n * 100) / 100).toFixed(2);
    return s.replace(/\.?0+$/, '') || '0';
  }

  function applyWaste(base, waste) {
    base = Math.max(0, base);
    waste = Math.max(0, Math.min(100, waste));
    if (base <= 0) return 0;
    var raw = base * (1 + waste / 100);
    return Math.round(Math.ceil((raw - 1e-9) * 100)) / 100;
  }

  function tradeRows() {
    return Array.prototype.slice.call(root.querySelectorAll('[data-rq-trade]'));
  }

  function selectedLines() {
    var lines = [];
    tradeRows().forEach(function (row) {
      var enable = row.querySelector('[data-rq-enable]');
      if (!enable || !enable.checked) return;
      var trade = row.getAttribute('data-rq-trade');
      var base = parseFloat(row.getAttribute('data-rq-base') || '0') || 0;
      var wasteEl = row.querySelector('[data-rq-waste]');
      var waste = parseFloat(String(wasteEl && wasteEl.value ? wasteEl.value : '0').replace(/,/g, '')) || 0;
      var priceEl = row.querySelector('[data-rq-price]');
      var price = parseMoney(priceEl ? priceEl.value : 0);
      var qty = applyWaste(base, waste);
      var label = (tradesMeta[trade] && tradesMeta[trade].label) || trade;
      var catalog = row.querySelector('[data-admin-search-select] input[type="hidden"]');
      var catalogLabel = row.querySelector('[data-admin-search-select] input[type="text"]');
      var materialName = catalogLabel && catalogLabel.value ? catalogLabel.value.split(' · ')[0] : '';
      lines.push({
        trade: trade,
        label: materialName ? label + ' · ' + materialName : label,
        qty: qty,
        unit: row.getAttribute('data-rq-unit') || '',
        price: price,
        total: Math.round(qty * price * 100) / 100,
        hasCatalog: !!(catalog && catalog.value)
      });
    });
    return lines;
  }

  function renderPreview() {
    var lines = selectedLines();
    tradeRows().forEach(function (row) {
      var on = row.querySelector('[data-rq-enable]');
      row.classList.toggle('is-on', !!(on && on.checked));
    });
    if (!preview || !previewBody) return;
    if (lines.length === 0) {
      preview.hidden = true;
      if (submitBtn) submitBtn.disabled = true;
      return;
    }
    preview.hidden = false;
    if (submitBtn) submitBtn.disabled = false;
    var html = '';
    var sum = 0;
    lines.forEach(function (line) {
      sum += line.total;
      html +=
        '<li class="util-rq-preview__row">' +
          '<span class="util-rq-preview__name">' + escapeHtml(line.label) + '</span>' +
          '<span class="util-rq-preview__math">' +
            formatQty(line.qty) + ' ' + escapeHtml(line.unit) +
            ' × ' + formatMoney(line.price) +
            ' = <strong>' + formatMoney(line.total) + '</strong>' +
          '</span>' +
        '</li>';
    });
    previewBody.innerHTML = html;
    if (previewTotal) previewTotal.textContent = formatMoney(sum);
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function toggleNewFields() {
    var isNew = !quoteSelect || !quoteSelect.value;
    newFields.forEach(function (el) {
      el.hidden = !isNew;
    });
  }

  root.addEventListener('admin-search-select:change', function (e) {
    var picker = e.target.closest('[data-rq-catalog]');
    if (!picker) return;
    var trade = picker.getAttribute('data-rq-catalog');
    var row = root.querySelector('[data-rq-trade="' + trade + '"]');
    if (!row) return;
    var opt = (e.detail && e.detail.option) || null;
    var priceEl = row.getAttribute ? row.querySelector('[data-rq-price]') : null;
    if (priceEl && opt && opt.price != null && String(opt.price) !== '') {
      priceEl.value = opt.price;
      if (window.cpMoneyMask && typeof window.cpMoneyMask.wire === 'function') {
        window.cpMoneyMask.wire(priceEl);
      }
    }
    var enable = row.querySelector('[data-rq-enable]');
    if (enable && opt && opt.id && !enable.checked) {
      enable.checked = true;
    }
    renderPreview();
  });

  root.addEventListener('input', function (e) {
    if (e.target.matches('[data-rq-waste], [data-rq-price], [data-rq-enable]')) {
      renderPreview();
    }
  });
  root.addEventListener('change', function (e) {
    if (e.target.matches('[data-rq-enable], #rq-quotation')) {
      if (e.target.id === 'rq-quotation') toggleNewFields();
      renderPreview();
    }
  });

  if (form) {
    form.addEventListener('submit', function () {
      if (window.cpMoneyMask && typeof window.cpMoneyMask.stripFormCommas === 'function') {
        window.cpMoneyMask.stripFormCommas(form);
      } else {
        form.querySelectorAll('.cp-money-input').forEach(function (input) {
          input.value = String(parseMoney(input.value) || 0);
        });
      }
    });
  }

  toggleNewFields();
  renderPreview();
})();
