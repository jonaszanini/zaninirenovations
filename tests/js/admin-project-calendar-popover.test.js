'use strict';

const assert = require('assert');

const calendar = { id: 'cp-calendar' };
global.document = {
  getElementById: function (id) {
    return id === 'cp-calendar' ? calendar : null;
  },
  querySelector: function () {
    return null;
  },
  querySelectorAll: function (sel) {
    if (sel === 'body > div[role="dialog"]') {
      return [
        {
          role: 'dialog',
          parentElement: { tagName: 'BODY' },
          closest: function () {
            return null;
          },
          getBoundingClientRect: function () {
            return { width: 220, height: 120 };
          },
          classList: { add: function () {} },
          contains: function () {
            return false;
          },
          querySelector: function () {
            return { click: function () {} };
          },
        },
      ];
    }
    return [];
  },
  body: { tagName: 'BODY' },
};

const pop = require('../../public/js/admin-project-calendar-popover.js');
const src = require('fs').readFileSync(require('path').join(__dirname, '../../public/js/admin-project-calendar-popover.js'), 'utf8');

assert.strictEqual(typeof pop.findMorePopoverDialog, 'function');
assert.ok(pop.findMorePopoverDialog());
assert.doesNotMatch(src, /new MutationObserver/);

console.log('admin-project-calendar-popover.test.js: ok');
