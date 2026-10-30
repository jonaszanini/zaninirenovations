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
    if (sel === 'div[role="dialog"]') {
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
          classList: { add: function () {}, contains: function () { return false; } },
          style: {},
          contains: function () {
            return false;
          },
          querySelector: function () {
            return null;
          },
        },
      ];
    }
    return [];
  },
  body: { tagName: 'BODY' },
};

const pop = require('../../public/js/admin-project-calendar-popover.js');

assert.strictEqual(typeof pop.findMorePopoverDialog, 'function');
assert.ok(pop.findMorePopoverDialog(), 'finds body-portaled role=dialog when calendar page is present');

console.log('admin-project-calendar-popover.test.js: ok');
