/**
 * Minimal DOM + behaviour tests for public/js/admin-info-tip.js.
 * Covers scroll reposition, server-side (sibling) placement hoist fallback,
 * and fresh ids after clone.
 */
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const require = createRequire(import.meta.url);
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsPath = path.resolve(__dirname, '../../public/js/admin-info-tip.js');
const source = readFileSync(jsPath, 'utf8');

class ClassList {
  constructor(el) {
    this.el = el;
  }
  get _set() {
    return new Set((this.el.className || '').split(/\s+/).filter(Boolean));
  }
  _write(set) {
    this.el.className = [...set].join(' ');
  }
  add(name) {
    const set = this._set;
    set.add(name);
    this._write(set);
  }
  remove(name) {
    const set = this._set;
    set.delete(name);
    this._write(set);
  }
  contains(name) {
    return this._set.has(name);
  }
}

function parseSelector(sel) {
  if (sel.startsWith(':scope > ')) {
    return { child: true, raw: sel.slice(':scope > '.length) };
  }
  return { child: false, raw: sel };
}

function matchSimple(el, raw) {
  if (raw === '*') return true;
  const tag = (raw.match(/^[a-z][\w-]*/i) || [''])[0].toLowerCase();
  const id = (raw.match(/#([\w-]+)/) || [])[1];
  const cls = [...raw.matchAll(/\.([\w-]+)/g)].map((m) => m[1]);
  if (tag && el.tagName.toLowerCase() !== tag) return false;
  if (id && el.id !== id) return false;
  for (const c of cls) {
    if (!el.classList.contains(c)) return false;
  }
  return true;
}

class FakeEl {
  constructor(tag, owner) {
    this.tagName = String(tag).toUpperCase();
    this.ownerDocument = owner;
    this.children = [];
    this.parentNode = null;
    this.attributes = Object.create(null);
    this.className = '';
    this.classList = new ClassList(this);
    this.style = {};
    this.id = '';
    this.textContent = '';
    this.nodeType = 1;
    this._rect = { top: 80, bottom: 124, left: 200, right: 244, width: 44, height: 44 };
    this._listeners = Object.create(null);
    this.control = null;
    this.htmlFor = '';
  }

  get className() {
    return this._className || '';
  }
  set className(v) {
    this._className = String(v || '');
  }

  matches(sel) {
    const { raw } = parseSelector(sel);
    return matchSimple(this, raw);
  }

  closest(sel) {
    let n = this;
    while (n && n.nodeType === 1) {
      if (n.matches(sel)) return n;
      n = n.parentNode;
    }
    return null;
  }

  querySelector(sel) {
    return this.querySelectorAll(sel)[0] || null;
  }

  querySelectorAll(sel) {
    const { child, raw } = parseSelector(sel);
    if (!child && raw.includes(' ')) {
      const parts = raw.split(/\s+/);
      const last = parts.pop();
      const out = [];
      const walk = (node) => {
        for (const c of node.children) {
          if (matchSimple(c, last)) {
            let anc = c.parentNode;
            let ok = true;
            for (let i = parts.length - 1; i >= 0 && ok; i--) {
              while (anc && anc.nodeType === 1 && !matchSimple(anc, parts[i])) {
                anc = anc.parentNode;
              }
              ok = !!(anc && anc.nodeType === 1);
              anc = anc ? anc.parentNode : null;
            }
            if (ok) out.push(c);
          }
          walk(c);
        }
      };
      walk(this);
      return out;
    }
    const out = [];
    const walk = (node, depth) => {
      for (const c of node.children) {
        if (matchSimple(c, raw) && (!child || depth === 0)) out.push(c);
        if (!child) walk(c, depth + 1);
      }
    };
    walk(this, 0);
    return out;
  }

  getAttribute(name) {
    if (name === 'id') return this.id || null;
    if (name === 'class') return this.className || null;
    return this.attributes[name] ?? null;
  }

  setAttribute(name, value) {
    const v = String(value);
    if (name === 'id') this.id = v;
    else if (name === 'class') this.className = v;
    else if (name === 'for') this.htmlFor = v;
    this.attributes[name] = v;
  }

  getBoundingClientRect() {
    return { ...this._rect };
  }

  contains(el) {
    let n = el;
    while (n) {
      if (n === this) return true;
      n = n.parentNode;
    }
    return false;
  }

  appendChild(el) {
    if (el.parentNode) {
      el.parentNode.children = el.parentNode.children.filter((c) => c !== el);
    }
    el.parentNode = this;
    this.children.push(el);
    return el;
  }

  insertBefore(el, ref) {
    if (el.parentNode) {
      el.parentNode.children = el.parentNode.children.filter((c) => c !== el);
    }
    el.parentNode = this;
    const i = ref ? this.children.indexOf(ref) : -1;
    if (i === -1) this.children.push(el);
    else this.children.splice(i, 0, el);
    return el;
  }

  addEventListener(type, fn) {
    (this._listeners[type] ||= []).push(fn);
  }

  dispatchEvent(type, event) {
    (this._listeners[type] || []).forEach((fn) => fn(event));
  }

  focus() {
    this.ownerDocument.activeElement = this;
  }

  cloneNode() {
    const clone = this.ownerDocument.createElement(this.tagName.toLowerCase());
    clone.className = this.className;
    clone.id = this.id;
    clone.textContent = this.textContent;
    Object.assign(clone.attributes, this.attributes);
    clone.htmlFor = this.htmlFor;
    for (const child of this.children) {
      clone.appendChild(child.cloneNode());
    }
    return clone;
  }
}

function createEnv() {
  const listeners = { document: {}, window: {} };

  const document = {
    readyState: 'complete',
    documentElement: null,
    body: null,
    activeElement: null,
    _els: [],
    createElement(tag) {
      const el = new FakeEl(tag, document);
      document._els.push(el);
      return el;
    },
    getElementById(id) {
      return document._els.find((el) => el.id === id) || null;
    },
    querySelector(sel) {
      return document.querySelectorAll(sel)[0] || null;
    },
    querySelectorAll(sel) {
      return document.body ? document.body.querySelectorAll(sel) : [];
    },
    addEventListener(type, fn, opts) {
      (listeners.document[type] ||= []).push({ fn, opts });
    },
  };

  const body = document.createElement('body');
  document.body = body;
  document.documentElement = document.createElement('html');
  document.documentElement.appendChild(body);

  const window = {
    innerWidth: 390,
    innerHeight: 700,
    document,
    requestAnimationFrame(fn) {
      return fn();
    },
    getComputedStyle() {
      return { overflow: 'visible', overflowX: 'visible', overflowY: 'visible' };
    },
    addEventListener(type, fn, opts) {
      (listeners.window[type] ||= []).push({ fn, opts });
    },
    MutationObserver: function MutationObserver() {
      this.observe = function () {};
    },
  };

  document.defaultView = window;

  const fn = new Function('window', 'document', source + '\nreturn window.AdminInfoTip;');
  const AdminInfoTip = fn(window, document);

  return { window, document, body, listeners, AdminInfoTip };
}

function makeTip(document, id, label) {
  const wrap = document.createElement('span');
  wrap.className = 'admin-info-tip-wrap';
  const btn = document.createElement('button');
  btn.className = 'admin-info-tip';
  btn.setAttribute('aria-label', 'About ' + label);
  btn.setAttribute('aria-expanded', 'false');
  btn.setAttribute('aria-controls', id);
  btn.setAttribute('aria-describedby', id);
  const bubble = document.createElement('span');
  bubble.className = 'admin-info-tip__bubble';
  bubble.id = id;
  bubble._rect = { top: 0, bottom: 80, left: 0, right: 200, width: 200, height: 80 };
  wrap.appendChild(btn);
  wrap.appendChild(bubble);
  return { wrap, btn, bubble };
}

function fire(list, type, event) {
  (list[type] || []).forEach(({ fn }) => fn(event || {}));
}

// --- scroll repositions an open tip ---
{
  const { document, body, listeners, AdminInfoTip } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-dup', 'Board style');
  body.appendChild(wrap);
  btn.dispatchEvent = function () {};
  const click = listeners.document.click.find((l) => l.opts === true || (l.opts && l.opts.capture));
  assert.ok(click, 'capture-phase click listener');
  click.fn({
    target: {
      closest(sel) {
        if (sel === '.admin-info-tip') return btn;
        if (sel === '.admin-info-tip__bubble') return null;
        if (sel === '.admin-info-tip-wrap') return wrap;
        return null;
      },
    },
    preventDefault() {},
    stopPropagation() {},
  });
  assert.equal(wrap.classList.contains('is-open'), true);
  assert.equal(bubble.style.position, 'fixed');
  const firstTop = bubble.style.top;
  btn._rect = { top: 300, bottom: 344, left: 40, right: 84, width: 44, height: 44 };
  const scroll = listeners.document.scroll.find((l) => l.opts && l.opts.capture && l.opts.passive);
  assert.ok(scroll, 'capture-phase passive scroll listener');
  scroll.fn();
  assert.equal(bubble.style.position, 'fixed');
  assert.notEqual(bubble.style.top, firstTop);

  btn._rect = { top: -80, bottom: -36, left: 40, right: 84, width: 44, height: 44 };
  scroll.fn();
  assert.equal(wrap.classList.contains('is-open'), false);
  AdminInfoTip.closeAll();
}

// --- hoist leftover in-label tips (fallback for server-side placement) ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const group = document.createElement('div');
  group.className = 'admin-form-group';
  const label = document.createElement('label');
  label.setAttribute('for', 'sku');
  label.htmlFor = 'sku';
  const input = document.createElement('input');
  input.id = 'sku';
  const { wrap } = makeTip(document, 'admin-info-tip-inlabel', 'SKU');
  label.appendChild(wrap);
  group.appendChild(label);
  group.appendChild(input);
  body.appendChild(group);
  label.control = input;
  AdminInfoTip.hoist(document);
  assert.equal(wrap.parentNode.className, 'admin-label-with-tip');
  assert.ok(!label.contains(wrap), 'tip is no longer inside the label');
  assert.ok(wrap.parentNode.contains(label), 'label stays in the hoisted row');
}

// --- cloned rows get fresh ids ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const { wrap } = makeTip(document, 'admin-info-tip-shared', 'Materials');
  body.appendChild(wrap);
  const clone = wrap.cloneNode(true);
  body.appendChild(clone);
  const firstId = wrap.querySelector('.admin-info-tip__bubble').id;
  const cloneIdBefore = clone.querySelector('.admin-info-tip__bubble').id;
  assert.equal(firstId, cloneIdBefore);
  AdminInfoTip.refreshIds(clone);
  const cloneIdAfter = clone.querySelector('.admin-info-tip__bubble').id;
  const cloneBtn = clone.querySelector('.admin-info-tip');
  assert.notEqual(cloneIdAfter, firstId);
  assert.equal(cloneBtn.getAttribute('aria-controls'), cloneIdAfter);
  assert.equal(cloneBtn.getAttribute('aria-describedby'), cloneIdAfter);
  assert.equal(wrap.querySelector('.admin-info-tip__bubble').id, firstId);
}

// --- document-wide dedupe when a cloned subtree repeats an existing id ---
{
  const { document, body, AdminInfoTip } = createEnv();
  const { wrap } = makeTip(document, 'admin-info-tip-shared', 'Materials');
  body.appendChild(wrap);
  const added = wrap.cloneNode(true);
  body.appendChild(added);
  const firstId = wrap.querySelector('.admin-info-tip__bubble').id;
  assert.equal(added.querySelector('.admin-info-tip__bubble').id, firstId);
  AdminInfoTip.refreshIds(added);
  const addedId = added.querySelector('.admin-info-tip__bubble').id;
  assert.notEqual(addedId, firstId);
  assert.equal(wrap.querySelector('.admin-info-tip__bubble').id, firstId);
}

// --- Esc returns focus to the button when focus is in the bubble ---
{
  const { document, body, listeners } = createEnv();
  const { wrap, btn, bubble } = makeTip(document, 'admin-info-tip-esc', 'Invoices');
  body.appendChild(wrap);
  wrap.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  document.activeElement = bubble;
  const key = listeners.document.keydown[0];
  assert.ok(key, 'keydown listener');
  key.fn({ key: 'Escape' });
  assert.equal(wrap.classList.contains('is-open'), false);
  assert.equal(document.activeElement, btn);
}

console.log('admin-info-tip.test.mjs: ok');
