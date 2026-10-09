/**
 * Shared fake DOM + AdminInfoTip loader for Node tests.
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const jsPath = path.resolve(__dirname, '../../public/js/admin-info-tip.js');
export const source = readFileSync(jsPath, 'utf8');

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

function matchAttr(el, spec) {
  const m = spec.match(/^([a-zA-Z_:][\w:.-]*)(?:([~|^$*]?=)(?:"([^"]*)"|'([^']*)'|(\S+)))?$/);
  if (!m) return false;
  const name = m[1];
  const op = m[2];
  const val = m[3] ?? m[4] ?? m[5] ?? '';
  if (!el.hasAttribute(name)) return false;
  if (!op) return true;
  const actual = String(el.getAttribute(name) ?? '');
  if (op === '=') return actual === val;
  if (op === '^=') return actual.startsWith(val);
  if (op === '$=') return actual.endsWith(val);
  if (op === '*=') return actual.includes(val);
  if (op === '~=') return actual.split(/\s+/).includes(val);
  if (op === '|=') return actual === val || actual.startsWith(val + '-');
  return false;
}

function matchSimple(el, raw) {
  if (raw === '*') return true;
  const tag = (raw.match(/^[a-z][\w-]*/i) || [''])[0].toLowerCase();
  const id = (raw.match(/#([\w-]+)/) || [])[1];
  const cls = [...raw.matchAll(/\.([\w-]+)/g)].map((m) => m[1]);
  const attrs = [...raw.matchAll(/\[([a-zA-Z_:][\w:.-]*(?:[~|^$*]?=(?:"[^"]*"|'[^']*'|[^\]]+))?)\]/g)].map((m) => m[1]);
  if (tag && el.tagName.toLowerCase() !== tag) return false;
  if (id && el.id !== id) return false;
  for (const c of cls) {
    if (!el.classList.contains(c)) return false;
  }
  for (const spec of attrs) {
    if (!matchAttr(el, spec)) return false;
  }
  return true;
}

class FakeText {
  constructor(text, owner) {
    this.nodeType = 3;
    this.ownerDocument = owner;
    this.parentNode = null;
    this.childNodes = [];
    this.children = [];
    this._text = String(text ?? '');
  }
  get textContent() {
    return this._text;
  }
  set textContent(v) {
    this._text = String(v ?? '');
  }
}

class FakeEl {
  constructor(tag, owner) {
    this.tagName = String(tag).toUpperCase();
    this.ownerDocument = owner;
    this.children = [];
    this.childNodes = [];
    this.parentNode = null;
    this.attributes = Object.create(null);
    this._className = '';
    this.classList = new ClassList(this);
    this.style = {};
    this.id = '';
    this._text = '';
    this.nodeType = 1;
    this.value = '';
    this.open = false;
    this._rect = { top: 80, bottom: 124, left: 200, right: 244, width: 44, height: 44 };
    this._listeners = Object.create(null);
    this.control = null;
    this.htmlFor = '';
  }

  get textContent() {
    if (this.childNodes.length) {
      return this.childNodes.map((c) => (c.textContent ?? '')).join('');
    }
    return this._text || '';
  }
  set textContent(v) {
    this._text = String(v ?? '');
    this.children.forEach((c) => {
      if (c.parentNode === this) c.parentNode = null;
    });
    this.children = [];
    this.childNodes = [];
    if (this._text !== '') {
      const t = new FakeText(this._text, this.ownerDocument);
      t.parentNode = this;
      this.childNodes.push(t);
    }
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
    if (sel.includes(',')) {
      const seen = new Set();
      const out = [];
      sel.split(',').forEach((part) => {
        this.querySelectorAll(part.trim()).forEach((el) => {
          if (!seen.has(el)) {
            seen.add(el);
            out.push(el);
          }
        });
      });
      return out;
    }
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

  hasAttribute(name) {
    if (name === 'id') return this.id !== '';
    if (name === 'class') return this.className !== '';
    if (name === 'for') return this.htmlFor !== '';
    return Object.prototype.hasOwnProperty.call(this.attributes, name);
  }

  getAttribute(name) {
    if (name === 'id') return this.id || null;
    if (name === 'class') return this.className || null;
    if (name === 'for') return this.htmlFor || null;
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

  get previousElementSibling() {
    if (!this.parentNode || !Array.isArray(this.parentNode.children)) return null;
    const i = this.parentNode.children.indexOf(this);
    return i > 0 ? this.parentNode.children[i - 1] : null;
  }

  _detach(el) {
    if (!el || !el.parentNode) return;
    const parent = el.parentNode;
    if (Array.isArray(parent.children)) {
      parent.children = parent.children.filter((c) => c !== el);
    }
    if (Array.isArray(parent.childNodes)) {
      parent.childNodes = parent.childNodes.filter((c) => c !== el);
    }
    el.parentNode = null;
  }

  appendChild(el) {
    this._detach(el);
    el.parentNode = this;
    this.childNodes.push(el);
    if (el.nodeType === 1) this.children.push(el);
    return el;
  }

  insertBefore(el, ref) {
    this._detach(el);
    el.parentNode = this;
    const i = ref ? this.childNodes.indexOf(ref) : -1;
    if (i === -1) this.childNodes.push(el);
    else this.childNodes.splice(i, 0, el);
    if (el.nodeType === 1) {
      const ci = ref && ref.nodeType === 1 ? this.children.indexOf(ref) : -1;
      if (ci === -1) this.children.push(el);
      else this.children.splice(ci, 0, el);
    }
    return el;
  }

  addEventListener(type, fn) {
    (this._listeners[type] ||= []).push(fn);
  }

  dispatchEvent(type, event) {
    (this._listeners[type] || []).forEach((fn) => fn(event));
  }

  click() {
    const ev = {
      target: this,
      preventDefault() {},
      stopPropagation() {},
    };
    const doc = this.ownerDocument;
    const capture = (doc && doc._captureClick) || [];
    capture.forEach((fn) => fn(ev));
    this.dispatchEvent('click', ev);
  }

  focus() {
    this.ownerDocument.activeElement = this;
  }

  cloneNode() {
    const clone = this.ownerDocument.createElement(this.tagName.toLowerCase());
    clone.className = this.className;
    clone.id = this.id;
    clone.value = this.value;
    Object.assign(clone.attributes, this.attributes);
    clone.htmlFor = this.htmlFor;
    for (const child of this.childNodes) {
      if (child.nodeType === 3) {
        clone.appendChild(this.ownerDocument.createTextNode(child.textContent));
      } else {
        clone.appendChild(child.cloneNode());
      }
    }
    return clone;
  }
}

export function createEnv() {
  const listeners = { document: {}, window: {} };

  const document = {
    readyState: 'complete',
    documentElement: null,
    body: null,
    activeElement: null,
    _els: [],
    _captureClick: [],
    createElement(tag) {
      const el = new FakeEl(tag, document);
      document._els.push(el);
      return el;
    },
    createTextNode(text) {
      return new FakeText(text, document);
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
      if (type === 'click' && (opts === true || (opts && opts.capture))) {
        document._captureClick.push(fn);
      }
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

export function makeTip(document, id, label) {
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

export function fire(list, type, event) {
  (list[type] || []).forEach(({ fn }) => fn(event || {}));
}
