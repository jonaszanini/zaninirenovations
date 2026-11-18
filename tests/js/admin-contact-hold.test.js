'use strict';

const assert = require('assert');
const hold = require('../../public/js/admin-contact-hold.js');

function manualSession(onCopy) {
  let pending = null;
  const session = hold.createSession({
    setTimer(fn) {
      pending = fn;
      return 1;
    },
    clearTimer() {
      pending = null;
    },
    onCopy: onCopy || function () {},
  });
  return {
    session: session,
    fire: function () {
      const fn = pending;
      pending = null;
      if (fn) fn();
    },
    pending: function () { return pending; },
  };
}

(function run() {
  assert.strictEqual(hold.HOLD_MS, 500, 'hold is about 500 ms');

  let copied = 0;
  const shortTap = manualSession(function () { copied += 1; });
  shortTap.session.pointerDown({ x: 10, y: 10 });
  assert.strictEqual(shortTap.session.pointerUp(), 'activate', 'a short tap follows the link');
  assert.strictEqual(shortTap.session.click(), 'allow');
  assert.strictEqual(copied, 0, 'a short tap does not copy');
  assert.strictEqual(shortTap.pending(), null, 'the hold timer is cleared on release');

  const longPress = manualSession(function () { copied += 1; });
  longPress.session.pointerDown({ x: 4, y: 4 });
  longPress.fire();
  assert.strictEqual(copied, 1, 'holding copies once');
  assert.strictEqual(longPress.session.pointerUp(), 'suppress', 'release after a hold does not activate the link');
  assert.strictEqual(longPress.session.click(), 'suppress', 'the click after a hold is swallowed');
  assert.strictEqual(longPress.session.click(), 'allow', 'the next click is a normal tap');

  const moved = manualSession(function () { copied += 1; });
  moved.session.pointerDown({ x: 0, y: 0 });
  assert.strictEqual(moved.session.pointerMove({ x: 30, y: 0 }), 'cancelled');
  assert.strictEqual(moved.pending(), null, 'sliding a finger cancels the hold');
  assert.strictEqual(moved.session.pointerUp(), 'activate');
  assert.strictEqual(copied, 1, 'a cancelled hold does not copy');

  const menu = hold.createSession();
  assert.strictEqual(menu.contextMenu(), 'suppress', 'the native context menu stays closed');

  let toasted = null;
  let written = null;
  hold.copyAndToast('client@example.com', {
    window: {
      AdminCopyValue: {
        copyText: function (text) {
          written = text;
          return Promise.resolve();
        },
      },
      AdminToast: {
        show: function (opts) { toasted = opts; },
      },
    },
  });
  assert.strictEqual(written, 'client@example.com');
  setTimeout(function () {
    assert.ok(toasted, 'copy shows a toast');
    assert.strictEqual(toasted.title, 'Copied');
    assert.strictEqual(toasted.type, 'success');
    testBind();
    console.log('admin-contact-hold.test.js: ok');
  }, 0);
})();

function fakeEl(attrs) {
  const attributes = Object.assign({}, attrs);
  const listeners = {};
  return {
    classList: {
      add() {},
      remove() {},
    },
    getAttribute(name) {
      return Object.prototype.hasOwnProperty.call(attributes, name) ? attributes[name] : null;
    },
    setAttribute(name, value) {
      attributes[name] = String(value);
    },
    addEventListener(type, fn) {
      listeners[type] = listeners[type] || [];
      listeners[type].push(fn);
    },
    dispatch(type, ev) {
      (listeners[type] || []).forEach((fn) => fn(ev));
    },
  };
}

function bindEl(attrs, onCopyText) {
  const el = fakeEl(attrs);
  let pending = null;
  const copies = [];
  hold.bind({
    querySelectorAll() {
      return [el];
    },
  }, {
    setTimer(fn) {
      pending = fn;
      return 1;
    },
    clearTimer() {
      pending = null;
    },
    window: {
      AdminCopyValue: {
        copyText(text) {
          copies.push(text);
          if (onCopyText) onCopyText(text);
          return Promise.resolve();
        },
      },
      AdminToast: { show() {} },
    },
  });
  return {
    el,
    copies,
    fire() {
      const fn = pending;
      pending = null;
      if (fn) fn();
    },
    pending() {
      return pending;
    },
  };
}

function testBind() {
  const mouse = bindEl({ 'data-cp-hold-copy': 'mouse@example.com' });
  mouse.el.dispatch('pointerdown', { pointerType: 'mouse', button: 0, clientX: 1, clientY: 1 });
  assert.strictEqual(mouse.pending(), null, 'a mouse press does not start a hold');
  const mouseClick = {
    pointerType: 'mouse',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  mouse.el.dispatch('click', mouseClick);
  assert.strictEqual(mouseClick.prevented, false, 'a mouse click is not swallowed');
  assert.deepStrictEqual(mouse.copies, [], 'a mouse click does not copy');
  const mouseMenu = {
    pointerType: 'mouse',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  mouse.el.dispatch('contextmenu', mouseMenu);
  assert.strictEqual(mouseMenu.prevented, false, 'a mouse context menu stays available');

  const tap = bindEl({ 'data-cp-hold-copy': '(613) 555-0100' });
  tap.el.dispatch('pointerdown', { pointerType: 'touch', button: 0, clientX: 2, clientY: 2 });
  assert.strictEqual(typeof tap.pending(), 'function', 'a touch starts the hold timer');
  const tapUp = {
    pointerType: 'touch',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  tap.el.dispatch('pointerup', tapUp);
  assert.strictEqual(tapUp.prevented, false, 'a short tap is not cancelled on release');
  const tapClick = {
    pointerType: 'touch',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  tap.el.dispatch('click', tapClick);
  assert.strictEqual(tapClick.prevented, false, 'a short tap can follow the link');
  assert.deepStrictEqual(tap.copies, [], 'a short tap does not copy');

  const held = bindEl({ 'data-cp-hold-copy': '10 Main Street' });
  held.el.dispatch('pointerdown', { pointerType: 'touch', button: 0, clientX: 3, clientY: 3 });
  held.fire();
  assert.deepStrictEqual(held.copies, ['10 Main Street'], 'a touch hold copies the icon value');
  const heldUp = {
    pointerType: 'touch',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  held.el.dispatch('pointerup', heldUp);
  assert.strictEqual(heldUp.prevented, true, 'release after a hold does not activate the link');
  const heldClick = {
    pointerType: 'touch',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  held.el.dispatch('click', heldClick);
  assert.strictEqual(heldClick.prevented, true, 'the tap after a hold is swallowed');
  const menu = {
    pointerType: 'touch',
    prevented: false,
    preventDefault() { this.prevented = true; },
    stopPropagation() {},
  };
  held.el.dispatch('contextmenu', menu);
  assert.strictEqual(menu.prevented, true, 'a touch context menu stays closed');
}
