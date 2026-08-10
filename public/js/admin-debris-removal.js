/**
 * Debris removal Yes/No card — toggles amount field and notifies live totals.
 */
(function () {
  'use strict';

  function parseAmount(raw) {
    var n = parseFloat(String(raw == null ? '' : raw).replace(/,/g, ''));
    return isFinite(n) && n > 0 ? Math.round(n * 100) / 100 : 0;
  }

  function money(n) {
    return 'CA$' + (Number(n) || 0).toFixed(2);
  }

  function notify(card, amount, included) {
    var detail = { amount: amount, included: included };
    try {
      card.dispatchEvent(new CustomEvent('debris-removal:change', { bubbles: true, detail: detail }));
    } catch (e) { /* IE */ }
    document.dispatchEvent(new CustomEvent('debris-removal:change', { detail: detail }));
    if (window.DebrisRemoval && typeof window.DebrisRemoval._notify === 'function') {
      window.DebrisRemoval._notify(detail);
    }
  }

  function syncBody(card) {
    var yes = card.querySelector('[data-cp-debris-include-yes]');
    var body = card.querySelector('[data-cp-debris-body]');
    var amountInput = card.querySelector('[data-cp-debris-amount]');
    var included = !!(yes && yes.checked);

    card.querySelectorAll('.q-commute-choice__option').forEach(function (opt) {
      var inp = opt.querySelector('input');
      opt.classList.toggle('is-selected', !!(inp && inp.checked));
    });

    if (body) {
      body.hidden = !included;
    }
    if (amountInput) {
      amountInput.required = included;
      if (!included) {
        // Keep typed value in the DOM for if they toggle Yes again, but do not require it.
      }
    }

    var amount = included ? parseAmount(amountInput && amountInput.value) : 0;
    notify(card, amount, included);
  }

  function initDebrisRemoval(card) {
    if (!card || card.getAttribute('data-cp-debris-ready') === '1') return;
    card.setAttribute('data-cp-debris-ready', '1');

    card.addEventListener('change', function (e) {
      if (e.target && (e.target.name === 'include_debris_removal' || e.target.hasAttribute('data-cp-debris-amount'))) {
        syncBody(card);
      }
    });
    card.addEventListener('input', function (e) {
      if (e.target && e.target.hasAttribute('data-cp-debris-amount')) {
        syncBody(card);
      }
    });

    syncBody(card);
  }

  function boot() {
    document.querySelectorAll('[data-cp-debris-removal]').forEach(initDebrisRemoval);
  }

  window.DebrisRemoval = window.DebrisRemoval || {
    _listeners: [],
    onChange: function (fn) {
      if (typeof fn === 'function') this._listeners.push(fn);
    },
    _notify: function (detail) {
      (this._listeners || []).forEach(function (fn) {
        try { fn(detail); } catch (e) {}
      });
    },
    getAmount: function () {
      var card = document.querySelector('[data-cp-debris-removal]');
      if (!card) return 0;
      var yes = card.querySelector('[data-cp-debris-include-yes]');
      if (!yes || !yes.checked) return 0;
      var inp = card.querySelector('[data-cp-debris-amount]');
      return parseAmount(inp && inp.value);
    },
    isIncluded: function () {
      var yes = document.querySelector('[data-cp-debris-removal] [data-cp-debris-include-yes]');
      return !!(yes && yes.checked);
    },
    formatMoney: money
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
