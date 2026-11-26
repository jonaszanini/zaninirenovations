'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

function parseSimpleCompound(compound) {
  const parts = {
    tag: null,
    id: null,
    classes: [],
    attrs: [],
    pseudos: [],
  };
  let rest = compound.trim();
  if (!rest) return null;

  const tagMatch = rest.match(/^([a-z0-9_-]+)/i);
  if (tagMatch) {
    parts.tag = tagMatch[1].toUpperCase();
    rest = rest.slice(tagMatch[0].length);
  }

  while (rest.length > 0) {
    if (rest[0] === '#') {
      const m = rest.slice(1).match(/^([a-z0-9_-]+)/i);
      if (!m) break;
      parts.id = m[1];
      rest = rest.slice(1 + m[0].length);
    } else if (rest[0] === '.') {
      const m = rest.slice(1).match(/^([a-z0-9_-]+)/i);
      if (!m) break;
      parts.classes.push(m[1]);
      rest = rest.slice(1 + m[0].length);
    } else if (rest[0] === '[') {
      const end = rest.indexOf(']');
      if (end === -1) break;
      const body = rest.slice(1, end).trim();
      const eq = body.indexOf('=');
      if (eq === -1) {
        parts.attrs.push({ name: body, value: null });
      } else {
        const name = body.slice(0, eq).trim();
        let val = body.slice(eq + 1).trim();
        if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
          val = val.slice(1, -1);
        }
        parts.attrs.push({ name, value: val });
      }
      rest = rest.slice(end + 1);
    } else if (rest[0] === ':') {
      const m = rest.slice(1).match(/^([a-z0-9_-]+)/i);
      if (!m) break;
      parts.pseudos.push(m[1]);
      rest = rest.slice(1 + m[0].length);
    } else {
      break;
    }
  }
  return parts;
}

function matchesCompound(el, compound) {
  if (!el) return false;
  const spec = parseSimpleCompound(compound);
  if (!spec) return false;
  if (spec.tag && el.tagName !== spec.tag) return false;
  if (spec.id && el.id !== spec.id) return false;
  for (const cls of spec.classes) {
    if (!el.classList.contains(cls)) return false;
  }
  for (const attr of spec.attrs) {
    if (!el.hasAttribute(attr.name)) return false;
    if (attr.value !== null && String(el.getAttribute(attr.name)) !== attr.value) return false;
  }
  for (const pseudo of spec.pseudos) {
    if (pseudo === 'checked' && !el.checked) return false;
    if (pseudo === 'invalid' && !el.invalid) return false;
    if (pseudo === 'disabled' && !el.disabled) return false;
  }
  return true;
}

function matchesSelector(el, selector) {
  const branches = String(selector || '').split(',').map((s) => s.trim()).filter(Boolean);
  return branches.some((branch) => {
    const tokens = branch.split(/\s+/).filter(Boolean);
    if (!tokens.length) return false;
    if (!matchesCompound(el, tokens[tokens.length - 1])) return false;
    let cur = el.parentNode;
    for (let i = tokens.length - 2; i >= 0; i--) {
      while (cur && !matchesCompound(cur, tokens[i])) {
        cur = cur.parentNode;
      }
      if (!cur) return false;
      cur = cur.parentNode;
    }
    return true;
  });
}

function makeElement(tag, attrs, children) {
  attrs = attrs || {};
  const listeners = {};
  const classSet = new Set(String(attrs.class || '').split(/\s+/).filter(Boolean));
  const rawAttrs = {};
  const dataset = {};
  Object.keys(attrs).forEach((k) => {
    if (k === 'class' || k === 'checked' || k === 'hidden' || k === 'disabled') return;
    rawAttrs[k] = String(attrs[k]);
    if (k.startsWith('data-')) {
      const camel = k.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
      dataset[camel] = String(attrs[k]);
    }
  });

  const el = {
    tagName: String(tag).toUpperCase(),
    children: [],
    parentNode: null,
    ownerDocument: null,
    checked: !!attrs.checked,
    hidden: !!attrs.hidden,
    disabled: !!attrs.disabled,
    value: attrs.value != null ? String(attrs.value) : '',
    textContent: attrs.textContent != null ? String(attrs.textContent) : '',
    innerHTML: '',
    placeholder: '',
    title: '',
    style: {
      setProperty() {},
    },
    dataset,
    classList: {
      add(c) { classSet.add(c); },
      remove(c) { classSet.delete(c); },
      contains(c) { return classSet.has(c); },
      toggle(c, force) {
        if (force === undefined) {
          if (classSet.has(c)) { classSet.delete(c); return false; }
          classSet.add(c);
          return true;
        }
        if (force) classSet.add(c);
        else classSet.delete(c);
        return !!force;
      },
    },
    get className() {
      return Array.from(classSet).join(' ');
    },
    set className(v) {
      classSet.clear();
      String(v || '').split(/\s+/).filter(Boolean).forEach((c) => classSet.add(c));
    },
    get id() {
      return rawAttrs.id || '';
    },
    set id(v) {
      rawAttrs.id = String(v);
    },
    get name() {
      return rawAttrs.name || '';
    },
    set name(v) {
      rawAttrs.name = String(v);
    },
    get type() {
      return rawAttrs.type || '';
    },
    set type(v) {
      rawAttrs.type = String(v);
    },
    get firstChild() {
      return this.children[0] || null;
    },
    get parentElement() {
      return this.parentNode;
    },
    getAttribute(name) {
      if (name === 'class') return this.className;
      return Object.prototype.hasOwnProperty.call(rawAttrs, name) ? rawAttrs[name] : null;
    },
    setAttribute(name, val) {
      rawAttrs[name] = String(val);
      if (name === 'class') this.className = String(val);
      if (name === 'hidden') this.hidden = true;
      if (name.startsWith('data-')) {
        const camel = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
        dataset[camel] = String(val);
      }
    },
    removeAttribute(name) {
      delete rawAttrs[name];
      if (name === 'hidden') this.hidden = false;
      if (name.startsWith('data-')) {
        const camel = name.slice(5).replace(/-([a-z])/g, (_, c) => c.toUpperCase());
        delete dataset[camel];
      }
    },
    hasAttribute(name) {
      if (name === 'hidden') return !!this.hidden || Object.prototype.hasOwnProperty.call(rawAttrs, 'hidden');
      return Object.prototype.hasOwnProperty.call(rawAttrs, name);
    },
    appendChild(child) {
      if (child.parentNode && child.parentNode.children) {
        const idx = child.parentNode.children.indexOf(child);
        if (idx !== -1) child.parentNode.children.splice(idx, 1);
      }
      child.parentNode = this;
      this.children.push(child);
      return child;
    },
    insertBefore(child, ref) {
      if (child.parentNode && child.parentNode.children) {
        const idx = child.parentNode.children.indexOf(child);
        if (idx !== -1) child.parentNode.children.splice(idx, 1);
      }
      child.parentNode = this;
      const refIdx = ref ? this.children.indexOf(ref) : -1;
      if (refIdx === -1) this.children.push(child);
      else this.children.splice(refIdx, 0, child);
      return child;
    },
    remove() {
      if (this.parentNode && this.parentNode.children) {
        const idx = this.parentNode.children.indexOf(this);
        if (idx !== -1) this.parentNode.children.splice(idx, 1);
        this.parentNode = null;
      }
    },
    addEventListener(type, fn) {
      if (!listeners[type]) listeners[type] = [];
      listeners[type].push(fn);
    },
    dispatchEvent(evt) {
      evt = evt || { type: '' };
      if (!evt.target) evt.target = this;
      if (!evt.preventDefault) evt.preventDefault = () => {};
      let cur = this;
      while (cur) {
        const fns = (cur._listeners && cur._listeners[evt.type]) || [];
        for (const fn of fns) fn.call(cur, evt);
        if (!evt.bubbles) break;
        cur = cur.parentNode;
      }
      return true;
    },
    click() {
      this.dispatchEvent({ type: 'click', bubbles: true, target: this });
    },
    closest(sel) {
      let cur = this;
      while (cur && cur.tagName) {
        if (matchesSelector(cur, sel)) return cur;
        cur = cur.parentNode;
      }
      return null;
    },
    matches(sel) {
      return matchesSelector(this, sel);
    },
    querySelectorAll(sel) {
      const out = [];
      const walk = (node) => {
        for (const child of node.children || []) {
          if (matchesSelector(child, sel)) out.push(child);
          walk(child);
        }
      };
      walk(this);
      return out;
    },
    querySelector(sel) {
      return this.querySelectorAll(sel)[0] || null;
    },
    getBoundingClientRect() {
      return { height: 64, width: 1000, top: 0, left: 0 };
    },
  };
  el._listeners = listeners;
  (children || []).forEach((c) => el.appendChild(c));
  return el;
}

// Build DOM matching resources/views/admin/quotations/basement/create.blade.php + _wizard_sections.blade.php
const allSections = [
  'overview', 'demolition', 'framing', 'electrical', 'plumbing',
  'insulation', 'ceiling', 'drywall', 'flooring', 'doors_trim',
  'painting', 'bathroom', 'finishing',
];
const tradeKeys = allSections.filter((k) => k !== 'overview');

const presetRadios = ['full', 'bathroom', 'wall', 'custom', 'flood'].map((val) =>
  makeElement('input', {
    type: 'radio',
    name: 'scope_preset',
    value: val,
    'data-br-scope-preset': '',
    checked: val === 'full',
  })
);

const tradeCheckboxes = tradeKeys.map((key) =>
  makeElement('input', {
    type: 'checkbox',
    name: 'enabled_sections[]',
    value: key,
    class: 'admin-checkbox__input br-preview',
    'data-br-enabled-section': key,
    checked: true,
  })
);

const stepAccordions = allSections.concat(['client_scope', 'review']).map((key) =>
  makeElement('details', {
    class: 'br-acc admin-card',
    'data-br-step': key,
  }, [
    makeElement('summary', { class: 'br-acc__summary' }, [
      makeElement('span', { class: 'br-acc__num' }),
    ]),
  ])
);

const progressButtons = allSections.concat(['client_scope', 'review']).map((key) =>
  makeElement('button', {
    type: 'button',
    class: 'br-progress__item',
    'data-br-goto': key,
  }, [
    makeElement('span', { class: 'br-progress__meta', 'data-br-step-meta': key }),
  ])
);

const perimeterInput = makeElement('input', {
  type: 'number',
  id: 'br-perimeter-lf',
  name: 'perimeter_lf',
  class: 'admin-input br-preview',
  value: '',
});
const partitionInput = makeElement('input', {
  type: 'number',
  id: 'br-partition',
  name: 'partition_lf',
  class: 'admin-input br-preview',
  value: '0',
});
const partitionField = makeElement('div', { id: 'br-partition-field', class: 'admin-form-group' }, [
  partitionInput,
]);
const wallPrimaryFields = makeElement('div', { id: 'br-wall-primary-fields' }, [partitionField]);
const overviewWallSlot = makeElement('div', { id: 'br-wall-primary-slot' });
const framingWallSlot = makeElement('div', { id: 'br-framing-primary-slot' }, [wallPrimaryFields]);
const floodPartitionSlot = makeElement('div', { id: 'br-flood-partition-slot' });

const areaInput = makeElement('input', {
  type: 'number',
  id: 'br-area',
  name: 'area_sqft',
  class: 'admin-input br-preview',
  value: '0',
});
const cutHeightInput = makeElement('input', {
  type: 'number',
  id: 'br-flood-cut-height',
  name: 'flood_cut_height_in',
  class: 'admin-input br-preview',
  value: '24',
});
const cutChip24 = makeElement('button', {
  type: 'button',
  class: 'br-flood-chip is-active',
  'data-br-flood-cut': '24',
  'aria-pressed': 'true',
});
const cutChip48 = makeElement('button', {
  type: 'button',
  class: 'br-flood-chip',
  'data-br-flood-cut': '48',
  'aria-pressed': 'false',
});
const bothFacesCheckbox = makeElement('input', {
  type: 'checkbox',
  id: 'br-flood-both-faces',
  name: 'flood_partition_both_faces',
  class: 'admin-checkbox__input br-preview',
  checked: true,
});
const casingLegsRadio = makeElement('input', {
  type: 'radio',
  name: 'flood_casing_mode',
  value: 'legs',
  checked: true,
});
const casingFullRadio = makeElement('input', {
  type: 'radio',
  name: 'flood_casing_mode',
  value: 'full',
  checked: false,
});

const floodOnlyGeom = makeElement('div', { class: 'br-flood-geom', 'data-br-flood-only': '', hidden: true }, [
  perimeterInput,
  floodPartitionSlot,
]);
const floodOnlyPanel = makeElement('div', { class: 'br-flood-panel', 'data-br-flood-only': '', hidden: true }, [
  cutHeightInput,
  cutChip24,
  cutChip48,
  bothFacesCheckbox,
]);
const floodOnlyCasing = makeElement('fieldset', { class: 'br-flood-casing', 'data-br-flood-only': '', hidden: true }, [
  casingLegsRadio,
  casingFullRadio,
]);

const dwWallInput = makeElement('input', {
  type: 'number',
  id: 'br-dw-wall',
  name: 'drywall_wall_sf',
  'data-br-lw-qty': '',
  value: '',
});
const dwWallWrap = makeElement('div', { 'data-br-lw': '' }, [dwWallInput]);

const ceilingTypeSelect = makeElement('select', { id: 'br-ceil-type', name: 'ceiling_type' }, []);
ceilingTypeSelect.value = 'drywall';
const flooringTypeSelect = makeElement('select', { id: 'br-fl-type', name: 'flooring_type' }, []);
flooringTypeSelect.value = 'lvp';
const insulateWallsCb = makeElement('input', { type: 'checkbox', name: 'insulate_walls', checked: true });
const insulateCeilingCb = makeElement('input', { type: 'checkbox', name: 'insulate_ceiling', checked: false });
const underslabCb = makeElement('input', { type: 'checkbox', name: 'underslab', checked: false });
const includeBathroomCb = makeElement('input', { type: 'checkbox', name: 'include_bathroom', 'data-br-reveal': 'br-bath-fields', checked: false });
const bathFields = makeElement('div', { id: 'br-bath-fields', hidden: true });
const includeDemoCb = makeElement('input', { type: 'checkbox', name: 'include_demolition', checked: false });
const includeCleaningCb = makeElement('input', { type: 'checkbox', name: 'include_cleaning', checked: true });
const includeTouchupsCb = makeElement('input', { type: 'checkbox', name: 'include_touchups', checked: true });
const includePermitCb = makeElement('input', { type: 'checkbox', name: 'include_building_permit', checked: false });
const insWallInput = makeElement('input', { type: 'number', id: 'br-ins-wall-sf', name: 'insulation_wall_sf', value: '' });
const paintWallInput = makeElement('input', { type: 'number', id: 'br-p-wall', name: 'paint_wall_sf', value: '' });
const baseInput = makeElement('input', { type: 'number', id: 'br-base', name: 'baseboard_lf', value: '' });

const form = makeElement('form', { id: 'br-main-form', class: 'pq-layout br-layout' }, [
  ...presetRadios,
  ...tradeCheckboxes,
  ...progressButtons,
  ...stepAccordions,
  overviewWallSlot,
  framingWallSlot,
  areaInput,
  floodOnlyGeom,
  floodOnlyPanel,
  floodOnlyCasing,
  dwWallWrap,
  ceilingTypeSelect,
  flooringTypeSelect,
  insulateWallsCb,
  insulateCeilingCb,
  underslabCb,
  includeBathroomCb,
  bathFields,
  includeDemoCb,
  includeCleaningCb,
  includeTouchupsCb,
  includePermitCb,
  insWallInput,
  paintWallInput,
  baseInput,
  makeElement('span', { id: 'br-step-status' }),
  makeElement('div', { id: 'br-sticky-total' }),
]);

const docRoot = makeElement('html', {}, [
  makeElement('head', {}, [
    makeElement('meta', { name: 'csrf-token', content: 'test-token' }),
  ]),
  makeElement('body', {}, [form]),
]);

const documentObj = {
  documentElement: docRoot,
  getElementById(id) {
    return docRoot.querySelector('#' + id);
  },
  querySelector(sel) {
    return docRoot.querySelector(sel);
  },
  querySelectorAll(sel) {
    return docRoot.querySelectorAll(sel);
  },
  createElement(tag) {
    return makeElement(tag);
  },
  addEventListener(type, fn) {
    docRoot.addEventListener(type, fn);
  },
  dispatchEvent(evt) {
    return docRoot.dispatchEvent(evt);
  },
};

class MockFormData {
  constructor() { this.map = new Map(); }
  set(k, v) { this.map.set(k, v); }
  delete(k) { this.map.delete(k); }
  append(k, v) { this.map.set(k, v); }
}

const fetchCalls = [];
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
  FormData: MockFormData,
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
  fetch(url, opts) {
    fetchCalls.push({ url, opts });
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
  isEdit: false,
  sectionKeys: allSections,
  presetSections: {
    full: allSections,
    bathroom: ['overview', 'framing', 'plumbing', 'electrical', 'drywall', 'painting', 'bathroom', 'finishing'],
    wall: ['overview', 'framing', 'drywall', 'doors_trim', 'painting', 'finishing'],
    flood: ['overview', 'insulation', 'drywall', 'flooring', 'doors_trim', 'painting', 'finishing'],
    custom: ['overview'],
  },
  previewUrl: '/preview',
};

const scriptPath = path.resolve(__dirname, '../../public/js/admin-basement-quotation.js');
const scriptCode = fs.readFileSync(scriptPath, 'utf8');
vm.runInNewContext(scriptCode, sandbox, { filename: scriptPath });

function selectPreset(val) {
  presetRadios.forEach((r) => {
    r.checked = r.value === val;
  });
  const target = presetRadios.find((r) => r.value === val);
  target.dispatchEvent(new sandbox.Event('change', { bubbles: true }));
}

// 1. Select 'flood' preset
selectPreset('flood');

const checkedTrades = tradeCheckboxes.filter((cb) => cb.checked).map((cb) => cb.value);
const uncheckedTrades = tradeCheckboxes.filter((cb) => !cb.checked).map((cb) => cb.value);
assert.deepStrictEqual(
  checkedTrades,
  ['insulation', 'drywall', 'flooring', 'doors_trim', 'painting', 'finishing'],
  'flood preset must check exact 6 trade checkboxes'
);
assert.deepStrictEqual(
  uncheckedTrades,
  ['demolition', 'framing', 'electrical', 'plumbing', 'ceiling', 'bathroom'],
  'flood preset must uncheck exact 6 trade checkboxes'
);

assert.strictEqual(floodOnlyGeom.hidden, false, '[data-br-flood-only] geom unhides on flood');
assert.strictEqual(floodOnlyPanel.hidden, false, '[data-br-flood-only] drywall panel unhides on flood');
assert.strictEqual(floodOnlyCasing.hidden, false, '[data-br-flood-only] casing fieldset unhides on flood');
assert.strictEqual(ceilingTypeSelect.value, 'none');
assert.strictEqual(flooringTypeSelect.value, 'lvp');
assert.strictEqual(insulateWallsCb.checked, true);
assert.strictEqual(includeBathroomCb.checked, false);

// 2. User toggles trade checkboxes after selecting 'flood'
const bathTradeCb = tradeCheckboxes.find((cb) => cb.value === 'bathroom');
const floorTradeCb = tradeCheckboxes.find((cb) => cb.value === 'flooring');

bathTradeCb.checked = true;
bathTradeCb.dispatchEvent(new sandbox.Event('change', { bubbles: true }));
assert.strictEqual(bathTradeCb.checked, true, 'bathroom trade stays toggleable');
assert.strictEqual(includeBathroomCb.checked, true, 'checking bathroom trade checks include_bathroom on flood');
assert.strictEqual(bathFields.hidden, false, 'bathroom fields unhide when bathroom trade checked on flood');

floorTradeCb.checked = false;
floorTradeCb.dispatchEvent(new sandbox.Event('change', { bubbles: true }));
assert.strictEqual(floorTradeCb.checked, false, 'flooring trade stays unchecked when toggled off');

// 3. Perimeter / partition / flood cut height drywall prefill & manual edit preservation
perimeterInput.value = '120';
perimeterInput.dispatchEvent(new sandbox.Event('input', { bubbles: true }));
partitionInput.value = '40';
partitionInput.dispatchEvent(new sandbox.Event('input', { bubbles: true }));
cutHeightInput.value = '24';
cutHeightInput.dispatchEvent(new sandbox.Event('input', { bubbles: true }));
bothFacesCheckbox.checked = true;
bothFacesCheckbox.dispatchEvent(new sandbox.Event('change', { bubbles: true }));

assert.strictEqual(dwWallInput.value, '400', '#br-dw-wall must prefill 400 for 120 perimeter + 40*2 partition at 24"');

insWallInput.dataset.brFloodQtyAuto = '1';
insWallInput.value = '240';
paintWallInput.dataset.brFloodQtyAuto = '1';
paintWallInput.value = '1600';
baseInput.dataset.brFloodQtyAuto = '1';
baseInput.value = '200';

// Click 48" chip -> #br-flood-cut-height becomes '48' and #br-dw-wall becomes '800'
// Imported insulation follows exterior cut only. Paint and baseboard stay full height / full run.
cutChip48.click();
assert.strictEqual(cutHeightInput.value, '48');
assert.strictEqual(cutChip48.classList.contains('is-active'), true);
assert.strictEqual(cutChip24.classList.contains('is-active'), false);
assert.strictEqual(dwWallInput.value, '800');
assert.strictEqual(insWallInput.value, '480', '48 in cut doubles imported exterior insulation');
assert.strictEqual(paintWallInput.value, '1600', 'paint stays full wall height when the cut changes');
assert.strictEqual(baseInput.value, '200', 'baseboard stays the full wall run when the cut changes');

// Manually edit #br-dw-wall to '350' -> changing cut height back to 24" preserves '350'
dwWallInput.value = '350';
dwWallInput.dispatchEvent(new sandbox.Event('input', { bubbles: true }));
cutChip24.click();
assert.strictEqual(cutHeightInput.value, '24');
assert.strictEqual(dwWallInput.value, '350', 'manual drywall edit must be preserved when cut height changes');

// 4. Switch back to 'full' -> flood panels hide and flood drywall qty does not stick
selectPreset('full');
assert.strictEqual(floodOnlyGeom.hidden, true, '[data-br-flood-only] geom hides on full');
assert.strictEqual(floodOnlyPanel.hidden, true, '[data-br-flood-only] drywall panel hides on full');
assert.strictEqual(floodOnlyCasing.hidden, true, '[data-br-flood-only] casing fieldset hides on full');
assert.strictEqual(dwWallInput.value, '', 'flood drywall quantity must not stay on the full preset');
assert.strictEqual(insWallInput.value, '', 'flood insulation quantity must not stay on the full preset');
assert.strictEqual(paintWallInput.value, '', 'flood paint quantity must not stay on the full preset');
assert.strictEqual(baseInput.value, '', 'flood baseboard quantity must not stay on the full preset');
assert.strictEqual(ceilingTypeSelect.value, 'drywall', 'full preset ceiling must not stay on the flood none value');
assert.strictEqual(includePermitCb.checked, false, 'building permit stays off');

// 5. Back to flood restores the manual drywall edit and flood panels
selectPreset('flood');
assert.strictEqual(floodOnlyPanel.hidden, false, 'flood drywall panel returns');
assert.strictEqual(dwWallInput.value, '350', 'manual flood drywall edit is restored');
assert.strictEqual(insWallInput.dataset.brFloodQtyAuto, '1', 'imported insulation stays auto after a round trip');
assert.strictEqual(insWallInput.value, '240', 'imported insulation is restored from the flood geometry');
assert.strictEqual(paintWallInput.value, '1600');
assert.strictEqual(baseInput.value, '200');
cutChip48.click();
assert.strictEqual(dwWallInput.value, '350', 'manual drywall edit stays after returning to flood');
assert.strictEqual(insWallInput.value, '480', 'cut change still refreshes insulation after a round trip');
assert.strictEqual(paintWallInput.value, '1600');
cutChip24.click();
assert.strictEqual(cutHeightInput.value, '24');
assert.strictEqual(ceilingTypeSelect.value, 'none');
assert.strictEqual(includePermitCb.checked, false);

// Flood Restoration checks Final cleaning and Touch-ups / punch list when chosen.
// Wall leaves them off. Full keeps them on. The finishing trade stays enabled.
includeCleaningCb.checked = false;
includeTouchupsCb.checked = false;
const finishingTradeCb = tradeCheckboxes.find((cb) => cb.value === 'finishing');
finishingTradeCb.checked = false;
selectPreset('wall');
assert.strictEqual(includeCleaningCb.checked, false, 'wall leaves Final cleaning off');
assert.strictEqual(includeTouchupsCb.checked, false, 'wall leaves Touch-ups / punch list off');
selectPreset('flood');
assert.strictEqual(includeCleaningCb.checked, true, 'switching to flood checks Final cleaning');
assert.strictEqual(includeTouchupsCb.checked, true, 'switching to flood checks Touch-ups / punch list');
assert.strictEqual(finishingTradeCb.checked, true, 'switching to flood enables Final Details & Cleanup');
selectPreset('full');
assert.strictEqual(includeCleaningCb.checked, true, 'full preset keeps Final cleaning on');
assert.strictEqual(includeTouchupsCb.checked, true, 'full preset keeps Touch-ups / punch list on');

console.log('admin-basement-flood-preset: ok');
