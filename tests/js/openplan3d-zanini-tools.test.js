'use strict';

const assert = require('assert');

// Set env var to skip browser-only init
process.env.OPENPLAN3D_TOOLS_TEST = '1';

// Mock window and document for Node.js
global.window = {};
global.document = {
  readyState: 'complete',
  addEventListener: function() {},
  createElement: function() { return { style: {}, appendChild: function() {} }; },
  getElementById: function() { return null; },
  querySelector: function() { return null; },
  querySelectorAll: function() { return []; },
  body: { appendChild: function() {} },
  head: { appendChild: function() {} }
};

const tools = require('../../public/js/openplan3d-zanini-tools.js');

console.log('openplan3d-zanini-tools tests');

// Test axisRect normalization
const normalized = tools.axisRect({ x1: 100, y1: 50, x2: 0, y2: 150 });
assert.deepStrictEqual(normalized, { x1: 0, y1: 50, x2: 100, y2: 150 }, 'axisRect should normalize min/max');

// Test rectsOverlap
assert.strictEqual(tools.rectsOverlap(
  { x1: 0, y1: 0, x2: 100, y2: 100 },
  { x1: 50, y1: 50, x2: 150, y2: 150 }
), true, 'overlapping rects should be detected');

assert.strictEqual(tools.rectsOverlap(
  { x1: 0, y1: 0, x2: 100, y2: 100 },
  { x1: 200, y1: 200, x2: 300, y2: 300 }
), false, 'non-overlapping rects should not be detected');

// Test collectZoneMemberRects
const lZone = {
  id: 'l-zone',
  x1: 0, y1: 0, x2: 100, y2: 100,
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 50 },
    { x1: 0, y1: 50, x2: 50, y2: 150 }
  ]
};

const members = tools.collectZoneMemberRects(lZone, [lZone]);
assert.strictEqual(members.length, 2, 'L-zone should have 2 member rects');
assert.deepStrictEqual(members[0], { x1: 0, y1: 0, x2: 100, y2: 50 });
assert.deepStrictEqual(members[1], { x1: 0, y1: 50, x2: 50, y2: 150 });

// Test that legacy zone (no rects array) falls back to zone itself
const legacyZone = {
  id: 'legacy',
  x1: 10, y1: 20, x2: 110, y2: 120
};
const legacyMembers = tools.collectZoneMemberRects(legacyZone, [legacyZone]);
assert.strictEqual(legacyMembers.length, 1, 'Legacy zone should have 1 member rect');
assert.deepStrictEqual(legacyMembers[0], { x1: 10, y1: 20, x2: 110, y2: 120 });

// Test merged zones are collected
const parentZone = {
  id: 'parent',
  x1: 0, y1: 0, x2: 100, y2: 100
};
const childZone = {
  id: 'child',
  x1: 100, y1: 0, x2: 200, y2: 100,
  mergedInto: 'parent'
};
const allZones = [parentZone, childZone];
const mergedMembers = tools.collectZoneMemberRects(parentZone, allZones);
assert.strictEqual(mergedMembers.length, 2, 'Parent zone should include merged child');

// Test zoneUnionBounds still returns bbox (for hit testing)
const unionBounds = tools.zoneUnionBounds(lZone, [lZone]);
assert.strictEqual(unionBounds.x1, 0);
assert.strictEqual(unionBounds.y1, 0);
assert.strictEqual(unionBounds.x2, 100);
assert.strictEqual(unionBounds.y2, 150);

// Test findOverlappingZone
const zones = [
  { id: 'z1', x1: 0, y1: 0, x2: 100, y2: 100 },
  { id: 'z2', x1: 200, y1: 200, x2: 300, y2: 300 }
];
const overlap = tools.findOverlappingZone(zones, { x1: 50, y1: 50, x2: 150, y2: 150 });
assert.strictEqual(overlap.id, 'z1', 'Should find overlapping zone');
const noOverlap = tools.findOverlappingZone(zones, { x1: 400, y1: 400, x2: 500, y2: 500 });
assert.strictEqual(noOverlap, null, 'Should return null when no overlap');

const lZoneOverlap = {
  id: 'l1',
  x1: 0,
  y1: 0,
  x2: 100,
  y2: 150,
  rects: [
    { x1: 0, y1: 0, x2: 100, y2: 50 },
    { x1: 0, y1: 0, x2: 50, y2: 150 }
  ]
};
const notchRect = { x1: 80, y1: 80, x2: 90, y2: 90 };
const lOverlap = tools.findOverlappingZone([lZoneOverlap], notchRect);
assert.strictEqual(lOverlap, null, 'Rect inside L bbox but not touching member rects must not merge (D3)');

const ext = require('../../public/js/openplan3d-zanini-extensions.js');
global.window.OpenPlan3DZaniniExtensions = ext;
const ft = ext.CM_PER_FOOT;
const lZoneFt = {
  id: 'l-canvas',
  x1: 0,
  y1: 0,
  x2: 15 * ft,
  y2: 15 * ft,
  rects: [
    { x1: 0, y1: 0, x2: 10 * ft, y2: 15 * ft },
    { x1: 10 * ft, y1: 10 * ft, x2: 15 * ft, y2: 15 * ft }
  ]
};
const lAreaTools = tools.zoneAreaSqFt(lZoneFt, [lZoneFt], ft);
assert.ok(Math.abs(lAreaTools - 175) < 0.05, 'canvas label path must use L union area (~175 sf), got ' + lAreaTools);
assert.ok(lAreaTools < 225, 'must not use bbox area 225 sf for L-shaped zone');

// Test mergeRectIntoZone
const targetZone = {
  id: 'target',
  x1: 0, y1: 0, x2: 100, y2: 100
};
const zonesForMerge = [targetZone];
tools.mergeRectIntoZone(zonesForMerge, 'target', { x1: 50, y1: 50, x2: 150, y2: 150 });
assert.ok(Array.isArray(targetZone.rects), 'Target should have rects array after merge');
assert.strictEqual(targetZone.rects.length, 2, 'Target should have 2 rects after merge');
// Bbox should be updated for hit testing
assert.strictEqual(targetZone.x2, 150, 'Bbox should expand after merge');
assert.strictEqual(targetZone.y2, 150, 'Bbox should expand after merge');

const mockCanvas = { style: {}, width: 800, height: 600, addEventListener: function () {} };
global.requestAnimationFrame = function (fn) { return 0; };
const htmlClass = {};
global.document.documentElement = {
  classList: {
    toggle: function (name, on) {
      htmlClass[name] = !!on;
    },
    contains: function (name) {
      return !!htmlClass[name];
    }
  }
};
global.document.querySelector = function (sel) {
  if (sel && sel.indexOf('canvas') !== -1) return mockCanvas;
  return null;
};

tools.setMode('zone');
assert.strictEqual(tools.getMode(), 'zone');
assert.strictEqual(mockCanvas.style.cursor, 'crosshair');
assert.ok(htmlClass['openplan3d-zanini-draw-mode'], 'draw mode class when Zone active');

tools.setMode('bulkhead', { explicit: true });
assert.strictEqual(tools.getMode(), 'bulkhead');
assert.strictEqual(mockCanvas.style.cursor, 'crosshair');

tools.setMode(null, { explicit: true });
assert.strictEqual(tools.getMode(), null);
assert.strictEqual(mockCanvas.style.cursor, '');
assert.ok(!htmlClass['openplan3d-zanini-draw-mode'], 'draw mode class cleared on exit');

global.window.__openPlan3D = {
  getProject: function () {
    return {
      activeFloorId: 'f1',
      floors: [{
        id: 'f1',
        walls: [
          { id: 'inner1', start: { x: 0, y: 0 }, end: { x: 10, y: 0 } },
          { id: 'mtn32xy9', start: { x: 0, y: 0 }, end: { x: 20, y: 0 } },
        ],
        rooms: [
          { id: 'r-living', walls: ['inner1'] },
          { id: 'r-basement', boundary_wall_ids: ['mtn32xy9'], walls: ['mtn32xy9'] },
        ],
      }],
    };
  },
  setProject: function () {},
};
assert.strictEqual(tools.wallIsBoundary('mtn32xy9'), true, 'perimeter wall on non-first room must be boundary');
assert.strictEqual(tools.wallIsBoundary('inner1'), true, 'room.walls list still marks boundary membership');
assert.strictEqual(tools.wallIsBoundary('unknown'), false, 'interior-only id is not boundary');
assert.strictEqual(tools.finishDefaultsForWall('mtn32xy9').studs, false, 'boundary default studs off');
assert.strictEqual(tools.finishDefaultsForWall('unknown').studs, true, 'partition default studs on');

assert.deepStrictEqual(
  tools.wallsEligibleForBaseboardSidesControl(['mtn32xy9', 'inner1', 'partA']),
  ['partA'],
  'perimeter walls excluded from baseboard sides control'
);
assert.strictEqual(
  tools.readBaseboardSidesForWall({ mtn32xy9: { baseboardSides: 2 } }, 'mtn32xy9'),
  1,
  'readBaseboardSides clamps perimeter to interior-only'
);
assert.strictEqual(
  tools.readBaseboardSidesForWall({ partA: { baseboardSides: 1 } }, 'partA'),
  1
);

function makeStore(initial) {
  var value = initial;
  return {
    subscribe: function (fn) { fn(value); return function () {}; },
    set: function (next) { value = next; }
  };
}
var rafQueue = [];
global.requestAnimationFrame = function (fn) { rafQueue.push(fn); return rafQueue.length; };
window.__openPlan3DStoreModule = {
  bg: makeStore(new Set(['pw0'])),
  X: makeStore('pw0'),
  aB: makeStore('pw0')
};
var finishPlan = {
  activeFloorId: 'f1',
  floors: [{
    id: 'f1',
    walls: [
      { id: 'pw0', start: { x: 0, y: 0 }, end: { x: 120, y: 0 } },
      { id: 'pw1', start: { x: 120, y: 0 }, end: { x: 120, y: 120 } }
    ],
    rooms: [{ id: 'r1', walls: ['pw0', 'pw1'] }]
  }],
  zanini: { version: 1, zones: [], bulkheads: [], wallFinishes: {} }
};
window.__openPlan3D = {
  getProject: function () { return finishPlan; },
  setProject: function (p) {
    finishPlan = p;
    window.__openPlan3DStoreModule.bg.set(new Set());
    window.__openPlan3DStoreModule.X.set(null);
    window.__openPlan3DStoreModule.aB.set(null);
  }
};

rafQueue.length = 0;
tools.writePlan(function (plan) {
  plan.zanini.wallFinishes.pw0 = { paint: true };
});
assert.deepStrictEqual(tools.readSelectedWallIds(), ['pw0'], 'writePlan restores selection after setProject wipe');
assert.strictEqual(finishPlan.zanini.wallFinishes.pw0.paint, true);

window.__openPlan3DStoreModule.bg.set(new Set());
window.__openPlan3DStoreModule.X.set(null);
window.__openPlan3DStoreModule.aB.set(null);
tools.invalidatePendingSelectionRestore();
rafQueue.splice(0).forEach(function (fn) { fn(); });
assert.deepStrictEqual(
  tools.readSelectedWallIds(),
  [],
  'writePlan rAF must not restore a snapshot after a later deselect'
);

rafQueue.length = 0;
window.__openPlan3DStoreModule.bg.set(new Set(['pw0']));
window.__openPlan3DStoreModule.X.set('pw0');
window.__openPlan3DStoreModule.aB.set('pw0');
tools.writePlan(function (plan) {
  plan.zanini.wallFinishes.pw0 = { paint: false };
});
assert.deepStrictEqual(tools.readSelectedWallIds(), ['pw0'], 'sync restore keeps the edited wall selected');
rafQueue.splice(0).forEach(function (fn) { fn(); });
assert.deepStrictEqual(tools.readSelectedWallIds(), ['pw0'], 'rAF restore is a no-op when selection already survived');

console.log('openplan3d-zanini-tools: ok');
