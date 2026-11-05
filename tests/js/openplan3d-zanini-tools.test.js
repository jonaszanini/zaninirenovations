'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

console.log('openplan3d-zanini-tools tests');

const toolsSrc = fs.readFileSync(path.join(__dirname, '../../public/js/openplan3d-zanini-tools.js'), 'utf8');
assert.match(toolsSrc, /MutationObserver|openplan3d-store-ready/, 'canvas hook must retry until editor canvas exists');
assert.match(toolsSrc, /data-plan3d-canvas="true"/, 'must target main plan canvas not minimap');
assert.match(toolsSrc, /clientWidth|cssW/, 'screenToPlan must use CSS canvas dimensions like vendor F()');
assert.match(toolsSrc, /PREVIEW_FILL/, 'zone drag preview must use gray preview fill');
assert.match(toolsSrc, /BULKHEAD_FILL/, 'bulkheads must use a distinct fill from zone palette');
assert.match(toolsSrc, /isEditor3DViewActive/, 'must skip zone/bulkhead overlay in 3D view');
assert.match(toolsSrc, /findWallSidebarHost|openplan3d-zanini-finish-host/, 'finishes must mount in editor sidebar');
assert.doesNotMatch(toolsSrc, /querySelectorAll\('aside'\)/, 'wall sidebar host must not rely on aside only');
assert.match(toolsSrc, /mod\.bg/, 'multi-wall selection must read store export bg (selected wall Set)');
assert.match(toolsSrc, /deleteSelectedBulkhead/, 'bulkheads must be deletable');

global.window = global;
global.window.addEventListener = function () {};
global.window.removeEventListener = function () {};
global.MutationObserver = function () {
  this.observe = function () {};
  this.disconnect = function () {};
};
global.document = {
  readyState: 'complete',
  addEventListener: function () {},
  querySelector: function () { return null; },
  querySelectorAll: function () { return []; },
  getElementById: function () { return null; },
  createElement: function () {
    return {
      style: {},
      setAttribute: function () {},
      appendChild: function () {},
      addEventListener: function () {},
      querySelector: function () { return null; },
      querySelectorAll: function () { return []; }
    };
  },
  body: { appendChild: function () {} },
  head: { appendChild: function () {} }
};
global.requestAnimationFrame = function (fn) { fn(); };
global.cancelAnimationFrame = function () {};
process.env.OPENPLAN3D_TOOLS_TEST = '1';

const tools = require('../../public/js/openplan3d-zanini-tools.js');
const bridge = require('../../public/js/openplan3d-editor-bridge.js');

function screenToPlanCss(canvas, clientX, clientY, view) {
  return bridge.clientToPlanCm(canvas, clientX, clientY, view, null);
}

function planToClientCss(canvas, x, y, view) {
  return bridge.planCmToClient(canvas, x, y, view, null);
}

const fakeCanvas = {
  width: 1600,
  height: 1200,
  clientWidth: 800,
  clientHeight: 600,
  getBoundingClientRect: function () {
    return { left: 10, top: 20, width: 800, height: 600 };
  }
};

const view = { zoom: 0.28, camX: 412.5, camY: 318.2 };
const center = screenToPlanCss(fakeCanvas, 10 + 400, 20 + 300, view);
assert.ok(Math.abs(center.x - view.camX) < 0.02, 'canvas center maps to cam, got x=' + center.x);
assert.ok(Math.abs(center.y - view.camY) < 0.02, 'canvas center maps to cam, got y=' + center.y);

const planPt = { x: 512.75, y: 640.125 };
const client = planToClientCss(fakeCanvas, planPt.x, planPt.y, view);
const back = screenToPlanCss(fakeCanvas, client.clientX, client.clientY, view);
assert.ok(Math.abs(back.x - planPt.x) < 0.05, 'round-trip x');
assert.ok(Math.abs(back.y - planPt.y) < 0.05, 'round-trip y');

const viewPan = { zoom: 0.37, camX: -50, camY: 120 };
const probe = { x: 10, y: -25 };
const c2 = planToClientCss(fakeCanvas, probe.x, probe.y, viewPan);
const back2 = screenToPlanCss(fakeCanvas, c2.clientX, c2.clientY, viewPan);
assert.ok(Math.abs(back2.x - probe.x) < 0.05 && Math.abs(back2.y - probe.y) < 0.05, 'round-trip under pan/zoom');

assert.ok(tools.rectsOverlap({ x1: 0, y1: 0, x2: 10, y2: 10 }, { x1: 5, y1: 5, x2: 15, y2: 15 }), 'rectsOverlap detects overlap');

const zones = [{
  id: 'z1',
  name: 'A',
  color: 'rgba(14, 165, 233, 0.35)',
  x1: 0,
  y1: 0,
  x2: 100,
  y2: 100
}];
const plan = {
  zanini: { version: 1, zones: zones.slice(), bulkheads: [], wallFinishes: {} }
};
const mergedId = tools.addZoneFromPreview(plan, { x0: 80, y0: 80, x1: 160, y1: 160 });
assert.strictEqual(mergedId, 'z1', 'overlapping zone preview merges into existing zone');
assert.strictEqual(plan.zanini.zones.filter(function (z) { return z && !z.mergedInto; }).length, 1, 'merge keeps one visible zone');
assert.ok(Array.isArray(plan.zanini.zones[0].rects) && plan.zanini.zones[0].rects.length >= 2, 'merge stores union rects');

const fresh = { zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: {} } };
const newId = tools.addZoneFromPreview(fresh, { x0: 0, y0: 0, x1: 50, y1: 50 });
assert.notStrictEqual(newId, null);
assert.strictEqual(fresh.zanini.zones.length, 1);
assert.ok(fresh.zanini.zones[0].color, 'new zone gets a color on create');

console.log('openplan3d-zanini-tools: ok');
