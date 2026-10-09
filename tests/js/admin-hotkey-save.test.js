'use strict';

const assert = require('assert');
const hotkey = require('../../public/js/admin-hotkey-save.js');
const toast = require('../../public/js/admin-toast.js');

function chord(overrides) {
  const e = Object.assign({
    key: 's',
    ctrlKey: true,
    metaKey: false,
    altKey: false,
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
assert.strictEqual(hotkey.isSaveChord(chord({ key: 'k' })), false, 'Ctrl+K is not save');
assert.strictEqual(hotkey.isSaveChord(chord({ altKey: true })), false, 'Alt+Ctrl+S is ignored');
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
  if (sel === 'label') return el.tagName === 'LABEL';
  if (sel === '[data-hotkey-save]') return el.hasAttribute('data-hotkey-save');
  if (sel === '[data-admin-toast-host]') return el.hasAttribute('data-admin-toast-host');
  if (sel.indexOf('[role="dialog"]') === 0) return el.getAttribute('role') === 'dialog';
  if (sel.indexOf('[role="alertdialog"]') === 0) return el.getAttribute('role') === 'alertdialog';
  if (sel.charAt(0) === '#') return el.id === sel.slice(1) || el.getAttribute('id') === sel.slice(1);
  if (sel.indexOf('label[for="') === 0) {
    const id = sel.slice('label[for="'.length, -2);
    return el.tagName === 'LABEL' && el.getAttribute('for') === id;
  }
  if (sel.indexOf('[name="') === 0) {
    const name = sel.slice('[name="'.length, -2);
    return el.getAttribute('name') === name;
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
    get name() { return this.attrs.name || ''; },
    get form() {
      let n = this;
      while (n) {
        if (n.tagName === 'FORM') return n;
        n = n.parent;
      }
      return null;
    },
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
    createElement(tag) { return makeEl(tag); },
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
  assert.strictEqual(btn.clicked, 2, 'after debounce a full-page save can fire again');
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
  assert.ok(input.blurred, 'pending value is committed via blur');
  assert.ok(input.changed || (input.events && input.events.indexOf('change') !== -1), 'change is dispatched before save');
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

console.log('admin-hotkey-save tests: ok');
