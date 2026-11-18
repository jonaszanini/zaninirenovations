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
    console.log('admin-contact-hold.test.js: ok');
  }, 0);
})();
