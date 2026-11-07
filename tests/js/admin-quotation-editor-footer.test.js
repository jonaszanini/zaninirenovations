'use strict';

const assert = require('assert');
const footer = require('../../public/js/admin-quotation-editor-footer.js');

assert.strictEqual(typeof footer.bind, 'function');
assert.strictEqual(footer.formatCad(0), 'CA$0.00');
assert.strictEqual(footer.formatCad(0.4), 'CA$0.40');
assert.strictEqual(footer.formatCad(1234.5), 'CA$1,234.50');
assert.strictEqual(footer.formatCad(1234567.89), 'CA$1,234,567.89');
assert.strictEqual(footer.formatCad('not-a-number'), 'CA$0.00');

function makeEnv(opts) {
  opts = opts || {};
  const css = {};
  const docListeners = {};
  const footerListeners = {};
  const amount = {
    textContent: opts.initialAmount || 'CA$0.00',
    attrs: {},
    setAttribute(name, value) { this.attrs[name] = String(value); },
    removeAttribute(name) { delete this.attrs[name]; },
    getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; },
  };
  const headerSave = {
    clicked: 0,
    click() { this.clicked += 1; },
  };
  const saveBtn = {
    closest(sel) { return sel === '[data-qh-footer-save]' ? saveBtn : null; },
  };
  const footerEl = {
    contains(node) { return node === saveBtn || node === amount; },
    querySelector(sel) {
      if (sel === '[data-qh-footer-amount]') return amount;
      return null;
    },
    addEventListener(type, fn) {
      footerListeners[type] = footerListeners[type] || [];
      footerListeners[type].push(fn);
    },
    getBoundingClientRect() { return { height: 56 }; },
  };
  const html = {
    style: { setProperty(name, value) { css[name] = value; } },
  };
  const doc = {
    documentElement: html,
    querySelector(sel) {
      if (!opts.hasFooter) return null;
      if (sel === '[data-qh-footer]') return footerEl;
      if (sel === '[data-qh-header] .qh-actions__save') return headerSave;
      return null;
    },
    addEventListener(type, fn) {
      docListeners[type] = docListeners[type] || [];
      docListeners[type].push(fn);
    },
  };
  const win = {
    document: doc,
    addEventListener() {},
  };
  if (typeof opts.grand === 'number') {
    win.__quoteGrandTotal = opts.grand;
  }
  return { win, amount, headerSave, saveBtn, css, docListeners, footerListeners };
}

(function paintingHasNoFooter() {
  const env = makeEnv({ hasFooter: false, grand: 99 });
  assert.strictEqual(footer.bind(env.win), null, 'Painting (no [data-qh-footer]) is a no-op');
})();

(function mirrorsSharedGrandTotal() {
  const env = makeEnv({ hasFooter: true, grand: 88.5, initialAmount: 'CA$0.00' });
  const api = footer.bind(env.win);
  assert.ok(api);
  assert.strictEqual(env.amount.textContent, 'CA$88.50', 'seed from window.__quoteGrandTotal');
  env.docListeners['quote:grand-total'][0]({ detail: { total: 2100 } });
  assert.strictEqual(env.amount.textContent, 'CA$2,100.00', 'mirrors quote:grand-total');
  env.docListeners['quote:grand-total'][0]({ detail: { total: 'nope' } });
  env.docListeners['quote:grand-total'][0]({});
  assert.strictEqual(env.amount.textContent, 'CA$2,100.00', 'ignores invalid or empty event totals');
})();

(function unavailableClearsLastGoodWithoutInventingZero() {
  const env = makeEnv({ hasFooter: true, grand: 4468.34, initialAmount: 'CA$4,468.34' });
  footer.bind(env.win);
  assert.strictEqual(env.amount.textContent, 'CA$4,468.34');
  env.docListeners['quote:grand-total'][0]({ detail: { unavailable: true, pending: true } });
  assert.strictEqual(env.amount.textContent, '…', 'loading must not keep the last settled amount');
  assert.notStrictEqual(env.amount.textContent, 'CA$0.00', 'loading must not invent $0 as a quote');
  env.docListeners['quote:grand-total'][0]({ detail: { unavailable: true } });
  assert.strictEqual(env.amount.textContent, '—', 'missing/error mark the total unavailable');
  assert.notStrictEqual(env.amount.textContent, 'CA$4,468.34');
  assert.notStrictEqual(env.amount.textContent, 'CA$0.00', 'unavailable must not invent $0 as a quote');
  env.docListeners['quote:grand-total'][0]({ detail: { total: 0 } });
  assert.strictEqual(env.amount.textContent, 'CA$0.00', 'an explicit 0 remains a valid quote total');
})();

(function bindHonorsUnavailableFlagSetBeforeBind() {
  const env = makeEnv({ hasFooter: true, initialAmount: 'CA$0.00' });
  env.win.__quoteGrandTotalUnavailable = true;
  env.win.__quoteGrandTotalPending = true;
  footer.bind(env.win);
  assert.strictEqual(env.amount.textContent, '…', 'bind after a pending unavailable event must not keep the HTML $0 seed');
})();

(function saveClickProxiesHeaderSave() {
  const env = makeEnv({ hasFooter: true });
  footer.bind(env.win);
  const e = {
    target: env.saveBtn,
    prevented: false,
    preventDefault() { this.prevented = true; },
  };
  env.footerListeners.click[0](e);
  assert.strictEqual(e.prevented, true, 'footer Save must not native-submit around header handlers');
  assert.strictEqual(env.headerSave.clicked, 1, 'footer Save clicks the header Save quotation button');
})();

(function measuresStickyHeight() {
  const env = makeEnv({ hasFooter: true });
  footer.bind(env.win);
  assert.strictEqual(env.css['--qh-sticky-bar-h'], '56px');
  assert.strictEqual(env.css['--br-sticky-bar-h'], '56px');
})();
