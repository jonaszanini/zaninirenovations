'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const helperSrc = fs.readFileSync(path.join(__dirname, 'admin-basement-flood-preset.test.js'), 'utf8');
const helperBody = helperSrc.slice(helperSrc.indexOf('function parseSimpleCompound'), helperSrc.indexOf('// Build DOM'));
const rawMake = new Function(helperBody + '\nreturn makeElement;')();

function makeElement(tag, attrs, children) {
  const el = rawMake(tag, attrs, children);
  const props = {};
  el.scrollCalls = [];
  el.scrollIntoView = function (opts) { el.scrollCalls.push(opts); };
  el.style = {
    setProperty(name, value) { props[name] = value; },
    getProperty(name) { return props[name]; },
  };
  el._styleProps = props;
  return el;
}

function section(key, open) {
  const attrs = { class: 'br-acc admin-card', 'data-br-step': key };
  if (open) attrs.open = '';
  const summary = makeElement('summary', { class: 'br-acc__summary' }, [
    makeElement('span', { class: 'br-acc__num', textContent: '1 of 4' }),
    makeElement('span', { class: 'br-progress__label', textContent: key }),
  ]);
  return makeElement('details', attrs, [summary]);
}

function navButton(key) {
  return makeElement('button', {
    type: 'button',
    class: 'br-progress__item',
    'data-br-goto': key,
  }, [
    makeElement('span', { class: 'br-progress__label', textContent: key }),
  ]);
}

const sections = {
  overview: section('overview', true),
  painting: section('painting', true),
  finishing: section('finishing', false),
  client_scope: section('client_scope', true),
  review: section('review', true),
};
const buttons = {
  overview: navButton('overview'),
  painting: navButton('painting'),
  finishing: navButton('finishing'),
  client_scope: navButton('client_scope'),
  review: navButton('review'),
};
const tradeCheckboxes = ['painting', 'finishing'].map((key) => makeElement('input', {
  type: 'checkbox',
  name: 'enabled_sections[]',
  value: key,
  'data-br-enabled-section': key,
  checked: true,
}));
const preset = makeElement('input', {
  type: 'radio',
  name: 'scope_preset',
  value: 'full',
  'data-br-scope-preset': '',
  checked: true,
});
const viewToggle = makeElement('button', { type: 'button', id: 'br-view-mode-toggle' }, [
  makeElement('span', { 'data-br-view-mode-label': '', textContent: 'All sections' }),
]);

const form = makeElement('form', { id: 'br-main-form', class: 'pq-layout br-layout' }, [
  preset,
  ...tradeCheckboxes,
  ...Object.values(sections),
  ...Object.values(buttons),
  viewToggle,
  makeElement('span', { id: 'br-step-status' }),
  makeElement('div', { id: 'br-sticky-total' }),
  makeElement('p', { id: 'br-live-empty' }),
  makeElement('div', { id: 'br-live-body', hidden: true }),
  makeElement('button', { type: 'button', id: 'br-prev' }),
  makeElement('button', { type: 'button', id: 'br-next' }),
]);

const docRoot = makeElement('html', {}, [
  makeElement('head', {}, [
    makeElement('meta', { name: 'csrf-token', content: 'test-token' }),
  ]),
  makeElement('body', {}, [form]),
]);

const documentObj = {
  documentElement: docRoot,
  getElementById(id) { return docRoot.querySelector('#' + id); },
  querySelector(sel) { return docRoot.querySelector(sel); },
  querySelectorAll(sel) { return docRoot.querySelectorAll(sel); },
  createElement(tag) { return makeElement(tag); },
  addEventListener() {},
  dispatchEvent(evt) { return docRoot.dispatchEvent(evt); },
};

let headerPosition = 'static';
let headerHeight = 80;

const sandbox = {
  console,
  Math,
  Number,
  String,
  Boolean,
  Array,
  Object,
  isFinite,
  parseFloat,
  parseInt,
  setTimeout,
  clearTimeout,
  document: documentObj,
  FormData: class MockFormData {
    constructor() { this.map = new Map(); }
    set(k, v) { this.map.set(k, v); }
    delete(k) { this.map.delete(k); }
    append(k, v) { this.map.set(k, v); }
  },
  Event: class Event {
    constructor(type, opts) {
      this.type = type;
      this.bubbles = !!(opts && opts.bubbles);
    }
  },
  CustomEvent: class CustomEvent {
    constructor(type, opts) {
      this.type = type;
      this.detail = opts ? opts.detail : undefined;
    }
  },
  HTMLFormElement: { prototype: { submit() {} } },
  getComputedStyle() {
    return { position: headerPosition };
  },
  fetch() {
    return Promise.resolve({
      ok: true,
      json: () => Promise.resolve({
        scope: {},
        labor: [],
        materials: [],
        summary: { grand_total: 0, section_subtotals_pre_hst: {} },
      }),
    });
  },
  window: null,
};
sandbox.window = sandbox;
sandbox.window.addEventListener = () => {};
sandbox.window.BR_QUOTATION = {
  isEdit: true,
  sectionKeys: ['overview', 'painting', 'finishing'],
  presetSections: {
    full: ['overview', 'painting', 'finishing'],
  },
  previewUrl: '/preview',
};

const topbar = makeElement('header', { class: 'admin-topbar' });
topbar.getBoundingClientRect = () => ({ height: headerHeight, width: 390, top: 0, left: 0 });
docRoot.appendChild(topbar);

const scriptPath = path.resolve(__dirname, '../../public/js/admin-basement-quotation.js');
vm.runInNewContext(fs.readFileSync(scriptPath, 'utf8'), sandbox, { filename: scriptPath });

function isOpen(el) {
  return el.hasAttribute('open');
}

assert.strictEqual(buttons.painting.getAttribute('type'), 'button');
assert.strictEqual(isOpen(sections.overview), true, 'edit boot must leave a manually open section open');
assert.strictEqual(isOpen(sections.painting), true, 'edit boot must not collapse other open sections');

buttons.painting.click();
assert.strictEqual(isOpen(sections.painting), true, 'nav click expands the painting section');
assert.strictEqual(isOpen(sections.overview), false, 'nav click collapses overview');
assert.strictEqual(isOpen(sections.finishing), false, 'nav click collapses finishing');
assert.strictEqual(isOpen(sections.client_scope), false, 'nav click collapses client scope');
assert.strictEqual(isOpen(sections.review), false, 'nav click collapses review');
assert.strictEqual(buttons.painting.classList.contains('is-current'), true);
assert.strictEqual(buttons.overview.classList.contains('is-current'), false);
assert.strictEqual(
  sections.painting.querySelector('.br-acc__summary').getAttribute('aria-expanded'),
  'true'
);
assert.strictEqual(
  sections.overview.querySelector('.br-acc__summary').getAttribute('aria-expanded'),
  'false'
);
assert.strictEqual(sections.painting.scrollCalls.length, 1, 'nav click scrolls the section');
assert.strictEqual(sections.painting.scrollCalls[0].block, 'start');

sections.overview.setAttribute('open', '');
sections.overview.dispatchEvent(new sandbox.Event('toggle', { bubbles: false }));
assert.strictEqual(isOpen(sections.overview), true, 'manual header toggle keeps the section open');
assert.strictEqual(isOpen(sections.painting), true, 'manual header toggle does not collapse the other section');
assert.strictEqual(
  sections.overview.querySelector('.br-acc__summary').getAttribute('aria-expanded'),
  'true'
);

const nextBtn = documentObj.getElementById('br-next');
nextBtn.click();
assert.strictEqual(isOpen(sections.painting), true, 'next on an edit page does not collapse the open section');
assert.strictEqual(isOpen(sections.overview), true, 'next on an edit page does not collapse a manually opened section');

viewToggle.click();
assert.strictEqual(form.classList.contains('br-layout--all-sections'), true);
assert.strictEqual(isOpen(sections.finishing), true, 'all-sections opens every step');

headerPosition = 'sticky';
headerHeight = 80;
buttons.overview.click();
assert.strictEqual(form.classList.contains('br-layout--all-sections'), false, 'nav click leaves all-sections mode');
assert.strictEqual(isOpen(sections.overview), true);
assert.strictEqual(isOpen(sections.painting), false);
assert.strictEqual(isOpen(sections.finishing), false);
assert.strictEqual(isOpen(sections.review), false);
assert.strictEqual(sections.overview._styleProps['scroll-margin-top'], '92px');

const rateCss = fs.readFileSync(path.join(__dirname, '../../public/css/admin-client-projects.css'), 'utf8');
assert.match(
  rateCss,
  /\.br-rate__custom\s*\{[^}]*display:\s*none;/,
  'default Custom badge stays hidden; display must not override the hidden attribute'
);
assert.match(
  rateCss,
  /\.br-rate\.is-custom\s+\.br-rate__custom\s*\{[^}]*display:\s*inline-flex;/,
  'Custom badge shows only on an overridden rate'
);
assert.match(
  rateCss,
  /\.br-rate__custom\[hidden\],\s*\.br-rate__reset\[hidden\]\s*\{[^}]*display:\s*none\s*!important;/,
  'hidden Custom badge and Reset stay hidden even when a display rule is present'
);

console.log('admin-basement-nav-exclusive: ok');
