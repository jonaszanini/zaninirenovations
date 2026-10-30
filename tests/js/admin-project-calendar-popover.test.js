'use strict';

const assert = require('assert');
const pop = require('../../public/js/admin-project-calendar-popover.js');

assert.strictEqual(typeof pop.findMorePopoverDialog, 'function');

const root = {
  querySelectorAll: function (sel) {
    if (sel === 'div[role="dialog"]') {
      return [
        { role: 'dialog', getBoundingClientRect: () => ({ width: 0, height: 0 }), closest: () => null },
        {
          role: 'dialog',
          getBoundingClientRect: () => ({ width: 200, height: 100 }),
          closest: () => null,
          classList: { add: function () {} },
          style: {},
          contains: () => false,
          querySelector: () => null,
        },
      ];
    }
    return [];
  },
};

assert.ok(pop.findMorePopoverDialog(root), 'finds visible role=dialog inside calendar root');

console.log('admin-project-calendar-popover.test.js: ok');
