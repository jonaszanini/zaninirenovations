/**
 * Dynamic description / amount / HST rows on quotation forms.
 * Additional-cost cards emit additional-costs:change.
 * Trade-line cards emit the event named on data-ac-event.
 */
(function () {
  'use strict';

  var HST_RATE = 0.13;

  function parseAmount(raw) {
    var n = parseFloat(String(raw == null ? '' : raw).replace(/,/g, ''));
    return isFinite(n) && n > 0 ? Math.round(n * 100) / 100 : 0;
  }

  function money(n) {
    return 'CA$' + (Number(n) || 0).toLocaleString('en-CA', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function rowPreTax(amount, hstIncluded) {
    if (amount <= 0) return 0;
    return hstIncluded
      ? Math.round((amount / (1 + HST_RATE)) * 100) / 100
      : Math.round(amount * 100) / 100;
  }

  function rowTaxIn(amount, hstIncluded) {
    if (amount <= 0) return 0;
    return hstIncluded
      ? Math.round(amount * 100) / 100
      : Math.round(amount * (1 + HST_RATE) * 100) / 100;
  }

  function attr(card, name, fallback) {
    var v = card.getAttribute(name);
    return v != null && v !== '' ? v : fallback;
  }

  function fieldName(card) {
    return attr(card, 'data-ac-field', 'additional_costs');
  }

  function idPrefix(card) {
    return attr(card, 'data-ac-id-prefix', 'q-ac');
  }

  function extraInputClass(card) {
    return attr(card, 'data-ac-input-class', '');
  }

  function collect(card) {
    var materialsPreTax = 0;
    var taxInclusiveTotal = 0;
    card.querySelectorAll('[data-ac-row]').forEach(function (row) {
      var amountInp = row.querySelector('[data-ac-amount]');
      var amount = parseAmount(amountInp && amountInp.value);
      var hstToggle = row.querySelector('[data-ac-hst-toggle]');
      var hstIncluded = !!(hstToggle && hstToggle.checked);
      materialsPreTax += rowPreTax(amount, hstIncluded);
      taxInclusiveTotal += rowTaxIn(amount, hstIncluded);
    });
    materialsPreTax = Math.round(materialsPreTax * 100) / 100;
    taxInclusiveTotal = Math.round(taxInclusiveTotal * 100) / 100;
    var materialsHst = Math.round(materialsPreTax * HST_RATE * 100) / 100;
    return {
      materialsPreTax: materialsPreTax,
      materialsHst: materialsHst,
      taxInclusiveTotal: taxInclusiveTotal
    };
  }

  function notify(card) {
    var detail = collect(card);
    var sumEl = card.querySelector('[data-ac-summary]');
    var mode = attr(card, 'data-ac-summary-mode', 'materials');
    if (sumEl) {
      if (detail.taxInclusiveTotal > 0) {
        sumEl.hidden = false;
        if (mode === 'tax-in') {
          sumEl.textContent = 'Adds ' + money(detail.taxInclusiveTotal) + ' tax-in to the quote';
        } else {
          sumEl.textContent = 'Adds ' + money(detail.materialsPreTax) + ' to materials (+ '
            + money(detail.materialsHst) + ' HST) · ' + money(detail.taxInclusiveTotal) + ' tax-in total';
        }
      } else {
        sumEl.hidden = true;
        sumEl.textContent = '';
      }
    }
    var eventName = attr(card, 'data-ac-event', 'additional-costs:change');
    try {
      card.dispatchEvent(new CustomEvent(eventName, { bubbles: true, detail: detail }));
    } catch (e) { /* IE */ }
    document.dispatchEvent(new CustomEvent(eventName, { bubbles: true, detail: detail }));
    if (eventName === 'additional-costs:change' && window.AdditionalCosts && typeof window.AdditionalCosts._notify === 'function') {
      window.AdditionalCosts._notify(detail);
    }
  }

  function nextIndex(card) {
    var max = -1;
    card.querySelectorAll('[data-ac-row]').forEach(function (row) {
      var idx = parseInt(row.getAttribute('data-ac-index') || '0', 10);
      if (isFinite(idx) && idx > max) max = idx;
    });
    return max + 1;
  }

  function syncHstLabel(row) {
    var toggle = row.querySelector('[data-ac-hst-toggle]');
    var text = row.querySelector('[data-ac-hst-text]');
    if (!text) return;
    text.textContent = (toggle && toggle.checked) ? 'Tax included' : 'Before tax';
  }

  function bindRow(card, row) {
    var hstToggle = row.querySelector('[data-ac-hst-toggle]');
    if (hstToggle) {
      hstToggle.addEventListener('change', function () {
        syncHstLabel(row);
        notify(card);
      });
      syncHstLabel(row);
    }
    var amount = row.querySelector('[data-ac-amount]');
    var desc = row.querySelector('[data-ac-description]');
    if (amount) {
      amount.addEventListener('input', function () { notify(card); });
      amount.addEventListener('change', function () { notify(card); });
    }
    if (desc) {
      desc.addEventListener('change', function () { notify(card); });
    }
    var removeBtn = row.querySelector('[data-ac-remove]');
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        var list = card.querySelector('[data-ac-rows]');
        row.remove();
        if (list && !list.querySelector('[data-ac-row]')) {
          addRow(card, { description: '', amount: '', hst_included: true });
        }
        notify(card);
      });
    }
  }

  function escapeAttr(value) {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  }

  function rowHtml(card, idx, data) {
    data = data || {};
    var field = fieldName(card);
    var prefix = idPrefix(card);
    var extra = extraInputClass(card);
    var extraCls = extra ? ' ' + extra : '';
    var desc = data.description != null ? String(data.description) : '';
    var amount = data.amount != null && data.amount !== '' ? String(data.amount) : '';
    var hstIncluded = data.hst_included !== false && data.hst_included !== 0 && data.hst_included !== '0';
    var ph = escapeAttr(attr(card, 'data-ac-placeholder', 'e.g. Dumpster rental, permits'));
    return ''
      + '<div class="q-ac-row" data-ac-row data-ac-index="' + idx + '">'
      +   '<div class="admin-form-group q-ac-row__desc">'
      +     '<label for="' + prefix + '-desc-' + idx + '">Description</label>'
      +     '<input type="text" id="' + prefix + '-desc-' + idx + '" name="' + field + '[' + idx + '][description]"'
      +            ' class="admin-input' + extraCls + '" maxlength="200" placeholder="' + ph + '"'
      +            ' value="' + escapeAttr(desc) + '" data-ac-description>'
      +   '</div>'
      +   '<div class="admin-form-group q-ac-row__amount">'
      +     '<label for="' + prefix + '-amt-' + idx + '">Amount (CA$)</label>'
      +     '<input type="text" id="' + prefix + '-amt-' + idx + '" name="' + field + '[' + idx + '][amount]"'
      +            ' class="admin-input cp-money-input' + extraCls + '" inputmode="decimal" autocomplete="off" data-cp-money-entry="direct"'
      +            ' placeholder="0.00" value="' + escapeAttr(amount) + '" data-ac-amount>'
      +   '</div>'
      +   '<div class="admin-form-group q-ac-row__hst">'
      +     '<span class="q-ac-row__hst-label" id="' + prefix + '-hst-label-' + idx + '">Tax</span>'
      +     '<label class="cp-visit-toggle cp-visit-toggle--compact q-ac-hst-toggle" for="' + prefix + '-hst-' + idx + '">'
      +       '<input type="hidden" name="' + field + '[' + idx + '][hst_included]" value="0">'
      +       '<input type="checkbox" id="' + prefix + '-hst-' + idx + '" name="' + field + '[' + idx + '][hst_included]"'
      +              ' value="1" data-ac-hst-toggle aria-describedby="' + prefix + '-hst-label-' + idx + '"'
      +              (hstIncluded ? ' checked' : '') + '>'
      +       '<span class="cp-visit-toggle__control" aria-hidden="true"></span>'
      +       '<span class="cp-visit-toggle__label" data-ac-hst-text">'
      +         (hstIncluded ? 'Tax included' : 'Before tax') + '</span>'
      +     '</label>'
      +   '</div>'
      +   '<div class="q-ac-row__actions">'
      +     '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ac-remove'
      +             ' title="Remove line" aria-label="Remove line">'
      +       '<i class="fa fa-times" aria-hidden="true"></i>'
      +     '</button>'
      +   '</div>'
      + '</div>';
  }

  function addRow(card, data) {
    var list = card.querySelector('[data-ac-rows]');
    if (!list) return;
    var idx = nextIndex(card);
    var wrap = document.createElement('div');
    wrap.innerHTML = rowHtml(card, idx, data);
    var row = wrap.firstElementChild;
    list.appendChild(row);
    bindRow(card, row);
    if (window.cpMoneyMask) {
      window.cpMoneyMask.wire(row.querySelector('[data-ac-amount]'));
    }
    try {
      document.dispatchEvent(new CustomEvent('amount-lines:row-added', { bubbles: true, detail: { card: card, row: row } }));
    } catch (e) { /* IE */ }
    var focus = row.querySelector('[data-ac-description]');
    if (focus && data && data.focus) focus.focus();
  }

  function init(card) {
    if (!card || card.getAttribute('data-ac-ready') === '1') return;
    card.setAttribute('data-ac-ready', '1');

    card.querySelectorAll('[data-ac-row]').forEach(function (row) {
      bindRow(card, row);
    });

    var addBtn = card.querySelector('[data-ac-add]');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        addRow(card, { description: '', amount: '', hst_included: true, focus: true });
        notify(card);
      });
    }

    notify(card);
  }

  function boot() {
    document.querySelectorAll('[data-ac-card]').forEach(init);
  }

  window.AdditionalCosts = window.AdditionalCosts || {
    _listeners: [],
    onChange: function (fn) {
      if (typeof fn === 'function') this._listeners.push(fn);
    },
    _notify: function (detail) {
      (this._listeners || []).forEach(function (fn) {
        try { fn(detail); } catch (e) {}
      });
    },
    getTotals: function () {
      var card = document.querySelector('[data-cp-additional-costs]');
      return card ? collect(card) : { materialsPreTax: 0, materialsHst: 0, taxInclusiveTotal: 0 };
    },
    formatMoney: money
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
