'use strict';

const assert = require('assert');
const hotkey = require('../../public/js/admin-hotkey-save.js');
const toast = require('../../public/js/admin-toast.js');

function chord(overrides) {
  const e = Object.assign({
    key: 's',
    code: 'KeyS',
    ctrlKey: true,
    metaKey: false,
    altKey: false,
    shiftKey: false,
    repeat: false,
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() { this.stopped = true; },
  }, overrides);
  return e;
}

assert.strictEqual(hotkey.isSaveChord(chord()), true, 'Ctrl+S is a save chord');
assert.strictEqual(hotkey.isSaveChord(chord({ ctrlKey: false, metaKey: true })), true, 'Cmd+S is a save chord');
assert.strictEqual(hotkey.isSaveChord(chord({ key: 'S' })), true, 'capital S still matches');
assert.strictEqual(hotkey.isSaveChord(chord({ key: 'k', code: 'KeyK' })), false, 'Ctrl+K is not save');
assert.strictEqual(hotkey.isSaveChord(chord({ altKey: true })), false, 'AltGr / Alt+Ctrl+S is ignored');
assert.strictEqual(hotkey.isSaveChord(chord({ shiftKey: true })), false, 'Ctrl+Shift+S is ignored');
assert.strictEqual(hotkey.isSaveChord(chord({ key: 'ы', code: 'KeyS' })), true, 'physical KeyS still matches on non-Latin layouts');
assert.strictEqual(hotkey.isSaveChord(chord({ key: 'o', code: 'KeyS' })), false, 'Dvorak Ctrl+O (KeyS) is not save');
assert.strictEqual(hotkey.isSaveChord(chord({ key: 's', code: 'KeyO' })), true, 'e.key s still saves when code is not KeyS');
assert.strictEqual(hotkey.shouldTriggerSave(chord({ repeat: true })), false, 'held-key repeat is ignored');
assert.strictEqual(hotkey.isSaveChord(chord({ ctrlKey: false, metaKey: false })), false, 'plain S is ignored');

assert.strictEqual(hotkey.shortcutLabel({ navigator: { platform: 'Win32' } }), 'Ctrl+S');
assert.strictEqual(hotkey.shortcutLabel({ navigator: { platform: 'MacIntel' } }), '\u2318S');

function walk(node, fn) {
  fn(node);
  (node.children || []).forEach((child) => walk(child, fn));
}

function matches(el, sel) {
  if (!el || !sel) return false;
  if (sel === ':invalid') return !!el.invalid;
  if (sel === 'form') return el.tagName === 'FORM';
  if (sel === 'fieldset') return el.tagName === 'FIELDSET';
  if (sel === 'legend') return el.tagName === 'LEGEND';
  if (sel === 'label') return el.tagName === 'LABEL';
  if (sel === '[data-hotkey-save]') return el.hasAttribute('data-hotkey-save');
  if (sel === '[data-admin-toast-host]') return el.hasAttribute('data-admin-toast-host');
  if (sel.indexOf('[role="dialog"]') === 0) return el.getAttribute('role') === 'dialog';
  if (sel.indexOf('[role="alertdialog"]') === 0) return el.getAttribute('role') === 'alertdialog';
  if (sel.charAt(0) === '#') return el.id === sel.slice(1) || el.getAttribute('id') === sel.slice(1);
  if (sel.charAt(0) === '.') {
    const cls = sel.slice(1);
    if (el.classList && el.classList.contains(cls)) return true;
    return !!(el.className && String(el.className).split(/\s+/).indexOf(cls) !== -1);
  }
  if (sel.indexOf('label[for="') === 0) {
    const id = sel.slice('label[for="'.length, -2);
    return el.tagName === 'LABEL' && el.getAttribute('for') === id;
  }
  const named = sel.match(/^([a-z0-9-]+)?\[name="([^"]+)"\]$/i);
  if (named && sel.indexOf('[data-') === -1) {
    if (named[1] && el.tagName !== named[1].toUpperCase()) return false;
    return el.getAttribute('name') === named[2];
  }
  const typedName = sel.match(/^input\[type="([^"]+)"\]\[name="([^"]+)"\]$/i);
  if (typedName) {
    return el.tagName === 'INPUT' && el.getAttribute('type') === typedName[1] && el.getAttribute('name') === typedName[2];
  }
  if (sel.indexOf('[data-admin-hotkey-field]') !== -1) {
    if (!el.hasAttribute('data-admin-hotkey-field')) return false;
    const nameMatch = sel.match(/\[name="([^"]+)"\]/);
    if (nameMatch) return el.getAttribute('name') === nameMatch[1];
    return true;
  }
  if (sel.indexOf('[data-admin-flash=') === 0) {
    const kind = sel.match(/\[data-admin-flash="([^"]+)"\]/);
    return kind && el.getAttribute('data-admin-flash') === kind[1];
  }
  if (sel.indexOf('[data-br-step]') === 0) return el.hasAttribute('data-br-step');
  if (sel.indexOf('[data-br-goto=') === 0) {
    const key = sel.match(/\[data-br-goto="([^"]+)"\]/);
    return key && el.getAttribute('data-br-goto') === key[1];
  }
  return false;
}

function makeEl(tag, attrs, children) {
  attrs = attrs || {};
  const el = {
    tagName: String(tag).toUpperCase(),
    attrs: Object.assign({}, attrs),
    children: [],
    parent: null,
    doc: null,
    disabled: !!attrs.disabled,
    hidden: !!attrs.hidden,
    value: attrs.value != null ? String(attrs.value) : '',
    clicked: 0,
    style: {},
    invalid: !!attrs.invalid,
    validationMessage: attrs.validationMessage || '',
    isContentEditable: !!attrs.contenteditable,
    classList: {
      items: {},
      add(name) { this.items[name] = true; },
      remove(name) { delete this.items[name]; },
      contains(name) { return !!this.items[name]; },
    },
    get id() { return this.attrs.id || ''; },
    set id(v) { this.attrs.id = v; },
    get name() { return this.attrs.name || ''; },
    set name(v) { this.attrs.name = v; },
    get type() { return this.attrs.type || ''; },
    set type(v) { this.attrs.type = v; },
    get ownerDocument() { return this.doc; },
    checked: !!attrs.checked,
    validity: { valid: !attrs.invalid },
    get form() {
      let n = this;
      while (n) {
        if (n.tagName === 'FORM') return n;
        n = n.parent;
      }
      return null;
    },
    get parentNode() { return this.parent; },
    get textContent() {
      if (this._text != null) return this._text;
      return (this.children || []).map((c) => c.textContent || '').join('');
    },
    set textContent(v) { this._text = String(v); },
    getAttribute(k) {
      if (k === 'hidden' && this.hidden) return '';
      return this.attrs[k] != null ? String(this.attrs[k]) : null;
    },
    hasAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k); },
    setAttribute(k, v) { this.attrs[k] = v; },
    removeAttribute(k) { delete this.attrs[k]; },
    appendChild(child) {
      child.parent = this;
      child.doc = this.doc;
      this.children.push(child);
      return child;
    },
    removeChild(child) {
      this.children = this.children.filter((c) => c !== child);
      if (child.parent === this) child.parent = null;
      return child;
    },
    cloneNode() {
      const copy = makeEl(String(this.tagName).toLowerCase(), Object.assign({}, this.attrs), []);
      copy.doc = this.doc;
      copy._text = this._text;
      copy.invalid = this.invalid;
      copy.validationMessage = this.validationMessage;
      copy.classList.items = Object.assign({}, this.classList.items);
      if (this.className) copy.className = this.className;
      (this.children || []).forEach((child) => {
        copy.appendChild(child.cloneNode());
      });
      return copy;
    },
    closest(sel) {
      let n = this;
      while (n) {
        if (matches(n, sel)) return n;
        n = n.parent;
      }
      return null;
    },
    querySelectorAll(sel) {
      const out = [];
      walk(this, (node) => {
        if (node !== this && matches(node, sel)) out.push(node);
        if (sel === '[data-hotkey-save]' && node === this && matches(node, sel)) out.push(node);
      });
      if (sel.indexOf(',') !== -1) {
        const parts = sel.split(',').map((s) => s.trim());
        const extra = [];
        walk(this, (node) => {
          if (parts.some((part) => matches(node, part))) extra.push(node);
        });
        return extra;
      }
      return out;
    },
    querySelector(sel) {
      return this.querySelectorAll(sel)[0] || null;
    },
    contains(node) {
      let found = false;
      walk(this, (child) => { if (child === node) found = true; });
      return found;
    },
    click() { this.clicked += 1; },
    focus() { if (this.doc) this.doc.activeElement = this; },
    blur() {
      this.blurred = true;
      if (this.doc && this.doc.activeElement === this) this.doc.activeElement = this.doc.body;
    },
    dispatchEvent(ev) {
      this.events = this.events || [];
      this.events.push(ev && ev.type ? ev.type : 'event');
      if (ev && ev.type === 'change') this.changed = true;
    },
    checkValidity() {
      let bad = this.invalid;
      walk(this, (node) => { if (node.invalid) bad = true; });
      return !bad;
    },
    getBoundingClientRect() {
      return this.rect || { left: 0, top: 0, right: 40, bottom: 40, width: 40, height: 40 };
    },
  };
  if (attrs.class || attrs.className) {
    const cls = String(attrs.class || attrs.className);
    el.className = cls;
    cls.split(/\s+/).forEach((c) => { if (c) el.classList.add(c); });
  }
  (children || []).forEach((child) => el.appendChild(child));
  return el;
}

function makeDoc(rootChildren) {
  const body = makeEl('body');
  const doc = {
    body,
    documentElement: makeEl('html'),
    activeElement: body,
    listeners: {},
    getElementById(id) {
      let found = null;
      walk(body, (node) => { if (node.getAttribute('id') === id) found = node; });
      return found;
    },
    querySelectorAll(sel) {
      if (sel.indexOf(',') !== -1) {
        const parts = sel.split(',').map((s) => s.trim());
        const out = [];
        walk(body, (node) => {
          if (parts.some((part) => matches(node, part))) out.push(node);
        });
        return out;
      }
      const out = [];
      walk(body, (node) => { if (matches(node, sel)) out.push(node); });
      return out;
    },
    querySelector(sel) { return this.querySelectorAll(sel)[0] || null; },
    addEventListener(type, fn) {
      this.listeners[type] = this.listeners[type] || [];
      this.listeners[type].push(fn);
    },
    createElement(tag) {
      const el = makeEl(tag);
      el.doc = doc;
      return el;
    },
  };
  body.doc = doc;
  (rootChildren || []).forEach((child) => {
    child.doc = doc;
    walk(child, (node) => { node.doc = doc; });
    body.appendChild(child);
  });
  return doc;
}

function page(type) {
  if (type === 'standard') {
    return makeDoc([
      makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'sq-save' }),
      makeEl('button', { type: 'submit', id: 'sq-email' }),
    ]);
  }
  if (type === 'draft-vs-send') {
    return makeDoc([
      makeEl('button', { type: 'button', id: 'br-btn-draft', 'data-hotkey-save': '' }),
      makeEl('button', { type: 'submit', id: 'br-btn-publish' }),
      makeEl('button', { type: 'submit', id: 'send-email' }),
    ]);
  }
  if (type === 'invoice') {
    return makeDoc([
      makeEl('button', { type: 'submit', 'data-hotkey-save': '', 'data-cp-invoice-intent': 'draft', id: 'inv-draft' }),
      makeEl('button', { type: 'submit', 'data-cp-invoice-intent': 'close', id: 'inv-send' }),
    ]);
  }
  if (type === 'blog') {
    return makeDoc([
      makeEl('button', { type: 'submit', id: 'bp-btn-save', 'data-intent': 'save', 'data-hotkey-save': '' }),
      makeEl('button', { type: 'submit', id: 'bp-btn-publish', 'data-intent': 'publish' }),
    ]);
  }
  if (type === 'none') {
    return makeDoc([makeEl('p', { id: 'empty' })]);
  }
  if (type === 'disabled') {
    return makeDoc([makeEl('button', { type: 'button', 'data-hotkey-save': '', disabled: true, id: 'save-disabled' })]);
  }
  if (type === 'modal') {
    const pageBtn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'page-save' });
    const modalSave = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'modal-save' });
    const modal = makeEl('div', { role: 'dialog', id: 'dlg' }, [modalSave]);
    return makeDoc([pageBtn, modal]);
  }
  if (type === 'typing') {
    const input = makeEl('input', { id: 'title', name: 'title', value: 'Kitchen' });
    const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'save' });
    const form = makeEl('form', { id: 'f' }, [input, btn]);
    return makeDoc([form]);
  }
  throw new Error('unknown page ' + type);
}

function apiFor(doc, clock) {
  let now = clock || 1000;
  const store = {};
  return hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      sessionStorage: {
        getItem(k) { return store[k] || null; },
        setItem(k, v) { store[k] = String(v); },
        removeItem(k) { delete store[k]; },
      },
      fetch: undefined,
    },
    now() { return now; },
    toast: {
      saved() { doc.toast = 'saved'; },
      error(msg) { doc.toast = 'error:' + msg; },
      validation(fields) { doc.toast = 'validation:' + fields.length; },
    },
    storage: {
      getItem(k) { return store[k] || null; },
      setItem(k, v) { store[k] = String(v); },
      removeItem(k) { delete store[k]; },
    },
  });
}

const mapping = {
  standard: 'sq-save',
  'draft-vs-send': 'br-btn-draft',
  invoice: 'inv-draft',
  blog: 'bp-btn-save',
};
Object.keys(mapping).forEach((type) => {
  const doc = page(type);
  const api = apiFor(doc);
  const btn = api.findTargetButton();
  assert.ok(btn, type + ' should have a marked save button');
  assert.strictEqual(btn.getAttribute('id'), mapping[type], type + ' maps to ' + mapping[type]);
  if (type === 'draft-vs-send' || type === 'invoice' || type === 'blog') {
    assert.ok(!doc.getElementById(type === 'blog' ? 'bp-btn-publish' : type === 'invoice' ? 'inv-send' : 'br-btn-publish').hasAttribute('data-hotkey-save'),
      type + ' send/publish button is not opted in');
  }
});

(function debounceRepeat() {
  const doc = page('standard');
  const api = apiFor(doc, 5000);
  const btn = doc.getElementById('sq-save');
  let now = 5000;
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' } },
    now() { return now; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  inst.handleKeydown(chord());
  now = 5100;
  inst.handleKeydown(chord({ key: 's', ctrlKey: true }));
  assert.strictEqual(btn.clicked, 1, 'key repeat / double press clicks once inside debounce');
  now = 5800;
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'in-flight lock blocks another save after debounce');
  inst.reset();
  now = 7000;
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 2, 'after the save finishes Ctrl+S works again');
  inst.reset();
})();

(function ajaxInflightBlocks() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'ajax-save' });
  const doc = makeDoc([btn]);
  let now = 1000;
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' }, fetch: undefined },
    now() { return now; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  now = 2000;
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'AJAX save in flight ignores another Ctrl+S');
  inst.reset();
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 2, 'after the AJAX save finishes Ctrl+S works again');
  inst.reset();
})();

(function typingCommits() {
  const doc = page('typing');
  const input = doc.getElementById('title');
  const btn = doc.getElementById('save');
  doc.activeElement = input;
  input.value = 'Just typed';
  const api = apiFor(doc);
  api.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'save fires while focus is in an input');
  assert.ok(input.changed || (input.events && input.events.indexOf('change') !== -1), 'change is dispatched before save');
  assert.strictEqual(doc.activeElement, input, 'focus stays on the field after Ctrl+S');
  api.reset();
})();

(function noButtonStillPrevents() {
  const doc = page('none');
  const api = apiFor(doc);
  const e = chord();
  const handled = api.handleKeydown(e);
  assert.strictEqual(handled, true, 'no-button pages still handle the chord');
  assert.strictEqual(e.prevented, true, 'browser Save page dialog is blocked');
  assert.strictEqual(api.findTargetButton(), null, 'no marked button means no click');
})();

(function modalWins() {
  const doc = page('modal');
  const api = apiFor(doc);
  const target = api.findTargetButton();
  assert.strictEqual(target.getAttribute('id'), 'modal-save', 'open dialog save is preferred');
  api.handleKeydown(chord());
  assert.strictEqual(doc.getElementById('modal-save').clicked, 1);
  assert.strictEqual(doc.getElementById('page-save').clicked, 0);
  api.reset();
})();

(function disabledDoesNothing() {
  const doc = page('disabled');
  const api = apiFor(doc);
  const e = chord();
  api.handleKeydown(e);
  assert.strictEqual(e.prevented, true, 'still preventDefault when disabled');
  assert.strictEqual(doc.getElementById('save-disabled').clicked, 0, 'disabled button is not clicked');
})();

(function busyIgnored() {
  const doc = page('standard');
  const btn = doc.getElementById('sq-save');
  btn.setAttribute('aria-busy', 'true');
  const api = apiFor(doc);
  api.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 0, 'busy button is ignored');
})();

assert.strictEqual(toast.SUCCESS_MS, 2500, 'success toast fades after 2.5s');
assert.ok(toast.rectsOverlap(
  { left: 0, top: 0, right: 50, bottom: 50 },
  { left: 40, top: 40, right: 80, bottom: 80 }
), 'overlap helper detects collisions');
assert.ok(!toast.rectsOverlap(
  { left: 0, top: 0, right: 20, bottom: 20 },
  { left: 80, top: 80, right: 120, bottom: 120 }
), 'far rects do not overlap');
assert.ok(toast.AVOID_SEL.indexOf('.qh-sticky-footer') !== -1, 'toast avoids shared quotation footer');
assert.ok(toast.AVOID_SEL.indexOf('.br-sticky-total') !== -1, 'toast avoids sticky totals');
assert.ok(toast.AVOID_SEL.indexOf('.br-step-nav') !== -1, 'toast avoids step nav');
assert.ok(toast.AVOID_SEL.indexOf('#br-next') !== -1, 'toast avoids Next');

(function heldKeyRepeat() {
  const doc = page('standard');
  const btn = doc.getElementById('sq-save');
  const inst = apiFor(doc);
  inst.handleKeydown(chord({ repeat: true }));
  assert.strictEqual(btn.clicked, 0, 'keydown.repeat does not click Save');
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'first non-repeat Ctrl+S clicks once');
  inst.handleKeydown(chord({ repeat: true }));
  assert.strictEqual(btn.clicked, 1, 'held-key repeats stay locked after the first save');
  inst.reset();
})();

(function neverEmail() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  assert.strictEqual(checkbox.checked, false, 'Ctrl+S unchecks send_client_email');
  const flag = form.children.find((c) => c.name === '_hotkey_save' || (c.getAttribute && c.getAttribute('name') === '_hotkey_save'));
  assert.ok(flag, 'Ctrl+S posts _hotkey_save');
  assert.strictEqual(flag.value, '1');
  const prior = form.children.find((c) => c.name === '_hotkey_email_prior' || (c.getAttribute && c.getAttribute('name') === '_hotkey_email_prior'));
  assert.ok(prior, 'Ctrl+S posts the prior email-box choice');
  assert.strictEqual(prior.value, '1');
  inst.reset();
  assert.strictEqual(checkbox.checked, true, 'finish restores the email box');
  assert.ok(!form.children.some((c) => c.name === '_hotkey_save' || (c.getAttribute && c.getAttribute('name') === '_hotkey_save')),
    'finish removes leftover _hotkey_save');
})();

(function neverEmailUntickedPriorIsZero() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: false, value: '1' });
  checkbox.checked = false;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  assert.strictEqual(checkbox.checked, false, 'Ctrl+S leaves an unticked email box unticked');
  const prior = form.children.find((c) => c.name === '_hotkey_email_prior' || (c.getAttribute && c.getAttribute('name') === '_hotkey_email_prior'));
  assert.ok(prior, 'Ctrl+S still posts the prior email-box choice when unticked');
  assert.strictEqual(prior.value, '0', 'unticked email box posts _hotkey_email_prior=0');
  inst.reset();
  assert.strictEqual(checkbox.checked, false, 'finish keeps an unticked email box unticked');
})();

(function focusedFormChoice() {
  const homeInput = makeEl('input', { id: 'home-address', name: 'home_address' });
  const homeBtn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save-home' });
  const homeForm = makeEl('form', { id: 'home-form' }, [homeInput, homeBtn]);

  const phone = makeEl('input', { id: 'company-contact-phone', name: 'contact_phone' });
  const contactBtn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save-contact' });
  const contactForm = makeEl('form', { id: 'contact-form' }, [phone, contactBtn]);

  const gas = makeEl('input', { id: 'company-gas-price', name: 'gas_price_per_liter' });
  const gasBtn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save-gas' });
  const gasForm = makeEl('form', { id: 'gas-form' }, [gas, gasBtn]);

  const formula = makeEl('input', { id: 'fq-labour-6', name: 'labour_rate_per_ft' });
  const formulaBtn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save-formula' });
  const formulaForm = makeEl('form', { id: 'formula-form' }, [formula, formulaBtn]);
  const linksBtn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save-links' });
  const linksForm = makeEl('form', { id: 'links-form' }, [linksBtn]);

  const doc = makeDoc([homeForm, contactForm, gasForm, linksForm, formulaForm]);
  const inst = apiFor(doc);

  doc.activeElement = doc.body;
  assert.strictEqual(inst.findTargetButton().id, 'save-home', 'no focus falls back to the first main save');

  doc.activeElement = phone;
  assert.strictEqual(inst.findTargetButton().id, 'save-contact', 'vehicles business contact form wins when focused');

  doc.activeElement = gas;
  assert.strictEqual(inst.findTargetButton().id, 'save-gas', 'vehicles gas price form wins when focused');

  doc.activeElement = formula;
  assert.strictEqual(inst.findTargetButton().id, 'save-formula', 'fence/low-deck formula form wins when focused');

  doc.activeElement = doc.body;
  inst.handleKeydown(chord());
  assert.strictEqual(homeBtn.clicked, 1, 'unfocused Ctrl+S clicks the main form save');
  assert.strictEqual(contactBtn.clicked, 0);
  inst.reset();
})();

(function confirmDialogNoOp() {
  const pageBtn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'page-save' });
  const dialog = makeEl('div', { id: 'admin-dialog', role: 'alertdialog' });
  const doc = makeDoc([pageBtn, dialog]);
  doc.documentElement.classList.add('admin-dialog-open');
  const inst = apiFor(doc);
  const e = chord();
  inst.handleKeydown(e);
  assert.strictEqual(e.prevented, true, 'confirm-open still blocks the browser Save dialog');
  assert.strictEqual(pageBtn.clicked, 0, 'Ctrl+S does nothing while a confirm dialog is open');
  inst.reset();
})();

(function ajaxResultRules() {
  const inst = apiFor(page('standard'));
  const json = { headers: { get() { return 'application/json'; } }, ok: true, status: 200 };
  assert.strictEqual(inst.parseAjaxResult(json, { ok: true }).kind, 'saved');
  assert.strictEqual(inst.parseAjaxResult(json, { ok: false, message: 'nope' }).kind, 'error');
  const created = { headers: { get() { return 'application/json'; } }, ok: true, status: 201 };
  assert.strictEqual(inst.parseAjaxResult(created, { event: { id: 1 } }).kind, 'saved', 'calendar {event} is ok');
  const html = { headers: { get() { return 'text/html'; } }, ok: true, status: 200 };
  const htmlResult = inst.parseAjaxResult(html, '<html><body>Projects</body></html>');
  assert.strictEqual(htmlResult.kind, 'error', 'HTML 200 is not Saved');
  assert.notStrictEqual(htmlResult.title, 'Sign in required', 'HTML 200 is not session expired');
  const login = inst.parseAjaxResult(html, '<html>Sign in</html>');
  assert.strictEqual(login.title, 'Sign in required');
  const expired = inst.parseAjaxResult(
    { headers: { get() { return 'text/html'; } }, ok: false, status: 419 },
    '<html>Page Expired. Please sign in.</html>'
  );
  assert.strictEqual(expired.title, 'Sign in required', '419 HTML is a login/session message');
  assert.ok(/session expired/i.test(expired.message));
  const stale = inst.parseAjaxResult(
    { headers: { get() { return 'text/html'; } }, ok: false, status: 419 },
    '<html><h1>Token mismatch</h1><p>Please retry the request.</p></html>'
  );
  assert.strictEqual(stale.title, 'Sign in required', '419 HTML without expired text still uses the status branch');
  const serverErr = inst.parseAjaxResult(
    { headers: { get() { return 'text/html'; } }, ok: false, status: 500 },
    '<html><h1>Server Error</h1></html>'
  );
  assert.notStrictEqual(serverErr.title, 'Sign in required', 'HTML 500 is not session expired');
  const unavailable = inst.parseAjaxResult(
    { headers: { get() { return 'text/html'; } }, ok: false, status: 503 },
    '<html>Service Unavailable</html>'
  );
  assert.notStrictEqual(unavailable.title, 'Sign in required', 'HTML 503 is not session expired');
  const fiveLogin = inst.parseAjaxResult(
    { headers: { get() { return 'text/html'; } }, ok: false, status: 500 },
    '<html><nav><a href="/login">Login</a></nav><p>Sign in again later</p></html>'
  );
  assert.strictEqual(fiveLogin.title, 'Could not save', '5xx HTML never shows Sign in required');
  const empty = inst.parseAjaxResult(
    { headers: { get() { return ''; } }, ok: true, status: 204 },
    ''
  );
  assert.strictEqual(empty.kind, 'saved', '204 is a confirmed save');
  const download = inst.parseAjaxResult(
    {
      headers: {
        get(name) {
          const key = String(name || '').toLowerCase();
          if (key === 'content-disposition') return 'attachment; filename="quote.pdf"';
          if (key === 'content-type') return 'application/pdf';
          return '';
        },
      },
      ok: true,
      status: 200,
    },
    null
  );
  assert.strictEqual(download.kind, 'saved', 'attachment download is a confirmed save');
  assert.ok(inst.isUnlockingResponse({ status: 204, headers: { get() { return ''; } } }));
  assert.ok(inst.isUnlockingResponse({
    status: 200,
    headers: { get(name) { return String(name).toLowerCase() === 'content-disposition' ? 'attachment; filename=x.csv' : ''; } },
  }));
  const csrf = inst.parseAjaxResult(
    { headers: { get() { return 'application/json'; } }, ok: false, status: 419 },
    { message: 'CSRF token mismatch' }
  );
  assert.strictEqual(csrf.title, 'Sign in required', 'JSON 419 uses the status branch');
  const unauthorized = inst.parseAjaxResult(
    { headers: { get() { return 'application/json'; } }, ok: false, status: 401 },
    { message: 'Unauthenticated.' }
  );
  assert.strictEqual(unauthorized.title, 'Sign in required', 'JSON 401 uses the status branch');
  inst.reset();
})();

(function dottedErrorKeys() {
  assert.strictEqual(hotkey.laravelToName('pieces.0.width'), 'pieces[0][width]');
})();

(function failedHotkeyDoesNotPersistEmailSkip() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const name = makeEl('input', { name: 'client_name', invalid: true, required: true });
  name.invalid = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, name, btn]);
  const doc = makeDoc([form]);
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 0, 'invalid Ctrl+S does not click Save');
  assert.strictEqual(checkbox.checked, true, 'failed Ctrl+S leaves the email box ticked');
  assert.ok(!form.children.some((c) => c.name === '_hotkey_save'), 'failed Ctrl+S does not append _hotkey_save');
  inst.reset();
})();

(function pageshowRestoresEmailAndClearsExpect() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const store = {};
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' } },
    now() { return 4000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: {
      getItem(k) { return store[k] || null; },
      setItem(k, v) { store[k] = String(v); },
      removeItem(k) { delete store[k]; },
    },
  });
  inst.handleKeydown(chord());
  assert.strictEqual(checkbox.checked, false);
  inst.setExpect();
  inst.onPageShow({ persisted: true });
  assert.strictEqual(checkbox.checked, true, 'bfcache restores the email box');
  assert.ok(!form.children.some((c) => c.name === '_hotkey_save'), 'bfcache removes _hotkey_save');
  assert.strictEqual(inst.consumeExpect(), false, 'bfcache clears the expect flag');
  assert.strictEqual(inst.isInflight(), false);
  inst.reset();
})();

(function cancelledSubmitReleasesLock() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const store = { 'admin-save-expect': '1' };
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 8000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: {
      getItem(k) { return store[k] || null; },
      setItem(k, v) { store[k] = String(v); },
      removeItem(k) { delete store[k]; },
    },
  });
  inst.onMarkedClick({ target: btn, preventDefault() {}, stopImmediatePropagation() {} });
  assert.ok(inst.isInflight(), 'mouse save starts a session');
  inst.onFormSubmit({ target: form, defaultPrevented: true });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  timers.filter((t) => t.ms === 50).forEach((t) => t.fn());
  assert.strictEqual(inst.isInflight(), false, 'cancelled submit releases the lock');
  assert.strictEqual(store['admin-save-expect'], undefined, 'cancelled submit clears the expect flag');
  const later = {
    target: btn,
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopImmediatePropagation() { this.stopped = true; },
  };
  inst.onMarkedClick(later);
  assert.strictEqual(later.prevented, false, 'later mouse click is not swallowed');
  assert.strictEqual(later.stopped, false);
  inst.reset();
})();

(function cancelledRedraftWindowConfirmReleasesImmediately() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'br-btn-draft' });
  const form = makeEl('form', { id: 'br-form' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  let confirmCalls = 0;
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
    clearTimeout() {},
    confirm() {
      confirmCalls += 1;
      return false;
    },
  };
  btn.click = function () {
    this.clicked += 1;
    // #82: capture-phase draft click uses window.confirm; cancel must not
    // leave the 12s watch holding Ctrl+S.
    if (!win.confirm('This quotation is already with the client. Saving it as a draft will stop their link from working until you publish it again. Continue?')) {
      this.cancelled = true;
    }
  };
  let errorToast = 0;
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 21000; },
    toast: { saved() {}, error() { errorToast += 1; }, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'Ctrl+S still clicks the marked draft button');
  assert.strictEqual(confirmCalls, 1, 're-draft confirm ran during the click');
  assert.strictEqual(inst.isInflight(), false, 'cancelled window.confirm releases the lock immediately');
  assert.strictEqual(errorToast, 0, 'cancel does not show a save error toast');
  timers.filter((t) => t.ms === 12000 || t.ms === inst.WATCH_MS).forEach((t) => t.fn());
  assert.strictEqual(inst.isInflight(), false, '12s watch is not what released the lock');
  inst.reset();
})();

(function acceptedRedraftConfirmKeepsLock() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'br-btn-draft' });
  const form = makeEl('form', { id: 'br-form' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
    clearTimeout() {},
    confirm() { return true; },
  };
  btn.click = function () {
    this.clicked += 1;
    if (!win.confirm('Continue?')) return;
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 22000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight(), 'accepted confirm keeps the save lock');
  timers.filter((t) => t.ms === 12000 || t.ms === inst.WATCH_MS).forEach((t) => t.fn());
  assert.strictEqual(inst.isInflight(), false, 'accepted confirm still ends via the 12s watch when no submit follows');
  inst.reset();
})();

(function cancelledAdminDialogConfirmReleasesImmediately() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'mw-btn-draft' });
  const form = makeEl('form', { id: 'mw-form' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const confirmCbs = [];
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
    clearTimeout() {},
    AdminDialog: {
      confirm() {
        return {
          then(cb) {
            confirmCbs.push(cb);
            return this;
          },
        };
      },
    },
  };
  btn.click = function () {
    this.clicked += 1;
    win.AdminDialog.confirm('Withdraw the sent quote?').then(function (ok) {
      btn.ok = ok;
    });
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 23000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight(), 'async AdminDialog confirm holds the lock while open');
  assert.ok(confirmCbs.length >= 1, 'AdminDialog.confirm was wrapped');
  confirmCbs.forEach((cb) => cb(false));
  assert.strictEqual(inst.isInflight(), false, 'AdminDialog cancel releases immediately');
  timers.filter((t) => t.ms === 12000 || t.ms === inst.WATCH_MS).forEach((t) => t.fn());
  assert.strictEqual(inst.isInflight(), false, '12s watch is not required after AdminDialog cancel');
  inst.reset();
})();

(function dialogCancelReleasesEvenIfConfirmWasCaptured() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'br-btn-draft' });
  const form = makeEl('form', { id: 'br-form' }, [btn]);
  const doc = makeDoc([form]);
  const capturedCbs = [];
  const capturedConfirm = function () {
    return {
      then(cb) {
        capturedCbs.push(cb);
        return this;
      },
    };
  };
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout() { return 1; },
    clearTimeout() {},
    AdminDialog: { confirm: capturedConfirm },
  };
  btn.click = function () {
    this.clicked += 1;
    // #82 captured AdminDialog.confirm before #69 wrapped it.
    capturedConfirm('Pause the client link?').then(function () {});
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 27000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight(), 'captured confirm still leaves the lock held');
  const cancelListeners = doc.listeners[hotkey.DIALOG_CANCEL_EVENT] || doc.listeners['admin-dialog:cancel'] || [];
  assert.ok(cancelListeners.length >= 1, 'session listens for admin-dialog:cancel');
  cancelListeners.forEach((fn) => fn());
  assert.strictEqual(inst.isInflight(), false, 'dialog-cancel releases without the confirm wrap');
  inst.reset();
})();

(function abortEventReleasesWaitingLock() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'sh-btn-draft' });
  const form = makeEl('form', { id: 'sh-form' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 24000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight(), 'Ctrl+S starts a waiting session');
  const listeners = doc.listeners[hotkey.ABORT_EVENT] || doc.listeners[inst.ABORT_EVENT] || [];
  assert.ok(listeners.length >= 1, 'session listens for ' + hotkey.ABORT_EVENT);
  listeners.forEach((fn) => fn());
  assert.strictEqual(inst.isInflight(), false, 'abort event releases immediately');
  inst.reset();
})();

(function abortWaitingDoesNotReleaseFormMode() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 25000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'native submit is form-mode');
  assert.strictEqual(inst.abortWaiting(), false, 'form-mode abort is a no-op');
  assert.ok(inst.isInflight(), 'form-mode lock stays until pageshow / 204');
  inst.reset();
})();

(function cancelledConfirmAllowsNextCtrlS() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'br-btn-draft' });
  let clicks = 0;
  let now = 26000;
  const form = makeEl('form', { id: 'br-form' }, [btn]);
  const doc = makeDoc([form]);
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout() { return 1; },
    clearTimeout() {},
    confirm() { return false; },
  };
  btn.click = function () {
    clicks += 1;
    win.confirm('Continue?');
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return now; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.strictEqual(clicks, 1);
  assert.strictEqual(inst.isInflight(), false);
  now += 800;
  inst.handleKeydown(chord());
  assert.strictEqual(clicks, 2, 'after confirm cancel, Ctrl+S clicks Save again');
  inst.reset();
})();

(function redraftBypassClickIsNotSwallowedWhileInflight() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'st-btn-draft' });
  const form = makeEl('form', { id: 'quote-form' }, []);
  const doc = makeDoc([form, btn]);
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight(), 'Ctrl+S starts a session before the re-draft confirm');
  btn.setAttribute('data-qh-draft-bypass', '1');
  const ev = {
    target: btn,
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopImmediatePropagation() { this.stopped = true; },
  };
  inst.onMarkedClick(ev);
  assert.strictEqual(ev.prevented, false, 'accepted re-draft bypass must reach the editor click handler');
  assert.strictEqual(ev.stopped, false, 'accepted re-draft bypass must not stopImmediatePropagation');
  inst.reset();
})();

(function bindRegistersAlwaysOnAbortListeners() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'save' });
  const doc = makeDoc([btn]);
  const inst = apiFor(doc);
  inst.bind();
  const abort = doc.listeners[hotkey.ABORT_EVENT] || [];
  const cancel = doc.listeners[hotkey.DIALOG_CANCEL_EVENT] || [];
  assert.ok(abort.length >= 1, 'bind() always listens for admin-hotkey-save:abort');
  assert.ok(cancel.length >= 1, 'bind() always listens for admin-dialog:cancel');
  inst.handleKeydown(chord());
  assert.ok(inst.isInflight());
  abort.forEach((fn) => fn());
  assert.strictEqual(inst.isInflight(), false, 'always-on abort listener releases without the session wrap');
  inst.reset();
})();

(function abortEventsAreDispatchedAndListenedInSource() {
  const fs = require('fs');
  const path = require('path');
  const dialog = fs.readFileSync(path.resolve(__dirname, '../../public/js/admin-dialog.js'), 'utf8');
  const leave = fs.readFileSync(path.resolve(__dirname, '../../public/js/admin-quotation-leave-save.js'), 'utf8');
  const src = fs.readFileSync(path.resolve(__dirname, '../../public/js/admin-hotkey-save.js'), 'utf8');
  assert.ok(
    /function close\(result\) \{[\s\S]*if\s*\(\s*!result[\s\S]*admin-dialog:cancel/.test(dialog),
    'AdminDialog.close dispatches admin-dialog:cancel only when result is falsy'
  );
  assert.ok(
    /if\s*\(\s*!ok\s*\)[\s\S]*admin-hotkey-save:abort/.test(leave),
    '#82 re-draft cancel dispatches admin-hotkey-save:abort only when the dialog is declined'
  );
  const bindBody = src.match(/function bind\(\) \{[\s\S]*?\n    \}/);
  assert.ok(bindBody, 'bind() is present');
  assert.ok(
    /addEventListener\(\s*ABORT_EVENT/.test(bindBody[0]),
    'bind() always-on listener for admin-hotkey-save:abort'
  );
  assert.ok(
    /addEventListener\(\s*DIALOG_CANCEL_EVENT/.test(bindBody[0]),
    'bind() always-on listener for admin-dialog:cancel'
  );
})();

(function clickSwallowWhileInflight() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'save' });
  const form = makeEl('form', { id: 'f' }, [btn]);
  const doc = makeDoc([form]);
  const inst = apiFor(doc);
  inst.onMarkedClick({ target: btn, preventDefault() {}, stopImmediatePropagation() {} });
  assert.ok(inst.isInflight());
  const click = {
    target: btn,
    prevented: false,
    stopped: false,
    preventDefault() { this.prevented = true; },
    stopImmediatePropagation() { this.stopped = true; },
  };
  inst.onMarkedClick(click);
  assert.strictEqual(click.prevented, true, 'in-flight click is preventDefaulted');
  assert.strictEqual(click.stopped, true, 'in-flight click is stopImmediatePropagationed');
  inst.reset();
})();

(function debounceAfterReleaseInflight() {
  const doc = page('standard');
  const btn = doc.getElementById('sq-save');
  let now = 9000;
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' } },
    now() { return now; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1);
  inst.releaseInflight();
  now = 9200;
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 1, 'debounce still applies after releaseInflight without reset');
  now = 9800;
  inst.handleKeydown(chord());
  assert.strictEqual(btn.clicked, 2, 'Ctrl+S works again after debounce');
  inst.reset();
})();

(function consumeFlashNeedsExpectAndKeepsActionableText() {
  const banner = makeEl('div', { 'data-admin-flash': 'success', id: 'flash' });
  banner.textContent = 'Schedule confirmation emailed to the client.';
  banner.hidden = false;
  const doc = makeDoc([banner]);
  const toasts = [];
  const store = {};
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' } },
    now() { return 1000; },
    toast: {
      saved(_env, message) { toasts.push(message || 'Saved'); },
      error() {},
      validation() {},
    },
    storage: {
      getItem(k) { return store[k] || null; },
      setItem(k, v) { store[k] = String(v); },
      removeItem(k) { delete store[k]; },
    },
  });
  inst.consumeFlash({ success: 'Schedule confirmation emailed to the client.' });
  assert.deepStrictEqual(toasts, [], 'reload without expect does not show Saved');
  assert.strictEqual(banner.hidden, false, 'banner stays visible without expect');
  inst.setExpect();
  inst.consumeFlash({ success: 'Schedule confirmation emailed to the client.' });
  assert.deepStrictEqual(toasts, ['Schedule confirmation emailed to the client.']);
  assert.strictEqual(banner.hidden, false, 'actionable flash is not hidden');
  inst.setExpect();
  inst.consumeFlash({ success: 'Saved' });
  assert.strictEqual(banner.hidden, true, 'plain Saved banner is hidden');
  inst.reset();
})();

(function paletteIsANoOp() {
  const pal = makeEl('div', { id: 'admin-search-palette' });
  const input = makeEl('input', { id: 'asp-input' });
  pal.appendChild(input);
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'page-save' });
  const form = makeEl('form', { id: 'f' }, [btn]);
  const doc = makeDoc([pal, form]);
  doc.activeElement = input;
  const inst = apiFor(doc);
  const e = chord();
  inst.handleKeydown(e);
  assert.strictEqual(e.prevented, true, 'palette still blocks the browser Save dialog');
  assert.strictEqual(btn.clicked, 0, 'Ctrl+S does nothing in the search palette');
  inst.reset();
})();

(function bindTinyRegistersAddEditor() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'tiny-save' });
  const doc = makeDoc([btn]);
  const editors = [];
  let addEditor = null;
  const tm = {
    editors,
    on(name, fn) { if (name === 'AddEditor') addEditor = fn; },
  };
  const inst = hotkey.create({
    document: doc,
    window: { navigator: { platform: 'Linux' }, tinymce: tm, setTimeout, clearTimeout },
    now() { return 1000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.bindTiny();
  assert.ok(typeof addEditor === 'function', 'bindTiny hooks tinymce AddEditor');
  let keydown = null;
  addEditor({ editor: { on(name, fn) { if (name === 'keydown') keydown = fn; } } });
  assert.ok(typeof keydown === 'function', 'new editors receive the hotkey keydown hook');
  inst.reset();
})();

(function wizardRevealAndEscAndClearOnFix() {
  const field = makeEl('input', { id: 'pieces-0-width', name: 'pieces[0][width]' });
  const step = makeEl('div', { 'data-br-step': 'framing', hidden: true }, [field]);
  step.hidden = true;
  const nav = makeEl('button', { 'data-br-goto': 'framing', id: 'goto-framing' });
  const host = makeEl('div', { 'data-admin-toast-host': '' });
  host.hidden = true;
  const doc = makeDoc([step, nav, host]);
  field.closest = function (sel) {
    if (sel.indexOf('[data-br-step]') !== -1) return step;
    return null;
  };
  toast.revealWizardFor(field);
  assert.strictEqual(step.hidden, false, 'wizard step is opened');
  assert.ok(step.classList.contains('is-active'), 'wizard step is marked active');
  assert.strictEqual(nav.clicked, 1, 'step nav is clicked');

  const env = { document: doc, window: { innerWidth: 1024, innerHeight: 800, addEventListener() {} } };
  toast.validation([{ el: field, label: 'Width', message: 'is required' }], {}, env);
  assert.strictEqual(host.hidden, false, 'validation toast is shown');
  assert.strictEqual(doc.activeElement, field, 'first invalid field is focused');
  field.validity = { valid: true };
  field.invalid = false;
  if (doc.listeners.input) {
    doc.listeners.input.forEach((fn) => fn({ target: field }));
  }
  assert.strictEqual(host.hidden, true, 'toast closes once the field is fixed');

  toast.error('boom', env);
  assert.strictEqual(host.hidden, false);
  if (doc.listeners.keydown) {
    doc.listeners.keydown.forEach((fn) => fn({ key: 'Escape', code: 'Escape' }));
  }
  assert.strictEqual(host.hidden, true, 'Esc closes the toast');
})();

(function toastAvoidsStickyAndSave() {
  const sticky = makeEl('div', { class: 'br-sticky-total', id: 'br-sticky-total' });
  sticky.className = 'br-sticky-total';
  sticky.rect = { left: 0, top: 744, right: 1024, bottom: 800, width: 1024, height: 56 };
  const next = makeEl('button', { id: 'br-next' });
  next.rect = { left: 900, top: 750, right: 1010, bottom: 794, width: 110, height: 44 };
  const save = makeEl('button', { 'data-hotkey-save': '', id: 'toolbar-save' });
  save.rect = { left: 880, top: 8, right: 1010, bottom: 52, width: 130, height: 44 };
  const field = makeEl('input', { id: 'area' });
  field.rect = { left: 0, right: 1024, top: 0, bottom: 130, width: 1024, height: 130 };
  const doc = makeDoc([sticky, next, save, field]);
  doc.activeElement = field;
  const host = makeEl('div', { 'data-admin-toast-host': '' });
  host.hidden = true;
  host.getBoundingClientRect = function () {
    const place = this.getAttribute('data-placement') || 'bottom-center';
    const pinned = parseFloat((this.style && this.style.bottom) || '');
    if (pinned > 40) {
      const bottom = 800 - pinned;
      return { left: 362, right: 662, top: bottom - 80, bottom, width: 300, height: 80 };
    }
    if (place === 'top-right') {
      return { left: 700, right: 1000, top: 12, bottom: 92, width: 300, height: 80 };
    }
    if (String(place).indexOf('bottom') === 0) {
      return { left: 362, right: 662, top: 700, bottom: 780, width: 300, height: 80 };
    }
    return { left: 20, right: 320, top: 20, bottom: 100, width: 300, height: 80 };
  };
  doc.body.appendChild(host);
  const env = { document: doc, window: { innerWidth: 1024, innerHeight: 800, addEventListener() {} } };
  toast.error('Please fix these fields', env);
  const tr = host.getBoundingClientRect();
  function hits(r) {
    return !(tr.right < r.left - 8 || tr.left > r.right + 8 || tr.bottom < r.top - 8 || tr.top > r.bottom + 8);
  }
  assert.strictEqual(hits(sticky.rect), false, 'scored placement does not cover sticky totals');
  assert.strictEqual(hits(next.rect), false, 'scored placement does not cover Next');
  assert.strictEqual(hits(save.rect), false, 'error toast does not cover Save at 1024');
  assert.ok(toast.PRIORITY_AVOID_SEL.indexOf('.br-sticky-total') !== -1);
  assert.ok(toast.PRIORITY_AVOID_SEL.indexOf('[data-hotkey-save]') !== -1, 'Save buttons are priority avoid rects');
  const place = host.getAttribute('data-placement');
  assert.ok(place !== 'bottom-center' || parseFloat(host.style.bottom || '0') > 40,
    'priority weighting prefers a placement that misses sticky over a tiny sticky overlap');
})();

(function toastPinsWhenEveryPlacementHitsPriority() {
  const sticky = makeEl('div', { class: 'br-sticky-total', id: 'br-sticky-total' });
  sticky.className = 'br-sticky-total';
  sticky.rect = { left: 0, top: 660, right: 1024, bottom: 800, width: 1024, height: 140 };
  const save = makeEl('button', { 'data-hotkey-save': '', id: 'toolbar-save' });
  save.rect = { left: 0, top: 0, right: 1024, bottom: 140, width: 1024, height: 140 };
  const doc = makeDoc([sticky, save]);
  const host = makeEl('div', { 'data-admin-toast-host': '' });
  host.hidden = true;
  host.getBoundingClientRect = function () {
    const pinned = parseFloat((this.style && this.style.bottom) || '');
    if (pinned > 40) {
      const bottom = 800 - pinned;
      return { left: 362, right: 662, top: bottom - 80, bottom, width: 300, height: 80 };
    }
    const place = this.getAttribute('data-placement') || 'bottom-center';
    if (String(place).indexOf('top') === 0) {
      return { left: 362, right: 662, top: 20, bottom: 100, width: 300, height: 80 };
    }
    return { left: 362, right: 662, top: 700, bottom: 780, width: 300, height: 80 };
  };
  doc.body.appendChild(host);
  const env = { document: doc, window: { innerWidth: 1024, innerHeight: 800, addEventListener() {} } };
  toast.error('Please fix these fields', env);
  assert.ok(parseFloat(host.style.bottom || '0') > 50, 'pinAboveSticky lifts the toast above the sticky bar');
  const tr = host.getBoundingClientRect();
  function hits(r) {
    return !(tr.right < r.left - 8 || tr.left > r.right + 8 || tr.bottom < r.top - 8 || tr.top > r.bottom + 8);
  }
  assert.strictEqual(hits(sticky.rect), false, 'pinned fallback does not cover sticky');
  assert.strictEqual(hits(save.rect), false, 'pinned fallback does not cover Save');
})();

(function toastShowsServerFlashText() {
  const host = makeEl('div', { 'data-admin-toast-host': '' });
  host.hidden = true;
  const doc = makeDoc([host]);
  const env = { document: doc, window: { innerWidth: 1024, innerHeight: 800, addEventListener() {} } };
  toast.saved(env, 'Schedule confirmation emailed to the client.');
  assert.ok(String(host.innerHTML).indexOf('Schedule confirmation emailed to the client.') !== -1,
    'success toast title uses the server flash text');
  toast.saved(env, 'Saved');
  assert.ok(String(host.innerHTML).indexOf('Saved') !== -1);
})();

(function darkCardOpacityIsSolid() {
  const fs = require('fs');
  const path = require('path');
  const dark = fs.readFileSync(path.resolve(__dirname, '../../public/css/admin-dark.css'), 'utf8');
  assert.ok(/html\.admin-dark \.admin-toast__card\s*\{[^}]*opacity:\s*1/.test(dark), 'dark toast card is opaque');
  assert.ok(/html\.admin-dark \.admin-toast--success\s*\{[^}]*opacity:\s*1/.test(dark), 'dark success toast is opaque');
  assert.ok(/html\.admin-dark \.admin-toast--error\s*\{[^}]*opacity:\s*1/.test(dark), 'dark error toast is opaque');
})();

(function formWatchTimerDoesNotRelease() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 11000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  assert.strictEqual(checkbox.checked, false);
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'real submit stays inflight');
  timers.filter((t) => t.ms === 12000 || t.ms === inst.WATCH_MS).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), '12s timer does not release a form-mode session');
  assert.strictEqual(checkbox.checked, false, '12s timer does not re-tick the email box');
  assert.ok(form.children.some((c) => c.name === '_hotkey_save'), '_hotkey_save stays until pageshow');
  inst.onPageShow({ persisted: true });
  assert.strictEqual(inst.isInflight(), false, 'pageshow releases the form session');
  assert.strictEqual(checkbox.checked, true, 'pageshow restores the email box');
  inst.reset();
})();

(function formUnlockTimeoutDoesNotReleaseNativeSubmit() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  let clicks = 0;
  btn.click = function () { clicks += 1; };
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 13000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'native submit stays locked');
  assert.ok(!timers.some((t) => t.ms === 30000), 'native submit must not schedule a 30s unlock');
  timers.filter((t) => t.ms >= 1000).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'no timer releases a native-submit lock');
  const beforeSecond = clicks;
  inst.handleKeydown(chord());
  assert.strictEqual(clicks, beforeSecond, 'second Ctrl+S during a pending native submit does not click Save again');
  assert.ok(inst.isInflight(), 'second Ctrl+S leaves the pending lock in place');
  inst.reset();
})();

(function nativeSubmitWithoutObserved204StaysLocked() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  let clicks = 0;
  btn.click = function () { clicks += 1; };
  const form = makeEl('form', { id: 'cp-create' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 16000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'native navigation stays locked');
  assert.strictEqual(
    inst.isUnlockingResponse({ status: 204, headers: { get() { return ''; } } }),
    true,
    '204 is an unlocking status only when a response object is observed'
  );
  assert.ok(inst.isInflight(), 'a 204 that never reaches fetch/XHR cannot unlock a native submit');
  const beforeSecond = clicks;
  inst.handleKeydown(chord());
  assert.strictEqual(clicks, beforeSecond, 'second Ctrl+S does not fire while the unobserved native submit stays locked');
  inst.reset();
})();

(function mouseSubmitThenLateHotkeyStaysLocked() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  let clicks = 0;
  btn.click = function () { clicks += 1; };
  const form = makeEl('form', { id: 'cp-create' }, [btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 15000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.onMarkedClick({ target: btn, preventDefault() {}, stopImmediatePropagation() {} });
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  timers.filter((t) => t.ms >= 1000).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'mouse native submit stays locked after late timers');
  inst.handleKeydown(chord());
  assert.strictEqual(clicks, 0, 'Ctrl+S after a pending mouse submit does not fire a second POST');
  inst.reset();
})();

(function submitOnceTokenStaysOnTheForm() {
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [btn]);
  const doc = makeDoc([form]);
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  const first = form.children.find((c) => c.name === '_submit_once' || (c.getAttribute && c.getAttribute('name') === '_submit_once'));
  assert.ok(first && first.value, 'Ctrl+S stamps a one-time submit token');
  const token = first.value;
  inst.onPageShow({ persisted: false });
  const afterShow = form.children.find((c) => c.name === '_submit_once' || (c.getAttribute && c.getAttribute('name') === '_submit_once'));
  assert.ok(afterShow, 'pageshow must not remove the submit token');
  assert.strictEqual(afterShow.value, token, 'pageshow must not rotate the submit token');
  inst.handleKeydown(chord());
  const again = form.children.find((c) => c.name === '_submit_once' || (c.getAttribute && c.getAttribute('name') === '_submit_once'));
  assert.strictEqual(again.value, token, 'a later Ctrl+S reuses the same submit token');
  inst.reset();
})();

(function sameDocumentPageShowUnlocksWithoutRetick() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const timers = [];
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
      clearTimeout() {},
    },
    now() { return 14000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.handleKeydown(chord());
  inst.onFormSubmit({ target: form, defaultPrevented: false });
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  inst.onPageShow({ persisted: false });
  assert.strictEqual(inst.isInflight(), false, 'same-document pageshow unlocks a stuck form session');
  assert.strictEqual(checkbox.checked, false, 'same-document pageshow never re-ticks the email box');
  inst.reset();
})();

(function pageshowListenerIsBound() {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const winListeners = {};
  const inst = hotkey.create({
    document: doc,
    window: {
      navigator: { platform: 'Linux' },
      addEventListener(type, fn) { winListeners[type] = fn; },
    },
    now() { return 12000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.bind();
  assert.ok(typeof winListeners.pageshow === 'function', 'bind() wires the pageshow listener');
  inst.handleKeydown(chord());
  assert.strictEqual(checkbox.checked, false);
  winListeners.pageshow({ persisted: true });
  assert.strictEqual(checkbox.checked, true, 'bound pageshow restores the email box');
  inst.reset();
})();

(function finishSessionRestoresFocus() {
  const doc = page('typing');
  const input = doc.getElementById('title');
  doc.activeElement = input;
  const inst = apiFor(doc);
  inst.handleKeydown(chord());
  doc.activeElement = doc.body;
  inst.releaseInflight();
  assert.strictEqual(doc.activeElement, input, 'finishSession restores focus');
  inst.reset();
})();

(function watchTinyUsesGet() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'tiny-save' });
  const doc = makeDoc([btn]);
  const timers = [];
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
    clearTimeout() {},
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 1000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.bindTiny();
  assert.ok(timers.some((t) => t.ms === 250), 'watchTiny polls while tinymce is missing');
  let keydown = null;
  let addEditor = null;
  const editor = { on(name, fn) { if (name === 'keydown') keydown = fn; } };
  win.tinymce = {
    get() { return [editor]; },
    on(name, fn) { if (name === 'AddEditor') addEditor = fn; },
  };
  timers.filter((t) => t.ms === 250).forEach((t) => t.fn());
  assert.ok(typeof addEditor === 'function', 'poll registers AddEditor');
  assert.ok(typeof keydown === 'function', 'poll hooks editors from tinymce.get()');
  inst.reset();
})();

(function watchTinyPollKeepsGoingAfter10s() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'tiny-save' });
  const doc = makeDoc([btn]);
  const timers = [];
  const win = {
    navigator: { platform: 'Linux' },
    setTimeout(fn, ms) {
      const t = { fn, ms: ms || 0, ran: false };
      timers.push(t);
      return timers.length;
    },
    clearTimeout() {},
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 1000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.bindTiny();
  let polls = 0;
  while (polls < 50) {
    const pending = timers.filter((t) => t.ms === 250 && !t.ran);
    if (!pending.length) break;
    pending.forEach((t) => {
      t.ran = true;
      t.fn();
    });
    polls += 1;
  }
  assert.ok(polls > 40, 'fallback poll does not stop after 40 tries / 10s');
  const leftover = timers.filter((t) => t.ms === 250 && !t.ran);
  assert.ok(leftover.length >= 1, 'a poll is still scheduled after the 10s window');

  let keydown = null;
  let addEditor = null;
  const editor = { on(name, fn) { if (name === 'keydown') keydown = fn; } };
  win.tinymce = {
    get() { return [editor]; },
    on(name, fn) { if (name === 'AddEditor') addEditor = fn; },
  };
  leftover.forEach((t) => {
    t.ran = true;
    t.fn();
  });
  assert.ok(typeof addEditor === 'function', 'tinymce appearing after 10s still registers AddEditor');
  assert.ok(typeof keydown === 'function', 'tinymce appearing after 10s is still hooked');
  inst.reset();
})();

(function watchTinyObserverHooksLateTiny() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'tiny-save' });
  const doc = makeDoc([btn]);
  const observers = [];
  function FakeObserver(cb) {
    this.cb = cb;
    this.disconnected = false;
    observers.push(this);
  }
  FakeObserver.prototype.observe = function () {};
  FakeObserver.prototype.disconnect = function () { this.disconnected = true; };

  const loadListeners = [];
  const win = {
    navigator: { platform: 'Linux' },
    MutationObserver: FakeObserver,
    addEventListener(type, fn) {
      if (type === 'load') loadListeners.push(fn);
    },
    removeEventListener() {},
    setTimeout() { throw new Error('watchTiny must not poll when MutationObserver exists'); },
    clearTimeout() {},
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 1000; },
    toast: { saved() {}, error() {}, validation() {} },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  inst.bindTiny();
  assert.strictEqual(observers.length, 1, 'watchTiny uses MutationObserver when available');
  assert.ok(loadListeners.length >= 1, 'watchTiny also listens for late script load');

  let keydown = null;
  let addEditor = null;
  const editor = { on(name, fn) { if (name === 'keydown') keydown = fn; } };
  win.tinymce = {
    get() { return [editor]; },
    on(name, fn) { if (name === 'AddEditor') addEditor = fn; },
  };
  observers[0].cb();
  assert.ok(typeof addEditor === 'function', 'late tinymce after the 10s window is still bound');
  assert.ok(typeof keydown === 'function', 'late tinymce editors receive the hotkey hook');
  assert.strictEqual(observers[0].disconnected, true, 'observer disconnects once tinymce is bound');
  inst.reset();
})();

(function logoutFormIsSkippedForErrors() {
  const logout = makeEl('form', { id: 'logout-form' });
  const field = makeEl('input', { name: 'pieces[0][width]', id: 'w' });
  const form = makeEl('form', { id: 'br-main-form' }, [field]);
  const doc = makeDoc([logout, form]);
  const inst = apiFor(doc);
  const mapped = inst.parseAjaxResult(
    { headers: { get() { return 'application/json'; } }, ok: false, status: 422 },
    { errors: { 'pieces.0.width': ['required'] } }
  );
  assert.strictEqual(mapped.kind, 'validation');
  assert.strictEqual(mapped.fields[0].el, field, 'dotted errors resolve on the real form, not logout');
  inst.reset();
})();

(function previewDoesNotClearSave() {
  const btn = makeEl('button', { type: 'button', 'data-hotkey-save': '', id: 'br-btn-draft' });
  const form = makeEl('form', { id: 'br-main-form', action: '/admin/quotations/basement' }, [btn]);
  const doc = makeDoc([form]);
  const toasts = [];
  const pending = [];
  function fakeFetch(url) {
    let resolve;
    const p = new Promise((res) => { resolve = res; });
    pending.push({ url: String(url), resolve, promise: p });
    return p;
  }
  let now = 4000;
  const win = {
    navigator: { platform: 'Linux' },
    fetch: fakeFetch,
    setTimeout,
    clearTimeout,
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return now; },
    toast: {
      saved() { toasts.push('saved'); },
      error(msg, _env, title) { toasts.push('error:' + (title || msg)); },
      validation() { toasts.push('validation'); },
    },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  btn.click = function () {
    this.clicked += 1;
    win.fetch('/admin/quotations/basement-calculate', { method: 'POST' });
    win.fetch('/admin/quotations/basement', { method: 'POST' });
  };
  inst.handleKeydown(chord());
  assert.strictEqual(pending.length, 2, 'draft click starts preview and save');
  assert.ok(inst.isInflight(), 'save lock stays on after the click');

  pending[0].resolve({
    ok: true,
    status: 200,
    headers: { get() { return 'application/json'; } },
    json() { return Promise.resolve({ ok: true, preview: true }); },
    clone() { return this; },
  });

  return Promise.resolve().then(() => Promise.resolve()).then(() => {
    assert.ok(inst.isInflight(), 'preview must not release the save lock');
    assert.deepStrictEqual(toasts, [], 'preview must not show Saved');
    pending[1].resolve({
      ok: true,
      status: 200,
      headers: { get() { return 'application/json'; } },
      json() { return Promise.resolve({ ok: true }); },
      clone() { return this; },
    });
    return Promise.resolve()
      .then(() => Promise.resolve())
      .then(() => Promise.resolve())
      .then(() => Promise.resolve());
  }).then(() => {
    assert.strictEqual(inst.isInflight(), false, 'real save releases the lock');
    assert.deepStrictEqual(toasts, ['saved'], 'Saved only after the real save JSON');
    inst.reset();
  });
})().then(() => {
  const inst = apiFor(page('standard'));
  let textCalls = 0;
  let jsonCalls = 0;
  return inst.readBody({
    clone() { return this; },
    text() { textCalls += 1; return Promise.resolve('{"ok":true}'); },
    json() { jsonCalls += 1; return Promise.resolve({ ok: true }); },
  }).then((body) => {
    assert.strictEqual(textCalls, 1, 'readBody reads text first');
    assert.strictEqual(jsonCalls, 0, 'readBody does not call json() first');
    assert.deepStrictEqual(body, { ok: true });
    inst.reset();
  });
}).then(() => {
  const checkbox = makeEl('input', { type: 'checkbox', name: 'send_client_email', checked: true, value: '1' });
  checkbox.checked = true;
  const btn = makeEl('button', { type: 'submit', 'data-hotkey-save': '', id: 'create-save' });
  const form = makeEl('form', { id: 'cp-create' }, [checkbox, btn]);
  const doc = makeDoc([form]);
  const toasts = [];
  const pending = [];
  const timers = [];
  const win = {
    navigator: { platform: 'Linux' },
    fetch() {
      let resolve;
      const p = new Promise((res) => { resolve = res; });
      pending.push({ resolve, promise: p });
      return p;
    },
    setTimeout(fn, ms) { timers.push({ fn, ms: ms || 0 }); return timers.length; },
    clearTimeout() {},
  };
  const inst = hotkey.create({
    document: doc,
    window: win,
    now() { return 20000; },
    toast: {
      saved() { toasts.push('saved'); },
      error() { toasts.push('error'); },
      validation() { toasts.push('validation'); },
    },
    storage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  });
  btn.click = function () {
    this.clicked += 1;
    inst.onFormSubmit({ target: form, defaultPrevented: false });
    win.fetch('/admin/client-projects', { method: 'POST' });
  };
  inst.handleKeydown(chord());
  assert.strictEqual(pending.length, 1, 'form-mode save still wraps fetch');
  timers.filter((t) => t.ms === 0).forEach((t) => t.fn());
  assert.ok(inst.isInflight(), 'native submit stays locked until a response');
  pending[0].resolve({
    ok: true,
    status: 204,
    headers: { get() { return ''; } },
    text() { return Promise.resolve(''); },
    clone() { return this; },
  });
  return Promise.resolve()
    .then(() => Promise.resolve())
    .then(() => Promise.resolve())
    .then(() => {
      assert.strictEqual(inst.isInflight(), false, '204 unlocks a form-mode session');
      assert.deepStrictEqual(toasts, ['saved'], '204 shows Saved');
      assert.strictEqual(checkbox.checked, true, '204 restore re-ticks the email box');
      inst.reset();
    });
}).then(() => {
  (function replyToAcceptValidationUsesFieldsetLegendNotYesOption() {
    const replyMsg =
      'Choose Yes or No for \u201cAsk client to reply I accept to this email\u201d before saving or sending.';
    const heading =
      'Ask client to reply \u201cI accept\u201d to this email';
    const tipCopy =
      'Required. Pick Yes or No before saving or emailing. When Yes, the email asks the client to reply \u201cI accept\u201d.';

    function makeProductionReplyLegend() {
      const legend = makeEl('legend', { class: 'cp-reply-to-accept__legend' });
      legend._text = heading + ' ';
      const tipEnd = makeEl('span', { class: 'admin-label-with-tip__end' });
      tipEnd.classList.add('admin-label-with-tip__end');
      const req = makeEl('span', { class: 'cp-required' });
      req.classList.add('cp-required');
      req.textContent = '*';
      const tipBtn = makeEl('button', {
        type: 'button',
        'data-admin-info-tip-trigger': '',
        'aria-label': 'Ask client to reply I accept',
      });
      tipBtn.textContent = 'i';
      const tipPanel = makeEl('div', { class: 'admin-info-tip', 'data-admin-info-tip-panel': '' });
      tipPanel.classList.add('admin-info-tip');
      tipPanel.textContent = tipCopy;
      tipEnd.appendChild(req);
      tipEnd.appendChild(tipBtn);
      tipEnd.appendChild(tipPanel);
      legend.appendChild(tipEnd);
      return legend;
    }

    const yes = makeEl('input', { type: 'radio', name: 'ask_reply_to_accept', value: '1', invalid: true });
    const no = makeEl('input', { type: 'radio', name: 'ask_reply_to_accept', value: '0', invalid: true });
    const fieldset = makeEl('fieldset', { class: 'cp-reply-to-accept__fieldset' }, [makeProductionReplyLegend()]);
    const yesLbl = makeEl('label', {}, [yes]);
    yesLbl.textContent = 'Yes';
    fieldset.appendChild(yesLbl);
    const noLbl = makeEl('label', {}, [no]);
    noLbl.textContent = 'No';
    fieldset.appendChild(noLbl);
    const form = makeEl('form', { id: 'sq-standard-form' }, [fieldset]);
    const host = makeEl('div', { 'data-admin-toast-host': '' });
    host.hidden = true;
    const doc = makeDoc([form, host]);
    const api = apiFor(doc);

    const fromPayload = api.fieldsFromPayload([
      { name: 'ask_reply_to_accept', message: replyMsg },
    ]);
    assert.strictEqual(fromPayload.length, 1);
    assert.strictEqual(fromPayload[0].label, heading);
    assert.notStrictEqual(fromPayload[0].label.trim(), 'Yes');
    assert.ok(fromPayload[0].label.indexOf('Required. Pick Yes or No') === -1, 'tooltip copy excluded');
    assert.ok(fromPayload[0].label.indexOf('*') === -1, 'required marker excluded');
    assert.strictEqual(fromPayload[0].el, yes, 'payload maps to first radio in group');
    assert.strictEqual(fromPayload[0].message, replyMsg);

    const fromDom = api.collectInvalidFields(form);
    assert.strictEqual(fromDom.length, 1, 'radio group deduped');
    assert.strictEqual(fromDom[0].label, heading);
    assert.strictEqual(fromDom[0].el, yes);

    const notify = makeEl('input', { type: 'checkbox', name: 'send_client_email', value: '1' });
    const notifyLegend = makeEl('legend', {});
    notifyLegend.textContent = 'Email options';
    const notifyFieldset = makeEl('fieldset', {}, [notifyLegend]);
    const notifyLbl = makeEl('label', {}, [notify]);
    notifyLbl.textContent = 'Send confirmation email';
    notifyFieldset.appendChild(notifyLbl);
    form.appendChild(notifyFieldset);
    const notifyFields = api.fieldsFromPayload([{ name: 'send_client_email', message: 'is required' }]);
    assert.strictEqual(notifyFields[0].label, 'Send confirmation email', 'checkbox keeps option label');

    const envMobile = { document: doc, window: { innerWidth: 390, innerHeight: 740, addEventListener() {} } };
    toast.validation(fromPayload, {}, envMobile);
    assert.strictEqual(host.hidden, false);
    const html = String(host.innerHTML);
    assert.ok(html.indexOf('Please fix these fields') !== -1, 'validation title');
    assert.ok(html.indexOf(heading) !== -1, 'toast link uses legend heading only');
    assert.ok(html.indexOf('admin-toast__field-link">Yes</a>') === -1, 'toast link is not the Yes option');
    assert.ok(html.indexOf('Required. Pick Yes or No') === -1, 'toast link excludes tooltip body');
    assert.ok(fieldset.classList.contains('admin-field-invalid'), 'fieldset highlighted');
    assert.ok(yes.classList.contains('admin-field-invalid'), 'first radio marked invalid');
    assert.strictEqual(doc.activeElement, yes, 'focus lands on reply Yes/No group');

    const envDesktop = { document: doc, window: { innerWidth: 1280, innerHeight: 800, addEventListener() {} } };
    toast.validation(fromPayload, {}, envDesktop);
    assert.ok(fieldset.classList.contains('admin-field-invalid'), 'desktop toast still highlights fieldset');
    toast.close(envDesktop);
  })();
  console.log('admin-hotkey-save tests: ok');
}).catch((err) => {
  console.error(err);
  process.exit(1);
});
