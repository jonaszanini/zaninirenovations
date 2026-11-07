/**
 * Shared quotation sticky footer. Mirrors the editor's live grand total
 * (quote:grand-total / window.__quoteGrandTotal) and forwards Save to the
 * header Save quotation button. Does not compute a second total.
 * No-ops when [data-qh-footer] is absent (Painting is excluded).
 */
(function (root, factory) {
  var api = factory(root);
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  root.AdminQuotationEditorFooter = api;
  if (root.document && root.document.addEventListener) {
    if (root.document.readyState === 'loading') {
      root.document.addEventListener('DOMContentLoaded', function () { api.bind(root); });
    } else {
      api.bind(root);
    }
  }
})(typeof window !== 'undefined' ? window : globalThis, function (root) {
  var HEIGHT_VAR = '--qh-sticky-bar-h';
  var HEIGHT_VAR_BR = '--br-sticky-bar-h';

  function formatCad(n) {
    var v = Number(n);
    if (!isFinite(v)) v = 0;
    var parts = v.toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return 'CA$' + parts.join('.');
  }

  function footerEl(doc) {
    return doc.querySelector('[data-qh-footer]');
  }

  function amountEl(footer) {
    return footer.querySelector('[data-qh-footer-amount]');
  }

  function syncAmount(el, total) {
    if (!el) return;
    el.textContent = formatCad(total);
  }

  function measure(footer, doc) {
    if (!footer || !footer.getBoundingClientRect) return;
    var h = Math.ceil(footer.getBoundingClientRect().height);
    if (!isFinite(h) || h < 1) h = 64;
    var html = (doc && doc.documentElement) || (root.document && root.document.documentElement);
    if (!html || !html.style) return;
    var px = h + 'px';
    html.style.setProperty(HEIGHT_VAR, px);
    html.style.setProperty(HEIGHT_VAR_BR, px);
  }

  function bind(win) {
    win = win || root;
    var doc = win.document;
    if (!doc || !doc.querySelector) return null;
    var footer = footerEl(doc);
    if (!footer) return null;

    var amount = amountEl(footer);
    if (typeof win.__quoteGrandTotal === 'number') {
      syncAmount(amount, win.__quoteGrandTotal);
    }

    function onTotal(e) {
      var t = e && e.detail ? Number(e.detail.total) : NaN;
      if (!isFinite(t)) return;
      syncAmount(amount, t);
    }
    doc.addEventListener('quote:grand-total', onTotal);

    function onSaveClick(e) {
      var btn = e.target && e.target.closest && e.target.closest('[data-qh-footer-save]');
      if (!btn || !footer.contains(btn)) return;
      var headerSave = doc.querySelector('[data-qh-header] .qh-actions__save');
      if (!headerSave) return;
      e.preventDefault();
      if (typeof headerSave.click === 'function') headerSave.click();
    }
    footer.addEventListener('click', onSaveClick);

    function syncHeight() {
      measure(footer, doc);
    }
    syncHeight();
    if (win.addEventListener) win.addEventListener('resize', syncHeight);
    if (typeof win.ResizeObserver !== 'undefined') {
      new win.ResizeObserver(syncHeight).observe(footer);
    }

    return {
      footer: footer,
      amount: amount,
      onTotal: onTotal,
      onSaveClick: onSaveClick,
    };
  }

  return {
    formatCad: formatCad,
    bind: bind,
    syncAmount: syncAmount,
    measure: measure,
  };
});
