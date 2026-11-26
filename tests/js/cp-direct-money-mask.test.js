'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function createHarness() {
  const docListeners = {};
  const protoSubmitCalls = [];

  function HTMLFormElement() {}
  HTMLFormElement.prototype = {
    submit() {
      protoSubmitCalls.push(this);
    },
  };

  function makeClassList() {
    const set = new Set();
    return {
      add(c) { set.add(c); },
      remove(c) { set.delete(c); },
      contains(c) { return set.has(c); },
    };
  }

  function createInput(opts = {}) {
    const listeners = {};
    const errs = [];
    const host = {
      classList: makeClassList(),
      querySelector(sel) {
        return sel === '.cp-money-error' ? (errs[0] || null) : null;
      },
      appendChild(el) {
        errs.push(el);
      },
      _errors: errs,
    };

    const input = {
      nodeType: 1,
      value: opts.value != null ? String(opts.value) : '',
      classList: makeClassList(),
      form: opts.form || null,
      parentElement: host,
      selectionStart: null,
      selectionEnd: null,
      _customValidity: '',
      _attrs: Object.assign({ 'data-cp-money-entry': 'direct' }, opts.attrs || {}),
      getAttribute(k) { return Object.prototype.hasOwnProperty.call(this._attrs, k) ? this._attrs[k] : null; },
      hasAttribute(k) { return Object.prototype.hasOwnProperty.call(this._attrs, k); },
      setAttribute(k, v) { this._attrs[k] = String(v); },
      closest(sel) {
        return sel === '.admin-form-group' ? host : null;
      },
      setCustomValidity(msg) { this._customValidity = String(msg || ''); },
      addEventListener(ev, fn) {
        listeners[ev] = listeners[ev] || [];
        listeners[ev].push(fn);
      },
      dispatch(ev, eventObj = {}) {
        (listeners[ev] || []).forEach((fn) => fn(Object.assign({ target: input, preventDefault() { this.defaultPrevented = true; } }, eventObj)));
      },
    };
    return { input, host, errs };
  }

  function createForm(inputs = []) {
    const listeners = {};
    const form = new HTMLFormElement();
    form.querySelectorAll = (sel) => (sel === '.cp-money-input' ? inputs : []);
    form.addEventListener = (ev, fn) => {
      listeners[ev] = listeners[ev] || [];
      listeners[ev].push(fn);
    };
    form.dispatchSubmit = () => {
      let prevented = false;
      const ev = {
        target: form,
        preventDefault() { prevented = true; },
      };
      (listeners.submit || []).forEach((fn) => fn(ev));
      return !prevented;
    };
    inputs.forEach((inp) => { inp.form = form; });
    return form;
  }

  const sandbox = {
    window: {},
    HTMLFormElement,
    document: {
      activeElement: null,
      addEventListener(ev, fn) {
        docListeners[ev] = docListeners[ev] || [];
        docListeners[ev].push(fn);
      },
      querySelectorAll() { return []; },
      createElement(tag) {
        return {
          tagName: tag.toUpperCase(),
          className: '',
          textContent: '',
          remove() {},
        };
      },
    },
    isFinite,
    parseFloat,
    parseInt,
    Math,
    Number,
    String,
  };
  sandbox.window = sandbox;

  const src = fs.readFileSync(path.join(__dirname, '../../public/js/admin-client-projects.js'), 'utf8');
  // Execute inside vm context cleanly
  vm.runInNewContext(src, sandbox, { filename: 'admin-client-projects.js' });

  return {
    cpMoneyMask: sandbox.window.cpMoneyMask,
    createInput,
    createForm,
    protoSubmitCalls,
  };
}

const { cpMoneyMask, createInput, createForm } = createHarness();
assert.ok(cpMoneyMask && typeof cpMoneyMask.wire === 'function', 'cpMoneyMask.wire should exist');

// 1. Direct entry parsing & comma decimal support
assert.strictEqual(cpMoneyMask.parse('4.75'), 4.75);
assert.strictEqual(cpMoneyMask.parse('4,75'), 4.75);
assert.strictEqual(cpMoneyMask.parse('1,234.56'), 1234.56);
assert.strictEqual(cpMoneyMask.parse('CA$ 1,234.56'), 0, 'prefix text stripped except $, digits ok when only $');
assert.strictEqual(cpMoneyMask.parse('$1,234.56'), 1234.56);
assert.strictEqual(cpMoneyMask.parse(''), 0);

// 2. Rounding half-up
assert.strictEqual(cpMoneyMask.round(1.005, 2), 1.01);
assert.strictEqual(cpMoneyMask.round(-30.485, 2), -30.49);

// 3. Prefilled server values format cleanly with thousands separators & 2 decimals
const { input: prefilled } = createInput({ value: '1234.5' });
const form1 = createForm([prefilled]);
cpMoneyMask.wire(prefilled);
assert.strictEqual(prefilled.value, '1,234.50');

// 4. Zero & empty prefill handling
const { input: zeroInp } = createInput({ value: '0' });
cpMoneyMask.wire(zeroInp);
assert.strictEqual(zeroInp.value, '0.00');

const { input: emptyInp } = createInput({ value: '' });
cpMoneyMask.wire(emptyInp);
assert.strictEqual(emptyInp.value, '');

// 5. Submit strips thousands separators while keeping plain decimal format
assert.strictEqual(form1.dispatchSubmit(), true);
assert.strictEqual(prefilled.value, '1234.50');

// 6. Negatives blocked unless data-cp-money-allow-negative is present
const { input: negBlocked } = createInput({ value: '-25' });
const formNeg = createForm([negBlocked]);
cpMoneyMask.wire(negBlocked);
assert.strictEqual(negBlocked.classList.contains('is-invalid'), true);
assert.strictEqual(formNeg.dispatchSubmit(), false);

const { input: negAllowed } = createInput({ value: '-25', attrs: { 'data-cp-money-allow-negative': '' } });
const formNegOk = createForm([negAllowed]);
cpMoneyMask.wire(negAllowed);
assert.strictEqual(negAllowed.classList.contains('is-invalid'), false);
assert.strictEqual(negAllowed.value, '-25.00');
assert.strictEqual(formNegOk.dispatchSubmit(), true);
assert.strictEqual(negAllowed.value, '-25.00');

console.log('cp-direct-money-mask.test.js: ok');
