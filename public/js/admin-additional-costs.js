/**
 * Dynamic additional-costs rows on quotation forms.
 * Emits additional-costs:change with materialsPreTax, materialsHst, taxInclusiveTotal.
 */
(function () {
  'use strict';

  var HST_RATE = 0.13;
  var rowIndex = 0;

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

  function collect(card) {
    var materialsPreTax = 0;
    var taxInclusiveTotal = 0;
    card.querySelectorAll('[data-ac-row]').forEach(function (row) {
      var amountInp = row.querySelector('[data-ac-amount]');
      var amount = parseAmount(amountInp && amountInp.value);
      var hstYes = row.querySelector('[data-ac-hst-yes]');
      var hstIncluded = !!(hstYes && hstYes.checked);
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
    if (sumEl) {
      if (detail.taxInclusiveTotal > 0) {
        sumEl.hidden = false;
        sumEl.textContent = 'Adds ' + money(detail.materialsPreTax) + ' to materials (+ '
          + money(detail.materialsHst) + ' HST) · ' + money(detail.taxInclusiveTotal) + ' tax-in total';
      } else {
        sumEl.hidden = true;
        sumEl.textContent = '';
      }
    }
    try {
      card.dispatchEvent(new CustomEvent('additional-costs:change', { bubbles: true, detail: detail }));
    } catch (e) { /* IE */ }
    document.dispatchEvent(new CustomEvent('additional-costs:change', { detail: detail }));
    if (window.AdditionalCosts && typeof window.AdditionalCosts._notify === 'function') {
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

  function bindRow(card, row) {
    row.querySelectorAll('[data-ac-hst-yes], [data-ac-hst-no]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        row.querySelectorAll('.q-ac-hst-option').forEach(function (opt) {
          var inp = opt.querySelector('input');
          opt.classList.toggle('is-selected', !!(inp && inp.checked));
        });
        notify(card);
      });
    });
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

  function rowHtml(idx, data) {
    data = data || {};
    var desc = data.description != null ? String(data.description) : '';
    var amount = data.amount != null && data.amount !== '' ? String(data.amount) : '';
    var hstIncluded = data.hst_included !== false && data.hst_included !== 0 && data.hst_included !== '0';
    return ''
      + '<div class="q-ac-row" data-ac-row data-ac-index="' + idx + '">'
      +   '<div class="admin-form-group q-ac-row__desc">'
      +     '<label for="q-ac-desc-' + idx + '">Description</label>'
      +     '<input type="text" id="q-ac-desc-' + idx + '" name="additional_costs[' + idx + '][description]"'
      +            ' class="admin-input" maxlength="200" placeholder="e.g. Dumpster rental, permits"'
      +            ' value="' + desc.replace(/"/g, '&quot;') + '" data-ac-description>'
      +   '</div>'
      +   '<div class="admin-form-group q-ac-row__amount">'
      +     '<label for="q-ac-amt-' + idx + '">Amount (CA$)</label>'
      +     '<input type="number" id="q-ac-amt-' + idx + '" name="additional_costs[' + idx + '][amount]"'
      +            ' class="admin-input" inputmode="decimal" step="0.01" min="0" max="999999"'
      +            ' placeholder="0.00" value="' + amount.replace(/"/g, '&quot;') + '" data-ac-amount>'
      +   '</div>'
      +   '<div class="admin-form-group q-ac-row__hst">'
      +     '<span class="q-ac-row__hst-label">Tax</span>'
      +     '<div class="q-ac-hst-choice" role="radiogroup" aria-label="Tax on additional cost">'
      +       '<label class="q-ac-hst-option' + (hstIncluded ? ' is-selected' : '') + '">'
      +         '<input type="radio" name="additional_costs[' + idx + '][hst_included]" value="1"'
      +                ' class="q-ac-hst-option__input" data-ac-hst-yes' + (hstIncluded ? ' checked' : '') + '>'
      +         '<span>Tax included</span>'
      +       '</label>'
      +       '<label class="q-ac-hst-option' + (!hstIncluded ? ' is-selected' : '') + '">'
      +         '<input type="radio" name="additional_costs[' + idx + '][hst_included]" value="0"'
      +                ' class="q-ac-hst-option__input" data-ac-hst-no' + (!hstIncluded ? ' checked' : '') + '>'
      +         '<span>Before tax</span>'
      +       '</label>'
      +     '</div>'
      +   '</div>'
      +   '<div class="q-ac-row__actions">'
      +     '<button type="button" class="admin-btn admin-btn--ghost admin-btn--sm" data-ac-remove'
      +             ' title="Remove cost" aria-label="Remove additional cost">'
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
    wrap.innerHTML = rowHtml(idx, data);
    var row = wrap.firstElementChild;
    list.appendChild(row);
    bindRow(card, row);
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
    document.querySelectorAll('[data-cp-additional-costs]').forEach(init);
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
