'use strict';

const assert = require('assert');
const copy = require('../../public/js/admin-copy-value.js');

function fakeBtn(value, label) {
  const icon = {
    className: 'fa fa-copy',
    attrs: {},
    getAttribute(name) { return this.attrs[name] || null; },
    setAttribute(name, val) { this.attrs[name] = String(val); },
  };
  const btn = {
    className: 'cl-copy-btn',
    attrs: {
      'data-copy-value': value,
      'aria-label': label,
      title: label,
    },
    classList: {
      tokens: new Set(),
      add(name) { this.tokens.add(name); },
      remove(name) { this.tokens.delete(name); },
      contains(name) { return this.tokens.has(name); },
    },
    querySelector(sel) { return sel === 'i' ? icon : null; },
    getAttribute(name) { return this.attrs[name] == null ? null : this.attrs[name]; },
    setAttribute(name, val) { this.attrs[name] = String(val); },
  };
  return { btn, icon };
}

(async function run() {
  let clipboardValue = null;
  await copy.copyText('plain-value', {
    navigator: { clipboard: { writeText: async (t) => { clipboardValue = t; } } },
    window: { isSecureContext: true },
  });
  assert.strictEqual(clipboardValue, 'plain-value', 'secure context uses clipboard.writeText');

  let fallbackValue = '';
  let execArg = '';
  const ta = {
    value: '',
    style: {},
    parentNode: null,
    setAttribute() {},
    focus() {},
    select() {},
  };
  const body = {
    appendChild(el) { el.parentNode = body; },
    removeChild(el) { el.parentNode = null; },
  };
  await copy.copyText('fallback-value', {
    navigator: {},
    window: { isSecureContext: false },
    document: {
      createElement() { return ta; },
      execCommand(cmd) {
        execArg = cmd;
        fallbackValue = ta.value;
        return true;
      },
      body,
      documentElement: {},
    },
  });
  assert.strictEqual(execArg, 'copy', 'non-secure context uses execCommand fallback');
  assert.strictEqual(fallbackValue, 'fallback-value', 'fallback copies the plain value');

  let usedFallback = false;
  await copy.copyText('after-clipboard-fail', {
    navigator: {
      clipboard: {
        writeText: async () => { throw new Error('denied'); },
      },
    },
    window: { isSecureContext: true },
    document: {
      createElement() { return ta; },
      execCommand() {
        usedFallback = true;
        return true;
      },
      body,
      documentElement: {},
    },
  });
  assert.strictEqual(usedFallback, true, 'clipboard rejection uses fallback');

  const { btn, icon } = fakeBtn('a@b.c', 'Copy email');
  copy.markCopied(btn);
  assert.ok(btn.classList.contains('is-copied'));
  assert.strictEqual(btn.getAttribute('aria-label'), 'Copied');
  assert.strictEqual(icon.className, 'fa fa-check');

  const handled = copy.onClick({
    target: {
      closest(sel) { return sel === '[data-copy-value]' ? btn : null; },
    },
    preventDefault() {},
  }, {
    navigator: { clipboard: { writeText: async () => {} } },
    window: { isSecureContext: true },
  });
  assert.strictEqual(handled, true, 'click on copy button is handled');

  console.log('admin-copy-value.test.js: ok');
})().catch((err) => {
  console.error(err);
  process.exit(1);
});
